<?php

declare(strict_types=1);

/**
 * Ödeme yaşam döngüsü orkestratörü (docs/ODEME-AKISI.md'nin uygulaması).
 *
 * Kurallar:
 *  1. Fiyat İSTEMCİDEN ASLA gelmez; rezervasyon kaydından okunur.
 *  2. Callback gövdesine güvenilmez; sonuç sağlayıcıdan sorgulanır.
 *  3. Callback idempotenttir: ödeme zaten 'basarili' ise no-op.
 *  4. Para çekildi ama masa gittiyse (hold süresi aşımı) OTOMATİK İADE yapılır.
 *  5. İade API çağrısı iptal transaction'ının DIŞINDA yapılır; düşerse
 *     denetim kaydına 'iade_bekliyor' yazılır (admin panosu Faz 4'te gösterir).
 */
final class OdemeYonetici
{
    /**
     * Ödeme başlatır; müşterinin yönlendirileceği URL'i döner.
     * @return string yönlendirme URL'i
     * @throws OdemeHatasi|RezervasyonHatasi
     */
    public static function baslat(string $kod): string
    {
        $detay = RezervasyonIslemleri::kodIleDetay($kod);
        if ($detay === null) {
            throw new RezervasyonHatasi('Rezervasyon bulunamadı.');
        }
        if ($detay['durum'] === 'onaylandi') {
            return '/rezervasyon/' . $kod; // zaten ödendi
        }
        if (!MasaTutma::holdGecerliMi($detay)) {
            throw new RezervasyonHatasi('Rezervasyon süresi doldu. Lütfen baştan başlayın.');
        }
        if ((int) $detay['toplam_tutar_kurus'] <= 0) {
            throw new OdemeHatasi('Bu rezervasyon ücretsizdir; ödeme gerekmez.');
        }
        if ($detay['ad_soyad'] === '' || $detay['eposta'] === '') {
            throw new RezervasyonHatasi('Önce iletişim bilgilerinizi doldurun.');
        }

        $saglayici = OdemeSecici::olustur();
        $konusmaKimligi = $kod . '-' . strtolower(Guvenlik::rastgeleKod(10, 'abcdefhjkmnprtuvwxyz23456789'));
        $simdi = simdiUtc();

        Veritabani::calistir(
            'INSERT INTO odemeler (rezervasyon_id, saglayici, tutar_kurus, para_birimi, durum, konusma_kimligi, olusturma_zamani, guncelleme_zamani)
             VALUES (?, ?, ?, \'TRY\', \'baslatildi\', ?, ?, ?)',
            [$detay['id'], $saglayici->ad(), $detay['toplam_tutar_kurus'], $konusmaKimligi, $simdi, $simdi]
        );

        $donusUrl = Ayarlar::tabanUrl() . '/odeme/geri-donus';
        try {
            $baslatma = $saglayici->odemeBaslat($detay, $konusmaKimligi, $donusUrl);
        } catch (OdemeHatasi $hata) {
            Veritabani::calistir(
                "UPDATE odemeler SET durum = 'basarisiz', ham_cevap = ?, guncelleme_zamani = ? WHERE konusma_kimligi = ?",
                [json_encode(['baslatma_hatasi' => $hata->getMessage()], JSON_UNESCAPED_UNICODE), simdiUtc(), $konusmaKimligi]
            );
            throw $hata;
        }

        Veritabani::calistir(
            'UPDATE odemeler SET ham_cevap = ?, guncelleme_zamani = ? WHERE konusma_kimligi = ?',
            [json_encode($baslatma['ham'], JSON_UNESCAPED_UNICODE), simdiUtc(), $konusmaKimligi]
        );

        return $baslatma['yonlendirme_url'];
    }

    /**
     * Sağlayıcı callback'i işler; müşterinin gideceği yolu döner.
     * @return array{yol: string, mesaj: ?string}
     */
    public static function geriDonus(string $jeton, ?string $konusmaKimligi = null): array
    {
        $saglayici = OdemeSecici::olustur();
        $sonuc = $saglayici->odemeSorgula($jeton, $konusmaKimligi);

        $kk = $konusmaKimligi ?? $sonuc['konusma_kimligi'];
        $odeme = $kk !== null
            ? Veritabani::satir('SELECT * FROM odemeler WHERE konusma_kimligi = ?', [$kk])
            : null;
        if ($odeme === null) {
            DenetimKaydi::yaz('odeme_eslesmedi', ['jeton' => $jeton, 'konusma_kimligi' => $kk]);
            return ['yol' => '/', 'mesaj' => 'Ödeme kaydı eşleştirilemedi; işletmeyle iletişime geçin.'];
        }

        $rezervasyon = Veritabani::satir('SELECT * FROM rezervasyonlar WHERE id = ?', [$odeme['rezervasyon_id']]);
        $kod = (string) $rezervasyon['kod'];
        $_SESSION['rez_erisim'][$kod] = true; // 3DS dönüşünde oturum erişimi tazele

        // İdempotency: bu ödeme zaten sonuçlandırıldıysa tekrar işlem yapma
        if ($odeme['durum'] === 'basarili') {
            return ['yol' => '/rezervasyon/' . $kod, 'mesaj' => null];
        }

        if (!$sonuc['basarili']) {
            Veritabani::calistir(
                "UPDATE odemeler SET durum = 'basarisiz', ham_cevap = ?, guncelleme_zamani = ? WHERE id = ? AND durum = 'baslatildi'",
                [json_encode($sonuc['ham'], JSON_UNESCAPED_UNICODE), simdiUtc(), $odeme['id']]
            );
            return [
                'yol'   => '/rezervasyon/' . $kod . '/odeme',
                'mesaj' => 'Ödeme tamamlanamadı. Kartınız reddedilmiş olabilir; süre dolmadan yeniden deneyebilirsiniz.',
            ];
        }

        // Tutar doğrulaması: kuruş kuruşuna eşit olmalı
        if ($sonuc['odenen_kurus'] !== null && $sonuc['odenen_kurus'] !== (int) $odeme['tutar_kurus']) {
            DenetimKaydi::yaz('odeme_tutar_uyusmadi', [
                'konusma_kimligi' => $kk,
                'beklenen' => $odeme['tutar_kurus'],
                'odenen' => $sonuc['odenen_kurus'],
            ]);
            self::iadeDene($saglayici, $odeme, $sonuc, (int) ($sonuc['odenen_kurus'] ?? 0));
            return ['yol' => '/rezervasyon/' . $kod . '/odeme', 'mesaj' => 'Ödeme tutarı doğrulanamadı; tahsilat iade edildi. Lütfen yeniden deneyin.'];
        }

        // Ödeme gerçek: önce ödemeyi işaretle (para izi asla kaybolmaz)
        Veritabani::calistir(
            "UPDATE odemeler SET durum = 'basarili', saglayici_odeme_id = ?, ham_cevap = ?, guncelleme_zamani = ? WHERE id = ?",
            [$sonuc['saglayici_odeme_id'], json_encode($sonuc['ham'], JSON_UNESCAPED_UNICODE), simdiUtc(), $odeme['id']]
        );
        $odeme = Veritabani::satir('SELECT * FROM odemeler WHERE id = ?', [$odeme['id']]);

        try {
            RezervasyonIslemleri::onayla($kod);
        } catch (RezervasyonHatasi $hata) {
            // Para çekildi ama masa kesinleştirilemedi (hold süresi aşımı + masa kapıldı):
            // otomatik iade + özür. 10 dk hold penceresinde pratikte çok nadirdir.
            self::iadeDene($saglayici, $odeme, $sonuc, (int) $odeme['tutar_kurus']);
            RezervasyonEpostalari::iptalGonder($kod, (int) $odeme['tutar_kurus']);
            DenetimKaydi::yaz('odeme_sonrasi_onay_hatasi', ['kod' => $kod, 'hata' => $hata->getMessage()]);
            return [
                'yol'   => '/mac/' . $rezervasyon['mac_id'],
                'mesaj' => 'Ödemeniz alındı ancak masa süresi dolduğu için kesinleştirilemedi. Tutarın tamamının iadesi başlatıldı; dilerseniz yeniden yer seçebilirsiniz.',
            ];
        }

        RezervasyonEpostalari::onayGonder($kod);
        return ['yol' => '/rezervasyon/' . $kod, 'mesaj' => null];
    }

    /**
     * Müşteri iptali + iade (tek akış). Pencere kontrolü RezervasyonIslemleri'ndedir.
     * @throws RezervasyonHatasi
     */
    public static function iadeliIptal(string $kod, bool $pencereKontrol = true, ?int $kismiIadeKurus = null): void
    {
        $detay = RezervasyonIslemleri::kodIleDetay($kod);
        if ($detay === null) {
            throw new RezervasyonHatasi('Rezervasyon bulunamadı.');
        }

        $odeme = Veritabani::satir(
            "SELECT * FROM odemeler WHERE rezervasyon_id = ? AND durum = 'basarili' ORDER BY id DESC",
            [$detay['id']]
        );

        // Önce iptal (masalar boşalır) — pencere kontrolü içeride
        RezervasyonIslemleri::iptalEt($kod, $pencereKontrol);

        $iadeTutari = 0;
        if ($odeme !== null) {
            $iadeTutari = $kismiIadeKurus ?? (int) $odeme['tutar_kurus'];
            $saglayici = OdemeSecici::olustur();
            self::iadeDene($saglayici, $odeme, null, $iadeTutari);
        }

        RezervasyonEpostalari::iptalGonder($kod, $iadeTutari);
    }

    /**
     * Reconciliation (cron + süresi dolan rezervasyonlar): 'baslatildi' kalmış
     * ödemeleri sağlayıcıdan sorgular. Para çekildiyse rezervasyonu kurtarır;
     * kurtarılamıyorsa iade eder. Callback kaybı senaryosunun sigortasıdır.
     * @return array{kurtarilan: int, iade_edilen: int, kontrol_edilen: int}
     */
    public static function askidaKalanlariKurtar(): array
    {
        $saglayici = OdemeSecici::olustur();
        $simdi = simdiUtc();

        // Hold süresi bitmiş ama 'baslatildi' ödemesi olan rezervasyonlar
        $askidakiler = Veritabani::satirlar(
            "SELECT o.*, r.kod AS rezervasyon_kodu, r.durum AS rezervasyon_durumu
             FROM odemeler o
             JOIN rezervasyonlar r ON r.id = o.rezervasyon_id
             WHERE o.durum = 'baslatildi'
               AND o.olusturma_zamani < ?
             ORDER BY o.id ASC LIMIT 50",
            [utcKaydir($simdi, -3)] // en az 3 dk beklemiş olsun (normal akışa karışma)
        );

        $kurtarilan = 0;
        $iadeEdilen = 0;
        foreach ($askidakiler as $odeme) {
            // Mock'ta jeton = konusma_kimligi; iyzico'da initialize'da saklanan token
            $ham = json_decode((string) ($odeme['ham_cevap'] ?? ''), true) ?: [];
            $jeton = $saglayici->ad() === 'mock'
                ? (string) $odeme['konusma_kimligi']
                : (string) ($ham['token'] ?? '');
            if ($jeton === '') {
                continue; // sorgulanacak jeton yok: banka sayfası hiç açılmamış
            }

            $sonuc = $saglayici->odemeSorgula($jeton, (string) $odeme['konusma_kimligi']);
            if (!$sonuc['basarili']) {
                // Para çekilmemiş: güvenle kapat
                Veritabani::calistir(
                    "UPDATE odemeler SET durum = 'basarisiz', guncelleme_zamani = ? WHERE id = ? AND durum = 'baslatildi'",
                    [simdiUtc(), $odeme['id']]
                );
                continue;
            }

            // Para çekilmiş! Ödemeyi işaretle, rezervasyonu kurtarmayı dene
            Veritabani::calistir(
                "UPDATE odemeler SET durum = 'basarili', saglayici_odeme_id = ?, ham_cevap = ?, guncelleme_zamani = ? WHERE id = ?",
                [$sonuc['saglayici_odeme_id'], json_encode($sonuc['ham'], JSON_UNESCAPED_UNICODE), simdiUtc(), $odeme['id']]
            );
            $odemeGuncel = Veritabani::satir('SELECT * FROM odemeler WHERE id = ?', [$odeme['id']]);
            $kod = (string) $odeme['rezervasyon_kodu'];

            // Hold'u geçici canlandır (masalar hâlâ bu rezervasyondaysa onay CAS'ı geçer)
            Veritabani::calistir(
                "UPDATE rezervasyonlar SET durum = 'odeme_bekliyor', hold_sona_erme = ? WHERE kod = ? AND durum IN ('odeme_bekliyor','suresi_doldu')",
                [utcKaydir(simdiUtc(), 2), $kod]
            );
            try {
                RezervasyonIslemleri::onayla($kod);
                RezervasyonEpostalari::onayGonder($kod);
                DenetimKaydi::yaz('odeme_kurtarildi', ['kod' => $kod, 'konusma_kimligi' => $odeme['konusma_kimligi']]);
                $kurtarilan++;
            } catch (RezervasyonHatasi) {
                self::iadeDene($saglayici, $odemeGuncel, $sonuc, (int) $odeme['tutar_kurus']);
                RezervasyonEpostalari::iptalGonder($kod, (int) $odeme['tutar_kurus']);
                DenetimKaydi::yaz('odeme_kurtarilamadi_iade', ['kod' => $kod]);
                $iadeEdilen++;
            }
        }

        return ['kurtarilan' => $kurtarilan, 'iade_edilen' => $iadeEdilen, 'kontrol_edilen' => count($askidakiler)];
    }

    /**
     * Bekleyen iadeyi yeniden dener (admin raporlar ekranı).
     * Bekleyen iade = ödeme 'basarili' ama rezervasyonu 'iptal_edildi'.
     * @return array{tamam: bool, mesaj: string}
     */
    public static function iadeTekrarDene(int $odemeId): array
    {
        $odeme = Veritabani::satir('SELECT * FROM odemeler WHERE id = ?', [$odemeId]);
        if ($odeme === null) {
            return ['tamam' => false, 'mesaj' => 'Ödeme kaydı bulunamadı.'];
        }
        if ($odeme['durum'] === 'iade_edildi') {
            return ['tamam' => true, 'mesaj' => 'Bu ödeme zaten iade edilmiş.'];
        }
        if ($odeme['durum'] !== 'basarili') {
            return ['tamam' => false, 'mesaj' => 'Yalnızca başarılı tahsilat iade edilebilir (durum: ' . $odeme['durum'] . ').'];
        }

        self::iadeDene(OdemeSecici::olustur(), $odeme, null, (int) $odeme['tutar_kurus']);
        $sonDurum = (string) Veritabani::deger('SELECT durum FROM odemeler WHERE id = ?', [$odemeId]);
        return $sonDurum === 'iade_edildi'
            ? ['tamam' => true, 'mesaj' => 'İade başarıyla tamamlandı: ' . kurusBicimle((int) $odeme['tutar_kurus'])]
            : ['tamam' => false, 'mesaj' => 'İade yine başarısız; denetim kaydına ayrıntı yazıldı. Sağlayıcı panelinden manuel deneyin.'];
    }

    // ------------------------------------------------------------

    /** İadeyi dener; düşerse denetim kaydına 'iade_bekliyor' yazar (asla sessiz kalmaz). */
    private static function iadeDene(OdemeSaglayici $saglayici, array $odeme, ?array $sorguSonucu, int $tutarKurus): void
    {
        try {
            $iade = $saglayici->iadeYap($odeme, $tutarKurus);
        } catch (Throwable $hata) {
            $iade = ['basarili' => false, 'ham' => ['istisna' => $hata->getMessage()]];
        }

        if ($iade['basarili']) {
            Veritabani::calistir(
                "UPDATE odemeler SET durum = 'iade_edildi', iade_tutar_kurus = ?, iade_zamani = ?, guncelleme_zamani = ? WHERE id = ?",
                [$tutarKurus, simdiUtc(), simdiUtc(), $odeme['id']]
            );
            DenetimKaydi::yaz('iade_yapildi', [
                'konusma_kimligi' => $odeme['konusma_kimligi'],
                'tutar_kurus'     => $tutarKurus,
            ]);
        } else {
            DenetimKaydi::yaz('iade_bekliyor', [
                'konusma_kimligi' => $odeme['konusma_kimligi'],
                'odeme_id'        => $odeme['id'],
                'tutar_kurus'     => $tutarKurus,
                'detay'           => $iade['ham'],
            ]);
        }
    }
}
