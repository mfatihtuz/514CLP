<?php /** @var string $icerik  Giriş sayfası düzeni (menüsüz) */ ?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($baslik ?? 'Giriş') . ' | Yönetim') ?></title>
    <link rel="icon" type="image/svg+xml" href="/simge.svg">
    <link rel="stylesheet" href="/varliklar/css/fontlar.css">
    <link rel="stylesheet" href="/varliklar/css/stil.css">
    <link rel="stylesheet" href="/varliklar/css/admin.css">
</head>
<body class="admin-giris-govde">
<?= $icerik ?>
</body>
</html>
