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

/**
 * Oturum yazma kilidini erkenden bırakır (PERFORMANS/DONMA ÖNLEME).
 *
 * PHP oturum dosyasını istek boyunca kilitli tutar; yanıt istemciye
 * gönderilirken ya da uzun bir dış çağrı sürerken bu kilit, aynı kullanıcının
 * diğer isteklerini bekletir (tıklayınca "cevap gelmiyor, fare dönüyor").
 * Tüm oturum YAZMALARI bittikten sonra bu çağrıyla kilit bırakılır; $_SESSION
 * bellekte okunur kalır, yalnızca yeni yazmalar artık kalıcı olmaz.
 */
function oturumKilidiniBirak(): void
{
    if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}
