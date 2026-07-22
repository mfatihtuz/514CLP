/**
 * Müşteri masa seçimi — sinema mantığı.
 *
 * Kurallar (sunucu son sözü söyler; burası yalnız kullanıcı deneyimi):
 *  - Masa yalnız 'bos' ise ve grup, masanın minimum şartını karşılıyorsa seçilebilir.
 *  - Birden çok masa seçilebilir (kalabalık gruplar): toplam minimum <= kişi <= toplam kapasite.
 *  - Kroki 10 saniyede bir tazelenir; seçili masa başkası tarafından kapılırsa kullanıcı uyarılır.
 */
function krokiSecimBaslat(kok) {
    'use strict';

    var GRID_G = 24, GRID_Y = 16;
    var macId = kok.dataset.macId;
    var fiyatKurus = parseInt(kok.dataset.fiyatKurus, 10) || 0;
    var csrf = kok.dataset.csrf;

    var tuval = kok.querySelector('[data-rol="tuval"]');
    var mesajEl = kok.querySelector('[data-rol="kroki-mesaj"]');
    var cubuk = kok.querySelector('[data-rol="secim-cubugu"]');
    var ozetBaslik = kok.querySelector('[data-rol="ozet-baslik"]');
    var ozetDetay = kok.querySelector('[data-rol="ozet-detay"]');
    var tutarEl = kok.querySelector('[data-rol="tutar"]');
    var devamButonu = kok.querySelector('[data-rol="devam"]');
    var salonKart = kok.querySelector('[data-rol="salon-kart"]');
    var kucukGrupNotu = kok.querySelector('[data-rol="kucuk-grup-notu"]');

    var masalar = [];
    var seciliIdler = [];
    var kisi = 4;
    var gonderiliyor = false;

    // ---- Kişi seçici ----
    kok.querySelectorAll('[data-kisi]').forEach(function (buton) {
        buton.addEventListener('click', function () {
            kok.querySelectorAll('[data-kisi]').forEach(function (b) { b.classList.remove('secili'); });
            buton.classList.add('secili');
            kisi = parseInt(buton.dataset.kisi, 10);
            secimleriAyikla();
            ciz();
        });
    });

    // ---- Veri ----
    function yukle() {
        fetch('/api/maclar/' + macId + '/kroki', { headers: { 'Accept': 'application/json' } })
            .then(function (c) { return c.json(); })
            .then(function (veri) {
                masalar = veri.masalar || [];
                if (salonKart) {
                    var kalanEl = salonKart.querySelector('[data-rol="salon-kalan"]');
                    var alButonu = salonKart.querySelector('[data-rol="salon-al"]');
                    if (kalanEl) kalanEl.textContent = veri.salonKalan + ' kişilik yer kaldı';
                    if (alButonu) alButonu.disabled = veri.salonKalan <= 0;
                    salonKart.classList.toggle('gizli', veri.salonKalan <= 0);
                }
                if (!veri.satisAcik) {
                    mesajEl.textContent = 'Satış kapandı; sayfayı yenileyin.';
                    return;
                }
                secimleriAyikla(true);
                mesajEl.textContent = masalar.length
                    ? 'Masaya dokunarak seçin; yeşil çerçeveli masalar grubunuza uygundur.'
                    : 'Bu maç için masa krokisi tanımlanmamış.';
                ciz();
            })
            .catch(function () { mesajEl.textContent = 'Kroki yüklenemedi; bağlantınızı kontrol edin.'; });
    }

    function masaUygunMu(masa) {
        return masa.durum === 'bos' && kisi >= masa.min_kisi;
    }

    /** Kişi sayısı değişince veya veri tazelenince geçersiz seçimleri düşürür. */
    function secimleriAyikla(dolulukKontrol) {
        var dusenler = [];
        seciliIdler = seciliIdler.filter(function (id) {
            var masa = masalar.find(function (m) { return m.id === id; });
            if (!masa) return false;
            if (dolulukKontrol && masa.durum !== 'bos') { dusenler.push(masa.ad); return false; }
            if (!masaUygunMu(masa)) return false;
            return true;
        });
        if (dusenler.length) {
            mesajEl.textContent = dusenler.join(', ') + ' masası az önce doldu; lütfen başka masa seçin.';
        }
        ozetGuncelle();
    }

    // ---- Çizim ----
    function ciz() {
        tuval.querySelectorAll('.kroki-masa').forEach(function (el) { el.remove(); });
        masalar.forEach(function (masa) {
            var el = document.createElement('button');
            el.type = 'button';
            var secili = seciliIdler.indexOf(masa.id) !== -1;
            var siniflar = ['kroki-masa'];
            if (masa.sekil === 'yuvarlak') siniflar.push('kroki-masa-yuvarlak');
            if (masa.durum === 'dolu') siniflar.push('kroki-masa-rezerve');
            else if (masa.durum === 'kapali') siniflar.push('kroki-masa-kapali');
            else if (secili) siniflar.push('secili');
            else if (!masaUygunMu(masa)) siniflar.push('kroki-masa-uygunsuz');
            else siniflar.push('kroki-masa-secilebilir');
            el.className = siniflar.join(' ');
            el.style.left = (masa.konum_x / GRID_G * 100) + '%';
            el.style.top = (masa.konum_y / GRID_Y * 100) + '%';
            el.style.width = (masa.genislik / GRID_G * 100) + '%';
            el.style.height = (masa.yukseklik / GRID_Y * 100) + '%';
            el.innerHTML = '<b>' + kacis(masa.ad) + '</b><span>' + masa.kapasite + ' kişi</span>' +
                (masa.durum === 'dolu' ? '<i>DOLU</i>' :
                 masa.durum === 'kapali' ? '<i>KAPALI</i>' :
                 (kisi < masa.min_kisi ? '<i>EN AZ ' + masa.min_kisi + ' KİŞİ</i>' : ''));
            if (masa.durum === 'bos' && masaUygunMu(masa)) {
                el.addEventListener('click', function () { masaSecim(masa.id); });
            } else {
                el.disabled = masa.durum !== 'bos';
            }
            tuval.appendChild(el);
        });
    }

    function masaSecim(id) {
        var yeri = seciliIdler.indexOf(id);
        if (yeri === -1) {
            if (seciliIdler.length >= 5) {
                mesajEl.textContent = 'En fazla 5 masa seçebilirsiniz.';
                return;
            }
            seciliIdler.push(id);
        } else {
            seciliIdler.splice(yeri, 1);
        }
        ozetGuncelle();
        ciz();
    }

    function ozetGuncelle() {
        var secilenler = masalar.filter(function (m) { return seciliIdler.indexOf(m.id) !== -1; });
        var toplamKapasite = secilenler.reduce(function (t, m) { return t + m.kapasite; }, 0);
        var toplamMin = secilenler.reduce(function (t, m) { return t + m.min_kisi; }, 0);

        kucukGrupNotu && kucukGrupNotu.classList.toggle('gizli', kisi > 2);

        if (!secilenler.length) {
            ozetBaslik.textContent = 'Masa seçilmedi';
            ozetDetay.textContent = kisi <= 2
                ? 'Küçük gruplar için Salon Girişi kullanılabilir'
                : 'Krokiden boş bir masaya dokunun';
            tutarEl.textContent = '';
            devamButonu.disabled = true;
            cubuk.classList.remove('aktif');
            return;
        }

        cubuk.classList.add('aktif');
        ozetBaslik.textContent = secilenler.map(function (m) { return m.ad; }).join(' + ') +
            ' (' + toplamKapasite + ' kişilik)';

        var uygun = kisi <= toplamKapasite && kisi >= toplamMin;
        if (kisi > toplamKapasite) {
            ozetDetay.textContent = kisi + ' kişi için kapasite yetersiz; bir masa daha ekleyin.';
        } else if (kisi < toplamMin) {
            ozetDetay.textContent = 'Bu seçim en az ' + toplamMin + ' kişi gerektirir; masa azaltın.';
        } else {
            ozetDetay.textContent = kisi + ' kişi için uygun.';
        }
        tutarEl.textContent = fiyatKurus > 0 ? tutarBicimle(kisi * fiyatKurus) : 'Ücretsiz';
        devamButonu.disabled = !uygun;
    }

    // ---- Hold ----
    devamButonu.addEventListener('click', function () {
        holdGonder({ tur: 'masa', masa_idler: seciliIdler });
    });

    var salonButonu = kok.querySelector('[data-rol="salon-al"]');
    if (salonButonu) {
        salonButonu.addEventListener('click', function () {
            holdGonder({ tur: 'salon' });
        });
    }

    function holdGonder(ekstra) {
        if (gonderiliyor) return;
        gonderiliyor = true;
        devamButonu.disabled = true;
        var govde = Object.assign({ csrf_jetonu: csrf, mac_id: parseInt(macId, 10), kisi_sayisi: kisi }, ekstra);
        fetch('/api/hold', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(govde)
        })
            .then(function (c) { return c.json().then(function (v) { return { ok: c.ok, veri: v }; }); })
            .then(function (sonuc) {
                if (sonuc.ok && sonuc.veri.tamam) {
                    window.location.href = sonuc.veri.yonlendir;
                    return;
                }
                gonderiliyor = false;
                mesajEl.textContent = sonuc.veri.hata || 'Masa tutulamadı; lütfen tekrar deneyin.';
                yukle(); // güncel krokiyi çek (kapılan masa dolu görünsün)
            })
            .catch(function () {
                gonderiliyor = false;
                mesajEl.textContent = 'Sunucuya ulaşılamadı; bağlantınızı kontrol edin.';
                devamButonu.disabled = false;
            });
    }

    // ---- Yardımcılar ----
    function tutarBicimle(kurus) {
        var tl = Math.floor(kurus / 100);
        var kalan = kurus % 100;
        var metin = tl.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return (kalan ? metin + ',' + String(kalan).padStart(2, '0') : metin) + ' TL';
    }

    function kacis(metin) {
        var kutu = document.createElement('span');
        kutu.textContent = metin;
        return kutu.innerHTML;
    }

    yukle();
    setInterval(yukle, 10000);   // canlı doluluk: 10 saniyede bir tazele
}
