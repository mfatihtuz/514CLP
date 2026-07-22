<?php /** @var string $icerik Ana düzen: tüm müşteri sayfaları bu iskelete gömülür. */ ?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($baslik) ? $baslik . ' | ' . Ayarlar::siteAdi() : Ayarlar::siteAdi()) ?></title>
    <meta name="description" content="<?= e(Ayarlar::restoranAdi()) ?> — maç günü masa rezervasyonu. Masanı seç, ödemeni yap, maç gecesi yerin hazır.">
    <link rel="stylesheet" href="/varliklar/css/fontlar.css">
    <link rel="stylesheet" href="/varliklar/css/stil.css">
</head>
<body>
<header class="baslik">
    <div class="kap baslik-ic">
        <a class="marka" href="/">
            <?= ikon('tv') ?>
            <span>Maç <b>Gecesi</b></span>
        </a>
        <nav class="ust-baglantilar">
            <a class="ust-baglanti" href="/#maclar"><?= ikon('calendar') ?><span>Maçlar</span></a>
            <a class="ust-baglanti" href="/#nasil-calisir"><?= ikon('info') ?><span>Nasıl Çalışır</span></a>
        </nav>
    </div>
</header>

<main>
<?= $icerik ?>
</main>

<footer class="altbilgi">
    <div class="kap">
        <div class="altbilgi-ic">
            <div>
                <strong><?= e(Ayarlar::restoranAdi()) ?></strong><br>
                <?php $adres = (string) Ayarlar::al('iletisim_adres', ''); ?>
                <?php $telefon = (string) Ayarlar::al('iletisim_telefon', ''); ?>
                <?php if ($adres !== ''): ?><span><?= e($adres) ?></span><br><?php endif; ?>
                <?php if ($telefon !== ''): ?><span><?= e($telefon) ?></span><?php endif; ?>
            </div>
            <div class="altbilgi-baglantilar">
                <a href="/#maclar">Yaklaşan Maçlar</a>
                <a href="/#nasil-calisir">Nasıl Çalışır</a>
            </div>
        </div>
        <div class="altbilgi-imza">
            © <?= e(utcNesne(simdiUtc())->format('Y')) ?> <?= e(Ayarlar::restoranAdi()) ?> — Tüm hakları saklıdır.
        </div>
    </div>
</footer>
</body>
</html>
