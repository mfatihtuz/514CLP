<?php

/**
 * TARAYICI KURULUM SİHİRBAZI (tek seferlik).
 *
 * Paylaşımlı hosting'de SSH olmadan kurulumu tamamlar:
 *   1. Veritabanı bağlantısını doğrular
 *   2. Tablolar yoksa şema + takım tohumunu kurar
 *   3. İlk admin kullanıcısını oluşturur
 *
 * GÜVENLİK:
 *   - .env içindeki KURULUM_ANAHTARI ile korunur; ?anahtar= eşleşmezse 404.
 *   - Kurulum bitince BU DOSYAYI SUNUCUDAN SİLİN (sayfa hatırlatır).
 *
 * Erişim: https://siteniz/kurulum.php?anahtar=ANAHTARINIZ
 */

declare(strict_types=1);

require __DIR__ . '/uygulama/baslat.php';

// --- Erişim koruması ---
$beklenen = (string) (Cevre::al('KURULUM_ANAHTARI', '') ?? '');
$gelen = (string) ($_GET['anahtar'] ?? '');
if ($beklenen === '' || $gelen === '' || !hash_equals($beklenen, $gelen)) {
    http_response_code(404);
    exit('Sayfa bulunamadı.');
}

$surucu = Veritabani::surucu();
$semaDosyasi = DIZIN_KOK . '/veritabani/' . ($surucu === 'sqlite' ? 'sema.sqlite.sql' : 'sema.mysql.sql');
$tohumDosyasi = DIZIN_KOK . '/veritabani/tohum.sql';

$hatalar = [];
$basari = null;
$vtBaglandi = false;
$tablolarVar = false;

try {
    Veritabani::baglanti();
    $vtBaglandi = true;
    $tablolarVar = tabloVarMi($surucu, 'takimlar');
} catch (Throwable $hata) {
    $hatalar[] = 'Veritabanına bağlanılamadı: ' . $hata->getMessage()
        . ' — .env dosyasındaki VT_AD / VT_KULLANICI / VT_SIFRE değerlerini kontrol edin.';
}

$adminVar = false;
if ($vtBaglandi && $tablolarVar) {
    try {
        $adminVar = (int) (Veritabani::deger('SELECT COUNT(*) FROM admin_kullanicilar') ?? 0) > 0;
    } catch (Throwable) {
        $adminVar = false;
    }
}

// --- POST: kurulumu çalıştır ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $vtBaglandi) {
    $eposta = mb_strtolower(trim((string) ($_POST['eposta'] ?? '')));
    $ad     = trim((string) ($_POST['ad'] ?? ''));
    $sifre  = (string) ($_POST['sifre'] ?? '');
    $sifre2 = (string) ($_POST['sifre2'] ?? '');

    if (!filter_var($eposta, FILTER_VALIDATE_EMAIL)) {
        $hatalar[] = 'Geçerli bir e-posta girin.';
    }
    if (mb_strlen($ad) < 3) {
        $hatalar[] = 'Ad-soyad en az 3 karakter olmalı.';
    }
    if (mb_strlen($sifre) < 10) {
        $hatalar[] = 'Şifre en az 10 karakter olmalı.';
    }
    if ($sifre !== $sifre2) {
        $hatalar[] = 'Şifreler eşleşmiyor.';
    }

    if ($hatalar === []) {
        try {
            if (!$tablolarVar) {
                sqlDosyasiCalistir($semaDosyasi);
                sqlDosyasiCalistir($tohumDosyasi);
                $tablolarVar = true;
            }

            $mevcut = Veritabani::satir('SELECT id FROM admin_kullanicilar WHERE eposta = ?', [$eposta]);
            if ($mevcut !== null) {
                Veritabani::calistir(
                    'UPDATE admin_kullanicilar SET sifre_ozeti = ?, ad = ? WHERE eposta = ?',
                    [password_hash($sifre, PASSWORD_DEFAULT), $ad, $eposta]
                );
            } else {
                Veritabani::calistir(
                    'INSERT INTO admin_kullanicilar (eposta, sifre_ozeti, ad) VALUES (?, ?, ?)',
                    [$eposta, password_hash($sifre, PASSWORD_DEFAULT), $ad]
                );
            }
            $takimSayisi = (int) (Veritabani::deger('SELECT COUNT(*) FROM takimlar') ?? 0);
            $basari = ['eposta' => $eposta, 'takim' => $takimSayisi];
            $adminVar = true;
        } catch (Throwable $hata) {
            $hatalar[] = 'Kurulum sırasında hata: ' . $hata->getMessage();
        }
    }
}

// --- Yardımcılar ---
function tabloVarMi(string $surucu, string $ad): bool
{
    if ($surucu === 'sqlite') {
        return Veritabani::deger("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$ad]) !== null;
    }
    return Veritabani::deger(
        'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        [$ad]
    ) !== null;
}

function sqlDosyasiCalistir(string $yol): void
{
    if (!is_file($yol)) {
        throw new RuntimeException('SQL dosyası bulunamadı: ' . basename($yol));
    }
    $icerik = (string) file_get_contents($yol);
    $temiz = preg_replace('/^\s*--.*$/m', '', $icerik) ?? '';
    foreach (array_filter(array_map('trim', explode(';', $temiz))) as $ifade) {
        Veritabani::baglanti()->exec($ifade);
    }
}

function html(?string $m): string
{
    return htmlspecialchars($m ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Kurulum Sihirbazı</title>
    <link rel="stylesheet" href="/varliklar/css/fontlar.css">
    <link rel="stylesheet" href="/varliklar/css/stil.css">
    <style>
        body { background: #0D3B66; }
        .kurulum-kutu { width: min(560px, 100% - 2rem); margin: 3rem auto; background: #fff;
            border-radius: 14px; box-shadow: 0 20px 60px -20px rgba(0,0,0,.5); padding: 2rem; }
        .kurulum-baslik { font-family: 'Barlow Condensed', sans-serif; font-weight: 700; font-size: 1.8rem;
            text-transform: uppercase; color: #0D3B66; margin-bottom: 1rem; display: flex; align-items: center; gap: .5rem; }
        .kurulum-adim { display: flex; gap: .6rem; align-items: center; padding: .5rem 0; font-size: .95rem; }
        .kurulum-adim .im { width: 1.4rem; height: 1.4rem; border-radius: 50%; display: inline-flex;
            align-items: center; justify-content: center; font-size: .8rem; font-weight: 700; flex: none; color: #fff; }
        .im-ok { background: #2E9E63; } .im-bekle { background: #EE964B; } .im-yok { background: #F95738; }
        .sil-uyari { background: rgba(249,87,56,.12); border: 2px solid #F95738; color: #DE4227;
            border-radius: 10px; padding: 1rem; margin-top: 1rem; font-weight: 600; }
    </style>
</head>
<body>
<div class="kurulum-kutu">
    <div class="kurulum-baslik">Maç Gecesi — Kurulum</div>

    <div class="kurulum-adim">
        <span class="im <?= $vtBaglandi ? 'im-ok' : 'im-yok' ?>"><?= $vtBaglandi ? '✓' : '!' ?></span>
        Veritabanı bağlantısı (<?= html($surucu) ?>): <?= $vtBaglandi ? 'başarılı' : 'başarısız' ?>
    </div>
    <div class="kurulum-adim">
        <span class="im <?= $tablolarVar ? 'im-ok' : 'im-bekle' ?>"><?= $tablolarVar ? '✓' : '…' ?></span>
        Tablolar: <?= $tablolarVar ? 'kurulu' : 'kurulacak' ?>
    </div>

    <?php foreach ($hatalar as $h): ?>
        <div class="uyari uyari-hata" style="margin-top:1rem;"><?= html($h) ?></div>
    <?php endforeach; ?>

    <?php if ($basari !== null): ?>
        <div class="uyari uyari-basari" style="margin-top:1rem;">
            Kurulum tamamlandı. <?= (int) $basari['takim'] ?> takım yüklendi.
            Admin girişi: <b><?= html($basari['eposta']) ?></b>
        </div>
        <div class="sil-uyari">
            ÖNEMLİ: Güvenlik için <b>kurulum.php</b> dosyasını şimdi sunucudan SİLİN.<br>
            Ardından yönetim paneline girin:
            <a href="/admin/giris">/admin/giris</a>
        </div>
    <?php elseif ($vtBaglandi): ?>
        <?php if ($adminVar && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
            <div class="uyari uyari-bilgi" style="margin-top:1rem;">
                Sistem zaten kurulu görünüyor. Yeni bir admin eklemek isterseniz formu doldurun;
                aksi halde bu dosyayı silip <a href="/admin/giris">/admin/giris</a> üzerinden girin.
            </div>
        <?php endif; ?>
        <p style="margin-top:1rem;color:#4A6684;">İlk yönetici hesabını oluşturun:</p>
        <form method="post">
            <div class="form-alan">
                <label class="form-etiket" for="ad">Ad Soyad</label>
                <input class="form-girdi" id="ad" name="ad" required value="<?= html($_POST['ad'] ?? '') ?>">
            </div>
            <div class="form-alan">
                <label class="form-etiket" for="eposta">E-posta (giriş için)</label>
                <input class="form-girdi" id="eposta" name="eposta" type="email" required value="<?= html($_POST['eposta'] ?? '') ?>">
            </div>
            <div class="form-alan">
                <label class="form-etiket" for="sifre">Şifre (en az 10 karakter)</label>
                <input class="form-girdi" id="sifre" name="sifre" type="password" required>
            </div>
            <div class="form-alan">
                <label class="form-etiket" for="sifre2">Şifre (tekrar)</label>
                <input class="form-girdi" id="sifre2" name="sifre2" type="password" required>
            </div>
            <button class="buton buton-birincil buton-genis" type="submit">Kurulumu Tamamla</button>
        </form>
    <?php else: ?>
        <p style="margin-top:1rem;color:#4A6684;">
            Veritabanı bağlantısı kurulamadığı için devam edilemiyor. Lütfen <b>.env</b> dosyasındaki
            veritabanı bilgilerini kontrol edip sayfayı yenileyin.
        </p>
    <?php endif; ?>
</div>
</body>
</html>
