<?php

/**
 * Ön denetleyici: tüm istekler .htaccess ile bu dosyaya yönlenir.
 * Uygulama kodu web kökünün DIŞINDA (../uygulama) durur.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/uygulama/baslat.php';

$yonlendirici = new Yonlendirici();
require DIZIN_UYGULAMA . '/rotalar.php';

$yonlendirici->calistir(
    $_SERVER['REQUEST_METHOD'],
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/'
);
