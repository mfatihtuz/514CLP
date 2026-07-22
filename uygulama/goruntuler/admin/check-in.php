<?php /** @var array<int, array<string, mixed>> $maclar */ ?>
<h1 class="admin-sayfa-baslik"><?= ikon('qr-code') ?> Check-in</h1>

<?php if ($maclar === []): ?>
    <div class="bos-durum">
        <?= ikon('calendar') ?>
        <h3>Aktif maç yok</h3>
        <p>Check-in için satışta veya satışı durdurulmuş bir maç gerekir.</p>
    </div>
<?php else: ?>
<div id="checkin" data-csrf="<?= e(Guvenlik::csrfJetonu()) ?>">
    <div class="admin-iki-sutun">
        <section class="admin-kart">
            <div class="form-alan">
                <label class="form-etiket" for="mac-secim">Maç</label>
                <select class="form-secim" id="mac-secim">
                    <?php foreach ($maclar as $mac): ?>
                        <option value="<?= e((string) $mac['id']) ?>">
                            <?= e($mac['ev_ad'] . ' - ' . $mac['dep_ad'] . ' | ' . macZamaniBicimle((string) $mac['baslangic_zamani'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="checkin-sayac">
                <span class="sayi-kart-deger" data-rol="sayac">–</span>
                <span class="sayi-kart-etiket">giriş yapan / onaylı kişi</span>
            </div>

            <div class="checkin-kamera-kutu">
                <video id="kamera" playsinline muted></video>
                <div class="checkin-kamera-kapali" data-rol="kamera-kapali">
                    <?= ikon('camera') ?>
                    <p>Müşterinin QR kodunu okutmak için kamerayı başlatın.</p>
                    <button type="button" class="buton buton-birincil" data-rol="kamera-baslat">
                        <?= ikon('camera') ?> Kamerayı Başlat
                    </button>
                </div>
            </div>

            <form data-rol="manuel-form" class="checkin-manuel">
                <input class="form-girdi" type="text" data-rol="manuel-kod" placeholder="veya kodu elle girin: A7C2F9KM"
                       maxlength="8" style="text-transform:uppercase" autocomplete="off">
                <button class="buton buton-ikincil" type="submit"><?= ikon('search') ?> Sorgula</button>
            </form>
        </section>

        <section class="admin-kart">
            <div class="admin-kart-baslik"><h2><?= ikon('ticket') ?> Sonuç</h2></div>
            <div class="checkin-sonuc" data-rol="sonuc">
                <p class="metin-soluk">Henüz bilet okutulmadı.</p>
            </div>
        </section>
    </div>
</div>

<script src="/varliklar/js/qr-scanner.umd.min.js"></script>
<script src="/varliklar/js/check-in.js"></script>
<script>checkinBaslat(document.getElementById('checkin'));</script>
<?php endif; ?>
