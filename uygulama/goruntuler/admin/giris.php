<div class="admin-giris-kutu">
    <div class="admin-giris-marka"><?= ikon('shield-check') ?> <span>Maç Gecesi <b>Yönetim</b></span></div>
    <?php if (!empty($hata)): ?>
        <div class="uyari uyari-hata"><?= ikon('circle-alert') ?> <?= e($hata) ?></div>
    <?php endif; ?>
    <form method="post" action="/admin/giris">
        <input type="hidden" name="csrf_jetonu" value="<?= e(Guvenlik::csrfJetonu()) ?>">
        <div class="form-alan">
            <label class="form-etiket" for="eposta">E-posta</label>
            <input class="form-girdi" type="email" id="eposta" name="eposta" required autofocus
                   value="<?= e($eposta ?? '') ?>" autocomplete="username">
        </div>
        <div class="form-alan">
            <label class="form-etiket" for="sifre">Şifre</label>
            <input class="form-girdi" type="password" id="sifre" name="sifre" required autocomplete="current-password">
        </div>
        <button class="buton buton-birincil buton-genis" type="submit"><?= ikon('log-in') ?> Giriş Yap</button>
    </form>
</div>
