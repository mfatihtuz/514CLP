<?php

declare(strict_types=1);

/**
 * HTTP yardımcıları: JSON gövde okuma (kroki editörü ve API uç noktaları).
 */

/** @return array<string, mixed> Gövde JSON değilse boş dizi döner. */
function jsonGovdeOku(): array
{
    $ham = file_get_contents('php://input');
    if ($ham === false || $ham === '') {
        return [];
    }
    $veri = json_decode($ham, true);
    return is_array($veri) ? $veri : [];
}

/** JSON isteklerde CSRF: jeton gövdede 'csrf_jetonu' alanıyla gelir. */
function jsonCsrfZorunlu(array $govde): void
{
    if (!Guvenlik::csrfDogrula($govde['csrf_jetonu'] ?? null)) {
        Sablon::json(['hata' => 'Oturum doğrulaması başarısız. Sayfayı yenileyin.'], 419);
        exit;
    }
}
