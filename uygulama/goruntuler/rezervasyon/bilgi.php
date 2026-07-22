<?php
/**
 * Misafir bilgileri adımı (hold süresi işlerken doldurulur).
 * @var array<string, mixed> $rezervasyon
 * @var array<string, mixed> $detay
 * @var array<int, array<string, mixed>> $masalar
 * @var array<int, string> $hatalar
 * @var array<string, mixed> $girdi
 */
?>
<section class="bolum">
    <div class="kap kap-dar">
        <div class="geri-sayim-kutu" data-bitis-utc="<?= e((string) $rezervasyon['hold_sona_erme']) ?>">
            <?= ikon('timer') ?>
            <span>Masanız sizin için ayrıldı: <b data-rol="geri-sayim">--:--</b></span>
        </div>

        <div class="ozet-kart">
            <div class="ozet-kart-baslik">
                <span class="takim-arma takim-arma-kucuk"><img src="/<?= e((string) $detay['ev_arma']) ?>" alt=""></span>
                <b><?= e($detay['ev_ad'] . ' - ' . $detay['dep_ad']) ?></b>
                <span class="takim-arma takim-arma-kucuk"><img src="/<?= e((string) $detay['dep_arma']) ?>" alt=""></span>
            </div>
            <div class="ozet-kart-satirlar">
                <span><?= ikon('calendar') ?> <?= e(macZamaniBicimle((string) $detay['baslangic_zamani'])) ?></span>
                <span><?= ikon('armchair') ?>
                    <?php if ($rezervasyon['tur'] === 'salon'): ?>
                        Salon Girişi (yeriniz girişte gösterilir)
                    <?php else: ?>
                        Masa: <?= e(implode(', ', array_map(static fn(array $m): string => (string) $m['ad'], $masalar))) ?>
                    <?php endif; ?>
                </span>
                <span><?= ikon('users') ?> <?= e((string) $rezervasyon['kisi_sayisi']) ?> kişi</span>
                <span><?= ikon('banknote') ?>
                    <?= (int) $rezervasyon['toplam_tutar_kurus'] > 0
                        ? e(kurusBicimle((int) $rezervasyon['toplam_tutar_kurus'])) . ' (toplam)'
                        : 'Ücretsiz' ?>
                </span>
            </div>
        </div>

        <h1 class="bolum-baslik"><?= ikon('user') ?> İletişim Bilgileriniz</h1>

        <?php if ($hatalar !== []): ?>
            <div class="uyari uyari-hata">
                <?= ikon('circle-alert') ?>
                <div><?php foreach ($hatalar as $hata): ?><div><?= e($hata) ?></div><?php endforeach; ?></div>
            </div>
        <?php endif; ?>

        <form method="post" action="/rezervasyon/<?= e((string) $rezervasyon['kod']) ?>/bilgi" class="bilgi-formu">
            <input type="hidden" name="csrf_jetonu" value="<?= e(Guvenlik::csrfJetonu()) ?>">
            <div class="form-alan">
                <label class="form-etiket" for="ad_soyad">Ad Soyad</label>
                <input class="form-girdi" type="text" id="ad_soyad" name="ad_soyad" required
                       autocomplete="name" value="<?= e((string) ($girdi['ad_soyad'] ?? '')) ?>">
            </div>
            <div class="form-alan">
                <label class="form-etiket" for="telefon">Cep Telefonu</label>
                <input class="form-girdi" type="tel" id="telefon" name="telefon" required
                       inputmode="tel" placeholder="05XX XXX XX XX" autocomplete="tel"
                       value="<?= e((string) ($girdi['telefon'] ?? '')) ?>">
                <p class="form-ipucu">Rezervasyon sorgulamada ve girişte doğrulama için kullanılır.</p>
            </div>
            <div class="form-alan">
                <label class="form-etiket" for="eposta">E-posta</label>
                <input class="form-girdi" type="email" id="eposta" name="eposta" required
                       autocomplete="email" value="<?= e((string) ($girdi['eposta'] ?? '')) ?>">
                <p class="form-ipucu">QR kodlu biletiniz bu adrese gönderilir.</p>
            </div>

            <label class="onay-kutusu">
                <input type="checkbox" name="kvkk_onay" required <?= !empty($girdi['kvkk_onay']) ? 'checked' : '' ?>>
                <span><a href="/yasal/kvkk" target="_blank" rel="noopener">KVKK Aydınlatma Metni</a>'ni okudum, kişisel verilerimin
                      rezervasyon amacıyla işlenmesine onay veriyorum.</span>
            </label>
            <label class="onay-kutusu">
                <input type="checkbox" name="sozlesme_onay" required <?= !empty($girdi['sozlesme_onay']) ? 'checked' : '' ?>>
                <span><a href="/yasal/mesafeli-satis" target="_blank" rel="noopener">Mesafeli Satış Sözleşmesi</a>'ni ve
                      <a href="/yasal/iade-kosullari" target="_blank" rel="noopener">iptal-iade koşullarını</a> kabul ediyorum.</span>
            </label>

            <button class="buton buton-birincil buton-genis" type="submit">
                <?= (int) $rezervasyon['toplam_tutar_kurus'] > 0 ? 'Ödemeye Geç' : 'Rezervasyonu Tamamla' ?>
                <?= ikon('arrow-right') ?>
            </button>
        </form>
    </div>
</section>

<script>
(function () {
    var kutu = document.querySelector('.geri-sayim-kutu');
    var hedefMetin = kutu.dataset.bitisUtc.replace(' ', 'T') + 'Z';
    var hedef = new Date(hedefMetin).getTime();
    var gosterge = kutu.querySelector('[data-rol="geri-sayim"]');
    function guncelle() {
        var kalanSn = Math.floor((hedef - Date.now()) / 1000);
        if (kalanSn <= 0) {
            gosterge.textContent = '00:00';
            kutu.classList.add('doldu');
            window.location.reload();
            return;
        }
        var dk = Math.floor(kalanSn / 60), sn = kalanSn % 60;
        gosterge.textContent = String(dk).padStart(2, '0') + ':' + String(sn).padStart(2, '0');
        if (kalanSn < 60) kutu.classList.add('kritik');
        setTimeout(guncelle, 1000);
    }
    guncelle();
})();
</script>
