<?php
/**
 * Bilet sayfası — QR + rezervasyon detayları + iptal.
 * @var array<string, mixed> $detay
 * @var array<int, array<string, mixed>> $masalar
 * @var array<int, string> $paket
 * @var string|null $qrDataUri
 * @var bool $iptalEdilebilir
 * @var string $sonIptal
 */
?>
<section class="bolum">
    <div class="kap kap-dar">
        <?php if (!empty($_SESSION['tek_seferlik_mesaj'])): ?>
            <div class="uyari uyari-hata"><?= ikon('circle-alert') ?> <?= e((string) $_SESSION['tek_seferlik_mesaj']) ?></div>
            <?php unset($_SESSION['tek_seferlik_mesaj']); ?>
        <?php endif; ?>

        <?php if ($detay['durum'] === 'onaylandi'): ?>
            <div class="uyari uyari-basari"><?= ikon('circle-check-big') ?>
                <span>Rezervasyonunuz onaylandı. QR kodlu biletiniz <b><?= e((string) $detay['eposta']) ?></b> adresine gönderildi.</span>
            </div>
        <?php elseif ($detay['durum'] === 'iptal_edildi'): ?>
            <div class="uyari uyari-hata"><?= ikon('circle-x') ?> <span>Bu rezervasyon iptal edilmiştir.</span></div>
        <?php endif; ?>

        <article class="bilet">
            <div class="bilet-ust">
                <div class="bilet-mac">
                    <span class="takim-arma"><img src="/<?= e((string) $detay['ev_arma']) ?>" alt="<?= e((string) $detay['ev_ad']) ?> arması"></span>
                    <div class="bilet-mac-orta">
                        <b><?= e((string) $detay['ev_ad']) ?></b>
                        <span class="mac-vs">VS</span>
                        <b><?= e((string) $detay['dep_ad']) ?></b>
                    </div>
                    <span class="takim-arma"><img src="/<?= e((string) $detay['dep_arma']) ?>" alt="<?= e((string) $detay['dep_ad']) ?> arması"></span>
                </div>
                <div class="bilet-zaman">
                    <span><?= ikon('calendar') ?> <?= e(macZamaniBicimle((string) $detay['baslangic_zamani'])) ?></span>
                    <?php if (!empty($detay['kapi_acilis_zamani'])): ?>
                        <span><?= ikon('clock') ?> Kapı açılışı <?= e(saatBicimle((string) $detay['kapi_acilis_zamani'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bilet-yirtik" aria-hidden="true"></div>

            <div class="bilet-alt">
                <?php if ($qrDataUri !== null): ?>
                    <div class="bilet-qr">
                        <img src="<?= e($qrDataUri) ?>" alt="Giriş QR kodu" width="200" height="200">
                        <span class="metin-soluk">Girişte bu kodu okutun</span>
                    </div>
                <?php endif; ?>
                <div class="bilet-bilgiler">
                    <div class="bilet-satir"><span>Rezervasyon kodu</span><b class="bilet-kod"><?= e((string) $detay['kod']) ?></b></div>
                    <div class="bilet-satir"><span>Ad Soyad</span><b><?= e((string) $detay['ad_soyad']) ?></b></div>
                    <div class="bilet-satir"><span>Kişi</span><b><?= e((string) $detay['kisi_sayisi']) ?> kişi</b></div>
                    <div class="bilet-satir">
                        <span>Yer</span>
                        <b><?php if ($detay['tur'] === 'salon'): ?>Salon Girişi<?php else: ?>
                            Masa <?= e(implode(', ', array_map(static fn(array $m): string => (string) $m['ad'], $masalar))) ?>
                        <?php endif; ?></b>
                    </div>
                    <?php if ($paket !== []): ?>
                        <div class="bilet-satir"><span>Fiyata dahil</span><b><?= e(implode(' + ', $paket)) ?></b></div>
                    <?php endif; ?>
                    <div class="bilet-satir">
                        <span>Toplam</span>
                        <b><?= (int) $detay['toplam_tutar_kurus'] > 0 ? e(kurusBicimle((int) $detay['toplam_tutar_kurus'])) : 'Ücretsiz' ?></b>
                    </div>
                </div>
            </div>
        </article>

        <?php if ($detay['durum'] === 'onaylandi'): ?>
            <?php if ($iptalEdilebilir): ?>
                <form method="post" action="/rezervasyon/<?= e((string) $detay['kod']) ?>/iptal" class="iptal-blok"
                      onsubmit="return confirm('Rezervasyonunuz iptal edilecek<?= (int) $detay['toplam_tutar_kurus'] > 0 ? ' ve ücret iadesi başlatılacak' : '' ?>. Emin misiniz?');">
                    <input type="hidden" name="csrf_jetonu" value="<?= e(Guvenlik::csrfJetonu()) ?>">
                    <button class="buton buton-koyu-hayalet" type="submit"><?= ikon('circle-x') ?> Rezervasyonu İptal Et</button>
                    <p class="form-ipucu"><?= e($sonIptal) ?> saatine kadar ücretsiz iptal edebilirsiniz.</p>
                </form>
            <?php else: ?>
                <p class="form-ipucu iptal-blok">İptal penceresi kapandı (son iptal: <?= e($sonIptal) ?>).
                    Değişiklik için işletmeyi arayın.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
