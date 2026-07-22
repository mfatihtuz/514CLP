<?php

declare(strict_types=1);

/**
 * Görünüm (view) motoru. Görünümler uygulama/goruntuler/ altındadır.
 * Görünüm önce tampona alınır, sonra düzen (layout) içine $icerik olarak gömülür.
 */
final class Sablon
{
    /** @param array<string, mixed> $veri */
    public static function goster(string $gorunum, array $veri = [], string $duzen = 'duzen/ana'): void
    {
        // Görünüm önce hazırlanır (şablonlar bu sırada CSRF jetonu vb. oturuma yazar),
        // ardından oturum kilidi BIRAKILIR; yanıt istemciye kilitsiz gönderilir
        // → aynı kullanıcının sonraki tıklamaları beklemez (donma önleme).
        $html = self::olustur($gorunum, $veri, $duzen);
        oturumKilidiniBirak();
        echo $html;
    }

    /** @param array<string, mixed> $veri */
    public static function olustur(string $gorunum, array $veri = [], ?string $duzen = 'duzen/ana'): string
    {
        $icerik = self::parcaOlustur($gorunum, $veri);
        if ($duzen === null) {
            return $icerik;
        }
        return self::parcaOlustur($duzen, $veri + ['icerik' => $icerik]);
    }

    /** Düzen olmadan tek parça render eder (e-posta şablonları, ajax parçaları).
     *  @param array<string, mixed> $veri */
    public static function parcaOlustur(string $gorunum, array $veri = []): string
    {
        $dosya = DIZIN_UYGULAMA . '/goruntuler/' . $gorunum . '.php';
        if (!is_file($dosya)) {
            throw new RuntimeException("Görünüm bulunamadı: {$gorunum}");
        }
        extract($veri, EXTR_SKIP);
        ob_start();
        require $dosya;
        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $veri JSON yanıtı (API uç noktaları) */
    public static function json(array $veri, int $durumKodu = 200): void
    {
        http_response_code($durumKodu);
        header('Content-Type: application/json; charset=utf-8');
        oturumKilidiniBirak();   // yanıt gönderilmeden kilidi bırak (donma önleme)
        echo json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function yonlendir(string $hedef): void
    {
        header('Location: ' . $hedef, true, 302);
        exit;
    }
}
