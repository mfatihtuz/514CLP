<?php
/** @var string $icerik  Yönetim paneli düzeni */
$aktifMenu = $aktifMenu ?? '';
$menuler = [
    'pano'    => ['/admin', 'layout-grid', 'Pano'],
    'maclar'  => ['/admin/maclar', 'calendar', 'Maçlar'],
    'kroki'   => ['/admin/kroki', 'armchair', 'Kat Planı'],
    'ayarlar' => ['/admin/ayarlar', 'settings', 'Ayarlar'],
];
$admin = AdminOturumu::aktifAdmin();
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($baslik ?? 'Panel') . ' | Yönetim') ?></title>
    <link rel="stylesheet" href="/varliklar/css/fontlar.css">
    <link rel="stylesheet" href="/varliklar/css/stil.css">
    <link rel="stylesheet" href="/varliklar/css/admin.css">
</head>
<body class="admin-govde">
<header class="admin-bar">
    <div class="admin-bar-ic">
        <a class="marka marka-kucuk" href="/admin"><?= ikon('shield-check') ?><span>Yönetim</span></a>
        <nav class="admin-menu">
            <?php foreach ($menuler as $anahtar => [$yol, $ikonAd, $etiket]): ?>
                <a class="admin-menu-oge<?= $aktifMenu === $anahtar ? ' aktif' : '' ?>" href="<?= e($yol) ?>">
                    <?= ikon($ikonAd) ?><span><?= e($etiket) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-bar-sag">
            <a class="admin-menu-oge" href="/" target="_blank" rel="noopener"><?= ikon('external-link') ?><span>Siteyi Gör</span></a>
            <form method="post" action="/admin/cikis">
                <input type="hidden" name="csrf_jetonu" value="<?= e(Guvenlik::csrfJetonu()) ?>">
                <button class="admin-menu-oge admin-cikis" type="submit" title="<?= e($admin['ad'] ?? '') ?> — çıkış">
                    <?= ikon('log-out') ?><span>Çıkış</span>
                </button>
            </form>
        </div>
    </div>
</header>
<main class="admin-icerik kap">
<?= $icerik ?>
</main>
</body>
</html>
