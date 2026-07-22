<?php

declare(strict_types=1);

/**
 * Mock ödeme sağlayıcısı — YALNIZ geliştirme/test.
 *
 * Gerçek akışın birebir taklidi: müşteri /mock-odeme sayfasına yönlenir
 * ("sahte banka"), Onayla/Reddet seçer; sonuç odemeler.ham_cevap içine
 * yazılır ve gerçek callback uç noktasına POST edilir. odemeSorgula()
 * sonucu bu kayıttan okur — yani "callback'e güvenme, sağlayıcıdan sorgula"
 * kuralı mock'ta da aynen işler ve tüm uç senaryolar test edilebilir.
 */
final class MockSaglayici implements OdemeSaglayici
{
    public function ad(): string
    {
        return 'mock';
    }

    public function odemeBaslat(array $rezervasyonDetay, string $konusmaKimligi, string $donusUrl): array
    {
        return [
            'yonlendirme_url' => '/mock-odeme?kk=' . urlencode($konusmaKimligi),
            'ham'             => ['saglayici' => 'mock', 'donus_url' => $donusUrl],
        ];
    }

    public function odemeSorgula(string $jeton, ?string $konusmaKimligi = null): array
    {
        // Mock'ta jeton = konusma_kimligi'dir
        $odeme = Veritabani::satir('SELECT * FROM odemeler WHERE konusma_kimligi = ?', [$jeton]);
        $ham = $odeme !== null ? (json_decode((string) ($odeme['ham_cevap'] ?? ''), true) ?: []) : [];
        $sonuc = (string) ($ham['mock_sonuc'] ?? 'bilinmiyor');

        return [
            'basarili'           => $sonuc === 'basarili',
            'konusma_kimligi'    => $jeton,
            'saglayici_odeme_id' => $sonuc === 'basarili' ? 'MOCK-' . substr(md5($jeton), 0, 10) : null,
            'odenen_kurus'       => $sonuc === 'basarili' && $odeme !== null ? (int) $odeme['tutar_kurus'] : null,
            'ham'                => $ham + ['sorgu_sonucu' => $sonuc],
        ];
    }

    public function iadeYap(array $odeme, int $tutarKurus): array
    {
        // Mock iade daima başarılıdır; senaryo testi için ham cevaba işlenir
        return [
            'basarili' => true,
            'ham'      => ['saglayici' => 'mock', 'iade_kurus' => $tutarKurus, 'zaman' => simdiUtc()],
        ];
    }
}
