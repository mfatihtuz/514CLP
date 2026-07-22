<?php

declare(strict_types=1);

/**
 * iyzico CheckoutForm entegrasyonu (SDK'sız, REST + IYZWSv2 HMAC imza).
 *
 * Akış: initialize → müşteri iyzico ödeme sayfasına gider (3DS orada) →
 * iyzico callbackUrl'e token POST'lar → retrieve ile sonuç doğrulanır.
 *
 * ÖNEMLİ: Bu sınıf sanal POS anahtarları alındığında sandbox'ta uçtan uca
 * test EDİLMEDEN canlıya alınmaz (docs/ODEME-AKISI.md). Test kartları:
 * https://docs.iyzico.com/ek-servisler/test-kartlari
 *
 * .env: IYZICO_API_ANAHTARI, IYZICO_GIZLI_ANAHTAR,
 *       IYZICO_TEMEL_URL (sandbox: https://sandbox-api.iyzipay.com)
 */
final class IyzicoSaglayici implements OdemeSaglayici
{
    public function ad(): string
    {
        return 'iyzico';
    }

    public function odemeBaslat(array $rezervasyonDetay, string $konusmaKimligi, string $donusUrl): array
    {
        $tutarOndalik = kurusOndalikMetin((int) $rezervasyonDetay['toplam_tutar_kurus']);
        [$ad, $soyad] = $this->adSoyadAyir((string) $rezervasyonDetay['ad_soyad']);

        $macBasligi = $rezervasyonDetay['ev_ad'] . ' - ' . $rezervasyonDetay['dep_ad'] . ' maç günü rezervasyonu';

        $govde = [
            'locale'         => 'tr',
            'conversationId' => $konusmaKimligi,
            'price'          => $tutarOndalik,
            'paidPrice'      => $tutarOndalik,
            'currency'       => 'TRY',
            'basketId'       => (string) $rezervasyonDetay['kod'],
            'paymentGroup'   => 'PRODUCT',
            'callbackUrl'    => $donusUrl,
            'buyer' => [
                'id'                  => 'misafir-' . $rezervasyonDetay['kod'],
                'name'                => $ad,
                'surname'             => $soyad,
                'gsmNumber'           => '+9' . $rezervasyonDetay['telefon'],
                'email'               => (string) $rezervasyonDetay['eposta'],
                // Üyeliksiz misafir akışında TCKN toplanmaz; iyzico'nun misafir
                // ödemeleri için önerdiği yer tutucu kullanılır.
                'identityNumber'      => '11111111111',
                'registrationAddress' => $this->isletmeAdresi(),
                'ip'                  => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'city'                => 'Istanbul',
                'country'             => 'Turkey',
            ],
            'billingAddress' => [
                'contactName' => trim($ad . ' ' . $soyad),
                'city'        => 'Istanbul',
                'country'     => 'Turkey',
                'address'     => $this->isletmeAdresi(),
            ],
            'basketItems' => [[
                'id'        => 'rez-' . $rezervasyonDetay['kod'],
                'name'      => mb_substr($macBasligi, 0, 120),
                'category1' => 'Rezervasyon',
                'itemType'  => 'VIRTUAL',
                'price'     => $tutarOndalik,
            ]],
        ];

        $cevap = $this->istek('/payment/iyzipos/checkoutform/initialize/auth/ecom', $govde);

        if (($cevap['status'] ?? '') !== 'success' || empty($cevap['paymentPageUrl'])) {
            throw new OdemeHatasi(
                'Ödeme sayfası açılamadı: ' . ($cevap['errorMessage'] ?? 'sağlayıcı hatası') .
                ' (kod: ' . ($cevap['errorCode'] ?? '-') . ')'
            );
        }

        return [
            'yonlendirme_url' => (string) $cevap['paymentPageUrl'],
            'ham'             => ['token' => $cevap['token'] ?? null, 'tokenExpireTime' => $cevap['tokenExpireTime'] ?? null],
        ];
    }

    public function odemeSorgula(string $jeton, ?string $konusmaKimligi = null): array
    {
        $govde = ['locale' => 'tr', 'token' => $jeton];
        if ($konusmaKimligi !== null) {
            $govde['conversationId'] = $konusmaKimligi;
        }
        $cevap = $this->istek('/payment/iyzipos/checkoutform/auth/ecom/detail', $govde);

        $basarili = ($cevap['status'] ?? '') === 'success'
            && ($cevap['paymentStatus'] ?? '') === 'SUCCESS';

        // İade için gerekli paymentTransactionId'yi ham cevapta saklıyoruz
        return [
            'basarili'           => $basarili,
            'konusma_kimligi'    => isset($cevap['conversationId']) ? (string) $cevap['conversationId'] : null,
            'saglayici_odeme_id' => isset($cevap['paymentId']) ? (string) $cevap['paymentId'] : null,
            'odenen_kurus'       => isset($cevap['paidPrice']) ? (int) round(((float) $cevap['paidPrice']) * 100) : null,
            'ham'                => $cevap,
        ];
    }

    public function iadeYap(array $odeme, int $tutarKurus): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ham = json_decode((string) ($odeme['ham_cevap'] ?? ''), true) ?: [];

        // Aynı gün tam iade: cancel; sonrası/kısmi: refund (işlem kalemi bazlı)
        if ($tutarKurus === (int) $odeme['tutar_kurus'] && !empty($odeme['saglayici_odeme_id'])) {
            $cevap = $this->istek('/payment/cancel', [
                'locale'    => 'tr',
                'paymentId' => (string) $odeme['saglayici_odeme_id'],
                'ip'        => $ip,
            ]);
            if (($cevap['status'] ?? '') === 'success') {
                return ['basarili' => true, 'ham' => $cevap];
            }
        }

        $islemId = $this->odemeIslemIdBul($ham);
        if ($islemId === null) {
            return ['basarili' => false, 'ham' => ['hata' => 'paymentTransactionId bulunamadı; iade panelden manuel yapılmalı']];
        }

        $cevap = $this->istek('/payment/refund', [
            'locale'               => 'tr',
            'paymentTransactionId' => $islemId,
            'price'                => kurusOndalikMetin($tutarKurus),
            'currency'             => 'TRY',
            'ip'                   => $ip,
        ]);
        return ['basarili' => ($cevap['status'] ?? '') === 'success', 'ham' => $cevap];
    }

    // ------------------------------------------------------------

    /** IYZWSv2 imzalı istek (resmi SDK'nın yaptığının birebir karşılığı). */
    private function istek(string $yol, array $govde): array
    {
        $apiAnahtari = Cevre::zorunlu('IYZICO_API_ANAHTARI');
        $gizliAnahtar = Cevre::zorunlu('IYZICO_GIZLI_ANAHTAR');
        $temelUrl = rtrim(Cevre::al('IYZICO_TEMEL_URL', 'https://sandbox-api.iyzipay.com') ?? '', '/');

        $govdeJson = json_encode($govde, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $rastgele = (string) time() . bin2hex(random_bytes(4));
        $imza = hash_hmac('sha256', $rastgele . $yol . $govdeJson, $gizliAnahtar);
        $yetkiDizgesi = 'apiKey:' . $apiAnahtari . '&randomKey:' . $rastgele . '&signature:' . $imza;

        $kanal = curl_init($temelUrl . $yol);
        curl_setopt_array($kanal, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $govdeJson,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: IYZWSv2 ' . base64_encode($yetkiDizgesi),
                'x-iyzi-rnd: ' . $rastgele,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        $cevapGovde = curl_exec($kanal);
        $hataNo = curl_errno($kanal);
        $hataMesaji = curl_error($kanal);
        curl_close($kanal);

        if ($cevapGovde === false || $hataNo !== 0) {
            throw new OdemeHatasi('Ödeme sağlayıcısına ulaşılamadı: ' . $hataMesaji);
        }

        $cevap = json_decode((string) $cevapGovde, true);
        if (!is_array($cevap)) {
            throw new OdemeHatasi('Ödeme sağlayıcısından geçersiz yanıt alındı.');
        }
        return $cevap;
    }

    /** Retrieve cevabındaki ilk kalem işlem kimliği (tek kalemli sepet). */
    private function odemeIslemIdBul(array $ham): ?string
    {
        foreach (($ham['itemTransactions'] ?? []) as $kalem) {
            if (!empty($kalem['paymentTransactionId'])) {
                return (string) $kalem['paymentTransactionId'];
            }
        }
        return null;
    }

    /** @return array{0: string, 1: string} */
    private function adSoyadAyir(string $adSoyad): array
    {
        $parcalar = preg_split('/\s+/', trim($adSoyad)) ?: ['Misafir'];
        $soyad = count($parcalar) > 1 ? (string) array_pop($parcalar) : 'Misafir';
        return [implode(' ', $parcalar), $soyad];
    }

    private function isletmeAdresi(): string
    {
        $adres = (string) Ayarlar::al('iletisim_adres', '');
        return $adres !== '' ? $adres : 'Isletme adresi';
    }
}
