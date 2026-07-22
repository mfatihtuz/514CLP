<section class="bolum">
    <div class="kap hata-sayfa">
        <?= ikon('timer', 'ikon ikon-buyuk-hata') ?>
        <h1 style="font-size:2.2rem;">Süre doldu</h1>
        <p><?= e($mesaj ?? 'Masanız için ayırdığımız süre doldu ve yer yeniden satışa açıldı.') ?><br>
            Merak etmeyin, yeniden seçim yapmak yalnızca bir dakika sürer.</p>
        <a class="buton buton-birincil" href="/mac/<?= e((string) $macId) ?>"><?= ikon('refresh-cw') ?> Yeniden Masa Seç</a>
    </div>
</section>
