<?php

declare(strict_types=1);

/**
 * Rota tanımları. $yonlendirici public/index.php içinde oluşturulur.
 * Yeni fazlarda rotalar bu dosyaya eklenir (müşteri + admin + api).
 *
 * @var Yonlendirici $yonlendirici
 */

// ---- Müşteri ----
$yonlendirici->get('/', AnaSayfaDenetleyici::class, 'listele');
