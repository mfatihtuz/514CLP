<?php

declare(strict_types=1);

/**
 * Sahte banka ekranı — YALNIZ geliştirme (mock sağlayıcı aktifken).
 * Üretimde 404 döner; OdemeSecici zaten üretimde mock'u reddeder.
 */
final class MockOdemeDenetleyici
{
    public function sayfa(array $parametreler = []): void
    {
        $this->yalnizGelistirme();
        $konusmaKimligi = (string) ($_GET['kk'] ?? '');
        $odeme = Veritabani::satir('SELECT * FROM odemeler WHERE konusma_kimligi = ?', [$konusmaKimligi]);
        if ($odeme === null) {
            http_response_code(404);
            exit('Mock ödeme kaydı bulunamadı.');
        }

        Sablon::goster('odeme/mock-banka', [
            'baslik'          => 'Mock Banka',
            'odeme'           => $odeme,
            'konusmaKimligi'  => $konusmaKimligi,
        ], 'duzen/admin-giris'); // menüsüz sade düzen
    }

    public function sonuc(array $parametreler = []): void
    {
        $this->yalnizGelistirme();
        Guvenlik::csrfZorunlu();
        $konusmaKimligi = (string) ($_POST['kk'] ?? '');
        $eylem = (string) ($_POST['eylem'] ?? '');
        $odeme = Veritabani::satir('SELECT * FROM odemeler WHERE konusma_kimligi = ?', [$konusmaKimligi]);
        if ($odeme === null || !in_array($eylem, ['onayla', 'reddet', 'onayla_callback_kayip'], true)) {
            http_response_code(422);
            exit('Geçersiz mock işlem.');
        }

        // "Banka" sonucu kaydeder — odemeSorgula() bunu okuyacak
        $ham = json_decode((string) ($odeme['ham_cevap'] ?? ''), true) ?: [];
        $ham['mock_sonuc'] = $eylem === 'reddet' ? 'basarisiz' : 'basarili';
        Veritabani::calistir(
            'UPDATE odemeler SET ham_cevap = ?, guncelleme_zamani = ? WHERE id = ?',
            [json_encode($ham, JSON_UNESCAPED_UNICODE), simdiUtc(), $odeme['id']]
        );

        if ($eylem === 'onayla_callback_kayip') {
            // Callback kaybı simülasyonu: banka onayladı ama site haber almadı.
            // Reconciliation (cron) bu ödemeyi kurtarmalı.
            exit('<meta charset="utf-8"><body style="font-family:sans-serif;padding:2rem">
                  <h2>Callback kaybı simüle edildi</h2>
                  <p>Ödeme "bankada" onaylandı ama siteye dönüş yapılmadı.
                  <br>Kurtarma testi: <code>php betikler/cron-temizlik.php</code></p></body>');
        }

        // Gerçek banka davranışı: müşterinin tarayıcısı callback'e form POST'lar
        echo '<!doctype html><meta charset="utf-8"><body onload="document.forms[0].submit()">
              <form method="post" action="/odeme/geri-donus">
              <input type="hidden" name="token" value="' . e($konusmaKimligi) . '">
              </form>Bankadan siteye dönülüyor…</body>';
    }

    private function yalnizGelistirme(): void
    {
        if (Cevre::al('ORTAM') === 'uretim' || Cevre::al('ODEME_SAGLAYICI', 'mock') !== 'mock') {
            http_response_code(404);
            exit;
        }
    }
}
