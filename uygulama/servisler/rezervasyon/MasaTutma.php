<?php

declare(strict_types=1);

/**
 * Masa tutma (hold) — EŞZAMANLILIĞIN KALBİ.
 *
 * Kural (docs/VERITABANI.md): çift rezervasyonun tek güvencesi buradaki
 * koşullu UPDATE'tir (compare-and-swap). Etkilenen satır sayısı istenen
 * masa sayısına eşit değilse işlem geri alınır. Uygulama katmanındaki
 * hiçbir ön kontrol güvenlik güvencesi DEĞİLDİR (yalnızca hata mesajı içindir).
 *
 * CLAUDE.md kural 15: bu dosyaya dokunan her değişiklikten sonra
 * betikler/test-eszamanlilik.php çalıştırılmadan iş bitmiş sayılmaz.
 */
final class MasaTutma
{
    /**
     * Masa seçmeli hold: taslak rezervasyon oluşturur ve masaları kilitler.
     *
     * @param array<int, int> $masaIdler
     * @return array{kod: string, hold_sona_erme: string}
     * @throws RezervasyonHatasi
     */
    public static function masaTut(int $macId, array $masaIdler, int $kisiSayisi): array
    {
        $masaIdler = array_values(array_unique(array_map('intval', $masaIdler)));
        if ($masaIdler === [] || count($masaIdler) > 5) {
            throw new RezervasyonHatasi('En az 1, en çok 5 masa seçebilirsiniz.');
        }
        if ($kisiSayisi < 1 || $kisiSayisi > 40) {
            throw new RezervasyonHatasi('Kişi sayısı geçersiz.');
        }

        return Veritabani::islem(function () use ($macId, $masaIdler, $kisiSayisi): array {
            $mac = self::satistakiMacGetir($macId);

            // Masalar bu maça ait mi + grup kuralları (bilgilendirici ön kontrol)
            $yerTutucular = implode(',', array_fill(0, count($masaIdler), '?'));
            $masalar = Veritabani::satirlar(
                "SELECT * FROM mac_masalari WHERE id IN ({$yerTutucular}) AND mac_id = ?",
                [...$masaIdler, $macId]
            );
            if (count($masalar) !== count($masaIdler)) {
                throw new RezervasyonHatasi('Seçilen masalar bu maça ait değil.');
            }

            $toplamKapasite = 0;
            $toplamMin = 0;
            foreach ($masalar as $masa) {
                $toplamKapasite += (int) $masa['kapasite'];
                $toplamMin += (int) $masa['min_kisi'];
            }
            if ($kisiSayisi > $toplamKapasite) {
                throw new RezervasyonHatasi(
                    "Seçilen masaların toplam kapasitesi {$toplamKapasite} kişi; {$kisiSayisi} kişi sığmaz."
                );
            }
            if ($kisiSayisi < $toplamMin) {
                throw new RezervasyonHatasi(
                    "Bu masalar en az {$toplamMin} kişilik gruplara açıktır. "
                    . 'Daha küçük gruplar için Salon Girişi biletini kullanabilirsiniz.'
                );
            }

            $simdi = simdiUtc();
            $holdSonu = utcKaydir($simdi, Ayarlar::holdDakika());
            $rezervasyonId = self::taslakRezervasyonOlustur($mac, 'masa', $kisiSayisi, $holdSonu);

            // --- CAS: tek güvence bu UPDATE'tir ---
            $etkilenen = Veritabani::calistir(
                "UPDATE mac_masalari
                 SET durum = 'tutuldu', tutma_sona_erme = ?, tutan_rezervasyon_id = ?
                 WHERE id IN ({$yerTutucular}) AND mac_id = ?
                   AND ( durum = 'bos'
                         OR (durum = 'tutuldu' AND tutma_sona_erme IS NOT NULL AND tutma_sona_erme < ?) )",
                [$holdSonu, $rezervasyonId, ...$masaIdler, $macId, $simdi]
            );
            if ($etkilenen !== count($masaIdler)) {
                // Transaction geri alınır: rezervasyon taslağı da, kısmi kilitler de yok olur
                throw new RezervasyonHatasi('Seçtiğiniz masalardan biri az önce başkası tarafından tutuldu. Lütfen yeniden seçin.');
            }

            $kod = (string) Veritabani::deger('SELECT kod FROM rezervasyonlar WHERE id = ?', [$rezervasyonId]);
            return ['kod' => $kod, 'hold_sona_erme' => $holdSonu];
        });
    }

    /**
     * Salon Girişi (paylaşımlı kontenjan) hold'u: masasız rezervasyon.
     * Kontenjan kontrolü maç satırı kilitlenerek yapılır (MySQL FOR UPDATE;
     * SQLite'ta tek yazar serileştirir).
     *
     * @return array{kod: string, hold_sona_erme: string}
     * @throws RezervasyonHatasi
     */
    public static function salonTut(int $macId, int $kisiSayisi): array
    {
        if ($kisiSayisi < 1 || $kisiSayisi > 8) {
            throw new RezervasyonHatasi('Salon girişi için kişi sayısı 1-8 arasında olmalı.');
        }

        return Veritabani::islem(function () use ($macId, $kisiSayisi): array {
            // Kilit: aynı maça eşzamanlı salon rezervasyonlarını serileştirir
            $mac = Veritabani::satirKilitle('SELECT * FROM maclar WHERE id = ?', [$macId]);
            if ($mac === null) {
                throw new RezervasyonHatasi('Maç bulunamadı.');
            }
            self::satisDurumuKontrol($mac);

            $kontenjan = (int) $mac['paylasimli_kontenjan'];
            if ($kontenjan <= 0) {
                throw new RezervasyonHatasi('Bu maçta salon girişi kontenjanı yok; lütfen masa seçin.');
            }

            $simdi = simdiUtc();
            $dolu = (int) (Veritabani::deger(
                "SELECT COALESCE(SUM(kisi_sayisi), 0) FROM rezervasyonlar
                 WHERE mac_id = ? AND tur = 'salon'
                   AND ( durum = 'onaylandi'
                         OR (durum = 'odeme_bekliyor' AND hold_sona_erme IS NOT NULL AND hold_sona_erme > ?) )",
                [$macId, $simdi]
            ) ?? 0);

            if ($dolu + $kisiSayisi > $kontenjan) {
                $kalan = max(0, $kontenjan - $dolu);
                throw new RezervasyonHatasi(
                    $kalan === 0
                        ? 'Salon girişi kontenjanı doldu.'
                        : "Salon girişinde yalnızca {$kalan} kişilik yer kaldı."
                );
            }

            $holdSonu = utcKaydir($simdi, Ayarlar::holdDakika());
            $rezervasyonId = self::taslakRezervasyonOlustur($mac, 'salon', $kisiSayisi, $holdSonu);
            $kod = (string) Veritabani::deger('SELECT kod FROM rezervasyonlar WHERE id = ?', [$rezervasyonId]);
            return ['kod' => $kod, 'hold_sona_erme' => $holdSonu];
        });
    }

    /** Müşteri vazgeçti: hold'u ve taslak rezervasyonu serbest bırakır (best-effort). */
    public static function holdBirak(string $kod): void
    {
        Veritabani::islem(function () use ($kod): void {
            $rezervasyon = Veritabani::satir(
                "SELECT * FROM rezervasyonlar WHERE kod = ? AND durum = 'odeme_bekliyor'",
                [$kod]
            );
            if ($rezervasyon === null) {
                return;
            }
            Veritabani::calistir(
                "UPDATE mac_masalari SET durum = 'bos', tutma_sona_erme = NULL, tutan_rezervasyon_id = NULL
                 WHERE tutan_rezervasyon_id = ? AND durum = 'tutuldu'",
                [$rezervasyon['id']]
            );
            Veritabani::calistir(
                "UPDATE rezervasyonlar SET durum = 'suresi_doldu', hold_sona_erme = NULL, guncelleme_zamani = ? WHERE id = ?",
                [simdiUtc(), $rezervasyon['id']]
            );
        });
    }

    /** Hold süresi geçerli mi? (bilgi/ödeme sayfaları her adımda kontrol eder) */
    public static function holdGecerliMi(array $rezervasyon): bool
    {
        return $rezervasyon['durum'] === 'odeme_bekliyor'
            && $rezervasyon['hold_sona_erme'] !== null
            && (string) $rezervasyon['hold_sona_erme'] > simdiUtc();
    }

    // ------------------------------------------------------------

    /** @return array<string, mixed> */
    private static function satistakiMacGetir(int $macId): array
    {
        $mac = Veritabani::satir('SELECT * FROM maclar WHERE id = ?', [$macId]);
        if ($mac === null) {
            throw new RezervasyonHatasi('Maç bulunamadı.');
        }
        self::satisDurumuKontrol($mac);
        return $mac;
    }

    private static function satisDurumuKontrol(array $mac): void
    {
        if ($mac['durum'] !== 'satista') {
            throw new RezervasyonHatasi('Bu maç için satış şu anda açık değil.');
        }
        if ((string) $mac['baslangic_zamani'] <= simdiUtc()) {
            throw new RezervasyonHatasi('Bu maç başladı; online rezervasyon kapandı.');
        }
    }

    private static function taslakRezervasyonOlustur(array $mac, string $tur, int $kisiSayisi, string $holdSonu): int
    {
        $simdi = simdiUtc();
        $tutar = $kisiSayisi * (int) $mac['kisi_basi_fiyat_kurus'];

        // Kod çakışması teorik olasılık: UNIQUE kısıtına güvenip yeniden deneriz
        for ($deneme = 0; $deneme < 5; $deneme++) {
            try {
                Veritabani::calistir(
                    'INSERT INTO rezervasyonlar
                        (kod, mac_id, tur, ad_soyad, telefon, eposta, kisi_sayisi, toplam_tutar_kurus,
                         durum, hold_sona_erme, qr_nonce, olusturma_zamani, guncelleme_zamani)
                     VALUES (?, ?, ?, \'\', \'\', \'\', ?, ?, \'odeme_bekliyor\', ?, ?, ?, ?)',
                    [
                        Guvenlik::rastgeleKod(8),
                        $mac['id'],
                        $tur,
                        $kisiSayisi,
                        $tutar,
                        $holdSonu,
                        Guvenlik::rastgeleKod(16, '23456789abcdefhjkmnprtuvwxyz'),
                        $simdi,
                        $simdi,
                    ]
                );
                return Veritabani::sonEklenenId();
            } catch (PDOException $hata) {
                $tekrarKod = str_contains($hata->getMessage(), 'kod') || str_contains($hata->getMessage(), 'UNIQUE');
                if (!$tekrarKod || $deneme === 4) {
                    throw $hata;
                }
            }
        }
        throw new RuntimeException('Rezervasyon kodu üretilemedi.');
    }
}
