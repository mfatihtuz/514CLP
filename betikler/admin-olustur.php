<?php

/**
 * Admin kullanıcısı oluşturma betiği (CLI).
 *
 * Kullanım:
 *   php betikler/admin-olustur.php eposta@ornek.com "Ad Soyad"
 *   php betikler/admin-olustur.php eposta@ornek.com "Ad Soyad" --sifre=GizliSifre123
 *
 * Şifre verilmezse güvenli bir şifre üretilir ve EKRANA BİR KEZ yazılır.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/uygulama/baslat.php';

$eposta = $argv[1] ?? null;
$ad     = $argv[2] ?? null;

if ($eposta === null || $ad === null || !epostaGecerliMi($eposta)) {
    fwrite(STDERR, "Kullanım: php betikler/admin-olustur.php eposta@ornek.com \"Ad Soyad\" [--sifre=...]\n");
    exit(1);
}

$sifre = null;
foreach ($argv as $arguman) {
    if (str_starts_with($arguman, '--sifre=')) {
        $sifre = substr($arguman, 8);
    }
}
$uretildi = false;
if ($sifre === null || strlen($sifre) < 10) {
    if ($sifre !== null) {
        fwrite(STDERR, "UYARI: Şifre 10 karakterden kısa olamaz; güvenli bir şifre üretildi.\n");
    }
    $sifre = Guvenlik::rastgeleKod(16, 'abcdefghjkmnprstuvwxyzABCDEFGHJKMNPRSTUVWXYZ23456789');
    $uretildi = true;
}

$mevcut = Veritabani::satir('SELECT id FROM admin_kullanicilar WHERE eposta = ?', [$eposta]);
if ($mevcut !== null) {
    Veritabani::calistir(
        'UPDATE admin_kullanicilar SET sifre_ozeti = ?, ad = ? WHERE eposta = ?',
        [password_hash($sifre, PASSWORD_DEFAULT), $ad, $eposta]
    );
    echo "Mevcut admin güncellendi: {$eposta}\n";
} else {
    Veritabani::calistir(
        'INSERT INTO admin_kullanicilar (eposta, sifre_ozeti, ad) VALUES (?, ?, ?)',
        [$eposta, password_hash($sifre, PASSWORD_DEFAULT), $ad]
    );
    echo "Admin oluşturuldu: {$eposta}\n";
}

if ($uretildi) {
    echo "Şifre (bir kez gösterilir, güvenli bir yere kaydedin): {$sifre}\n";
}
