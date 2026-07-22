/**
 * Kapı check-in ekranı: kamera ile QR okuma + manuel kod sorgulama.
 * Önce tarayıcının yerleşik BarcodeDetector'ı denenir (Android Chrome);
 * yoksa vendor'daki qr-scanner kütüphanesine düşülür (iOS Safari).
 */
function checkinBaslat(kok) {
    'use strict';

    var csrf = kok.dataset.csrf;
    var video = kok.querySelector('#kamera');
    var kameraKapali = kok.querySelector('[data-rol="kamera-kapali"]');
    var sonucKutusu = kok.querySelector('[data-rol="sonuc"]');
    var sayac = kok.querySelector('[data-rol="sayac"]');
    var macSecim = kok.querySelector('#mac-secim');

    var sonJeton = '';
    var sonZaman = 0;
    var mesgul = false;

    // ---- Sayaç ----
    function sayacGuncelle(ozet) {
        if (ozet) {
            sayac.textContent = ozet.giren_kisi + ' / ' + ozet.toplam_kisi;
            return;
        }
        fetch('/admin/api/check-in/' + macSecim.value + '/ozet')
            .then(function (c) { return c.json(); })
            .then(function (veri) { sayac.textContent = veri.giren_kisi + ' / ' + veri.toplam_kisi; })
            .catch(function () { sayac.textContent = '–'; });
    }
    macSecim.addEventListener('change', function () { sayacGuncelle(); });
    sayacGuncelle();

    // ---- Ses geri bildirimi (kapıda bakmadan anlamak için) ----
    function bip(basarili) {
        try {
            var ses = new (window.AudioContext || window.webkitAudioContext)();
            var osilator = ses.createOscillator();
            var kazanc = ses.createGain();
            osilator.frequency.value = basarili ? 880 : 220;
            kazanc.gain.value = 0.15;
            osilator.connect(kazanc).connect(ses.destination);
            osilator.start();
            osilator.stop(ses.currentTime + (basarili ? 0.12 : 0.4));
        } catch (hata) { /* ses desteklenmiyorsa sessiz devam */ }
    }

    // ---- Doğrulama isteği ----
    function dogrula(govdeEk) {
        if (mesgul) return;
        mesgul = true;
        var govde = Object.assign({ csrf_jetonu: csrf, mac_id: parseInt(macSecim.value, 10) }, govdeEk);
        fetch('/admin/api/check-in', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(govde)
        })
            .then(function (c) { return c.json(); })
            .then(function (veri) {
                mesgul = false;
                sonucGoster(veri);
                bip(veri.sonuc === 'basarili');
                if (veri.ozet) sayacGuncelle(veri.ozet);
            })
            .catch(function () {
                mesgul = false;
                sonucGoster({ sonuc: 'gecersiz', mesaj: 'Sunucuya ulaşılamadı.' });
            });
    }

    function sonucGoster(veri) {
        var sinif = veri.sonuc === 'basarili' ? 'checkin-ok'
            : veri.sonuc === 'zaten' ? 'checkin-tekrar' : 'checkin-hata';
        var baslikMetni = veri.sonuc === 'basarili' ? 'GİRİŞ ONAYLANDI'
            : veri.sonuc === 'zaten' ? 'TEKRAR OKUTULDU' : 'GİRİŞ REDDEDİLDİ';
        var satirlar = '';
        if (veri.ad)    satirlar += '<div class="checkin-satir"><b>' + kacis(veri.ad) + '</b></div>';
        if (veri.kisi)  satirlar += '<div class="checkin-satir">' + veri.kisi + ' kişi</div>';
        if (veri.yer)   satirlar += '<div class="checkin-satir">' + kacis(veri.yer) + '</div>';
        if (veri.paket) satirlar += '<div class="checkin-satir checkin-paket">' + kacis(veri.paket) + '</div>';
        if (veri.mesaj) satirlar += '<div class="checkin-satir">' + kacis(veri.mesaj) + '</div>';
        sonucKutusu.innerHTML = '<div class="checkin-kart ' + sinif + '">'
            + '<div class="checkin-kart-baslik">' + baslikMetni + '</div>' + satirlar + '</div>';
    }

    function jetonIsle(metin) {
        var simdi = Date.now();
        if (metin === sonJeton && simdi - sonZaman < 4000) return; // aynı QR'ı üst üste okuma
        sonJeton = metin;
        sonZaman = simdi;
        dogrula({ jeton: metin });
    }

    // ---- Manuel kod ----
    kok.querySelector('[data-rol="manuel-form"]').addEventListener('submit', function (olay) {
        olay.preventDefault();
        var girdi = kok.querySelector('[data-rol="manuel-kod"]');
        var kod = girdi.value.trim().toUpperCase();
        if (kod.length >= 6) {
            dogrula({ kod: kod });
            girdi.value = '';
        }
    });

    // ---- Kamera ----
    kok.querySelector('[data-rol="kamera-baslat"]').addEventListener('click', function () {
        kameraKapali.classList.add('gizli');
        video.classList.add('aktif');

        if ('BarcodeDetector' in window) {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function (akis) {
                    video.srcObject = akis;
                    video.play();
                    var okuyucu = new BarcodeDetector({ formats: ['qr_code'] });
                    setInterval(function () {
                        okuyucu.detect(video)
                            .then(function (kodlar) {
                                if (kodlar.length) jetonIsle(kodlar[0].rawValue);
                            })
                            .catch(function () { /* kare atlandı */ });
                    }, 400);
                })
                .catch(kameraHatasi);
        } else if (window.QrScanner) {
            window.QrScanner.WORKER_PATH = '/varliklar/js/qr-scanner-worker.min.js';
            var tarayici = new window.QrScanner(
                video,
                function (sonuc) { jetonIsle(typeof sonuc === 'string' ? sonuc : sonuc.data); },
                { preferredCamera: 'environment', highlightScanRegion: true }
            );
            tarayici.start().catch(kameraHatasi);
        } else {
            kameraHatasi();
        }
    });

    function kameraHatasi() {
        video.classList.remove('aktif');
        kameraKapali.classList.remove('gizli');
        sonucGoster({ sonuc: 'gecersiz', mesaj: 'Kamera açılamadı; kodu elle girebilirsiniz.' });
    }

    function kacis(metin) {
        var kutu = document.createElement('span');
        kutu.textContent = metin;
        return kutu.innerHTML;
    }
}
