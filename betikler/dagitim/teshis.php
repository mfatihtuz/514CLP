<?php

/**
 * TEŞHİS (sağlık kontrolü) — geçici tanılama aracı.
 *
 * public_html'e yükle, tarayıcıda aç:
 *   https://siteniz/teshis.php?anahtar=<.env icindeki KURULUM_ANAHTARI>
 *
 * Neyin eksik/bozuk olduğunu listeler. Sorunu çözünce BU DOSYAYI SİLİN.
 * Uygulamayı önyüklemeden çalışır; bu yüzden uygulama bozuk olsa bile sonuç verir.
 */

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);

$kok = __DIR__;

// --- .env'i kendi başına oku (uygulamaya bağımlı olmadan) ---
$env = [];
$envDosya = $kok . '/.env';
$envOkundu = is_file($envDosya) && is_readable($envDosya);
if ($envOkundu) {
    foreach (file($envDosya, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $satir) {
        $satir = trim($satir);
        if ($satir === '' || $satir[0] === '#' || !str_contains($satir, '=')) {
            continue;
        }
        [$a, $d] = explode('=', $satir, 2);
        $env[trim($a)] = trim($d);
    }
}

// --- Erişim koruması ---
$token = $env['KURULUM_ANAHTARI'] ?? '';
$gelen = (string) ($_GET['anahtar'] ?? '');
$tamMod = $token !== '' && hash_equals($token, $gelen);
if ($token !== '' && !$tamMod) {
    http_response_code(404);
    exit('Sayfa bulunamadı.');
}
// .env okunamıyorsa token doğrulanamaz; yalnız sır içermeyen kontroller gösterilir.

// --- Kontroller ---
$sonuc = [];
function kontrol(string $ad, bool $iyi, string $detay = ''): void
{
    global $sonuc;
    $sonuc[] = [$ad, $iyi, $detay];
}

// PHP
$phpTamam = version_compare(PHP_VERSION, '8.0.0', '>=');
kontrol('PHP sürümü', $phpTamam, PHP_VERSION . ($phpTamam ? '' : ' — 8.0+ gerekli! hPanel > PHP Configuration'));

// Uzantılar
foreach (['pdo_mysql' => 'MySQL bağlantısı', 'mbstring' => 'Türkçe karakter', 'curl' => 'iyzico/fikstür', 'gd' => 'QR bilet üretimi', 'json' => 'veri', 'openssl' => 'imza/HTTPS'] as $uz => $ne) {
    kontrol("Uzantı: {$uz}", extension_loaded($uz), extension_loaded($uz) ? $ne : "EKSİK — {$ne} çalışmaz (hPanel > PHP eklentileri)");
}

// Anahtar dosyalar
foreach ([
    'index.php', '.htaccess', 'uygulama/baslat.php', 'vendor/autoload.php',
    'varliklar/css/stil.css', 'varliklar/css/admin.css', 'varliklar/js/kroki-editor.js',
    'varliklar/ikonlar/calendar.svg', 'armalar/galatasaray.svg', 'veritabani/sema.mysql.sql',
] as $rel) {
    kontrol("Dosya: {$rel}", is_file($kok . '/' . $rel), is_file($kok . '/' . $rel) ? '' : 'YÜKLENMEMİŞ — FTP yüklemesi eksik olabilir');
}

// varliklar/ikonlar sayısı (eksik ikon = eksik görünüm)
$ikonSayi = is_dir($kok . '/varliklar/ikonlar') ? count(glob($kok . '/varliklar/ikonlar/*.svg') ?: []) : 0;
kontrol('İkon dosyası sayısı', $ikonSayi >= 40, $ikonSayi . ' adet' . ($ikonSayi >= 40 ? '' : ' — beklenen 50; FTP yüklemesi yarım kalmış olabilir'));
$fontSayi = is_dir($kok . '/varliklar/fontlar') ? count(glob($kok . '/varliklar/fontlar/*.woff2') ?: []) : 0;
kontrol('Font dosyası sayısı', $fontSayi >= 12, $fontSayi . ' adet' . ($fontSayi >= 12 ? '' : ' — yazı tipleri eksik olabilir'));

// .env
kontrol('.env okunuyor', $envOkundu, $envOkundu ? '' : 'PHP .env dosyasını okuyamıyor');
if ($tamMod) {
    kontrol('ORTAM=uretim', ($env['ORTAM'] ?? '') === 'uretim', $env['ORTAM'] ?? '(boş)');
    kontrol('VT ayarları dolu', !empty($env['VT_AD']) && !empty($env['VT_KULLANICI']),
        'VT_AD=' . ($env['VT_AD'] ?? '(boş)'));
}

// Veritabanı bağlantısı + tablolar (tam mod)
$dbTamam = false;
if ($tamMod && extension_loaded('pdo_mysql')) {
    try {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $env['VT_SUNUCU'] ?? 'localhost', $env['VT_AD'] ?? '');
        $pdo = new PDO($dsn, $env['VT_KULLANICI'] ?? '', $env['VT_SIFRE'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $dbTamam = true;
        $tablo = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
        kontrol('Veritabanı bağlantısı', true, "{$tablo} tablo");
        $takim = (int) $pdo->query('SELECT COUNT(*) FROM takimlar')->fetchColumn();
        $admin = (int) $pdo->query('SELECT COUNT(*) FROM admin_kullanicilar')->fetchColumn();
        kontrol('Takım / admin kaydı', $takim >= 18 && $admin >= 1, "takım={$takim}, admin={$admin}");
    } catch (Throwable $e) {
        kontrol('Veritabanı bağlantısı', false, $e->getMessage());
    }
}

// Oturum yazımı
$oturumTamam = false;
try {
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }
    $_SESSION['teshis'] = 1;
    $oturumTamam = ($_SESSION['teshis'] ?? null) === 1;
} catch (Throwable) {}
kontrol('Oturum (session) yazımı', $oturumTamam, $oturumTamam ? '' : 'Giriş kalıcı olmayabilir');

// Uygulama önyüklemesi gerçekten çalışıyor mu?
$bootTamam = false;
$bootHata = '';
if ($tamMod) {
    try {
        // Ayrı süreçte önyükle ki bu sayfayı etkilemesin
        $ciktilar = [];
        $kod = 0;
        $phpBin = PHP_BINARY ?: 'php';
        @exec(escapeshellarg($phpBin) . ' -r ' . escapeshellarg('require ' . var_export($kok . '/uygulama/baslat.php', true) . '; echo "OK";') . ' 2>&1', $ciktilar, $kod);
        $cikti = implode("\n", $ciktilar);
        $bootTamam = str_contains($cikti, 'OK') && $kod === 0;
        if (!$bootTamam) { $bootHata = mb_substr($cikti, 0, 300); }
    } catch (Throwable $e) {
        $bootHata = $e->getMessage();
    }
    kontrol('Uygulama önyüklemesi', $bootTamam, $bootTamam ? '' : ('HATA: ' . $bootHata));
}

$hepsiIyi = array_reduce($sonuc, fn($t, $s) => $t && $s[1], true);
?>
<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Teşhis</title>
<style>
body{font-family:system-ui,Arial,sans-serif;background:#0D3B66;color:#0D3B66;margin:0;padding:1.5rem}
.k{max-width:820px;margin:0 auto;background:#fff;border-radius:12px;padding:1.5rem}
h1{font-size:1.4rem;margin:0 0 1rem}
table{width:100%;border-collapse:collapse;font-size:.92rem}
td{padding:.5rem .4rem;border-bottom:1px solid #eee;vertical-align:top}
.d{color:#fff;font-weight:700;text-align:center;width:2rem;border-radius:6px}
.ok{background:#2E9E63}.no{background:#F95738}
.det{color:#555;font-size:.85rem}
.ozet{padding:1rem;border-radius:8px;margin-bottom:1rem;font-weight:600}
.ozet.iyi{background:#E4F6EC;color:#16542F}.ozet.kotu{background:#FDE7E2;color:#DE4227}
.sil{margin-top:1rem;padding:.8rem;background:#FDE7E2;color:#DE4227;border-radius:8px;font-weight:600}
code{background:#f3f3f3;padding:1px 5px;border-radius:4px}
</style></head><body><div class="k">
<h1>Kurulum Teşhisi</h1>
<div class="ozet <?= $hepsiIyi ? 'iyi' : 'kotu' ?>">
    <?= $hepsiIyi ? 'Görünen tüm kontroller BAŞARILI.' : 'Aşağıda KIRMIZI işaretli satır(lar) sorunun kaynağı.' ?>
    <?= $tamMod ? '' : ' (Sınırlı mod: .env doğrulanamadı, veritabanı kontrolleri atlandı.)' ?>
</div>
<table>
<?php foreach ($sonuc as [$ad, $iyi, $det]): ?>
    <tr>
        <td class="d <?= $iyi ? 'ok' : 'no' ?>"><?= $iyi ? '✓' : '×' ?></td>
        <td><b><?= htmlspecialchars($ad) ?></b><?php if ($det !== ''): ?><br><span class="det"><?= htmlspecialchars($det) ?></span><?php endif; ?></td>
    </tr>
<?php endforeach; ?>
</table>
<div class="sil">Teşhis bitince bu dosyayı (<code>teshis.php</code>) sunucudan SİLİN.</div>
</div></body></html>
