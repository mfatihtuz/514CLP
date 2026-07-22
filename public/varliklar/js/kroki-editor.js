/**
 * Kroki Editörü — sürükle-bırak masa yerleşimi (kütüphanesiz, pointer events).
 *
 * 24x16 sanal grid; masalar hücre koordinatlarıyla tutulur, % ile çizilir.
 * İki mod:
 *   ana : ana kat planı — serbest ekle/düzenle/sil
 *   mac : maça özel kroki — silme yok (kapat/aç), rezerve-tutulu masada yalnız konum değişir
 *
 * Kullanım: krokiEditorBaslat(kokElemani)
 *   data-veri-url, data-kaydet-url, data-mod, data-csrf, [data-masa-durum-url]
 */
function krokiEditorBaslat(kok) {
    'use strict';

    var GRID_G = 24, GRID_Y = 16;
    var mod = kok.dataset.mod || 'ana';
    var csrf = kok.dataset.csrf || '';
    var masalar = [];
    var seciliSira = -1;
    var kirliMi = false;
    var geciciSayac = 0;

    // ---- İskelet ----
    kok.classList.add('kroki-editor');
    kok.innerHTML =
        '<div class="kroki-arac-cubugu">' +
        '  <div class="kroki-arac-sol">' +
        '    <span class="form-etiket">Masa ekle:</span>' +
        '    <button type="button" class="buton buton-koyu-hayalet buton-kucuk" data-ekle="2">2 kişilik</button>' +
        '    <button type="button" class="buton buton-koyu-hayalet buton-kucuk" data-ekle="4">4 kişilik</button>' +
        '    <button type="button" class="buton buton-koyu-hayalet buton-kucuk" data-ekle="6">6 kişilik</button>' +
        '    <button type="button" class="buton buton-koyu-hayalet buton-kucuk" data-ekle="8">8 kişilik</button>' +
        '  </div>' +
        '  <div class="kroki-arac-sag">' +
        '    <span class="kroki-kirli gizli">Kaydedilmemiş değişiklik var</span>' +
        '    <button type="button" class="buton buton-birincil buton-kucuk" data-eylem="kaydet">Kaydet</button>' +
        '  </div>' +
        '</div>' +
        '<div class="kroki-hatalar uyari uyari-hata gizli"></div>' +
        '<div class="kroki-govde">' +
        '  <div class="kroki-tuval" data-rol="tuval"><div class="kroki-sahne">SAHNE / EKRAN</div></div>' +
        '  <div class="kroki-panel" data-rol="panel">' +
        '    <p class="metin-soluk kroki-panel-bos">Düzenlemek için bir masa seçin.</p>' +
        '    <div class="kroki-panel-form gizli">' +
        '      <div class="form-alan"><label class="form-etiket">Masa adı</label>' +
        '        <input class="form-girdi" data-alan="ad" maxlength="12"></div>' +
        '      <div class="kroki-panel-ikili">' +
        '        <div class="form-alan"><label class="form-etiket">Kapasite</label>' +
        '          <input class="form-girdi" data-alan="kapasite" type="number" min="1" max="12"></div>' +
        '        <div class="form-alan"><label class="form-etiket">En az kişi</label>' +
        '          <input class="form-girdi" data-alan="min_kisi" type="number" min="1" max="12"></div>' +
        '      </div>' +
        '      <div class="kroki-panel-ikili">' +
        '        <div class="form-alan"><label class="form-etiket">Şekil</label>' +
        '          <select class="form-secim" data-alan="sekil">' +
        '            <option value="kare">Köşeli</option><option value="yuvarlak">Yuvarlak</option>' +
        '          </select></div>' +
        '        <div class="form-alan"><label class="form-etiket">Boyut (en x boy)</label>' +
        '          <div class="kroki-boyut">' +
        '            <input class="form-girdi" data-alan="g" type="number" min="2" max="6">' +
        '            <span>x</span>' +
        '            <input class="form-girdi" data-alan="yk" type="number" min="2" max="6">' +
        '          </div></div>' +
        '      </div>' +
        '      <p class="form-ipucu kroki-panel-durum"></p>' +
        '      <div class="kroki-panel-eylemler">' +
        '        <button type="button" class="buton buton-koyu-hayalet buton-kucuk gizli" data-eylem="sil">Masayı Sil</button>' +
        '        <button type="button" class="buton buton-ikincil buton-kucuk gizli" data-eylem="kapat">Masayı Kapat</button>' +
        '        <button type="button" class="buton buton-ikincil buton-kucuk gizli" data-eylem="ac">Masayı Aç</button>' +
        '      </div>' +
        '    </div>' +
        '  </div>' +
        '</div>' +
        '<div class="kroki-bilgi metin-soluk">Masaları sürükleyerek taşıyın; en yakın hücreye oturur. ' +
        (mod === 'mac'
            ? 'Rezervasyonlu masalar yalnızca taşınabilir; silme yerine kapatma kullanılır.'
            : 'Bu plan yeni maç yayınlanırken maça kopyalanır.') +
        '</div>';

    var tuval = kok.querySelector('[data-rol="tuval"]');
    var panel = kok.querySelector('[data-rol="panel"]');
    var panelForm = panel.querySelector('.kroki-panel-form');
    var panelBos = panel.querySelector('.kroki-panel-bos');
    var hataKutusu = kok.querySelector('.kroki-hatalar');
    var kirliEtiket = kok.querySelector('.kroki-kirli');

    // ---- Veri ----
    function normallestir(satir) {
        return {
            id: satir.id !== undefined && satir.id !== null ? satir.id : null,
            geciciId: 'g' + (++geciciSayac),
            ad: String(satir.ad || ''),
            kapasite: parseInt(satir.kapasite, 10) || 4,
            min_kisi: parseInt(satir.min_kisi, 10) || 1,
            sekil: satir.sekil === 'yuvarlak' ? 'yuvarlak' : 'kare',
            x: parseInt(satir.konum_x !== undefined ? satir.konum_x : satir.x, 10) || 0,
            y: parseInt(satir.konum_y !== undefined ? satir.konum_y : satir.y, 10) || 0,
            g: parseInt(satir.genislik !== undefined ? satir.genislik : satir.g, 10) || 2,
            yk: parseInt(satir.yukseklik !== undefined ? satir.yukseklik : satir.yk, 10) || 2,
            durum: String(satir.durum || 'bos')
        };
    }

    function yukle() {
        fetch(kok.dataset.veriUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (cevap) { return cevap.json(); })
            .then(function (veri) {
                masalar = (veri.masalar || []).map(normallestir);
                seciliSira = -1;
                kirliYap(false);
                ciz();
            })
            .catch(function () { hataGoster(['Kroki verisi yüklenemedi. Sayfayı yenileyin.']); });
    }

    function kilitliMi(masa) {
        return mod === 'mac' && masa.id !== null && (masa.durum === 'rezerve' || masa.durum === 'tutuldu');
    }

    // ---- Çizim ----
    function ciz() {
        tuval.querySelectorAll('.kroki-masa').forEach(function (el) { el.remove(); });
        masalar.forEach(function (masa, sira) {
            var el = document.createElement('div');
            el.className = 'kroki-masa kroki-masa-' + masa.durum +
                (masa.sekil === 'yuvarlak' ? ' kroki-masa-yuvarlak' : '') +
                (sira === seciliSira ? ' secili' : '') +
                (kilitliMi(masa) ? ' kilitli' : '');
            el.style.left = (masa.x / GRID_G * 100) + '%';
            el.style.top = (masa.y / GRID_Y * 100) + '%';
            el.style.width = (masa.g / GRID_G * 100) + '%';
            el.style.height = (masa.yk / GRID_Y * 100) + '%';
            el.innerHTML = '<b>' + kacis(masa.ad || '?') + '</b><span>' + masa.kapasite + ' kişi</span>' +
                (masa.durum === 'rezerve' ? '<i>DOLU</i>' :
                 masa.durum === 'tutuldu' ? '<i>BEKLETME</i>' :
                 masa.durum === 'kapali' ? '<i>KAPALI</i>' : '');
            el.dataset.sira = String(sira);
            tuval.appendChild(el);
        });
    }

    function kacis(metin) {
        var kutu = document.createElement('span');
        kutu.textContent = metin;
        return kutu.innerHTML;
    }

    // ---- Seçim + panel ----
    function sec(sira) {
        seciliSira = sira;
        ciz();
        if (sira < 0) {
            panelForm.classList.add('gizli');
            panelBos.classList.remove('gizli');
            return;
        }
        var masa = masalar[sira];
        panelBos.classList.add('gizli');
        panelForm.classList.remove('gizli');

        ['ad', 'kapasite', 'min_kisi', 'sekil', 'g', 'yk'].forEach(function (alan) {
            var girdi = panelForm.querySelector('[data-alan="' + alan + '"]');
            girdi.value = masa[alan];
            girdi.disabled = kilitliMi(masa);
        });

        var durumYazi = panelForm.querySelector('.kroki-panel-durum');
        durumYazi.textContent = kilitliMi(masa)
            ? 'Bu masada aktif rezervasyon/bekletme var: yalnızca sürükleyerek taşıyabilirsiniz.'
            : (mod === 'mac' && masa.id === null ? 'Bu maça özel eklenen ekstra masa (kaydedilmemiş).' : '');

        var silButonu = panelForm.querySelector('[data-eylem="sil"]');
        var kapatButonu = panelForm.querySelector('[data-eylem="kapat"]');
        var acButonu = panelForm.querySelector('[data-eylem="ac"]');
        silButonu.classList.add('gizli');
        kapatButonu.classList.add('gizli');
        acButonu.classList.add('gizli');

        if (mod === 'ana' || masa.id === null) {
            silButonu.classList.remove('gizli');
        } else if (masa.durum === 'bos') {
            kapatButonu.classList.remove('gizli');
        } else if (masa.durum === 'kapali') {
            acButonu.classList.remove('gizli');
        }
    }

    panelForm.addEventListener('input', function (olay) {
        var alan = olay.target.dataset.alan;
        if (!alan || seciliSira < 0) return;
        var masa = masalar[seciliSira];
        if (kilitliMi(masa)) return;
        if (alan === 'ad' || alan === 'sekil') {
            masa[alan] = olay.target.value;
        } else {
            masa[alan] = parseInt(olay.target.value, 10) || 0;
        }
        if (alan === 'kapasite' && masa.min_kisi > masa.kapasite) {
            masa.min_kisi = masa.kapasite;
        }
        kirliYap(true);
        ciz();
    });

    // ---- Sürükleme ----
    var surukleme = null;

    tuval.addEventListener('pointerdown', function (olay) {
        var masaEl = olay.target.closest('.kroki-masa');
        if (!masaEl) { sec(-1); return; }
        var sira = parseInt(masaEl.dataset.sira, 10);
        sec(sira);
        olay.preventDefault();
        var kutu = tuval.getBoundingClientRect();
        surukleme = {
            sira: sira,
            baslangicX: olay.clientX,
            baslangicY: olay.clientY,
            eskiX: masalar[sira].x,
            eskiY: masalar[sira].y,
            hucreW: kutu.width / GRID_G,
            hucreH: kutu.height / GRID_Y,
            el: masaEl
        };
        masaEl.setPointerCapture(olay.pointerId);
        masaEl.classList.add('suruklenen');
    });

    tuval.addEventListener('pointermove', function (olay) {
        if (!surukleme) return;
        var masa = masalar[surukleme.sira];
        var deltaX = (olay.clientX - surukleme.baslangicX) / surukleme.hucreW;
        var deltaY = (olay.clientY - surukleme.baslangicY) / surukleme.hucreH;
        var yeniX = sinirla(Math.round(surukleme.eskiX + deltaX), 0, GRID_G - masa.g);
        var yeniY = sinirla(Math.round(surukleme.eskiY + deltaY), 0, GRID_Y - masa.yk);
        surukleme.el.style.left = (yeniX / GRID_G * 100) + '%';
        surukleme.el.style.top = (yeniY / GRID_Y * 100) + '%';
        surukleme.sonX = yeniX;
        surukleme.sonY = yeniY;
    });

    function suruklemeBitir() {
        if (!surukleme) return;
        var masa = masalar[surukleme.sira];
        var hedefX = surukleme.sonX !== undefined ? surukleme.sonX : masa.x;
        var hedefY = surukleme.sonY !== undefined ? surukleme.sonY : masa.y;
        var eskiX = masa.x, eskiY = masa.y;
        masa.x = hedefX; masa.y = hedefY;

        if (cakismaVarMi(surukleme.sira)) {
            masa.x = eskiX; masa.y = eskiY;
            surukleme.el.classList.add('sarsinti');
        } else if (hedefX !== eskiX || hedefY !== eskiY) {
            kirliYap(true);
        }
        surukleme.el.classList.remove('suruklenen');
        surukleme = null;
        ciz();
    }
    tuval.addEventListener('pointerup', suruklemeBitir);
    tuval.addEventListener('pointercancel', suruklemeBitir);

    function sinirla(deger, enAz, enCok) {
        return Math.max(enAz, Math.min(enCok, deger));
    }

    function cakismaVarMi(sira) {
        var a = masalar[sira];
        return masalar.some(function (b, i) {
            if (i === sira) return false;
            return a.x < b.x + b.g && b.x < a.x + a.g && a.y < b.y + b.yk && b.y < a.y + a.yk;
        });
    }

    // ---- Masa ekleme ----
    var boyutlar = { 2: [2, 2], 4: [3, 2], 6: [4, 2], 8: [4, 3] };

    function masaEkle(kapasite) {
        var boyut = boyutlar[kapasite] || [3, 2];
        var yeni = {
            id: null,
            geciciId: 'g' + (++geciciSayac),
            ad: bosMasaAdi(),
            kapasite: kapasite,
            min_kisi: Math.max(1, kapasite - 1),
            sekil: 'kare',
            x: 0, y: 0, g: boyut[0], yk: boyut[1],
            durum: 'bos'
        };
        var yer = bosYerBul(yeni.g, yeni.yk);
        yeni.x = yer[0]; yeni.y = yer[1];
        masalar.push(yeni);
        kirliYap(true);
        sec(masalar.length - 1);
    }

    function bosMasaAdi() {
        var no = 1;
        while (masalar.some(function (m) { return m.ad.toUpperCase() === 'M' + no; })) no++;
        return 'M' + no;
    }

    function bosYerBul(g, yk) {
        for (var y = 0; y <= GRID_Y - yk; y++) {
            for (var x = 0; x <= GRID_G - g; x++) {
                var deneme = { x: x, y: y, g: g, yk: yk };
                var carpisma = masalar.some(function (b) {
                    return deneme.x < b.x + b.g && b.x < deneme.x + deneme.g &&
                           deneme.y < b.y + b.yk && b.y < deneme.y + deneme.yk;
                });
                if (!carpisma) return [x, y];
            }
        }
        return [0, 0];
    }

    // ---- Eylemler ----
    kok.addEventListener('click', function (olay) {
        var ekle = olay.target.closest('[data-ekle]');
        if (ekle) { masaEkle(parseInt(ekle.dataset.ekle, 10)); return; }

        var eylemEl = olay.target.closest('[data-eylem]');
        if (!eylemEl) return;
        var eylem = eylemEl.dataset.eylem;

        if (eylem === 'kaydet') { kaydet(); }
        if (eylem === 'sil' && seciliSira >= 0) {
            masalar.splice(seciliSira, 1);
            kirliYap(true);
            sec(-1);
        }
        if ((eylem === 'kapat' || eylem === 'ac') && seciliSira >= 0) {
            masaDurumDegistir(masalar[seciliSira], eylem);
        }
    });

    function masaDurumDegistir(masa, eylem) {
        if (masa.id === null) return;
        var url = (kok.dataset.masaDurumUrl || '').replace('{id}', masa.id);
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_jetonu: csrf, eylem: eylem })
        })
            .then(function (cevap) { return cevap.json().then(function (v) { return { ok: cevap.ok, veri: v }; }); })
            .then(function (sonuc) {
                if (!sonuc.ok) { hataGoster(sonuc.veri.hatalar || ['İşlem yapılamadı.']); return; }
                masa.durum = eylem === 'kapat' ? 'kapali' : 'bos';
                hataGizle();
                sec(seciliSira);
            })
            .catch(function () { hataGoster(['Sunucuya ulaşılamadı.']); });
    }

    function kaydet() {
        if (kaydet.calisiyor) return;
        kaydet.calisiyor = true;
        var govde = {
            csrf_jetonu: csrf,
            masalar: masalar.map(function (m) {
                return {
                    id: m.id, ad: m.ad, kapasite: m.kapasite, min_kisi: m.min_kisi,
                    sekil: m.sekil, konum_x: m.x, konum_y: m.y, genislik: m.g, yukseklik: m.yk
                };
            })
        };
        fetch(kok.dataset.kaydetUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(govde)
        })
            .then(function (cevap) { return cevap.json().then(function (v) { return { ok: cevap.ok, veri: v }; }); })
            .then(function (sonuc) {
                kaydet.calisiyor = false;
                if (!sonuc.ok || !sonuc.veri.tamam) {
                    hataGoster(sonuc.veri.hatalar || ['Kaydedilemedi.']);
                    return;
                }
                masalar = (sonuc.veri.masalar || []).map(normallestir);
                hataGizle();
                kirliYap(false);
                sec(-1);
            })
            .catch(function () {
                kaydet.calisiyor = false;
                hataGoster(['Sunucuya ulaşılamadı; kaydedilemedi.']);
            });
    }

    // ---- Küçük yardımcılar ----
    function kirliYap(deger) {
        kirliMi = deger;
        kirliEtiket.classList.toggle('gizli', !deger);
    }

    function hataGoster(hatalar) {
        hataKutusu.innerHTML = hatalar.map(function (h) { return '<div>' + kacis(h) + '</div>'; }).join('');
        hataKutusu.classList.remove('gizli');
    }

    function hataGizle() {
        hataKutusu.classList.add('gizli');
    }

    window.addEventListener('beforeunload', function (olay) {
        if (kirliMi) { olay.preventDefault(); olay.returnValue = ''; }
    });

    yukle();
}
