<h1 class="admin-sayfa-baslik"><?= ikon('settings') ?> Ayarlar</h1>

<?php if (!empty($mesaj)): ?>
    <div class="uyari uyari-basari"><?= ikon('circle-check-big') ?> <?= e((string) $mesaj) ?></div>
<?php endif; ?>

<div class="admin-kart">
    <form method="post" action="/admin/ayarlar">
        <input type="hidden" name="csrf_jetonu" value="<?= e(Guvenlik::csrfJetonu()) ?>">
        <div class="form-izgara">
            <?php foreach ($alanlar as $anahtar => [$etiket, $tur, $aciklama]): ?>
                <div class="form-alan">
                    <label class="form-etiket" for="<?= e($anahtar) ?>"><?= e($etiket) ?></label>
                    <input class="form-girdi"
                           type="<?= $tur === 'sayi' ? 'number' : 'text' ?>"
                           <?= $tur === 'sayi' ? 'min="0"' : '' ?>
                           id="<?= e($anahtar) ?>" name="<?= e($anahtar) ?>"
                           value="<?= e((string) $degerler[$anahtar]) ?>">
                    <p class="form-ipucu"><?= e($aciklama) ?></p>
                </div>
            <?php endforeach; ?>
            <div class="form-alan form-alan-genis">
                <label class="form-etiket" for="varsayilan_paket">Varsayılan paket içeriği (her satır bir kalem)</label>
                <textarea class="form-metin" id="varsayilan_paket" name="varsayilan_paket" rows="3"><?= e((string) $paket) ?></textarea>
                <p class="form-ipucu">Yeni maç formunda önerilecek içerik; maç bazında değiştirilebilir.</p>
            </div>
        </div>
        <button class="buton buton-birincil" type="submit"><?= ikon('save') ?> Kaydet</button>
    </form>
</div>
