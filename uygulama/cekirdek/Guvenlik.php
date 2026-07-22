<?php

declare(strict_types=1);

/**
 * Güvenlik yardımcıları: CSRF, HMAC imza (QR bilet), güvenli kod üretimi.
 */
final class Guvenlik
{
    /** Rezervasyon kodu alfabesi: karışan karakterler (0/O, 1/I/L, S/5, G/6...) çıkarılmıştır. */
    public const KOD_ALFABESI = '23456789ABCDEFHJKMNPRTUVWXYZ';

    public static function csrfJetonu(): string
    {
        if (empty($_SESSION['csrf_jetonu'])) {
            $_SESSION['csrf_jetonu'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf_jetonu'];
    }

    public static function csrfDogrula(?string $jeton): bool
    {
        return is_string($jeton)
            && !empty($_SESSION['csrf_jetonu'])
            && hash_equals($_SESSION['csrf_jetonu'], $jeton);
    }

    /** CSRF geçersizse isteği 419 ile durdurur (form uç noktalarının ilk satırı). */
    public static function csrfZorunlu(): void
    {
        if (!self::csrfDogrula($_POST['csrf_jetonu'] ?? null)) {
            http_response_code(419);
            exit('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
        }
    }

    public static function rastgeleKod(int $uzunluk = 8, string $alfabe = self::KOD_ALFABESI): string
    {
        $kod = '';
        $ustSinir = strlen($alfabe) - 1;
        for ($i = 0; $i < $uzunluk; $i++) {
            $kod .= $alfabe[random_int(0, $ustSinir)];
        }
        return $kod;
    }

    /**
     * QR bilet imzası: HMAC-SHA256(kod|macId|nonce) → base64url ilk 22 karakter.
     * qr_nonce veritabanında durduğu için rezervasyon kodu sızsa bile QR taklit edilemez.
     */
    public static function qrImzala(string $rezervasyonKodu, int $macId, string $nonce): string
    {
        $anahtar = Cevre::zorunlu('QR_IMZA_ANAHTARI');
        $ozet = hash_hmac('sha256', $rezervasyonKodu . '|' . $macId . '|' . $nonce, $anahtar, true);
        return substr(rtrim(strtr(base64_encode($ozet), '+/', '-_'), '='), 0, 22);
    }

    public static function qrDogrula(string $rezervasyonKodu, int $macId, string $nonce, string $imza): bool
    {
        return hash_equals(self::qrImzala($rezervasyonKodu, $macId, $nonce), $imza);
    }
}
