<?php

/**
 * DÜZ YERLEŞİM ön denetleyici (paylaşımlı hosting / public_html).
 * Tüm dosyalar tek klasördedir; uygulama kodu index.php ile aynı seviyede
 * 'uygulama/' altındadır ve .htaccess ile web erişimine kapalıdır.
 */

declare(strict_types=1);

require __DIR__ . '/uygulama/baslat.php';

$yonlendirici = new Yonlendirici();
require DIZIN_UYGULAMA . '/rotalar.php';

$yonlendirici->calistir(
    $_SERVER['REQUEST_METHOD'],
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/'
);
