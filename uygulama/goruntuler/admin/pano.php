<h1 class="admin-sayfa-baslik"><?= ikon('layout-grid') ?> Pano</h1>

<?php
$bekleyenIadeSayisi = (int) (Veritabani::deger(
    "SELECT COUNT(*) FROM odemeler o JOIN rezervasyonlar r ON r.id = o.rezervasyon_id
     WHERE o.durum = 'basarili' AND r.durum = 'iptal_edildi'"
) ?? 0);
?>
<?php if ($bekleyenIadeSayisi > 0): ?>
    <div class="uyari uyari-hata">
        <?= ikon('triangle-alert') ?>
        <span><b><?= e((string) $bekleyenIadeSayisi) ?> bekleyen iade var.</b>
            Müşteriye para iadesi tamamlanamadı; <a href="/admin/raporlar">Raporlar</a> ekranından yeniden deneyin.</span>
    </div>
<?php endif; ?>

<div class="sayi-kartlari">
    <div class="sayi-kart">
        <span class="sayi-kart-deger"><?= e((string) $sayilar['satista']) ?></span>
        <span class="sayi-kart-etiket">Satıştaki maç</span>
    </div>
    <div class="sayi-kart">
        <span class="sayi-kart-deger"><?= e((string) $sayilar['taslak']) ?></span>
        <span class="sayi-kart-etiket">Taslak maç</span>
    </div>
    <div class="sayi-kart">
        <span class="sayi-kart-deger"><?= e((string) $sayilar['bugunRez']) ?></span>
        <span class="sayi-kart-etiket">Son 24 saatte rezervasyon</span>
    </div>
    <div class="sayi-kart">
        <span class="sayi-kart-deger"><?= e((string) $sayilar['masaSayisi']) ?></span>
        <span class="sayi-kart-etiket">Kat planındaki masa</span>
    </div>
</div>

<div class="admin-iki-sutun">
    <section class="admin-kart">
        <div class="admin-kart-baslik">
            <h2><?= ikon('calendar') ?> Yaklaşan Maçlar</h2>
            <a class="buton buton-birincil buton-kucuk" href="/admin/maclar/yeni"><?= ikon('plus') ?> Yeni Maç</a>
        </div>
        <?php if ($yaklasanMaclar === []): ?>
            <p class="metin-soluk">Yaklaşan maç yok. "Yeni Maç" ile ekleyin.</p>
        <?php else: ?>
            <div class="tablo-sar">
            <table class="admin-tablo tablo-kart">
                <thead><tr><th>Maç</th><th>Zaman</th><th>Durum</th><th>Doluluk</th></tr></thead>
                <tbody>
                <?php foreach ($yaklasanMaclar as $mac): ?>
                    <tr>
                        <td data-etiket="Maç"><a href="/admin/maclar/<?= e((string) $mac['id']) ?>"><?= e($mac['ev_ad'] . ' - ' . $mac['dep_ad']) ?></a></td>
                        <td data-etiket="Zaman"><?= e(macZamaniBicimle((string) $mac['baslangic_zamani'])) ?></td>
                        <td data-etiket="Durum"><?= macDurumRozeti((string) $mac['durum']) ?></td>
                        <td data-etiket="Doluluk">
                            <?php if ($mac['durum'] === 'taslak'): ?>
                                <span class="metin-soluk">—</span>
                            <?php else: ?>
                                <?= e($mac['doluluk']['rezerve_masa'] . '/' . $mac['doluluk']['acik_masa']) ?> masa,
                                <?= e((string) $mac['doluluk']['rezerve_kisi']) ?> kişi
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-kart">
        <div class="admin-kart-baslik"><h2><?= ikon('file-text') ?> Son İşlemler</h2></div>
        <?php if ($sonKayitlar === []): ?>
            <p class="metin-soluk">Henüz kayıt yok.</p>
        <?php else: ?>
            <ul class="denetim-listesi">
                <?php foreach ($sonKayitlar as $kayit): ?>
                    <li>
                        <span class="denetim-islem"><?= e((string) $kayit['islem']) ?></span>
                        <span class="metin-soluk"><?= e(($kayit['admin_ad'] ?? 'sistem') . ' — ' . macZamaniBicimle((string) $kayit['zaman'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
