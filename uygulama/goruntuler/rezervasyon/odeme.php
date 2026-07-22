<?php
/**
 * Ödeme adımı — Faz 3'te OdemeSaglayici (mock/iyzico) buraya bağlanır.
 * @var array<string, mixed> $rezervasyon
 * @var array<string, mixed> $detay
 */
?>
<section class="bolum">
    <div class="kap kap-dar">
        <div class="geri-sayim-kutu" data-bitis-utc="<?= e((string) $rezervasyon['hold_sona_erme']) ?>">
            <?= ikon('timer') ?>
            <span>Masanız ayrılmaya devam ediyor: <b data-rol="geri-sayim">--:--</b></span>
        </div>

        <h1 class="bolum-baslik"><?= ikon('banknote') ?> Ödeme</h1>

        <div class="ozet-kart">
            <div class="ozet-kart-satirlar">
                <span><?= e($detay['ev_ad'] . ' - ' . $detay['dep_ad']) ?> — <?= e(macZamaniBicimle((string) $detay['baslangic_zamani'])) ?></span>
                <span><?= e((string) $rezervasyon['kisi_sayisi']) ?> kişi</span>
                <span><b>Toplam: <?= e(kurusBicimle((int) $rezervasyon['toplam_tutar_kurus'])) ?></b></span>
            </div>
        </div>

        <?php if (!empty($_SESSION['tek_seferlik_mesaj'])): ?>
            <div class="uyari uyari-hata"><?= ikon('circle-alert') ?> <?= e((string) $_SESSION['tek_seferlik_mesaj']) ?></div>
            <?php unset($_SESSION['tek_seferlik_mesaj']); ?>
        <?php endif; ?>

        <form method="post" action="/rezervasyon/<?= e((string) $rezervasyon['kod']) ?>/odeme/baslat">
            <input type="hidden" name="csrf_jetonu" value="<?= e(Guvenlik::csrfJetonu()) ?>">
            <button class="buton buton-birincil buton-genis" type="submit">
                <?= ikon('shield-check') ?> Güvenli Ödemeye Geç
            </button>
        </form>
        <p class="form-ipucu" style="text-align:center;margin-top:0.8rem;">
            <?= ikon('shield-check') ?> Kart bilgileriniz bu sitede TUTULMAZ; ödeme, bankanızın
            3D Secure doğrulamasıyla sanal POS sağlayıcısının güvenli sayfasında gerçekleşir.
            Ödeme tamamlanınca QR kodlu biletiniz e-postanıza gönderilir.
        </p>
    </div>
</section>
<script>
(function () {
    var kutu = document.querySelector('.geri-sayim-kutu');
    var hedef = new Date(kutu.dataset.bitisUtc.replace(' ', 'T') + 'Z').getTime();
    var gosterge = kutu.querySelector('[data-rol="geri-sayim"]');
    (function guncelle() {
        var kalanSn = Math.floor((hedef - Date.now()) / 1000);
        if (kalanSn <= 0) { window.location.reload(); return; }
        gosterge.textContent = String(Math.floor(kalanSn / 60)).padStart(2, '0') + ':' + String(kalanSn % 60).padStart(2, '0');
        setTimeout(guncelle, 1000);
    })();
})();
</script>
