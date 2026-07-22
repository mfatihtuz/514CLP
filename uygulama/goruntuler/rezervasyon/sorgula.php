<section class="bolum">
    <div class="kap kap-dar">
        <h1 class="bolum-baslik"><?= ikon('search') ?> Rezervasyon Sorgula</h1>
        <p class="metin-soluk">Onay e-postanızdaki rezervasyon kodu ve rezervasyonda kullandığınız
            telefon numarasıyla biletinize ulaşın.</p>

        <?php if (!empty($hata)): ?>
            <div class="uyari uyari-hata"><?= ikon('circle-alert') ?> <?= e((string) $hata) ?></div>
        <?php endif; ?>

        <form method="post" action="/rezervasyon-sorgula" class="bilgi-formu">
            <input type="hidden" name="csrf_jetonu" value="<?= e(Guvenlik::csrfJetonu()) ?>">
            <div class="form-alan">
                <label class="form-etiket" for="kod">Rezervasyon Kodu</label>
                <input class="form-girdi" type="text" id="kod" name="kod" required maxlength="8"
                       style="text-transform:uppercase" value="<?= e((string) ($kod ?? '')) ?>" placeholder="Örn: A7C2F9KM">
            </div>
            <div class="form-alan">
                <label class="form-etiket" for="telefon">Telefon</label>
                <input class="form-girdi" type="tel" id="telefon" name="telefon" required
                       inputmode="tel" placeholder="05XX XXX XX XX">
            </div>
            <button class="buton buton-birincil buton-genis" type="submit"><?= ikon('ticket') ?> Biletimi Göster</button>
        </form>
    </div>
</section>
