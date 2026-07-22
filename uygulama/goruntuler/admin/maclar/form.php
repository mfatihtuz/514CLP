<?php
/**
 * @var array<int, array<string, mixed>> $takimlar
 * @var array<string, mixed>|null $mac        null = yeni maç
 * @var array<int, string> $hatalar
 * @var array<string, mixed> $girdi
 */
$duzenleme = $mac !== null;
$eylemYolu = $duzenleme ? '/admin/maclar/' . $mac['id'] : '/admin/maclar';
?>

<div class="admin-kart-baslik">
    <h1 class="admin-sayfa-baslik">
        <?= ikon('calendar') ?>
        <?= $duzenleme ? e($mac['ev_ad'] . ' - ' . $mac['dep_ad']) : 'Yeni Maç' ?>
    </h1>
    <?php if ($duzenleme): ?><?= macDurumRozeti((string) $mac['durum']) ?><?php endif; ?>
</div>

<?php if (!empty($mesaj)): ?>
    <div class="uyari uyari-basari"><?= ikon('circle-check-big') ?> <?= e((string) $mesaj) ?></div>
<?php endif; ?>

<?php if ($hatalar !== []): ?>
    <div class="uyari uyari-hata">
        <?= ikon('circle-alert') ?>
        <div><?php foreach ($hatalar as $hata): ?><div><?= e($hata) ?></div><?php endforeach; ?></div>
    </div>
<?php endif; ?>

<div class="admin-iki-sutun admin-iki-sutun-genis">
<section class="admin-kart">
    <div class="admin-kart-baslik"><h2><?= ikon('pencil') ?> Maç Bilgileri</h2></div>
    <form method="post" action="<?= e($eylemYolu) ?>" id="mac-formu">
        <input type="hidden" name="csrf_jetonu" value="<?= e(Guvenlik::csrfJetonu()) ?>">

        <div class="takim-secici">
            <div class="form-alan">
                <label class="form-etiket" for="ev_sahibi_takim_id">Ev sahibi</label>
                <span class="takim-arma" id="ev-arma-onizleme">
                    <img src="/armalar/galatasaray.svg" alt="" style="visibility:hidden">
                </span>
                <select class="form-secim" id="ev_sahibi_takim_id" name="ev_sahibi_takim_id" required>
                    <option value="">Takım seçin</option>
                    <?php foreach ($takimlar as $takim): ?>
                        <option value="<?= e((string) $takim['id']) ?>" data-arma="/<?= e((string) $takim['arma_dosya']) ?>"
                            <?= (string) $girdi['ev_sahibi_takim_id'] === (string) $takim['id'] ? 'selected' : '' ?>>
                            <?= e((string) $takim['ad']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <span class="takim-secici-vs">VS</span>
            <div class="form-alan">
                <label class="form-etiket" for="deplasman_takim_id">Deplasman</label>
                <span class="takim-arma" id="dep-arma-onizleme">
                    <img src="/armalar/fenerbahce.svg" alt="" style="visibility:hidden">
                </span>
                <select class="form-secim" id="deplasman_takim_id" name="deplasman_takim_id" required>
                    <option value="">Takım seçin</option>
                    <?php foreach ($takimlar as $takim): ?>
                        <option value="<?= e((string) $takim['id']) ?>" data-arma="/<?= e((string) $takim['arma_dosya']) ?>"
                            <?= (string) $girdi['deplasman_takim_id'] === (string) $takim['id'] ? 'selected' : '' ?>>
                            <?= e((string) $takim['ad']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-izgara">
            <div class="form-alan">
                <label class="form-etiket" for="baslangic_yerel">Maç başlangıcı (İstanbul saati)</label>
                <input class="form-girdi" type="datetime-local" id="baslangic_yerel" name="baslangic_yerel"
                       required value="<?= e((string) $girdi['baslangic_yerel']) ?>">
            </div>
            <div class="form-alan">
                <label class="form-etiket" for="kapi_acilis_yerel">Kapı açılışı</label>
                <input class="form-girdi" type="datetime-local" id="kapi_acilis_yerel" name="kapi_acilis_yerel"
                       value="<?= e((string) $girdi['kapi_acilis_yerel']) ?>">
                <p class="form-ipucu">Boş bırakılırsa maçtan 1,5 saat öncesi kullanılır.</p>
            </div>
            <div class="form-alan">
                <label class="form-etiket" for="kisi_basi_fiyat">Kişi başı fiyat (TL)</label>
                <input class="form-girdi" type="text" id="kisi_basi_fiyat" name="kisi_basi_fiyat"
                       inputmode="decimal" required value="<?= e((string) $girdi['kisi_basi_fiyat']) ?>">
                <p class="form-ipucu">Ücretsiz maç için 0 yazın; ödeme adımı otomatik atlanır.</p>
            </div>
            <div class="form-alan">
                <label class="form-etiket" for="paylasimli_kontenjan">Salon girişi kontenjanı (kişi)</label>
                <input class="form-girdi" type="number" id="paylasimli_kontenjan" name="paylasimli_kontenjan"
                       min="0" max="200" value="<?= e((string) $girdi['paylasimli_kontenjan']) ?>">
                <p class="form-ipucu">1-2 kişilik gruplar masa seçemez; bu kontenjandan yer alır. 0 = kapalı.</p>
            </div>
            <div class="form-alan">
                <label class="form-etiket" for="iptal_saat_once">İptal penceresi (saat)</label>
                <input class="form-girdi" type="number" id="iptal_saat_once" name="iptal_saat_once"
                       min="0" max="72" value="<?= e((string) ($girdi['iptal_saat_once'] ?? '')) ?>"
                       placeholder="Genel ayar: <?= e((string) Ayarlar::varsayilanIptalSaat()) ?>">
                <p class="form-ipucu">Boş bırakılırsa genel ayar geçerlidir.</p>
            </div>
            <div class="form-alan form-alan-genis">
                <label class="form-etiket" for="paket_icerigi">Fiyata dahil paket (her satır bir kalem)</label>
                <textarea class="form-metin" id="paket_icerigi" name="paket_icerigi" rows="3"><?= e((string) $girdi['paket_icerigi']) ?></textarea>
            </div>
        </div>

        <button class="buton buton-birincil" type="submit">
            <?= ikon('save') ?> <?= $duzenleme ? 'Değişiklikleri Kaydet' : 'Maçı Taslak Olarak Oluştur' ?>
        </button>
    </form>
</section>

<?php if ($duzenleme): ?>
<section class="admin-kart">
    <div class="admin-kart-baslik"><h2><?= ikon('settings') ?> Durum ve Satış</h2></div>

    <?php if (!empty($doluluk)): ?>
        <div class="sayi-kartlari sayi-kartlari-dar">
            <div class="sayi-kart"><span class="sayi-kart-deger"><?= e($doluluk['rezerve_masa'] . '/' . $doluluk['acik_masa']) ?></span><span class="sayi-kart-etiket">Dolu masa</span></div>
            <div class="sayi-kart"><span class="sayi-kart-deger"><?= e((string) $doluluk['rezerve_kisi']) ?></span><span class="sayi-kart-etiket">Onaylı kişi</span></div>
        </div>
    <?php endif; ?>

    <?php if ($mac['durum'] === 'taslak'): ?>
        <p class="metin-soluk">Maç yayınlandığında kat planındaki masalar bu maça KOPYALANIR
            ve satış başlar. Sonrasında bu maçın krokisi ana plandan bağımsızdır.</p>
        <form method="post" action="/admin/maclar/<?= e((string) $mac['id']) ?>/yayinla">
            <input type="hidden" name="csrf_jetonu" value="<?= e(Guvenlik::csrfJetonu()) ?>">
            <button class="buton buton-birincil buton-genis" type="submit"><?= ikon('check') ?> Yayınla ve Satışa Aç</button>
        </form>
    <?php else: ?>
        <div class="durum-eylemler">
            <?php
            $eylemler = [];
            if ($mac['durum'] === 'satista') {
                $eylemler[] = ['satis_kapali', 'Satışı Durdur', 'buton-ikincil', 'x'];
            } elseif ($mac['durum'] === 'satis_kapali') {
                $eylemler[] = ['satista', 'Satışı Yeniden Aç', 'buton-ikincil', 'check'];
                $eylemler[] = ['tamamlandi', 'Maçı Tamamlandı İşaretle', 'buton-koyu-hayalet', 'circle-check-big'];
            }
            if (in_array($mac['durum'], ['taslak', 'satista', 'satis_kapali'], true)) {
                $eylemler[] = ['iptal', 'Maçı İptal Et', 'buton-koyu-hayalet', 'trash-2'];
            }
            ?>
            <?php foreach ($eylemler as [$durum, $etiket, $sinif, $ikonAd]): ?>
                <form method="post" action="/admin/maclar/<?= e((string) $mac['id']) ?>/durum">
                    <input type="hidden" name="csrf_jetonu" value="<?= e(Guvenlik::csrfJetonu()) ?>">
                    <input type="hidden" name="yeni_durum" value="<?= e($durum) ?>">
                    <button class="buton <?= e($sinif) ?> buton-genis" type="submit"><?= ikon($ikonAd) ?> <?= e($etiket) ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
</div>

<?php if ($duzenleme && in_array($mac['durum'], ['satista', 'satis_kapali'], true)): ?>
<section class="admin-kart">
    <div class="admin-kart-baslik">
        <h2><?= ikon('armchair') ?> Bu Maçın Krokisi</h2>
        <span class="metin-soluk">Ekstra masa ekleyebilir, boş masayı kapatabilir, masaları taşıyabilirsiniz.
            Rezervasyonlu masada yalnız konum değişir; silme yoktur.</span>
    </div>
    <div id="kroki-editor"
         data-veri-url="/admin/api/maclar/<?= e((string) $mac['id']) ?>/kroki"
         data-kaydet-url="/admin/api/maclar/<?= e((string) $mac['id']) ?>/kroki"
         data-masa-durum-url="/admin/api/mac-masa/{id}/durum"
         data-mod="mac"
         data-csrf="<?= e(Guvenlik::csrfJetonu()) ?>"></div>
</section>
<?php elseif ($duzenleme && $mac['durum'] === 'taslak'): ?>
<section class="admin-kart">
    <div class="admin-kart-baslik"><h2><?= ikon('armchair') ?> Kroki</h2></div>
    <p class="metin-soluk">Maç yayınlanınca <a href="/admin/kroki">ana kat planı</a> bu maça kopyalanacak.
        Planı yayına almadan önce Kat Planı ekranından düzenleyin.</p>
</section>
<?php endif; ?>

<script src="/varliklar/js/kroki-editor.js"></script>
<script>
// Takım seçicilerde arma önizlemesi
['ev', 'dep'].forEach(function (on) {
    var secim = document.getElementById(on === 'ev' ? 'ev_sahibi_takim_id' : 'deplasman_takim_id');
    var resim = document.querySelector('#' + on + '-arma-onizleme img');
    function guncelle() {
        var secenek = secim.options[secim.selectedIndex];
        var arma = secenek ? secenek.getAttribute('data-arma') : null;
        if (arma) { resim.src = arma; resim.style.visibility = 'visible'; }
        else { resim.style.visibility = 'hidden'; }
    }
    secim.addEventListener('change', guncelle);
    guncelle();
});
var krokiKok = document.getElementById('kroki-editor');
if (krokiKok) { krokiEditorBaslat(krokiKok); }
</script>
