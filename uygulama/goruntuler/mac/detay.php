<?php
/**
 * Masa seçim ekranı — "sinema bileti" deneyiminin kalbi.
 * @var array<string, mixed> $mac
 * @var array<int, string> $paket
 * @var int $salonKalan
 * @var int $holdDakika
 * @var bool $satisAcik
 */
?>

<section class="mac-baslik-serit">
    <div class="kap">
        <a class="geri-baglanti" href="/"><?= ikon('arrow-left') ?> Tüm maçlar</a>
        <div class="mac-baslik-esleme">
            <div class="mac-takim">
                <span class="takim-arma takim-arma-buyuk"><img src="/<?= e((string) $mac['ev_arma']) ?>" alt="<?= e((string) $mac['ev_ad']) ?> arması"></span>
                <span class="mac-takim-ad"><?= e((string) $mac['ev_ad']) ?></span>
            </div>
            <div class="mac-baslik-orta">
                <span class="mac-vs">VS</span>
                <span class="mac-baslik-tarih"><?= ikon('calendar') ?> <?= e(macZamaniBicimle((string) $mac['baslangic_zamani'])) ?></span>
                <?php if (!empty($mac['kapi_acilis_zamani'])): ?>
                    <span class="mac-baslik-kapi"><?= ikon('clock') ?> Kapı: <?= e(saatBicimle((string) $mac['kapi_acilis_zamani'])) ?></span>
                <?php endif; ?>
            </div>
            <div class="mac-takim">
                <span class="takim-arma takim-arma-buyuk"><img src="/<?= e((string) $mac['dep_arma']) ?>" alt="<?= e((string) $mac['dep_ad']) ?> arması"></span>
                <span class="mac-takim-ad"><?= e((string) $mac['dep_ad']) ?></span>
            </div>
        </div>
        <div class="mac-baslik-detaylar">
            <span class="rozet rozet-sari">
                <?= ikon('banknote') ?>
                <?= (int) $mac['kisi_basi_fiyat_kurus'] > 0 ? e(kurusBicimle((int) $mac['kisi_basi_fiyat_kurus'])) . ' / kişi' : 'Ücretsiz giriş' ?>
            </span>
            <?php if ($paket !== []): ?>
                <span class="rozet rozet-lacivert-acik"><?= ikon('utensils') ?> <?= e(implode(' + ', $paket)) ?></span>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if (!$satisAcik): ?>
<section class="bolum">
    <div class="kap">
        <div class="bos-durum">
            <?= ikon('circle-alert') ?>
            <h3>Satış şu anda kapalı</h3>
            <p>Bu maç için rezervasyon alımı durduruldu veya maç başladı.</p>
            <a class="buton buton-birincil" href="/">Diğer Maçlara Bak</a>
        </div>
    </div>
</section>
<?php else: ?>

<section class="bolum secim-bolumu" id="masa-secim"
         data-mac-id="<?= e((string) $mac['id']) ?>"
         data-fiyat-kurus="<?= e((string) $mac['kisi_basi_fiyat_kurus']) ?>"
         data-csrf="<?= e(Guvenlik::csrfJetonu()) ?>">
    <div class="kap">
        <h2 class="bolum-baslik"><?= ikon('users') ?> Kaç kişi geliyorsunuz?</h2>
        <div class="kisi-secici" data-rol="kisi-secici">
            <?php for ($kisi = 1; $kisi <= 8; $kisi++): ?>
                <button type="button" class="kisi-secenek<?= $kisi === 4 ? ' secili' : '' ?>" data-kisi="<?= $kisi ?>"><?= $kisi ?></button>
            <?php endfor; ?>
        </div>

        <div class="uyari uyari-bilgi kucuk-grup-notu gizli" data-rol="kucuk-grup-notu">
            <?= ikon('info') ?>
            <span>1-2 kişilik gruplar masa seçemez; aşağıdaki <b>Salon Girişi</b> ile yer ayırtabilirsiniz.
                  Masanız maç günü işletme tarafından gösterilir.</span>
        </div>

        <h2 class="bolum-baslik"><?= ikon('armchair') ?> Masanı seç</h2>
        <div class="kroki-aciklama">
            <span class="kroki-gosterge"><i class="gosterge gosterge-bos"></i> Boş</span>
            <span class="kroki-gosterge"><i class="gosterge gosterge-secili"></i> Seçimin</span>
            <span class="kroki-gosterge"><i class="gosterge gosterge-dolu"></i> Dolu</span>
            <span class="kroki-gosterge"><i class="gosterge gosterge-uygunsuz"></i> Grup şartına uymuyor</span>
        </div>
        <div class="kroki-tuval" data-rol="tuval">
            <div class="kroki-sahne">DEV EKRAN</div>
        </div>
        <p class="form-ipucu" data-rol="kroki-mesaj">Masalar yükleniyor…</p>

        <?php if ($salonKalan > 0 || (int) $mac['paylasimli_kontenjan'] > 0): ?>
        <div class="salon-kart" data-rol="salon-kart">
            <div class="salon-kart-metin">
                <h3><?= ikon('ticket') ?> Salon Girişi</h3>
                <p>Masa seçmeden yer ayırt; oturacağın yeri maç günü işletme gösterir.
                   Küçük gruplar (1-2 kişi) için idealdir.</p>
                <span class="rozet rozet-sari" data-rol="salon-kalan"><?= e((string) $salonKalan) ?> kişilik yer kaldı</span>
            </div>
            <button type="button" class="buton buton-ikincil" data-rol="salon-al"
                <?= $salonKalan <= 0 ? 'disabled' : '' ?>><?= ikon('ticket') ?> Salon Girişi Al</button>
        </div>
        <?php endif; ?>
    </div>

    <div class="secim-cubugu" data-rol="secim-cubugu">
        <div class="kap secim-cubugu-ic">
            <div class="secim-ozet">
                <b data-rol="ozet-baslik">Masa seçilmedi</b>
                <span class="metin-soluk" data-rol="ozet-detay">Krokiden boş bir masaya dokunun</span>
            </div>
            <div class="secim-tutar" data-rol="tutar"></div>
            <button type="button" class="buton buton-birincil" data-rol="devam" disabled>
                Devam Et <?= ikon('arrow-right') ?>
            </button>
        </div>
    </div>
</section>

<script src="/varliklar/js/kroki-secim.js"></script>
<script>krokiSecimBaslat(document.getElementById('masa-secim'));</script>
<?php endif; ?>
