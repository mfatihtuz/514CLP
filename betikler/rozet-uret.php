<?php

/**
 * Jenerik rozet üretici (CLI).
 *
 * Takım renkleri + kısaltmadan kalkan biçimli SVG rozet üretir.
 * İKİ amacı vardır:
 *   1. Gerçek armalar (betikler/armalari-indir.sh) inene kadar geçici görsel
 *   2. Telif itirazı gelirse tek komutla dönülecek kalıcı yedek (CLAUDE.md kural 5)
 *
 * Kullanım: php betikler/rozet-uret.php [--zorla]
 *   Var olan dosyanın üzerine yazmaz; --zorla ile yazar.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/uygulama/baslat.php';

$zorla = in_array('--zorla', $argv, true);
$hedefKlasor = DIZIN_PUBLIC . '/armalar';
if (!is_dir($hedefKlasor)) {
    mkdir($hedefKlasor, 0775, true);
}

$takimlar = Veritabani::satirlar('SELECT * FROM takimlar ORDER BY id');
if ($takimlar === []) {
    fwrite(STDERR, "HATA: takimlar tablosu boş. Önce: php betikler/vt-kur.php\n");
    exit(1);
}

$uretilen = 0;
foreach ($takimlar as $takim) {
    $dosya = DIZIN_PUBLIC . '/' . $takim['arma_dosya'];
    if (is_file($dosya) && !$zorla) {
        echo "Atlandı (mevcut): {$takim['arma_dosya']}\n";
        continue;
    }
    RozetUretici::dosyaUret(
        $dosya,
        (string) $takim['kisa_ad'],
        (string) $takim['renk1'],
        (string) $takim['renk2']
    );
    echo "Üretildi: {$takim['arma_dosya']}\n";
    $uretilen++;
}
echo "Toplam {$uretilen} rozet üretildi.\n";
