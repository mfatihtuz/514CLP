<?php

declare(strict_types=1);

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QROutputInterface;

/**
 * QR bilet üretimi (chillerlan/php-qrcode, vendor'da).
 * İçerik: check-in URL'i + HMAC imzalı jeton — doğrulama daima sunucudadır.
 */
final class QrUretici
{
    /** Rezervasyonun QR jetonunu üretir: KOD.IMZA */
    public static function jeton(array $rezervasyon): string
    {
        return $rezervasyon['kod'] . '.' . Guvenlik::qrImzala(
            (string) $rezervasyon['kod'],
            (int) $rezervasyon['mac_id'],
            (string) $rezervasyon['qr_nonce']
        );
    }

    /** QR içeriği: herhangi bir okuyucuda anlamlı bir URL görünür. */
    public static function icerik(array $rezervasyon): string
    {
        return Ayarlar::tabanUrl() . '/checkin?t=' . self::jeton($rezervasyon);
    }

    /** Tarayıcıda gösterim için data URI (PNG). */
    public static function dataUri(array $rezervasyon): string
    {
        return self::olustur($rezervasyon, true);
    }

    /** E-postaya CID ile gömmek için ham PNG baytları. */
    public static function pngBaytlari(array $rezervasyon): string
    {
        return self::olustur($rezervasyon, false);
    }

    private static function olustur(array $rezervasyon, bool $base64): string
    {
        $secenekler = new QROptions();
        $secenekler->outputType = QROutputInterface::GDIMAGE_PNG;
        $secenekler->scale = 6;
        $secenekler->quietzoneSize = 2;
        $secenekler->outputBase64 = $base64;
        $secenekler->drawLightModules = true;

        return (new QRCode($secenekler))->render(self::icerik($rezervasyon));
    }
}
