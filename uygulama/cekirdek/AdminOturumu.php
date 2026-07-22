<?php

declare(strict_types=1);

/**
 * Admin oturum yönetimi (PHP session tabanlı).
 * Panel sayfaları ilk satırda AdminOturumu::zorunlu() çağırır.
 */
final class AdminOturumu
{
    /** @var array<string, mixed>|null */
    private static ?array $aktifAdmin = null;

    public static function girisDene(string $eposta, string $sifre): bool
    {
        // Kaba kuvvet denemelerini yavaşlat + oturum başına deneme sınırı
        usleep(300000);
        $deneme = (int) ($_SESSION['giris_deneme'] ?? 0);
        if ($deneme >= 10) {
            return false;
        }
        $_SESSION['giris_deneme'] = $deneme + 1;

        $admin = Veritabani::satir('SELECT * FROM admin_kullanicilar WHERE eposta = ?', [mb_strtolower(trim($eposta))]);
        if ($admin === null || !password_verify($sifre, (string) $admin['sifre_ozeti'])) {
            DenetimKaydi::yaz('giris_basarisiz', ['eposta' => $eposta]);
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        unset($_SESSION['giris_deneme']);
        Veritabani::calistir(
            'UPDATE admin_kullanicilar SET son_giris_zamani = ? WHERE id = ?',
            [simdiUtc(), $admin['id']]
        );
        return true;
    }

    public static function aktifId(): ?int
    {
        $id = $_SESSION['admin_id'] ?? null;
        return is_int($id) ? $id : null;
    }

    /** @return array<string, mixed>|null */
    public static function aktifAdmin(): ?array
    {
        $id = self::aktifId();
        if ($id === null) {
            return null;
        }
        if (self::$aktifAdmin === null || (int) self::$aktifAdmin['id'] !== $id) {
            self::$aktifAdmin = Veritabani::satir('SELECT * FROM admin_kullanicilar WHERE id = ?', [$id]);
        }
        return self::$aktifAdmin;
    }

    /** Oturum yoksa giriş sayfasına yönlendirir ve isteği durdurur. */
    public static function zorunlu(): void
    {
        if (self::aktifId() === null) {
            Sablon::yonlendir('/admin/giris');
        }
    }

    /** API (JSON) uç noktaları için: oturum yoksa 401 döner. */
    public static function apiZorunlu(): void
    {
        if (self::aktifId() === null) {
            Sablon::json(['hata' => 'Oturum gerekli'], 401);
            exit;
        }
    }

    public static function cikis(): void
    {
        unset($_SESSION['admin_id']);
        session_regenerate_id(true);
    }
}
