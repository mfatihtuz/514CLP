<?php

declare(strict_types=1);

/**
 * Para yardımcıları. Tüm tutarlar iç dünyada KURUŞ cinsinden tam sayıdır;
 * ondalıklı TL değeri yalnızca görüntülemede ve sağlayıcı API sınırında oluşur.
 */

/** 50000 → "500 TL"  |  50050 → "500,50 TL" */
function kurusBicimle(int $kurus): string
{
    $tl = intdiv($kurus, 100);
    $kalan = $kurus % 100;
    $tlMetni = number_format($tl, 0, ',', '.');
    return $kalan === 0
        ? $tlMetni . ' TL'
        : $tlMetni . ',' . str_pad((string) $kalan, 2, '0', STR_PAD_LEFT) . ' TL';
}

/** Admin formundan gelen "500" veya "500,50" girdisini kuruşa çevirir. Geçersizse null. */
function tlGirdisiniKurusaCevir(string $girdi): ?int
{
    $girdi = str_replace([' ', '.'], '', trim($girdi)); // binlik ayracı temizle
    $girdi = str_replace(',', '.', $girdi);
    if ($girdi === '' || !is_numeric($girdi)) {
        return null;
    }
    $deger = (float) $girdi;
    if ($deger < 0 || $deger > 1000000) {
        return null;
    }
    return (int) round($deger * 100);
}

/** iyzico gibi sağlayıcılara giden "500.50" biçimi */
function kurusOndalikMetin(int $kurus): string
{
    return number_format($kurus / 100, 2, '.', '');
}
