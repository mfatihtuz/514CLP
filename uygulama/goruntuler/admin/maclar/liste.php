<div class="admin-kart-baslik">
    <h1 class="admin-sayfa-baslik"><?= ikon('calendar') ?> Maçlar</h1>
    <a class="buton buton-birincil" href="/admin/maclar/yeni"><?= ikon('plus') ?> Yeni Maç</a>
</div>

<?php if ($maclar === []): ?>
    <div class="bos-durum">
        <?= ikon('calendar') ?>
        <h3>Henüz maç yok</h3>
        <p>"Yeni Maç" ile ilk maçı ekleyin; fikstürden otomatik çekme Faz 4'te devreye girecek.</p>
    </div>
<?php else: ?>
    <div class="admin-kart">
        <table class="admin-tablo">
            <thead>
            <tr>
                <th>Maç</th>
                <th>Zaman (İstanbul)</th>
                <th>Kişi başı</th>
                <th>Kontenjan</th>
                <th>Durum</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($maclar as $mac): ?>
                <tr>
                    <td class="mac-hucre">
                        <span class="takim-arma takim-arma-kucuk"><img src="/<?= e((string) $mac['ev_arma']) ?>" alt=""></span>
                        <span><?= e($mac['ev_ad'] . ' - ' . $mac['dep_ad']) ?></span>
                        <span class="takim-arma takim-arma-kucuk"><img src="/<?= e((string) $mac['dep_arma']) ?>" alt=""></span>
                    </td>
                    <td><?= e(macZamaniBicimle((string) $mac['baslangic_zamani'])) ?></td>
                    <td><?= (int) $mac['kisi_basi_fiyat_kurus'] > 0 ? e(kurusBicimle((int) $mac['kisi_basi_fiyat_kurus'])) : '<span class="rozet rozet-sari">Ücretsiz</span>' ?></td>
                    <td><?= (int) $mac['paylasimli_kontenjan'] > 0 ? e($mac['paylasimli_kontenjan'] . ' kişi') : '—' ?></td>
                    <td><?= macDurumRozeti((string) $mac['durum']) ?></td>
                    <td><a class="buton buton-koyu-hayalet buton-kucuk" href="/admin/maclar/<?= e((string) $mac['id']) ?>"><?= ikon('pencil') ?> Yönet</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
