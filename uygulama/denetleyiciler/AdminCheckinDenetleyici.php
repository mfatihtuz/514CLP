<?php

declare(strict_types=1);

/**
 * Kapıda QR check-in: kamera ile jeton okunur veya kod elle girilir.
 * Jeton biçimi: KOD.IMZA — imza HMAC ile timing-safe doğrulanır.
 */
final class AdminCheckinDenetleyici
{
    public function ekran(array $parametreler = []): void
    {
        AdminOturumu::zorunlu();
        $simdi = simdiUtc();
        $maclar = Veritabani::satirlar(
            "SELECT m.id, m.baslangic_zamani, ev.ad AS ev_ad, dep.ad AS dep_ad
             FROM maclar m
             JOIN takimlar ev ON ev.id = m.ev_sahibi_takim_id
             JOIN takimlar dep ON dep.id = m.deplasman_takim_id
             WHERE m.durum IN ('satista', 'satis_kapali') AND m.baslangic_zamani > ?
             ORDER BY m.baslangic_zamani ASC LIMIT 10",
            [utcKaydir($simdi, -300)] // maç başlangıcından 5 saat sonrasına dek listede kalır
        );
        Sablon::goster('admin/check-in', [
            'baslik'    => 'Check-in',
            'aktifMenu' => 'check-in',
            'maclar'    => $maclar,
        ], 'duzen/admin');
    }

    public function dogrula(array $parametreler = []): void
    {
        AdminOturumu::apiZorunlu();
        $govde = jsonGovdeOku();
        jsonCsrfZorunlu($govde);

        $macId = (int) ($govde['mac_id'] ?? 0);
        $jeton = trim((string) ($govde['jeton'] ?? ''));
        $manuelKod = strtoupper(trim((string) ($govde['kod'] ?? '')));

        // Jetonlu (QR) veya manuel (yalnız kod) arama
        $imzaGerekli = $jeton !== '';
        if ($imzaGerekli) {
            $parcalar = explode('.', $jeton, 2);
            if (count($parcalar) !== 2) {
                // QR başka bir sitenin linki olabilir: içinden t= parametresini dene
                if (preg_match('/[?&]t=([A-Z0-9]+\.[A-Za-z0-9_\-]+)/', $jeton, $eslesme)) {
                    $parcalar = explode('.', $eslesme[1], 2);
                } else {
                    Sablon::json(['sonuc' => 'gecersiz', 'mesaj' => 'Bu QR bir rezervasyon bileti değil.']);
                    return;
                }
            }
            [$kod, $imza] = $parcalar;
            $kod = strtoupper($kod);
        } else {
            $kod = $manuelKod;
            $imza = '';
        }

        if ($kod === '' || $macId < 1) {
            Sablon::json(['sonuc' => 'gecersiz', 'mesaj' => 'Kod veya maç seçimi eksik.']);
            return;
        }

        $detay = RezervasyonIslemleri::kodIleDetay($kod);
        if ($detay === null) {
            Sablon::json(['sonuc' => 'bulunamadi', 'mesaj' => 'Rezervasyon bulunamadı: ' . $kod]);
            return;
        }

        if ($imzaGerekli && !Guvenlik::qrDogrula($kod, (int) $detay['mac_id'], (string) $detay['qr_nonce'], $imza)) {
            DenetimKaydi::yaz('checkin_sahte_qr', ['kod' => $kod]);
            Sablon::json(['sonuc' => 'gecersiz', 'mesaj' => 'QR imzası GEÇERSİZ — sahte veya bozuk bilet.']);
            return;
        }

        if ((int) $detay['mac_id'] !== $macId) {
            Sablon::json([
                'sonuc' => 'baska_mac',
                'mesaj' => 'Bu bilet başka maça ait: ' . $detay['ev_ad'] . ' - ' . $detay['dep_ad']
                    . ' (' . macZamaniBicimle((string) $detay['baslangic_zamani']) . ')',
            ]);
            return;
        }

        if ($detay['durum'] !== 'onaylandi') {
            $durumMetni = ['iptal_edildi' => 'İPTAL EDİLMİŞ', 'suresi_doldu' => 'süresi dolmuş', 'odeme_bekliyor' => 'ödemesi tamamlanmamış'];
            Sablon::json([
                'sonuc' => 'iptal',
                'mesaj' => 'Bu rezervasyon ' . ($durumMetni[$detay['durum']] ?? $detay['durum']) . '.',
            ]);
            return;
        }

        if ($detay['checkin_zamani'] !== null) {
            Sablon::json([
                'sonuc' => 'zaten',
                'mesaj' => 'DAHA ÖNCE GİRİŞ YAPILDI: ' . saatBicimle((string) $detay['checkin_zamani']),
                'kisi'  => (int) $detay['kisi_sayisi'],
                'ad'    => (string) $detay['ad_soyad'],
            ]);
            return;
        }

        // CAS: iki görevli aynı bileti aynı anda okutursa yalnız biri kazanır
        $etkilenen = Veritabani::calistir(
            'UPDATE rezervasyonlar SET checkin_zamani = ?, checkin_admin_id = ?, guncelleme_zamani = ? WHERE id = ? AND checkin_zamani IS NULL',
            [simdiUtc(), AdminOturumu::aktifId(), simdiUtc(), $detay['id']]
        );
        if ($etkilenen === 0) {
            Sablon::json(['sonuc' => 'zaten', 'mesaj' => 'DAHA ÖNCE GİRİŞ YAPILDI (eşzamanlı okuma).']);
            return;
        }

        $masalar = RezervasyonIslemleri::masalari($detay);
        Sablon::json([
            'sonuc'   => 'basarili',
            'ad'      => (string) $detay['ad_soyad'],
            'kisi'    => (int) $detay['kisi_sayisi'],
            'yer'     => $detay['tur'] === 'salon'
                ? 'Salon Girişi — işletme yer gösterecek'
                : 'Masa ' . implode(', ', array_map(static fn(array $m): string => (string) $m['ad'], $masalar)),
            'paket'   => implode(' + ', MacSorgulari::paketIcerigi($detay)),
            'ozet'    => self::macOzet($macId),
        ]);
    }

    /** Ekrandaki canlı sayaç: giriş yapan / onaylı toplam kişi. */
    public function ozet(array $parametreler): void
    {
        AdminOturumu::apiZorunlu();
        Sablon::json(self::macOzet((int) $parametreler['macId']));
    }

    /** @return array{giren_kisi: int, toplam_kisi: int} */
    private static function macOzet(int $macId): array
    {
        return [
            'giren_kisi'  => (int) (Veritabani::deger(
                "SELECT COALESCE(SUM(kisi_sayisi),0) FROM rezervasyonlar WHERE mac_id = ? AND durum='onaylandi' AND checkin_zamani IS NOT NULL",
                [$macId]
            ) ?? 0),
            'toplam_kisi' => (int) (Veritabani::deger(
                "SELECT COALESCE(SUM(kisi_sayisi),0) FROM rezervasyonlar WHERE mac_id = ? AND durum='onaylandi'",
                [$macId]
            ) ?? 0),
        ];
    }
}
