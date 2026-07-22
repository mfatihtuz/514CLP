<?php
/**
 * Sahte banka ödeme ekranı (yalnız geliştirme).
 * @var array<string, mixed> $odeme
 * @var string $konusmaKimligi
 */
?>
<div class="admin-giris-kutu">
    <div class="admin-giris-marka"><?= ikon('banknote') ?> <span>MOCK <b>BANKA</b></span></div>
    <div class="uyari uyari-bilgi"><?= ikon('info') ?>
        <span>Bu ekran geliştirme içindir; gerçek para hareketi yoktur.
              Canlıda müşteri burada iyzico'nun 3D Secure sayfasını görür.</span>
    </div>
    <p style="text-align:center;font-size:1.6rem;font-family:var(--font-baslik);font-weight:700;">
        <?= e(kurusBicimle((int) $odeme['tutar_kurus'])) ?>
    </p>
    <p class="metin-soluk" style="text-align:center;font-size:0.85rem;">İşlem: <?= e($konusmaKimligi) ?></p>
    <form method="post" action="/mock-odeme" style="display:flex;flex-direction:column;gap:0.6rem;">
        <input type="hidden" name="csrf_jetonu" value="<?= e(Guvenlik::csrfJetonu()) ?>">
        <input type="hidden" name="kk" value="<?= e($konusmaKimligi) ?>">
        <button class="buton buton-birincil buton-genis" name="eylem" value="onayla" type="submit">
            <?= ikon('check') ?> Ödemeyi Onayla
        </button>
        <button class="buton buton-koyu-hayalet buton-genis" name="eylem" value="reddet" type="submit">
            <?= ikon('x') ?> Reddet (yetersiz bakiye)
        </button>
        <button class="buton buton-koyu-hayalet buton-genis" name="eylem" value="onayla_callback_kayip" type="submit">
            <?= ikon('wifi-off') ?> Onayla ama callback'i kaybet (test)
        </button>
    </form>
</div>
