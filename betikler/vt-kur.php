<?php

/**
 * Veritabanı kurulum betiği (CLI).
 *
 * Kullanım:
 *   php betikler/vt-kur.php            → şemayı kurar + tohum verisini yükler
 *   php betikler/vt-kur.php --sifirla  → (yalnız sqlite) dosyayı silip baştan kurar
 *
 * Üretimde (Hostinger) alternatif: phpMyAdmin'de sema.mysql.sql ve tohum.sql
 * dosyalarını "İçe Aktar" ile sırayla çalıştırmak da aynı sonucu verir.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/uygulama/baslat.php';

$surucu = Cevre::al('VT_SURUCU', 'mysql');

if (in_array('--sifirla', $argv, true)) {
    if ($surucu !== 'sqlite') {
        fwrite(STDERR, "HATA: --sifirla yalnızca sqlite sürücüsüyle kullanılabilir (üretim verisini korumak için).\n");
        exit(1);
    }
    $dosya = Cevre::al('VT_SQLITE_YOL', DIZIN_KOK . '/veritabani/gelistirme.sqlite');
    if (is_file($dosya)) {
        unlink($dosya);
        echo "Silindi: {$dosya}\n";
    }
}

$semaDosyasi = $surucu === 'sqlite'
    ? DIZIN_KOK . '/veritabani/sema.sqlite.sql'
    : DIZIN_KOK . '/veritabani/sema.mysql.sql';

$mevcut = Veritabani::deger(
    $surucu === 'sqlite'
        ? "SELECT name FROM sqlite_master WHERE type='table' AND name='takimlar'"
        : "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'takimlar'"
);
if ($mevcut !== null) {
    fwrite(STDERR, "HATA: 'takimlar' tablosu zaten var; kurulum daha önce yapılmış görünüyor.\n"
        . "Yerelde sıfırlamak için: php betikler/vt-kur.php --sifirla\n");
    exit(1);
}

sqlDosyasiCalistir($semaDosyasi);
echo "Şema kuruldu: " . basename($semaDosyasi) . "\n";

sqlDosyasiCalistir(DIZIN_KOK . '/veritabani/tohum.sql');
$takimSayisi = (int) Veritabani::deger('SELECT COUNT(*) FROM takimlar');
echo "Tohum verisi yüklendi: {$takimSayisi} takım + varsayılan ayarlar\n";
echo "Sıradaki adım: php betikler/admin-olustur.php eposta@ornek.com \"Ad Soyad\"\n";

// sqlDosyasiCalistir() artık uygulama/yardimcilar/sql.php içinde (tırnak/yorum duyarlı).
