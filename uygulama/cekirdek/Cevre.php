<?php

declare(strict_types=1);

/**
 * .env dosyası okuyucu. Dış paket gerektirmez.
 * Örnek dosya: .env.ornek — kopyalayıp .env yapın, değerleri doldurun.
 */
final class Cevre
{
    /** @var array<string, string> */
    private static array $degerler = [];

    public static function yukle(string $dosyaYolu): void
    {
        if (!is_file($dosyaYolu)) {
            return; // .env yoksa yalnızca gerçek ortam değişkenleri kullanılır
        }
        foreach (file($dosyaYolu, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $satir) {
            $satir = trim($satir);
            if ($satir === '' || str_starts_with($satir, '#') || !str_contains($satir, '=')) {
                continue;
            }
            [$anahtar, $deger] = explode('=', $satir, 2);
            $anahtar = trim($anahtar);
            $deger   = trim($deger);
            // Tırnaklı değer desteği: ANAHTAR="değer"
            if (strlen($deger) >= 2 && ($deger[0] === '"' || $deger[0] === "'") && str_ends_with($deger, $deger[0])) {
                $deger = substr($deger, 1, -1);
            }
            self::$degerler[$anahtar] = $deger;
        }
    }

    public static function al(string $anahtar, ?string $varsayilan = null): ?string
    {
        $ortamDegeri = getenv($anahtar);
        if ($ortamDegeri !== false && $ortamDegeri !== '') {
            return $ortamDegeri;
        }
        return self::$degerler[$anahtar] ?? $varsayilan;
    }

    public static function zorunlu(string $anahtar): string
    {
        $deger = self::al($anahtar);
        if ($deger === null || $deger === '') {
            throw new RuntimeException("Zorunlu ortam değişkeni eksik: {$anahtar} (.env dosyasını kontrol edin)");
        }
        return $deger;
    }
}
