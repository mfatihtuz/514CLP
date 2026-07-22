<?php

declare(strict_types=1);

/**
 * Müşteri rezervasyon akışı: hold → bilgi → (ödeme) → bilet → iptal.
 *
 * Bilet erişim güvenliği: oturumda erişim izni (kendi akışı / sorgulama)
 * VEYA e-postadaki imzalı link (?e=IMZA). Kod tek başına yeterli DEĞİLDİR.
 */
final class RezervasyonDenetleyici
{
    // ---------- API: hold ----------

    public function holdAl(array $parametreler = []): void
    {
        $govde = jsonGovdeOku();
        jsonCsrfZorunlu($govde);

        // Basit oran sınırı: aynı oturumdan saniyede 1 hold isteği
        $simdi = microtime(true);
        if (isset($_SESSION['son_hold_istegi']) && $simdi - (float) $_SESSION['son_hold_istegi'] < 1.0) {
            Sablon::json(['tamam' => false, 'hata' => 'Çok hızlı istek; bir saniye bekleyin.'], 429);
            return;
        }
        $_SESSION['son_hold_istegi'] = $simdi;

        $macId = (int) ($govde['mac_id'] ?? 0);
        $kisiSayisi = (int) ($govde['kisi_sayisi'] ?? 0);
        $tur = (string) ($govde['tur'] ?? 'masa');

        try {
            if ($tur === 'salon') {
                $sonuc = MasaTutma::salonTut($macId, $kisiSayisi);
            } else {
                $masaIdler = is_array($govde['masa_idler'] ?? null) ? $govde['masa_idler'] : [];
                $sonuc = MasaTutma::masaTut($macId, $masaIdler, $kisiSayisi);
            }
        } catch (RezervasyonHatasi $hata) {
            Sablon::json(['tamam' => false, 'hata' => $hata->getMessage()], 409);
            return;
        }

        $_SESSION['rez_erisim'][$sonuc['kod']] = true;
        Sablon::json([
            'tamam'      => true,
            'kod'        => $sonuc['kod'],
            'yonlendir'  => '/rezervasyon/' . $sonuc['kod'] . '/bilgi',
        ]);
    }

    public function holdBirakApi(array $parametreler = []): void
    {
        $govde = jsonGovdeOku();
        $kod = strtoupper(trim((string) ($govde['kod'] ?? '')));
        // sendBeacon CSRF taşıyamayabilir; yalnızca oturumun kendi hold'u bırakılabilir
        if ($kod !== '' && !empty($_SESSION['rez_erisim'][$kod])) {
            MasaTutma::holdBirak($kod);
        }
        Sablon::json(['tamam' => true]);
    }

    // ---------- Bilgi adımı ----------

    public function bilgiForm(array $parametreler): void
    {
        $rezervasyon = $this->erisimliRezervasyon((string) $parametreler['kod']);
        if ($rezervasyon === null) {
            return;
        }
        if ($rezervasyon['durum'] === 'onaylandi') {
            Sablon::yonlendir('/rezervasyon/' . $rezervasyon['kod']);
        }
        if (!MasaTutma::holdGecerliMi($rezervasyon)) {
            $this->suresiDoldu($rezervasyon);
            return;
        }

        Sablon::goster('rezervasyon/bilgi', [
            'baslik'      => 'Rezervasyon Bilgileri',
            'rezervasyon' => $rezervasyon,
            'detay'       => RezervasyonIslemleri::kodIleDetay($rezervasyon['kod']),
            'masalar'     => RezervasyonIslemleri::masalari($rezervasyon),
            'hatalar'     => [],
            'girdi'       => ['ad_soyad' => $rezervasyon['ad_soyad'], 'telefon' => $rezervasyon['telefon'], 'eposta' => $rezervasyon['eposta']],
        ]);
    }

    public function bilgiKaydet(array $parametreler): void
    {
        Guvenlik::csrfZorunlu();
        $rezervasyon = $this->erisimliRezervasyon((string) $parametreler['kod']);
        if ($rezervasyon === null) {
            return;
        }

        $hatalar = RezervasyonIslemleri::bilgiKaydet(
            $rezervasyon['kod'],
            (string) ($_POST['ad_soyad'] ?? ''),
            (string) ($_POST['telefon'] ?? ''),
            (string) ($_POST['eposta'] ?? ''),
            isset($_POST['kvkk_onay']),
            isset($_POST['sozlesme_onay'])
        );

        if ($hatalar !== []) {
            Sablon::goster('rezervasyon/bilgi', [
                'baslik'      => 'Rezervasyon Bilgileri',
                'rezervasyon' => $rezervasyon,
                'detay'       => RezervasyonIslemleri::kodIleDetay($rezervasyon['kod']),
                'masalar'     => RezervasyonIslemleri::masalari($rezervasyon),
                'hatalar'     => $hatalar,
                'girdi'       => $_POST,
            ]);
            return;
        }

        // Ücretsiz maç: doğrudan onay + e-posta. Ücretli: ödeme adımına.
        if ((int) $rezervasyon['toplam_tutar_kurus'] === 0) {
            try {
                RezervasyonIslemleri::onayla($rezervasyon['kod']);
            } catch (RezervasyonHatasi $hata) {
                $this->suresiDoldu($rezervasyon, $hata->getMessage());
                return;
            }
            RezervasyonEpostalari::onayGonder($rezervasyon['kod']);
            Sablon::yonlendir('/rezervasyon/' . $rezervasyon['kod']);
        }

        Sablon::yonlendir('/rezervasyon/' . $rezervasyon['kod'] . '/odeme');
    }

    // ---------- Ödeme adımı (Faz 3'te sağlayıcıya bağlanır) ----------

    public function odemeSayfasi(array $parametreler): void
    {
        $rezervasyon = $this->erisimliRezervasyon((string) $parametreler['kod']);
        if ($rezervasyon === null) {
            return;
        }
        if ($rezervasyon['durum'] === 'onaylandi') {
            Sablon::yonlendir('/rezervasyon/' . $rezervasyon['kod']);
        }
        if (!MasaTutma::holdGecerliMi($rezervasyon)) {
            $this->suresiDoldu($rezervasyon);
            return;
        }

        Sablon::goster('rezervasyon/odeme', [
            'baslik'      => 'Ödeme',
            'rezervasyon' => $rezervasyon,
            'detay'       => RezervasyonIslemleri::kodIleDetay($rezervasyon['kod']),
            'masalar'     => RezervasyonIslemleri::masalari($rezervasyon),
        ]);
    }

    // ---------- Bilet ----------

    public function bilet(array $parametreler): void
    {
        $kod = strtoupper(trim((string) $parametreler['kod']));
        $detay = RezervasyonIslemleri::kodIleDetay($kod);
        if ($detay === null) {
            http_response_code(404);
            Sablon::goster('hatalar/404', ['baslik' => 'Rezervasyon bulunamadı']);
            return;
        }

        // Erişim: oturum izni VEYA e-postadaki imzalı link
        $imza = (string) ($_GET['e'] ?? '');
        $imzaliErisim = $imza !== '' && Guvenlik::qrDogrula(
            (string) $detay['kod'],
            (int) $detay['mac_id'],
            (string) $detay['qr_nonce'],
            $imza
        );
        if ($imzaliErisim) {
            $_SESSION['rez_erisim'][$kod] = true;
        }
        if (empty($_SESSION['rez_erisim'][$kod])) {
            Sablon::yonlendir('/rezervasyon-sorgula?kod=' . urlencode($kod));
        }

        if ($detay['durum'] === 'odeme_bekliyor') {
            if (MasaTutma::holdGecerliMi($detay)) {
                Sablon::yonlendir('/rezervasyon/' . $kod . '/bilgi');
            }
            $this->suresiDoldu($detay);
            return;
        }

        Sablon::goster('rezervasyon/bilet', [
            'baslik'          => 'Biletiniz — ' . $detay['kod'],
            'detay'           => $detay,
            'masalar'         => RezervasyonIslemleri::masalari($detay),
            'paket'           => MacSorgulari::paketIcerigi($detay),
            'qrDataUri'       => $detay['durum'] === 'onaylandi' ? QrUretici::dataUri($detay) : null,
            'iptalEdilebilir' => RezervasyonIslemleri::iptalEdilebilirMi($detay),
            'sonIptal'        => RezervasyonEpostalari::sonIptalMetni($detay),
        ]);
    }

    // ---------- Sorgulama ----------

    public function sorgulaForm(array $parametreler = []): void
    {
        Sablon::goster('rezervasyon/sorgula', [
            'baslik' => 'Rezervasyon Sorgula',
            'hata'   => null,
            'kod'    => (string) ($_GET['kod'] ?? ''),
        ]);
    }

    public function sorgula(array $parametreler = []): void
    {
        Guvenlik::csrfZorunlu();
        usleep(250000); // kaba kuvvet yavaşlatma
        $kod = strtoupper(trim((string) ($_POST['kod'] ?? '')));
        $telefon = telefonNormallestir((string) ($_POST['telefon'] ?? ''));

        $rezervasyon = $kod !== '' ? RezervasyonIslemleri::kodIleBul($kod) : null;
        if ($rezervasyon === null || $telefon === null || $rezervasyon['telefon'] !== $telefon) {
            Sablon::goster('rezervasyon/sorgula', [
                'baslik' => 'Rezervasyon Sorgula',
                'hata'   => 'Kod ve telefon eşleşmedi. Onay e-postanızdaki kodu ve rezervasyonda kullandığınız telefonu girin.',
                'kod'    => $kod,
            ]);
            return;
        }

        $_SESSION['rez_erisim'][$kod] = true;
        Sablon::yonlendir('/rezervasyon/' . $kod);
    }

    // ---------- İptal ----------

    public function iptal(array $parametreler): void
    {
        Guvenlik::csrfZorunlu();
        $kod = strtoupper(trim((string) $parametreler['kod']));
        if (empty($_SESSION['rez_erisim'][$kod])) {
            Sablon::yonlendir('/rezervasyon-sorgula?kod=' . urlencode($kod));
        }
        $detay = RezervasyonIslemleri::kodIleDetay($kod);
        if ($detay === null) {
            http_response_code(404);
            return;
        }

        try {
            if ((int) $detay['toplam_tutar_kurus'] > 0) {
                // Ücretli rezervasyon: iptal + iade TEK işlem olarak ödeme katmanında (Faz 3)
                if (!class_exists('OdemeYonetici')) {
                    throw new RezervasyonHatasi('Ücretli rezervasyon iptali için lütfen işletmeyi arayın.');
                }
                OdemeYonetici::iadeliIptal($kod);
            } else {
                RezervasyonIslemleri::iptalEt($kod);
                RezervasyonEpostalari::iptalGonder($kod, 0);
            }
        } catch (RezervasyonHatasi $hata) {
            $_SESSION['tek_seferlik_mesaj'] = $hata->getMessage();
        }

        Sablon::yonlendir('/rezervasyon/' . $kod);
    }

    // ------------------------------------------------------------

    /** @return array<string, mixed>|null Erişim yoksa yönlendirir ve null döner. */
    private function erisimliRezervasyon(string $kod): ?array
    {
        $kod = strtoupper(trim($kod));
        if (empty($_SESSION['rez_erisim'][$kod])) {
            Sablon::yonlendir('/rezervasyon-sorgula?kod=' . urlencode($kod));
        }
        $rezervasyon = RezervasyonIslemleri::kodIleBul($kod);
        if ($rezervasyon === null) {
            http_response_code(404);
            Sablon::goster('hatalar/404', ['baslik' => 'Rezervasyon bulunamadı']);
            return null;
        }
        return $rezervasyon;
    }

    private function suresiDoldu(array $rezervasyon, ?string $mesaj = null): void
    {
        Sablon::goster('rezervasyon/suresi-doldu', [
            'baslik' => 'Süre Doldu',
            'macId'  => (int) $rezervasyon['mac_id'],
            'mesaj'  => $mesaj,
        ]);
    }
}
