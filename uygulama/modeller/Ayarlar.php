<?php

declare(strict_types=1);

/**
 * Ayarlar tablosu erişimi (anahtar-değer, değerler JSON).
 * Sık kullanılan ayarlar istek boyunca önbelleklenir.
 */
final class Ayarlar
{
    /** @var array<string, mixed> */
    private static array $onbellek = [];

    public static function al(string $anahtar, mixed $varsayilan = null): mixed
    {
        if (array_key_exists($anahtar, self::$onbellek)) {
            return self::$onbellek[$anahtar];
        }
        $ham = Veritabani::deger('SELECT deger FROM ayarlar WHERE anahtar = ?', [$anahtar]);
        $deger = $ham === null ? $varsayilan : json_decode((string) $ham, true);
        self::$onbellek[$anahtar] = $deger;
        return $deger;
    }

    public static function kaydet(string $anahtar, mixed $deger): void
    {
        $json = json_encode($deger, JSON_UNESCAPED_UNICODE);
        // Önce VARLIK kontrolü, sonra UPDATE/INSERT.
        // NOT: rowCount()'a GÜVENİLMEZ — MySQL bir satırı AYNI değerle güncelleyince
        // "0 satır değişti" döner; eski "UPDATE, 0 ise INSERT" deseni bu durumda
        // yinelenen birincil anahtar (anahtar) hatası verip 500'e yol açıyordu.
        $var = Veritabani::deger('SELECT 1 FROM ayarlar WHERE anahtar = ?', [$anahtar]);
        if ($var !== null) {
            Veritabani::calistir('UPDATE ayarlar SET deger = ? WHERE anahtar = ?', [$json, $anahtar]);
        } else {
            Veritabani::calistir('INSERT INTO ayarlar (anahtar, deger) VALUES (?, ?)', [$anahtar, $json]);
        }
        self::$onbellek[$anahtar] = $deger;
    }

    // --- Sık kullanılanlar için tipli kısayollar ---

    public static function holdDakika(): int
    {
        return (int) self::al('hold_dakika', 10);
    }

    public static function varsayilanIptalSaat(): int
    {
        return (int) self::al('varsayilan_iptal_saat', 2);
    }

    public static function restoranAdi(): string
    {
        return (string) self::al('restoran_adi', 'Restoranımız');
    }

    public static function siteAdi(): string
    {
        return (string) self::al('site_adi', 'Maç Gecesi Rezervasyon');
    }

    public static function tabanUrl(): string
    {
        return rtrim((string) (Cevre::al('TABAN_URL') ?? self::al('taban_url', '')), '/');
    }

    /** @return array<int, string> */
    public static function varsayilanPaket(): array
    {
        $paket = self::al('varsayilan_paket', []);
        return is_array($paket) ? $paket : [];
    }
}
