<?php

declare(strict_types=1);

final class TakimSorgulari
{
    /** @return array<int, array<string, mixed>> */
    public static function aktifTakimlar(): array
    {
        return Veritabani::satirlar(
            'SELECT * FROM takimlar WHERE aktif = 1 ORDER BY uc_buyuk DESC, ad ASC'
        );
    }

    /** @return array<string, mixed>|null */
    public static function idIleBul(int $id): ?array
    {
        return Veritabani::satir('SELECT * FROM takimlar WHERE id = ?', [$id]);
    }

    /** @return array<int, array<string, mixed>> Üç büyükler (fikstür çekme hedefleri) */
    public static function ucBuyukler(): array
    {
        return Veritabani::satirlar('SELECT * FROM takimlar WHERE uc_buyuk = 1 AND aktif = 1');
    }
}
