<?php

declare(strict_types=1);

/**
 * Denetim kaydı: para işlemleri ve kritik admin eylemlerinin izi.
 * Yazma hatası ana işlemi ASLA bozmaz (log kaybı < işlem kaybı).
 */
final class DenetimKaydi
{
    /** @param array<string, mixed> $detay */
    public static function yaz(string $islem, array $detay = [], ?int $adminId = null): void
    {
        try {
            Veritabani::calistir(
                'INSERT INTO denetim_kayitlari (admin_id, islem, detay, zaman) VALUES (?, ?, ?, ?)',
                [
                    $adminId ?? (class_exists('AdminOturumu') ? AdminOturumu::aktifId() : null),
                    $islem,
                    json_encode($detay, JSON_UNESCAPED_UNICODE),
                    simdiUtc(),
                ]
            );
        } catch (Throwable $hata) {
            error_log('Denetim kaydı yazılamadı: ' . $hata->getMessage());
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function sonKayitlar(int $adet = 20): array
    {
        return Veritabani::satirlar(
            'SELECT dk.*, ak.ad AS admin_ad
             FROM denetim_kayitlari dk
             LEFT JOIN admin_kullanicilar ak ON ak.id = dk.admin_id
             ORDER BY dk.id DESC LIMIT ' . max(1, min(100, $adet))
        );
    }
}
