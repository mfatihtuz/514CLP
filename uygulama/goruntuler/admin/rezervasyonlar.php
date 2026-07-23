<?php
/**
 * @var array<int, array<string, mixed>> $rezervasyonlar
 * @var array<int, array<int, string>> $masaHaritasi
 * @var array<int, array<string, mixed>> $maclar
 * @var array<string, mixed> $secilen
 */
$rezDurumRozeti = static function (string $durum): string {
    $harita = [
        'onaylandi'      => ['rozet-yesil', 'Onaylı'],
        'odeme_bekliyor' => ['rozet-sari', 'Ödeme bekliyor'],
        'iptal_edildi'   => ['rozet-kirmizi', 'İptal'],
        'suresi_doldu'   => ['rozet-lacivert', 'Süresi doldu'],
    ];
    [$sinif, $etiket] = $harita[$durum] ?? ['rozet-lacivert', $durum];
    return '<span class="rozet ' . $sinif . '">' . e($etiket) . '</span>';
};
?>
<h1 class="admin-sayfa-baslik"><?= ikon('ticket') ?> Rezervasyonlar</h1>

<?php if (!empty($mesaj)): ?>
    <div class="uyari uyari-basari"><?= ikon('circle-check-big') ?> <?= e((string) $mesaj) ?></div>
<?php endif; ?>

<div class="admin-kart">
    <form method="get" action="/admin/rezervasyonlar" class="filtre-cubugu">
        <select class="form-secim" name="mac">
            <option value="">Tüm maçlar</option>
            <?php foreach ($maclar as $mac): ?>
                <option value="<?= e((string) $mac['id']) ?>" <?= (int) $secilen['mac'] === (int) $mac['id'] ? 'selected' : '' ?>>
                    <?= e($mac['ev_kisa'] . '-' . $mac['dep_kisa'] . ' | ' . tarihBicimle((string) $mac['baslangic_zamani'])) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select class="form-secim" name="durum">
            <option value="">Tüm durumlar</option>
            <?php foreach (['onaylandi' => 'Onaylı', 'odeme_bekliyor' => 'Ödeme bekliyor', 'iptal_edildi' => 'İptal', 'suresi_doldu' => 'Süresi doldu'] as $deger => $etiket): ?>
                <option value="<?= e($deger) ?>" <?= $secilen['durum'] === $deger ? 'selected' : '' ?>><?= e($etiket) ?></option>
            <?php endforeach; ?>
        </select>
        <input class="form-girdi" type="search" name="ara" placeholder="Kod, ad, telefon, e-posta ara"
               value="<?= e((string) $secilen['ara']) ?>">
        <button class="buton buton-ikincil" type="submit"><?= ikon('funnel') ?> Filtrele</button>
    </form>
</div>

<?php if ($rezervasyonlar === []): ?>
    <div class="bos-durum">
        <?= ikon('ticket') ?>
        <h3>Kayıt bulunamadı</h3>
        <p>Filtreleri değiştirerek yeniden deneyin.</p>
    </div>
<?php else: ?>
    <div class="admin-kart">
        <div class="tablo-sar">
        <table class="admin-tablo tablo-kart">
            <thead>
            <tr>
                <th>Kod</th><th>Misafir</th><th>Maç</th><th>Yer</th><th>Kişi</th>
                <th>Tutar</th><th>Durum</th><th>Check-in</th><th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rezervasyonlar as $rezervasyon): ?>
                <tr>
                    <td data-etiket="Kod"><b class="rez-kod"><?= e((string) $rezervasyon['kod']) ?></b></td>
                    <td data-etiket="Misafir">
                        <?= e((string) $rezervasyon['ad_soyad']) ?><br>
                        <span class="metin-soluk"><?= e((string) $rezervasyon['telefon']) ?></span>
                    </td>
                    <td data-etiket="Maç">
                        <?= e($rezervasyon['ev_kisa'] . '-' . $rezervasyon['dep_kisa']) ?><br>
                        <span class="metin-soluk"><?= e(tarihBicimle((string) $rezervasyon['baslangic_zamani'])) ?></span>
                    </td>
                    <td data-etiket="Yer">
                        <?php if ($rezervasyon['tur'] === 'salon'): ?>
                            <span class="rozet rozet-lacivert">Salon</span>
                        <?php else: ?>
                            <?= e(implode(', ', $masaHaritasi[(int) $rezervasyon['id']] ?? ['—'])) ?>
                        <?php endif; ?>
                    </td>
                    <td data-etiket="Kişi"><?= e((string) $rezervasyon['kisi_sayisi']) ?></td>
                    <td data-etiket="Tutar"><?= (int) $rezervasyon['toplam_tutar_kurus'] > 0 ? e(kurusBicimle((int) $rezervasyon['toplam_tutar_kurus'])) : '—' ?></td>
                    <td data-etiket="Durum"><?= $rezDurumRozeti((string) $rezervasyon['durum']) ?></td>
                    <td data-etiket="Check-in">
                        <?= $rezervasyon['checkin_zamani'] !== null
                            ? '<span class="rozet rozet-yesil">' . e(saatBicimle((string) $rezervasyon['checkin_zamani'])) . '</span>'
                            : '<span class="metin-soluk">—</span>' ?>
                    </td>
                    <td data-etiket="İşlem">
                        <?php if ($rezervasyon['durum'] === 'onaylandi'): ?>
                            <form method="post" action="/admin/rezervasyonlar/<?= e((string) $rezervasyon['id']) ?>/iptal"
                                  onsubmit="return confirm('<?= e((string) $rezervasyon['kod']) ?> iptal edilecek<?= (int) $rezervasyon['toplam_tutar_kurus'] > 0 ? ' ve İADE başlatılacak' : '' ?>. Emin misiniz?');">
                                <input type="hidden" name="csrf_jetonu" value="<?= e(Guvenlik::csrfJetonu()) ?>">
                                <button class="buton buton-koyu-hayalet buton-kucuk" type="submit">
                                    <?= ikon('circle-x') ?> İptal<?= (int) $rezervasyon['toplam_tutar_kurus'] > 0 ? ' + İade' : '' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php endif; ?>
