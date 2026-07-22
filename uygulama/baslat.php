<?php

/**
 * Uygulama önyükleme: sabitler, otomatik sınıf yükleme, ortam, oturum.
 * Hem web (public/index.php) hem CLI (betikler/*) buradan başlar.
 */

declare(strict_types=1);

define('DIZIN_KOK', dirname(__DIR__));
define('DIZIN_UYGULAMA', __DIR__);
// İki yerleşim desteklenir:
//  - Geliştirme/ayrık: web kökü public/ alt klasörüdür (public/ vardır).
//  - Düz (paylaşımlı hosting): tüm dosyalar tek klasörde; public varlıkları köktedir.
define('DIZIN_PUBLIC', is_dir(DIZIN_KOK . '/public') ? DIZIN_KOK . '/public' : DIZIN_KOK);

// Tüm iç işlemler UTC'dir; görüntüleme uygulama/yardimcilar/tarih.php ile yapılır.
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

// Otomatik sınıf yükleme: uygulama altındaki klasörlerde SinifAdi.php aranır.
spl_autoload_register(function (string $sinif): void {
    static $klasorler = null;
    if ($klasorler === null) {
        $klasorler = array_merge(
            [DIZIN_UYGULAMA . '/cekirdek', DIZIN_UYGULAMA . '/denetleyiciler', DIZIN_UYGULAMA . '/modeller'],
            glob(DIZIN_UYGULAMA . '/servisler/*', GLOB_ONLYDIR) ?: [],
            [DIZIN_UYGULAMA . '/servisler']
        );
    }
    foreach ($klasorler as $klasor) {
        $yol = $klasor . '/' . $sinif . '.php';
        if (is_file($yol)) {
            require $yol;
            return;
        }
    }
});

// Yardımcı fonksiyonlar (sınıf değil, düz fonksiyon dosyaları)
require DIZIN_UYGULAMA . '/yardimcilar/metin.php';
require DIZIN_UYGULAMA . '/yardimcilar/tarih.php';
require DIZIN_UYGULAMA . '/yardimcilar/para.php';
require DIZIN_UYGULAMA . '/yardimcilar/http.php';
require DIZIN_UYGULAMA . '/yardimcilar/sql.php';

// Composer paketleri (QR, e-posta) — vendor repo ile birlikte gelir
if (is_file(DIZIN_KOK . '/vendor/autoload.php')) {
    require DIZIN_KOK . '/vendor/autoload.php';
}

// .env yükle
Cevre::yukle(DIZIN_KOK . '/.env');

// GÜVENLİK KAPISI: üretimde mock ödeme sağlayıcısı kesinlikle çalışmaz
if (Cevre::al('ORTAM') === 'uretim' && strtolower((string) (Cevre::al('ODEME_SAGLAYICI', 'mock') ?? '')) === 'mock') {
    throw new RuntimeException(
        'GÜVENLİK: ORTAM=uretim iken ODEME_SAGLAYICI=mock olamaz. .env dosyasında iyzico seçin veya ortamı düzeltin.'
    );
}

// Hata görünürlüğü: geliştirmede açık, üretimde log'a
if (Cevre::al('ORTAM', 'uretim') === 'gelistirme') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Üretimde yakalanmamış hata: logla, müşteriye sade 500 sayfası göster
if (PHP_SAPI !== 'cli' && Cevre::al('ORTAM', 'uretim') !== 'gelistirme') {
    set_exception_handler(function (Throwable $hata): void {
        error_log('Yakalanmamış hata: ' . $hata->getMessage() . ' @ ' . $hata->getFile() . ':' . $hata->getLine());
        http_response_code(500);
        try {
            Sablon::goster('hatalar/500', ['baslik' => 'Bir sorun oluştu']);
        } catch (Throwable) {
            echo 'Beklenmeyen bir sorun oluştu. Lütfen daha sonra tekrar deneyin.';
        }
        exit;
    });
}

// Oturum yalnızca web isteklerinde başlar
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (($_SERVER['HTTPS'] ?? '') !== '' || Cevre::al('ORTAM') === 'uretim'),
    ]);
    session_start();
}
