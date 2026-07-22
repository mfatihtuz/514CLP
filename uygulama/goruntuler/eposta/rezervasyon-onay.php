<?php
/**
 * Onay e-postası — e-posta istemcileri için tablo düzeni + inline stil.
 * Not: e-postada SVG desteklenmediği için armalar yerine takım adları kullanılır;
 * QR, cid:qrbilet olarak gömülür (EpostaGonderici değiştirir).
 * @var array<string, mixed> $rezervasyon
 * @var array<int, array<string, mixed>> $masalar
 * @var array<int, string> $paket
 * @var string $biletUrl
 * @var string $sonIptal
 */
$lacivert = '#0D3B66'; $krem = '#FAF0CA'; $sari = '#F4D35E'; $kirmizi = '#F95738';
?>
<!doctype html>
<html lang="tr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:<?= $krem ?>;font-family:Arial,Helvetica,sans-serif;color:<?= $lacivert ?>;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:<?= $krem ?>;padding:16px 0;">
<tr><td align="center">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">

    <tr><td style="background:<?= $lacivert ?>;padding:18px 24px;color:<?= $krem ?>;">
        <div style="font-size:20px;font-weight:bold;letter-spacing:1px;"><?= e(Ayarlar::siteAdi()) ?></div>
        <div style="font-size:13px;opacity:.85;"><?= e(Ayarlar::restoranAdi()) ?></div>
    </td></tr>

    <tr><td style="padding:26px 24px 8px;">
        <div style="font-size:22px;font-weight:bold;">Rezervasyonunuz onaylandı</div>
        <div style="margin-top:6px;font-size:14px;color:#4A6684;">
            Sayın <?= e((string) $rezervasyon['ad_soyad']) ?>, maç gecesi yeriniz hazır.
        </div>
    </td></tr>

    <tr><td style="padding:16px 24px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
               style="background:<?= $krem ?>;border-radius:10px;">
            <tr><td style="padding:16px 18px;" align="center">
                <div style="font-size:19px;font-weight:bold;">
                    <?= e((string) $rezervasyon['ev_ad']) ?>
                    <span style="color:<?= $kirmizi ?>;padding:0 8px;">VS</span>
                    <?= e((string) $rezervasyon['dep_ad']) ?>
                </div>
                <div style="margin-top:6px;font-size:14px;">
                    <?= e(macZamaniBicimle((string) $rezervasyon['baslangic_zamani'])) ?>
                </div>
                <?php if (!empty($rezervasyon['kapi_acilis_zamani'])): ?>
                    <div style="font-size:13px;color:#4A6684;">
                        Kapı açılışı: <?= e(saatBicimle((string) $rezervasyon['kapi_acilis_zamani'])) ?>
                    </div>
                <?php endif; ?>
            </td></tr>
        </table>
    </td></tr>

    <tr><td style="padding:0 24px;" align="center">
        <div style="font-size:13px;color:#4A6684;">Rezervasyon kodunuz</div>
        <div style="font-size:30px;font-weight:bold;letter-spacing:5px;margin:4px 0 12px;"><?= e((string) $rezervasyon['kod']) ?></div>
        <img src="cid:qrbilet" alt="QR bilet" width="180" height="180" style="display:block;border:6px solid <?= $sari ?>;border-radius:10px;">
        <div style="font-size:12px;color:#4A6684;margin-top:8px;">Girişte bu QR kodu gösterin.</div>
    </td></tr>

    <tr><td style="padding:20px 24px 4px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
            <tr>
                <td style="padding:6px 0;border-bottom:1px solid #eee;color:#4A6684;">Kişi</td>
                <td style="padding:6px 0;border-bottom:1px solid #eee;" align="right"><?= e((string) $rezervasyon['kisi_sayisi']) ?> kişi</td>
            </tr>
            <tr>
                <td style="padding:6px 0;border-bottom:1px solid #eee;color:#4A6684;">Yer</td>
                <td style="padding:6px 0;border-bottom:1px solid #eee;" align="right">
                    <?php if ($rezervasyon['tur'] === 'salon'): ?>
                        Salon Girişi (masanız girişte gösterilecek)
                    <?php else: ?>
                        <?= e(implode(', ', array_map(static fn(array $m): string => (string) $m['ad'], $masalar))) ?> numaralı masa
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($paket !== []): ?>
            <tr>
                <td style="padding:6px 0;border-bottom:1px solid #eee;color:#4A6684;">Fiyata dahil</td>
                <td style="padding:6px 0;border-bottom:1px solid #eee;" align="right"><?= e(implode(' + ', $paket)) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td style="padding:6px 0;color:#4A6684;">Toplam</td>
                <td style="padding:6px 0;font-weight:bold;" align="right">
                    <?= (int) $rezervasyon['toplam_tutar_kurus'] > 0 ? e(kurusBicimle((int) $rezervasyon['toplam_tutar_kurus'])) : 'Ücretsiz' ?>
                </td>
            </tr>
        </table>
    </td></tr>

    <tr><td style="padding:18px 24px;" align="center">
        <a href="<?= e($biletUrl) ?>"
           style="display:inline-block;background:<?= $kirmizi ?>;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:12px 28px;border-radius:999px;">
            Bileti Görüntüle
        </a>
        <div style="font-size:12px;color:#4A6684;margin-top:12px;">
            Planınız değişirse <?= e($sonIptal) ?> saatine kadar biletinizi ücretsiz iptal edebilirsiniz:
            bilet sayfasındaki "İptal Et" düğmesini kullanın.
        </div>
    </td></tr>

    <tr><td style="background:<?= $lacivert ?>;padding:14px 24px;color:<?= $krem ?>;font-size:12px;" align="center">
        <?= e(Ayarlar::restoranAdi()) ?>
        <?php $adres = (string) Ayarlar::al('iletisim_adres', ''); ?>
        <?php if ($adres !== ''): ?> — <?= e($adres) ?><?php endif; ?>
        <?php $telefon = (string) Ayarlar::al('iletisim_telefon', ''); ?>
        <?php if ($telefon !== ''): ?> — <?= e($telefon) ?><?php endif; ?>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
