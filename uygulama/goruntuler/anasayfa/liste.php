<?php /** @var array<int, array<string, mixed>> $maclar */ ?>

<section class="kahraman">
    <div class="kap">
        <span class="kahraman-ust"><?= ikon('tv') ?> Dev ekranda canlı maç keyfi</span>
        <h1>Maçı <em>masandan</em> izle, yerini şimdiden ayırt</h1>
        <p class="kahraman-alt">
            <?= e(Ayarlar::restoranAdi()) ?>'nın üst katında büyük maçlar dev ekranda.
            Sinema bileti alır gibi masanı kendin seç; menün ve sınırsız çayın fiyata dahil,
            maç gecesi yerin hazır olsun.
        </p>
        <div class="kahraman-eylemler">
            <a class="buton buton-birincil" href="#maclar"><?= ikon('ticket') ?> Masanı Ayırt</a>
            <a class="buton buton-hayalet" href="#nasil-calisir"><?= ikon('chevron-down') ?> Nasıl çalışır?</a>
        </div>
    </div>
</section>

<section class="bolum" id="maclar">
    <div class="kap">
        <h2 class="bolum-baslik"><?= ikon('calendar') ?> Yaklaşan Maçlar</h2>

        <?php if ($maclar === []): ?>
            <div class="bos-durum">
                <?= ikon('timer') ?>
                <h3>Satış birazdan başlıyor</h3>
                <p>Yaklaşan maçların rezervasyonu henüz açılmadı. Kısa süre sonra tekrar kontrol edin.</p>
            </div>
        <?php else: ?>
            <div class="mac-listesi">
                <?php foreach ($maclar as $mac): ?>
                    <article class="mac-kart">
                        <div class="mac-kart-ust">
                            <span class="mac-kart-tarih"><?= ikon('calendar') ?> <?= e(macZamaniBicimle((string) $mac['baslangic_zamani'])) ?></span>
                            <?php if ((int) $mac['kisi_basi_fiyat_kurus'] === 0): ?>
                                <span class="rozet rozet-sari">Ücretsiz</span>
                            <?php endif; ?>
                        </div>
                        <div class="mac-kart-govde">
                            <div class="mac-eslesme">
                                <div class="mac-takim">
                                    <span class="takim-arma"><img src="/<?= e((string) $mac['ev_arma']) ?>" alt="<?= e((string) $mac['ev_ad']) ?> arması"></span>
                                    <span class="mac-takim-ad"><?= e((string) $mac['ev_ad']) ?></span>
                                </div>
                                <span class="mac-vs">VS</span>
                                <div class="mac-takim">
                                    <span class="takim-arma"><img src="/<?= e((string) $mac['dep_arma']) ?>" alt="<?= e((string) $mac['dep_ad']) ?> arması"></span>
                                    <span class="mac-takim-ad"><?= e((string) $mac['dep_ad']) ?></span>
                                </div>
                            </div>
                            <div class="mac-kart-detay">
                                <span class="yatay-orta"><?= ikon('utensils') ?> Menü + sınırsız çay dahil</span>
                                <span class="mac-fiyat">
                                    <?php if ((int) $mac['kisi_basi_fiyat_kurus'] > 0): ?>
                                        <b><?= e(kurusBicimle((int) $mac['kisi_basi_fiyat_kurus'])) ?></b>
                                        <span>kişi başı</span>
                                    <?php else: ?>
                                        <b>Ücretsiz</b>
                                        <span>giriş</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="mac-kart-alt">
                            <a class="buton buton-birincil buton-genis" href="/mac/<?= e((string) $mac['id']) ?>">
                                <?= ikon('armchair') ?> Masa Seç
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="bolum" id="nasil-calisir">
    <div class="kap">
        <h2 class="bolum-baslik"><?= ikon('info') ?> Nasıl Çalışır?</h2>
        <div class="adimlar">
            <div class="adim">
                <span class="adim-no">1</span>
                <h3>Maçını seç</h3>
                <p>Yaklaşan maçlar arasından izlemek istediğini seç.</p>
            </div>
            <div class="adim">
                <span class="adim-no">2</span>
                <h3>Masanı ayırt</h3>
                <p>Kat planından boş masalardan birini kendin seç; kaç kişi geleceğini belirt.</p>
            </div>
            <div class="adim">
                <span class="adim-no">3</span>
                <h3>Ödemeni yap</h3>
                <p>Güvenli online ödeme ile rezervasyonun kesinleşsin. Menün fiyata dahil.</p>
            </div>
            <div class="adim">
                <span class="adim-no">4</span>
                <h3>QR ile giriş yap</h3>
                <p>E-postana gelen QR kodla maç gecesi kapıda bekletilmeden içeri gir.</p>
            </div>
        </div>
    </div>
</section>
