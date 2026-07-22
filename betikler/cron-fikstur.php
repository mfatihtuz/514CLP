<?php

/**
 * Cron: üç büyüklerin fikstürünü çekip TASLAK maç olarak panele düşürür.
 * Yayınlamak (fiyat + onay) daima admin kararıdır — CLAUDE.md kural 13.
 *
 * Hostinger hPanel cron örneği (her gün 06:00): docs/KURULUM.md
 */

declare(strict_types=1);

require dirname(__DIR__) . '/uygulama/baslat.php';

$sonuc = FiksturCekici::calistir();
echo sprintf(
    "[%s] Fikstür: %d taslak eklendi, %d saat güncellendi, %d atlandı\n",
    simdiUtc(),
    $sonuc['eklenen'],
    $sonuc['guncellenen'],
    $sonuc['atlanan']
);
foreach ($sonuc['hatalar'] as $hata) {
    echo "  UYARI: {$hata}\n";
}
exit($sonuc['hatalar'] === [] ? 0 : 1);
