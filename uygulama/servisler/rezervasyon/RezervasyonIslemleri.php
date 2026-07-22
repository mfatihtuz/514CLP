<?php

declare(strict_types=1);

/**
 * Rezervasyon yaşam döngüsü: misafir bilgisi, onay, iptal.
 * Onay da CAS desenlidir: hold'lu masalar yalnız bu rezervasyona aitse rezerve olur.
 */
final class RezervasyonIslemleri
{
    /** @return array<string, mixed>|null */
    public static function kodIleBul(string $kod): ?array
    {
        return Veritabani::satir('SELECT * FROM rezervasyonlar WHERE kod = ?', [strtoupper(trim($kod))]);
    }

    /** @return array<string, mixed>|null Maç ve takım bilgileriyle birlikte */
    public static function kodIleDetay(string $kod): ?array
    {
        return Veritabani::satir(
            "SELECT r.*,
                    m.baslangic_zamani, m.kapi_acilis_zamani, m.kisi_basi_fiyat_kurus, m.paket_icerigi,
                    m.iptal_saat_once, m.durum AS mac_durum,
                    ev.ad AS ev_ad, ev.arma_dosya AS ev_arma,
                    dep.ad AS dep_ad, dep.arma_dosya AS dep_arma
             FROM rezervasyonlar r
             JOIN maclar m ON m.id = r.mac_id
             JOIN takimlar ev ON ev.id = m.ev_sahibi_takim_id
             JOIN takimlar dep ON dep.id = m.deplasman_takim_id
             WHERE r.kod = ?",
            [strtoupper(trim($kod))]
        );
    }

    /** @return array<int, array<string, mixed>> Rezervasyonun masaları (onaylıysa bağdan, holdluysa tutandan) */
    public static function masalari(array $rezervasyon): array
    {
        if ($rezervasyon['tur'] !== 'masa') {
            return [];
        }
        if ($rezervasyon['durum'] === 'onaylandi') {
            return Veritabani::satirlar(
                'SELECT mm.* FROM mac_masalari mm
                 JOIN rezervasyon_masalari rm ON rm.mac_masa_id = mm.id
                 WHERE rm.rezervasyon_id = ? ORDER BY mm.ad',
                [$rezervasyon['id']]
            );
        }
        return Veritabani::satirlar(
            'SELECT * FROM mac_masalari WHERE tutan_rezervasyon_id = ? ORDER BY ad',
            [$rezervasyon['id']]
        );
    }

    /**
     * Misafir bilgilerini kaydeder (hold süresi içinde).
     * @return array<int, string> hata listesi (boşsa başarılı)
     */
    public static function bilgiKaydet(string $kod, string $adSoyad, string $telefon, string $eposta, bool $kvkkOnay, bool $sozlesmeOnay): array
    {
        $hatalar = [];
        $adSoyad = trim($adSoyad);
        if (mb_strlen($adSoyad) < 5 || !str_contains($adSoyad, ' ')) {
            $hatalar[] = 'Ad ve soyadınızı birlikte yazın.';
        }
        $telefonNormal = telefonNormallestir($telefon);
        if ($telefonNormal === null) {
            $hatalar[] = 'Telefon numarası geçersiz (örnek: 05XX XXX XX XX).';
        }
        if (!epostaGecerliMi(trim($eposta))) {
            $hatalar[] = 'E-posta adresi geçersiz.';
        }
        if (!$kvkkOnay) {
            $hatalar[] = 'KVKK aydınlatma metnini onaylamanız gerekiyor.';
        }
        if (!$sozlesmeOnay) {
            $hatalar[] = 'Mesafeli satış sözleşmesini onaylamanız gerekiyor.';
        }
        if ($hatalar !== []) {
            return $hatalar;
        }

        $rezervasyon = self::kodIleBul($kod);
        if ($rezervasyon === null || !MasaTutma::holdGecerliMi($rezervasyon)) {
            return ['Rezervasyon süresi doldu. Lütfen masa seçimini baştan yapın.'];
        }

        $simdi = simdiUtc();
        Veritabani::calistir(
            'UPDATE rezervasyonlar
             SET ad_soyad = ?, telefon = ?, eposta = ?, kvkk_onay_zamani = ?, sozlesme_onay_zamani = ?, guncelleme_zamani = ?
             WHERE id = ?',
            [$adSoyad, $telefonNormal, mb_strtolower(trim($eposta)), $simdi, $simdi, $simdi, $rezervasyon['id']]
        );
        return [];
    }

    /**
     * Rezervasyonu kesinleştirir. Ücretli maçta yalnızca ödeme katmanı çağırır.
     * @throws RezervasyonHatasi
     */
    public static function onayla(string $kod): void
    {
        Veritabani::islem(function () use ($kod): void {
            $rezervasyon = self::kodIleBul($kod);
            if ($rezervasyon === null) {
                throw new RezervasyonHatasi('Rezervasyon bulunamadı.');
            }
            if ($rezervasyon['durum'] === 'onaylandi') {
                return; // idempotent: çifte onay zararsız
            }
            if (!MasaTutma::holdGecerliMi($rezervasyon)) {
                throw new RezervasyonHatasi('Rezervasyon süresi doldu. Lütfen baştan başlayın.');
            }
            if ($rezervasyon['ad_soyad'] === '' || $rezervasyon['telefon'] === '' || $rezervasyon['eposta'] === '') {
                throw new RezervasyonHatasi('İletişim bilgileri eksik.');
            }

            $simdi = simdiUtc();

            if ($rezervasyon['tur'] === 'masa') {
                $masaIdleri = array_map(
                    static fn(array $satir): int => (int) $satir['id'],
                    Veritabani::satirlar(
                        "SELECT id FROM mac_masalari WHERE tutan_rezervasyon_id = ? AND durum = 'tutuldu'",
                        [$rezervasyon['id']]
                    )
                );
                if ($masaIdleri === []) {
                    throw new RezervasyonHatasi('Masa kilidi bulunamadı; süre dolmuş olabilir. Lütfen baştan başlayın.');
                }

                // CAS: masalar hâlâ BU rezervasyona kilitliyse kesinleşir
                $yerTutucular = implode(',', array_fill(0, count($masaIdleri), '?'));
                $etkilenen = Veritabani::calistir(
                    "UPDATE mac_masalari
                     SET durum = 'rezerve', tutma_sona_erme = NULL
                     WHERE id IN ({$yerTutucular}) AND durum = 'tutuldu' AND tutan_rezervasyon_id = ?",
                    [...$masaIdleri, $rezervasyon['id']]
                );
                if ($etkilenen !== count($masaIdleri)) {
                    throw new RezervasyonHatasi('Masa kilidi doğrulanamadı. Lütfen baştan başlayın.');
                }

                foreach ($masaIdleri as $masaId) {
                    Veritabani::calistir(
                        'INSERT INTO rezervasyon_masalari (rezervasyon_id, mac_masa_id) VALUES (?, ?)',
                        [$rezervasyon['id'], $masaId]
                    );
                }
            } else {
                // Salon: kontenjan hold ile zaten sayılıyor; onay yalnızca durumu kesinleştirir
                $mac = Veritabani::satirKilitle('SELECT * FROM maclar WHERE id = ?', [$rezervasyon['mac_id']]);
                if ($mac === null || $mac['durum'] !== 'satista') {
                    throw new RezervasyonHatasi('Bu maç için satış kapandı.');
                }
            }

            Veritabani::calistir(
                "UPDATE rezervasyonlar SET durum = 'onaylandi', hold_sona_erme = NULL, guncelleme_zamani = ? WHERE id = ?",
                [$simdi, $rezervasyon['id']]
            );
            DenetimKaydi::yaz('rezervasyon_onaylandi', [
                'kod' => $rezervasyon['kod'],
                'mac_id' => $rezervasyon['mac_id'],
                'kisi' => $rezervasyon['kisi_sayisi'],
                'tutar_kurus' => $rezervasyon['toplam_tutar_kurus'],
            ]);
        });
    }

    /** Maçın iptal penceresi içinde miyiz? (UTC karşılaştırma) */
    public static function iptalEdilebilirMi(array $rezervasyonDetay): bool
    {
        if ($rezervasyonDetay['durum'] !== 'onaylandi') {
            return false;
        }
        $pencereSaat = $rezervasyonDetay['iptal_saat_once'] !== null
            ? (int) $rezervasyonDetay['iptal_saat_once']
            : Ayarlar::varsayilanIptalSaat();
        $sonIptalZamani = utcKaydir((string) $rezervasyonDetay['baslangic_zamani'], -60 * $pencereSaat);
        return simdiUtc() < $sonIptalZamani;
    }

    /**
     * Müşteri iptali. ÜCRETLİ rezervasyonda iade zorunludur: iade çağrısını
     * ödeme katmanı yapar (Faz 3); bu metod yalnız durumu ve masaları çözer.
     * @throws RezervasyonHatasi
     */
    public static function iptalEt(string $kod, bool $pencereKontrol = true): void
    {
        Veritabani::islem(function () use ($kod, $pencereKontrol): void {
            $detay = self::kodIleDetay($kod);
            if ($detay === null) {
                throw new RezervasyonHatasi('Rezervasyon bulunamadı.');
            }
            if ($detay['durum'] !== 'onaylandi') {
                throw new RezervasyonHatasi('Yalnızca onaylı rezervasyon iptal edilebilir.');
            }
            if ($pencereKontrol && !self::iptalEdilebilirMi($detay)) {
                $pencere = $detay['iptal_saat_once'] ?? Ayarlar::varsayilanIptalSaat();
                throw new RezervasyonHatasi("İptal süresi geçti: maç başlangıcından {$pencere} saat öncesine kadar iptal edilebilir.");
            }

            // Masaları serbest bırak
            Veritabani::calistir(
                "UPDATE mac_masalari SET durum = 'bos', tutma_sona_erme = NULL, tutan_rezervasyon_id = NULL
                 WHERE id IN (SELECT mac_masa_id FROM rezervasyon_masalari WHERE rezervasyon_id = ?)",
                [$detay['id']]
            );
            Veritabani::calistir('DELETE FROM rezervasyon_masalari WHERE rezervasyon_id = ?', [$detay['id']]);
            Veritabani::calistir(
                "UPDATE rezervasyonlar SET durum = 'iptal_edildi', guncelleme_zamani = ? WHERE id = ?",
                [simdiUtc(), $detay['id']]
            );
            DenetimKaydi::yaz('rezervasyon_iptal', ['kod' => $detay['kod'], 'mac_id' => $detay['mac_id']]);
        });
    }
}
