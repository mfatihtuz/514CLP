<?php

declare(strict_types=1);

/**
 * Fikstür çekici: üç büyüklerin (uc_buyuk=1) yaklaşan maçlarını dış kaynaktan
 * çeker ve TASLAK maç olarak ekler (CLAUDE.md kural 13):
 *  - Fiyat belirlemek ve yayınlamak DAİMA admin kararıdır; otomatik yayın YOKTUR.
 *  - Manuel maç girişi her zaman açık kalır; bu servis yalnız kolaylıktır.
 *  - Kaynak arızası sistemi etkilemez: hata mesajı panelde gösterilir, o kadar.
 *
 * Kaynak: TheSportsDB (ücretsiz JSON API — HTML scrape'ten daha dayanıklı).
 * .env: FIKSTUR_API_ANAHTARI (boşsa ücretsiz test anahtarı '3' denenir)
 *
 * Maç saatleri kaynakta UTC gelir ve UTC saklanır; saat değişikliklerine karşı
 * mevcut TASLAK maçların saati güncellenir (yayınlanmış maça dokunulmaz).
 */
final class FiksturCekici
{
    private const TEMEL_URL = 'https://www.thesportsdb.com/api/v1/json';

    /** @return array{eklenen: int, guncellenen: int, atlanan: int, hatalar: array<int, string>} */
    public static function calistir(): array
    {
        $eklenen = 0;
        $guncellenen = 0;
        $atlanan = 0;
        $hatalar = [];

        foreach (TakimSorgulari::ucBuyukler() as $takim) {
            try {
                $kaynakTakimId = self::kaynakTakimIdBul($takim);
                if ($kaynakTakimId === null) {
                    $hatalar[] = $takim['ad'] . ': kaynak takım kimliği bulunamadı.';
                    continue;
                }
                $etkinlikler = self::yaklasanEtkinlikler($kaynakTakimId);
            } catch (Throwable $hata) {
                $hatalar[] = $takim['ad'] . ': ' . $hata->getMessage();
                continue;
            }

            foreach ($etkinlikler as $etkinlik) {
                try {
                    $sonuc = self::etkinligiIsle($etkinlik);
                    if ($sonuc === 'eklendi') {
                        $eklenen++;
                    } elseif ($sonuc === 'guncellendi') {
                        $guncellenen++;
                    } else {
                        $atlanan++;
                    }
                } catch (Throwable $hata) {
                    $hatalar[] = ($etkinlik['strEvent'] ?? 'etkinlik') . ': ' . $hata->getMessage();
                }
            }
        }

        DenetimKaydi::yaz('fikstur_cekildi', [
            'eklenen' => $eklenen, 'guncellenen' => $guncellenen,
            'atlanan' => $atlanan, 'hata_sayisi' => count($hatalar),
        ]);
        return ['eklenen' => $eklenen, 'guncellenen' => $guncellenen, 'atlanan' => $atlanan, 'hatalar' => $hatalar];
    }

    // ------------------------------------------------------------

    /** Kaynak takım kimliği ayarlar tablosunda önbelleklenir. */
    private static function kaynakTakimIdBul(array $takim): ?string
    {
        $onbellek = Ayarlar::al('fikstur_takim_idleri', []);
        if (!is_array($onbellek)) {
            $onbellek = [];
        }
        $anahtar = (string) $takim['sef_ad'];
        if (!empty($onbellek[$anahtar])) {
            return (string) $onbellek[$anahtar];
        }

        $cevap = self::jsonGetir('/searchteams.php?t=' . urlencode((string) $takim['ad']));
        foreach (($cevap['teams'] ?? []) ?: [] as $aday) {
            if (($aday['strSport'] ?? '') === 'Soccer' && !empty($aday['idTeam'])) {
                $onbellek[$anahtar] = (string) $aday['idTeam'];
                Ayarlar::kaydet('fikstur_takim_idleri', $onbellek);
                return $onbellek[$anahtar];
            }
        }
        return null;
    }

    /** @return array<int, array<string, mixed>> */
    private static function yaklasanEtkinlikler(string $kaynakTakimId): array
    {
        $cevap = self::jsonGetir('/eventsnext.php?id=' . urlencode($kaynakTakimId));
        return ($cevap['events'] ?? []) ?: [];
    }

    /** @return 'eklendi'|'guncellendi'|'atlandi' */
    private static function etkinligiIsle(array $etkinlik): string
    {
        if (($etkinlik['strSport'] ?? 'Soccer') !== 'Soccer' || empty($etkinlik['idEvent'])) {
            return 'atlandi';
        }
        $fiksturAnahtari = 'tsdb-' . $etkinlik['idEvent'];

        // Zaman: dateEvent + strTime UTC'dir; saat yoksa 17:00 UTC (20:00 TSİ) varsayılır
        $tarih = (string) ($etkinlik['dateEvent'] ?? '');
        if ($tarih === '') {
            return 'atlandi';
        }
        $saat = (string) ($etkinlik['strTime'] ?? '');
        $saat = preg_match('/^\d{2}:\d{2}/', $saat) ? substr($saat, 0, 8) : '17:00:00';
        $baslangicUtc = $tarih . ' ' . (strlen($saat) === 5 ? $saat . ':00' : $saat);

        $evTakimId = self::takimEslestirVeyaOlustur((string) ($etkinlik['strHomeTeam'] ?? ''));
        $depTakimId = self::takimEslestirVeyaOlustur((string) ($etkinlik['strAwayTeam'] ?? ''));
        if ($evTakimId === null || $depTakimId === null) {
            return 'atlandi';
        }

        $mevcut = Veritabani::satir('SELECT * FROM maclar WHERE fikstur_anahtari = ?', [$fiksturAnahtari]);
        $simdi = simdiUtc();

        if ($mevcut !== null) {
            // Saat değişikliği: yalnız TASLAK maçta otomatik düzeltilir
            if ($mevcut['durum'] === 'taslak' && (string) $mevcut['baslangic_zamani'] !== $baslangicUtc) {
                Veritabani::calistir(
                    'UPDATE maclar SET baslangic_zamani = ?, kapi_acilis_zamani = ?, guncelleme_zamani = ? WHERE id = ?',
                    [$baslangicUtc, utcKaydir($baslangicUtc, -90), $simdi, $mevcut['id']]
                );
                return 'guncellendi';
            }
            return 'atlandi';
        }

        // Aynı gün + aynı eşleşme manuel girilmişse kopya oluşturma
        $ayniGunVar = Veritabani::deger(
            "SELECT id FROM maclar
             WHERE ev_sahibi_takim_id = ? AND deplasman_takim_id = ?
               AND baslangic_zamani BETWEEN ? AND ?",
            [$evTakimId, $depTakimId, utcKaydir($baslangicUtc, -720), utcKaydir($baslangicUtc, 720)]
        );
        if ($ayniGunVar !== null) {
            return 'atlandi';
        }

        Veritabani::calistir(
            "INSERT INTO maclar (ev_sahibi_takim_id, deplasman_takim_id, baslangic_zamani, kapi_acilis_zamani,
                                 kisi_basi_fiyat_kurus, paylasimli_kontenjan, durum, kaynak, fikstur_anahtari,
                                 olusturma_zamani, guncelleme_zamani)
             VALUES (?, ?, ?, ?, 0, ?, 'taslak', 'fikstur', ?, ?, ?)",
            [
                $evTakimId, $depTakimId, $baslangicUtc, utcKaydir($baslangicUtc, -90),
                (int) Ayarlar::al('paylasimli_kontenjan_varsayilan', 8),
                $fiksturAnahtari, $simdi, $simdi,
            ]
        );
        return 'eklendi';
    }

    /**
     * Kaynak takım adını yerel takıma eşler; bilinmeyen takım (Avrupa kupası
     * rakibi vb.) gri renkli jenerik rozetle OTOMATİK oluşturulur.
     */
    private static function takimEslestirVeyaOlustur(string $kaynakAd): ?int
    {
        $kaynakAd = trim($kaynakAd);
        if ($kaynakAd === '') {
            return null;
        }
        $sefAd = self::sefAdYap($kaynakAd);

        // 1) sef_ad birebir / içerme eşleşmesi
        foreach (Veritabani::satirlar('SELECT id, sef_ad FROM takimlar') as $takim) {
            $yerli = (string) $takim['sef_ad'];
            if ($yerli === $sefAd
                || str_contains($sefAd, $yerli)
                || str_contains($yerli, $sefAd)) {
                return (int) $takim['id'];
            }
        }

        // 2) Bilinmeyen takım: otomatik oluştur (arma yerine gri jenerik rozet)
        $kisaAd = mb_strtoupper(mb_substr(preg_replace('/[^A-Za-zĞÜŞİÖÇğüşiöç]/u', '', $kaynakAd) ?: 'TKM', 0, 3));
        $armaDosya = 'armalar/' . $sefAd . '.svg';
        Veritabani::calistir(
            'INSERT INTO takimlar (ad, kisa_ad, sef_ad, arma_dosya, renk1, renk2, uc_buyuk, aktif)
             VALUES (?, ?, ?, ?, ?, ?, 0, 1)',
            [$kaynakAd, $kisaAd, $sefAd, $armaDosya, '#5B6770', '#98A4AE']
        );
        RozetUretici::dosyaUret(DIZIN_PUBLIC . '/' . $armaDosya, $kisaAd, '#5B6770', '#98A4AE');
        DenetimKaydi::yaz('takim_otomatik_eklendi', ['ad' => $kaynakAd, 'sef_ad' => $sefAd]);
        return Veritabani::sonEklenenId();
    }

    private static function sefAdYap(string $ad): string
    {
        $donusum = ['ı' => 'i', 'İ' => 'i', 'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g',
            'ö' => 'o', 'Ö' => 'o', 'ü' => 'u', 'Ü' => 'u', 'ç' => 'c', 'Ç' => 'c'];
        $ad = strtr($ad, $donusum);
        $ad = strtolower(trim($ad));
        $ad = preg_replace('/[^a-z0-9]+/', '-', $ad) ?? '';
        return trim($ad, '-');
    }

    /** @return array<string, mixed> */
    private static function jsonGetir(string $yol): array
    {
        $anahtar = (string) (Cevre::al('FIKSTUR_API_ANAHTARI', '3') ?: '3');
        $url = self::TEMEL_URL . '/' . $anahtar . $yol;

        $kanal = curl_init($url);
        curl_setopt_array($kanal, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 8,   // yavaş kaynakta panel uzun süre beklemesin
            CURLOPT_USERAGENT      => 'MacGecesiRezervasyon/1.0',
        ]);
        $govde = curl_exec($kanal);
        $hata = curl_error($kanal);
        $kod = (int) curl_getinfo($kanal, CURLINFO_RESPONSE_CODE);
        curl_close($kanal);

        if ($govde === false || $kod !== 200) {
            throw new RuntimeException("fikstür kaynağına ulaşılamadı (HTTP {$kod}) {$hata}");
        }
        $veri = json_decode((string) $govde, true);
        if (!is_array($veri)) {
            throw new RuntimeException('fikstür kaynağı geçersiz veri döndürdü');
        }
        return $veri;
    }
}
