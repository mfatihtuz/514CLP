<?php

declare(strict_types=1);

/**
 * Ortam değişkenine göre aktif ödeme sağlayıcısını kurar.
 * ODEME_SAGLAYICI=mock | iyzico
 *
 * GÜVENLİK: üretimde mock kesinlikle çalışmaz (uygulama/baslat.php açılışta
 * durdurur; buradaki kontrol ikinci emniyet kemeridir).
 */
final class OdemeSecici
{
    public static function olustur(): OdemeSaglayici
    {
        $secim = strtolower((string) (Cevre::al('ODEME_SAGLAYICI', 'mock') ?? 'mock'));

        if ($secim === 'mock') {
            if (Cevre::al('ORTAM') === 'uretim') {
                throw new RuntimeException('GÜVENLİK: üretim ortamında mock ödeme kullanılamaz.');
            }
            return new MockSaglayici();
        }

        if ($secim === 'iyzico') {
            return new IyzicoSaglayici();
        }

        throw new RuntimeException("Bilinmeyen ödeme sağlayıcısı: {$secim}");
    }
}
