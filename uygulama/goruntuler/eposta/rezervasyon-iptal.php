<?php
/**
 * İptal e-postası.
 * @var array<string, mixed> $rezervasyon
 * @var int $iadeTutarKurus
 */
$lacivert = '#0D3B66'; $krem = '#FAF0CA';
?>
<!doctype html>
<html lang="tr">
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:<?= $krem ?>;font-family:Arial,Helvetica,sans-serif;color:<?= $lacivert ?>;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:<?= $krem ?>;padding:16px 0;">
<tr><td align="center">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
    <tr><td style="background:<?= $lacivert ?>;padding:18px 24px;color:<?= $krem ?>;">
        <div style="font-size:20px;font-weight:bold;"><?= e(Ayarlar::siteAdi()) ?></div>
    </td></tr>
    <tr><td style="padding:24px;">
        <div style="font-size:21px;font-weight:bold;">Rezervasyonunuz iptal edildi</div>
        <p style="font-size:14px;line-height:1.6;color:#4A6684;">
            Sayın <?= e((string) $rezervasyon['ad_soyad']) ?>,<br>
            <b><?= e((string) $rezervasyon['ev_ad']) ?> - <?= e((string) $rezervasyon['dep_ad']) ?></b> maçı için
            <b><?= e((string) $rezervasyon['kod']) ?></b> kodlu rezervasyonunuz iptal edilmiştir.
        </p>
        <?php if ($iadeTutarKurus > 0): ?>
            <p style="font-size:14px;line-height:1.6;">
                <b><?= e(kurusBicimle($iadeTutarKurus)) ?></b> tutarındaki iade, ödeme yaptığınız karta başlatıldı.
                Bankanıza bağlı olarak hesabınıza yansıması 1-7 iş günü sürebilir.
            </p>
        <?php endif; ?>
        <p style="font-size:13px;color:#4A6684;">Sizi başka bir maç gecesinde ağırlamaktan mutluluk duyarız.</p>
    </td></tr>
    <tr><td style="background:<?= $lacivert ?>;padding:14px 24px;color:<?= $krem ?>;font-size:12px;" align="center">
        <?= e(Ayarlar::restoranAdi()) ?>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
