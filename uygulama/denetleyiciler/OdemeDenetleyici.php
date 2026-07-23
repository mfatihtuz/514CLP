<?php

declare(strict_types=1);

/** Ödeme başlatma ve sağlayıcı geri dönüşü (callback). */
final class OdemeDenetleyici
{
    /** POST /rezervasyon/{kod}/odeme/baslat — sağlayıcıya yönlendirir. */
    public function baslat(array $parametreler): void
    {
        Guvenlik::csrfZorunlu();
        $kod = strtoupper(trim((string) $parametreler['kod']));
        if (empty($_SESSION['rez_erisim'][$kod])) {
            Sablon::yonlendir('/rezervasyon-sorgula?kod=' . urlencode($kod));
        }

        try {
            $url = OdemeYonetici::baslat($kod);
        } catch (RezervasyonHatasi | OdemeHatasi $hata) {
            $_SESSION['tek_seferlik_mesaj'] = $hata->getMessage();
            Sablon::yonlendir('/rezervasyon/' . $kod . '/odeme');
            return;
        } catch (Throwable $hata) {
            // Beklenmeyen ödeme hatası: 500 yerine anlaşılır mesaj
            error_log('Ödeme başlatma hatası: ' . $hata->getMessage());
            $_SESSION['tek_seferlik_mesaj'] = 'Ödeme başlatılamadı. Lütfen tekrar deneyin veya işletmeyle iletişime geçin.';
            Sablon::yonlendir('/rezervasyon/' . $kod . '/odeme');
            return;
        }

        Sablon::yonlendir($url);
    }

    /**
     * Sağlayıcı callback'i (iyzico token POST'lar; mock da aynı biçimi kullanır).
     * Sonuç DAIMA sağlayıcıdan sorgulanır; buradaki parametreler yalnız anahtardır.
     */
    public function geriDonus(array $parametreler = []): void
    {
        $jeton = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
        $konusmaKimligi = ($_POST['conversationId'] ?? $_GET['kk'] ?? null);
        $konusmaKimligi = is_string($konusmaKimligi) && $konusmaKimligi !== '' ? $konusmaKimligi : null;

        if ($jeton === '') {
            Sablon::yonlendir('/');
        }

        $sonuc = OdemeYonetici::geriDonus($jeton, $konusmaKimligi);
        if ($sonuc['mesaj'] !== null) {
            $_SESSION['tek_seferlik_mesaj'] = $sonuc['mesaj'];
        }
        Sablon::yonlendir($sonuc['yol']);
    }
}
