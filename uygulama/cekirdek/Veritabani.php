<?php

declare(strict_types=1);

/**
 * PDO sarmalayıcı. Üretimde MySQL (Hostinger), yerelde SQLite çalışır.
 * Sürücü .env'deki VT_SURUCU ile seçilir: mysql | sqlite
 *
 * Eşzamanlılık notu: masa kilitleme (hold) doğruluğu bu katmandaki
 * islem() + koşullu UPDATE deseniyle sağlanır; uygulama koduna güvenilmez.
 */
final class Veritabani
{
    private static ?PDO $pdo = null;

    public static function baglanti(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $surucu = Cevre::al('VT_SURUCU', 'mysql');

        if ($surucu === 'sqlite') {
            $yol = Cevre::al('VT_SQLITE_YOL', DIZIN_KOK . '/veritabani/gelistirme.sqlite');
            self::$pdo = new PDO('sqlite:' . $yol, null, null, self::secenekler());
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo->exec('PRAGMA busy_timeout = 5000');
        } else {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                Cevre::zorunlu('VT_SUNUCU'),
                Cevre::zorunlu('VT_AD')
            );
            self::$pdo = new PDO($dsn, Cevre::zorunlu('VT_KULLANICI'), Cevre::al('VT_SIFRE', ''), self::secenekler());
        }

        return self::$pdo;
    }

    /** @return array<int, mixed> */
    private static function secenekler(): array
    {
        return [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
    }

    /** @param array<string|int, mixed> $parametreler
     *  @return array<int, array<string, mixed>> */
    public static function satirlar(string $sql, array $parametreler = []): array
    {
        $ifade = self::baglanti()->prepare($sql);
        $ifade->execute($parametreler);
        return $ifade->fetchAll();
    }

    /** @param array<string|int, mixed> $parametreler
     *  @return array<string, mixed>|null */
    public static function satir(string $sql, array $parametreler = []): ?array
    {
        $ifade = self::baglanti()->prepare($sql);
        $ifade->execute($parametreler);
        $sonuc = $ifade->fetch();
        return $sonuc === false ? null : $sonuc;
    }

    /** @param array<string|int, mixed> $parametreler */
    public static function deger(string $sql, array $parametreler = []): mixed
    {
        $ifade = self::baglanti()->prepare($sql);
        $ifade->execute($parametreler);
        $sonuc = $ifade->fetchColumn();
        return $sonuc === false ? null : $sonuc;
    }

    /**
     * INSERT/UPDATE/DELETE çalıştırır, etkilenen satır sayısını döner.
     * CAS (koşullu UPDATE) desenlerinde dönüş değeri MUTLAKA kontrol edilir.
     * @param array<string|int, mixed> $parametreler
     */
    public static function calistir(string $sql, array $parametreler = []): int
    {
        $ifade = self::baglanti()->prepare($sql);
        $ifade->execute($parametreler);
        return $ifade->rowCount();
    }

    public static function sonEklenenId(): int
    {
        return (int) self::baglanti()->lastInsertId();
    }

    /**
     * Verilen işlevi tek transaction içinde çalıştırır.
     * İşlev istisna atarsa geri alınır (ROLLBACK) ve istisna yeniden fırlatılır.
     * @template T
     * @param callable(): T $islev
     * @return T
     */
    public static function islem(callable $islev): mixed
    {
        $vt = self::baglanti();
        if ($vt->inTransaction()) {
            return $islev(); // iç içe transaction açılmaz, mevcut olana katılır
        }
        $vt->beginTransaction();
        try {
            $sonuc = $islev();
            $vt->commit();
            return $sonuc;
        } catch (Throwable $hata) {
            $vt->rollBack();
            throw $hata;
        }
    }
}
