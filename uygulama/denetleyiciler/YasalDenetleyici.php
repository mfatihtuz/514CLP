<?php

declare(strict_types=1);

/**
 * Yasal sayfalar: KVKK, mesafeli satış, ön bilgilendirme, iade koşulları.
 * İçerikler taslaktır; canlıya çıkmadan önce mali müşavir/avukat kontrolü
 * önerilir (docs/KURULUM.md). iyzico başvurusu bu sayfaların yayında
 * olmasını şart koşar.
 */
final class YasalDenetleyici
{
    private const SAYFALAR = [
        'kvkk'             => ['yasal/kvkk', 'KVKK Aydınlatma Metni'],
        'mesafeli-satis'   => ['yasal/mesafeli-satis', 'Mesafeli Satış Sözleşmesi'],
        'on-bilgilendirme' => ['yasal/on-bilgilendirme', 'Ön Bilgilendirme Formu'],
        'iade-kosullari'   => ['yasal/iade-kosullari', 'İptal ve İade Koşulları'],
    ];

    public function goster(array $parametreler): void
    {
        $sayfa = (string) ($parametreler['sayfa'] ?? '');
        if (!isset(self::SAYFALAR[$sayfa])) {
            http_response_code(404);
            Sablon::goster('hatalar/404', ['baslik' => 'Sayfa Bulunamadı']);
            return;
        }
        [$gorunum, $baslik] = self::SAYFALAR[$sayfa];
        Sablon::goster($gorunum, [
            'baslik'        => $baslik,
            'isletmeUnvani' => (string) Ayarlar::al('isletme_unvani', '') ?: '[İşletme ticari unvanı — Ayarlar ekranından girin]',
            'adres'         => (string) Ayarlar::al('iletisim_adres', '') ?: '[İşletme adresi — Ayarlar ekranından girin]',
            'telefon'       => (string) Ayarlar::al('iletisim_telefon', '') ?: '[Telefon]',
            'vergiBilgisi'  => (string) Ayarlar::al('vergi_bilgisi', '') ?: '[Vergi dairesi ve numarası]',
            'iptalSaat'     => Ayarlar::varsayilanIptalSaat(),
        ]);
    }
}
