<?php
/**
 * @var array<int, array<string, mixed>> $rapor
 * @var array<int, array<string, mixed>> $bekleyenIadeler
 * @var array<int, array<string, mixed>> $sonKayitlar
 */
?>
<h1 class="admin-sayfa-baslik"><?= ikon('file-text') ?> Raporlar</h1>

<?php if (!empty($mesaj)): ?>
    <div class="uyari uyari-basari"><?= ikon('circle-check-big') ?> <?= e((string) $mesaj) ?></div>
<?php endif; ?>

<?php if ($bekleyenIadeler !== []): ?>
    <div class="admin-kart bekleyen-iade-kutu">
        <div class="admin-kart-baslik">
            <h2><?= ikon('triangle-alert') ?> Bekleyen İadeler — İŞLEM GEREKLİ</h2>
        </div>
        <p class="metin-soluk">Rezervasyon iptal edildi ancak iade tamamlanamadı.
            Yeniden deneyin; olmuyorsa sağlayıcı panelinden manuel iade yapın.</p>
        <table class="admin-tablo">
            <thead><tr><th>Kod</th><th>Misafir</th><th>Tutar</th><th>Sağlayıcı</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($bekleyenIadeler as $odeme): ?>
                <tr>
                    <td><b><?= e((string) $odeme['rezervasyon_kodu']) ?></b></td>
                    <td><?= e((string) $odeme['ad_soyad']) ?></td>
                    <td><?= e(kurusBicimle((int) $odeme['tutar_kurus'])) ?></td>
                    <td><?= e((string) $odeme['saglayici']) ?></td>
                    <td>
                        <form method="post" action="/admin/odemeler/<?= e((string) $odeme['id']) ?>/iade-tekrar">
                            <input type="hidden" name="csrf_jetonu" value="<?= e(Guvenlik::csrfJetonu()) ?>">
                            <button class="buton buton-birincil buton-kucuk" type="submit">
                                <?= ikon('refresh-cw') ?> İadeyi Tekrar Dene
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="admin-kart">
    <div class="admin-kart-baslik"><h2><?= ikon('calendar') ?> Maç Bazında Doluluk ve Ciro</h2></div>
    <?php if ($rapor === []): ?>
        <p class="metin-soluk">Henüz raporlanacak maç yok.</p>
    <?php else: ?>
        <table class="admin-tablo">
            <thead>
            <tr>
                <th>Maç</th><th>Zaman</th><th>Masa doluluk</th><th>Onaylı kişi</th>
                <th>Salon</th><th>Giriş yapan</th><th>Brüt</th><th>İade</th><th>Net</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rapor as $satir): $mac = $satir['mac']; ?>
                <tr>
                    <td><a href="/admin/rezervasyonlar?mac=<?= e((string) $mac['id']) ?>"><?= e($mac['ev_ad'] . ' - ' . $mac['dep_ad']) ?></a></td>
                    <td><?= e(tarihBicimle((string) $mac['baslangic_zamani'])) ?></td>
                    <td>
                        <?= e($satir['doluluk']['rezerve_masa'] . '/' . $satir['doluluk']['acik_masa']) ?>
                        <?php if ($satir['doluluk']['acik_masa'] > 0): ?>
                            <span class="metin-soluk">(%<?= e((string) (int) round(100 * $satir['doluluk']['rezerve_masa'] / max(1, $satir['doluluk']['acik_masa']))) ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e((string) $satir['doluluk']['rezerve_kisi']) ?></td>
                    <td><?= e((string) $satir['salon_kisi']) ?></td>
                    <td><?= e((string) $satir['giren_kisi']) ?></td>
                    <td><?= e(kurusBicimle((int) $satir['brut'])) ?></td>
                    <td><?= $satir['iade'] > 0 ? e(kurusBicimle((int) $satir['iade'])) : '—' ?></td>
                    <td><b><?= e(kurusBicimle((int) $satir['net'])) ?></b></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="admin-kart">
    <div class="admin-kart-baslik"><h2><?= ikon('file-text') ?> Denetim Kaydı (son 30)</h2></div>
    <ul class="denetim-listesi">
        <?php foreach ($sonKayitlar as $kayit): ?>
            <li>
                <span class="denetim-islem"><?= e((string) $kayit['islem']) ?></span>
                <span class="metin-soluk">
                    <?= e(($kayit['admin_ad'] ?? 'sistem') . ' — ' . macZamaniBicimle((string) $kayit['zaman'])) ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
