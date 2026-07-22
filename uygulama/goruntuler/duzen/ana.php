<?php /** @var string $icerik Ana düzen: tüm müşteri sayfaları bu iskelete gömülür. */ ?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($baslik) ? $baslik . ' | ' . Ayarlar::siteAdi() : Ayarlar::siteAdi()) ?></title>
    <meta name="description" content="<?= e(Ayarlar::restoranAdi()) ?> — maç günü masa rezervasyonu. Masanı seç, ödemeni yap, maç gecesi yerin hazır.">
    <meta property="og:title" content="<?= e(isset($baslik) ? $baslik . ' | ' . Ayarlar::siteAdi() : Ayarlar::siteAdi()) ?>">
    <meta property="og:description" content="Maçı dev ekranda, masandan izle. Sinema bileti alır gibi masanı seç, yerin hazır olsun.">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="tr_TR">
    <link rel="icon" type="image/svg+xml" href="/simge.svg">
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
                <a href="/rezervasyon-sorgula">Rezervasyon Sorgula</a>
                <a href="/yasal/kvkk">KVKK</a>
                <a href="/yasal/mesafeli-satis">Mesafeli Satış</a>
                <a href="/yasal/on-bilgilendirme">Ön Bilgilendirme</a>
                <a href="/yasal/iade-kosullari">İptal ve İade</a>
            </div>
        </div>
        <div class="altbilgi-imza">
            <?php $unvan = (string) Ayarlar::al('isletme_unvani', ''); ?>
            © <?= e(utcNesne(simdiUtc())->format('Y')) ?> <?= e($unvan !== '' ? $unvan : Ayarlar::restoranAdi()) ?> — Tüm hakları saklıdır.
            Kart bilgileri sitede tutulmaz; ödemeler lisanslı sanal POS üzerinden alınır.
        </div>
    </div>
</footer>
</body>
</html>
