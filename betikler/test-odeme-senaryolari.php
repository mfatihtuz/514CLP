<?php

/**
 * ÖDEME SENARYO TESTLERİ (docs/ODEME-AKISI.md'deki 6 kritik senaryo).
 * Mock sağlayıcı ile uçtan uca HTTP akışı test edilir.
 *
 * Ön koşul: çalışan yerel sunucu + ODEME_SAGLAYICI=mock
 *   bash betikler/yerel-sunucu.sh
 * Kullanım: php betikler/test-odeme-senaryolari.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/uygulama/baslat.php';

const SUNUCU = 'http://localhost:8514';
$MAC_ID = testMaciHazirla();
$sonuclar = [];

// ============================================================
// Senaryo 1: Mutlu yol — ödeme onayı → rezervasyon kesinleşir
// ============================================================
$istemci = new TestIstemcisi();
$kod = holdVeBilgi($istemci, $MAC_ID, 'Mutlu Yol', '05321110001');
$kk = odemeBaslat($istemci, $kod);
mockBankaIslem($istemci, $kk, 'onayla', true);

$rezervasyon = Veritabani::satir('SELECT * FROM rezervasyonlar WHERE kod = ?', [$kod]);
$odeme = Veritabani::satir('SELECT * FROM odemeler WHERE konusma_kimligi = ?', [$kk]);
$masa = Veritabani::satir('SELECT durum FROM mac_masalari WHERE tutan_rezervasyon_id = ?', [$rezervasyon['id']]);
$sonuclar['1. Mutlu yol'] = $rezervasyon['durum'] === 'onaylandi'
    && $odeme['durum'] === 'basarili'
    && $masa !== null && $masa['durum'] === 'rezerve';
$mutluKod = $kod;

// ============================================================
// Senaryo 2: Red → hold korunur → yeniden deneme başarılı
// ============================================================
$istemci = new TestIstemcisi();
$kod = holdVeBilgi($istemci, $MAC_ID, 'Red Deneme', '05321110002');
$kk1 = odemeBaslat($istemci, $kod);
mockBankaIslem($istemci, $kk1, 'reddet', true);

$araDurum = Veritabani::satir('SELECT * FROM rezervasyonlar WHERE kod = ?', [$kod]);
$redSonrasiHoldSaglam = $araDurum['durum'] === 'odeme_bekliyor'
    && (string) Veritabani::deger('SELECT durum FROM odemeler WHERE konusma_kimligi = ?', [$kk1]) === 'basarisiz';

$kk2 = odemeBaslat($istemci, $kod);   // ikinci deneme yeni konusma_kimligi açar
mockBankaIslem($istemci, $kk2, 'onayla', true);
$sonuclar['2. Red + yeniden deneme'] = $redSonrasiHoldSaglam
    && (string) Veritabani::deger('SELECT durum FROM rezervasyonlar WHERE kod = ?', [$kod]) === 'onaylandi';

// ============================================================
// Senaryo 3: Çifte callback → idempotent (durum değişmez)
// ============================================================
$oncekiGuncelleme = Veritabani::deger('SELECT guncelleme_zamani FROM odemeler WHERE konusma_kimligi = ?', [$kk2]);
$masaSayisiOnce = (int) Veritabani::deger(
    'SELECT COUNT(*) FROM rezervasyon_masalari rm JOIN rezervasyonlar r ON r.id = rm.rezervasyon_id WHERE r.kod = ?', [$kod]
);
$cevap = $istemci->formGonder(SUNUCU . '/odeme/geri-donus', ['token' => $kk2]); // aynı token ikinci kez
$masaSayisiSonra = (int) Veritabani::deger(
    'SELECT COUNT(*) FROM rezervasyon_masalari rm JOIN rezervasyonlar r ON r.id = rm.rezervasyon_id WHERE r.kod = ?', [$kod]
);
$sonuclar['3. Çifte callback idempotency'] = str_contains($cevap['son_url'], '/rezervasyon/' . $kod)
    && $masaSayisiOnce === $masaSayisiSonra
    && (string) Veritabani::deger('SELECT durum FROM odemeler WHERE konusma_kimligi = ?', [$kk2]) === 'basarili';

// ============================================================
// Senaryo 4: Callback kaybı → cron reconciliation kurtarır
// ============================================================
$istemci = new TestIstemcisi();
$kod = holdVeBilgi($istemci, $MAC_ID, 'Kayip Callback', '05321110003');
$kk = odemeBaslat($istemci, $kod);
mockBankaIslem($istemci, $kk, 'onayla_callback_kayip', false);  // banka onayladı, site duymadı

$sonuclar['4a. Callback kaybı: rezervasyon askıda'] =
    (string) Veritabani::deger('SELECT durum FROM rezervasyonlar WHERE kod = ?', [$kod]) === 'odeme_bekliyor';

// Kurtarma koşulu: ödeme en az 3 dk önce başlamış görünsün
Veritabani::calistir('UPDATE odemeler SET olusturma_zamani = ? WHERE konusma_kimligi = ?', [utcKaydir(simdiUtc(), -5), $kk]);
$kurtarma = OdemeYonetici::askidaKalanlariKurtar();
$sonuclar['4b. Reconciliation kurtardı'] = $kurtarma['kurtarilan'] >= 1
    && (string) Veritabani::deger('SELECT durum FROM rezervasyonlar WHERE kod = ?', [$kod]) === 'onaylandi';

// ============================================================
// Senaryo 5: Süre aşımı + masa kapıldı → OTOMATİK İADE
// ============================================================
$istemci = new TestIstemcisi();
$kod = holdVeBilgi($istemci, $MAC_ID, 'Gec Kalan', '05321110004');
$kk = odemeBaslat($istemci, $kod);

// Banka onayı geldi ama biz callback gelmeden hold'u geçmişe çekip masayı kaptırıyoruz
mockBankaIslem($istemci, $kk, 'onayla_callback_kayip', false); // sonuç bankada: onaylı
$gecmis = utcKaydir(simdiUtc(), -1);
Veritabani::calistir('UPDATE rezervasyonlar SET hold_sona_erme = ? WHERE kod = ?', [$gecmis, $kod]);
$rezId = (int) Veritabani::deger('SELECT id FROM rezervasyonlar WHERE kod = ?', [$kod]);
$kapilanMasaId = (int) Veritabani::deger('SELECT id FROM mac_masalari WHERE tutan_rezervasyon_id = ?', [$rezId]);
Veritabani::calistir('UPDATE mac_masalari SET tutma_sona_erme = ? WHERE id = ?', [$gecmis, $kapilanMasaId]);

// Başka müşteri aynı masayı kapıyor
$rakip = new TestIstemcisi();
$rakipCsrf = $rakip->csrfAl(SUNUCU . '/mac/' . $MAC_ID);
$rakip->jsonGonder(SUNUCU . '/api/hold', [
    'csrf_jetonu' => $rakipCsrf, 'mac_id' => $MAC_ID, 'tur' => 'masa',
    'masa_idler' => [$kapilanMasaId], 'kisi_sayisi' => 4,
]);

// Şimdi geciken callback geliyor: para alınmış ama masa gitmiş → iade beklenir
$cevap = $istemci->formGonder(SUNUCU . '/odeme/geri-donus', ['token' => $kk]);
$odemeDurum = (string) Veritabani::deger('SELECT durum FROM odemeler WHERE konusma_kimligi = ?', [$kk]);
$sonuclar['5. Süre aşımı → otomatik iade'] = $odemeDurum === 'iade_edildi'
    && (string) Veritabani::deger('SELECT durum FROM rezervasyonlar WHERE kod = ?', [$kod]) !== 'onaylandi';

// ============================================================
// Senaryo 6: Müşteri iptali → iade + masa serbest
// ============================================================
$istemci = new TestIstemcisi();
$kod = holdVeBilgi($istemci, $MAC_ID, 'Iptal Eden', '05321110005');
$kk = odemeBaslat($istemci, $kod);
mockBankaIslem($istemci, $kk, 'onayla', true);

$biletSayfasi = $istemci->getir(SUNUCU . '/rezervasyon/' . $kod);
$csrf = $istemci->csrfCoz($biletSayfasi['govde']);
$istemci->formGonder(SUNUCU . '/rezervasyon/' . $kod . '/iptal', ['csrf_jetonu' => $csrf]);

$rezId = (int) Veritabani::deger('SELECT id FROM rezervasyonlar WHERE kod = ?', [$kod]);
$sonuclar['6. İadeli iptal'] =
    (string) Veritabani::deger('SELECT durum FROM rezervasyonlar WHERE kod = ?', [$kod]) === 'iptal_edildi'
    && (string) Veritabani::deger('SELECT durum FROM odemeler WHERE konusma_kimligi = ?', [$kk]) === 'iade_edildi'
    && (int) Veritabani::deger('SELECT COUNT(*) FROM rezervasyon_masalari WHERE rezervasyon_id = ?', [$rezId]) === 0;

// ============================================================
rapor($sonuclar);

// ================= yardımcılar =================

function testMaciHazirla(): int
{
    $simdi = simdiUtc();
    Veritabani::calistir("DELETE FROM rezervasyon_masalari WHERE rezervasyon_id IN (SELECT id FROM rezervasyonlar WHERE mac_id IN (SELECT id FROM maclar WHERE fikstur_anahtari = 'odeme-testi'))");
    Veritabani::calistir("DELETE FROM odemeler WHERE rezervasyon_id IN (SELECT id FROM rezervasyonlar WHERE mac_id IN (SELECT id FROM maclar WHERE fikstur_anahtari = 'odeme-testi'))");
    Veritabani::calistir("DELETE FROM rezervasyonlar WHERE mac_id IN (SELECT id FROM maclar WHERE fikstur_anahtari = 'odeme-testi')");
    Veritabani::calistir("DELETE FROM mac_masalari WHERE mac_id IN (SELECT id FROM maclar WHERE fikstur_anahtari = 'odeme-testi')");
    Veritabani::calistir("DELETE FROM maclar WHERE fikstur_anahtari = 'odeme-testi'");

    Veritabani::calistir(
        "INSERT INTO maclar (ev_sahibi_takim_id, deplasman_takim_id, baslangic_zamani, kisi_basi_fiyat_kurus,
                             paylasimli_kontenjan, durum, kaynak, fikstur_anahtari, olusturma_zamani, guncelleme_zamani)
         VALUES (1, 3, ?, 45000, 10, 'satista', 'manuel', 'odeme-testi', ?, ?)",
        [utcKaydir($simdi, 60 * 48), $simdi, $simdi]
    );
    $macId = Veritabani::sonEklenenId();
    for ($no = 1; $no <= 8; $no++) {
        Veritabani::calistir(
            "INSERT INTO mac_masalari (mac_id, ad, kapasite, min_kisi, sekil, konum_x, konum_y, genislik, yukseklik, durum)
             VALUES (?, ?, 4, 3, 'kare', ?, 0, 3, 2, 'bos')",
            [$macId, 'OT' . $no, ($no - 1) * 3]
        );
    }
    echo "Test maçı #{$macId} (8 masa) hazırlandı.\n\n";
    return $macId;
}

function holdVeBilgi(TestIstemcisi $istemci, int $macId, string $adSoyad, string $telefon): string
{
    $csrf = $istemci->csrfAl(SUNUCU . '/mac/' . $macId);
    $bosMasa = Veritabani::satir(
        "SELECT id FROM mac_masalari WHERE mac_id = ? AND durum = 'bos' ORDER BY id LIMIT 1", [$macId]
    );
    $cevap = $istemci->jsonGonder(SUNUCU . '/api/hold', [
        'csrf_jetonu' => $csrf, 'mac_id' => $macId, 'tur' => 'masa',
        'masa_idler' => [(int) $bosMasa['id']], 'kisi_sayisi' => 4,
    ]);
    $veri = json_decode($cevap['govde'], true);
    if (empty($veri['tamam'])) {
        durdur('Hold alınamadı: ' . $cevap['govde']);
    }
    $kod = (string) $veri['kod'];

    $bilgiSayfasi = $istemci->getir(SUNUCU . '/rezervasyon/' . $kod . '/bilgi');
    $csrf = $istemci->csrfCoz($bilgiSayfasi['govde']);
    $istemci->formGonder(SUNUCU . '/rezervasyon/' . $kod . '/bilgi', [
        'csrf_jetonu' => $csrf,
        'ad_soyad' => $adSoyad . ' Test',
        'telefon' => $telefon,
        'eposta' => strtolower(str_replace(' ', '', $adSoyad)) . '@test.local',
        'kvkk_onay' => '1',
        'sozlesme_onay' => '1',
    ]);
    return $kod;
}

/** Ödeme başlatır, mock banka URL'inden konusma_kimligi döner. */
function odemeBaslat(TestIstemcisi $istemci, string $kod): string
{
    $odemeSayfasi = $istemci->getir(SUNUCU . '/rezervasyon/' . $kod . '/odeme');
    $csrf = $istemci->csrfCoz($odemeSayfasi['govde']);
    $cevap = $istemci->formGonder(SUNUCU . '/rezervasyon/' . $kod . '/odeme/baslat', ['csrf_jetonu' => $csrf]);
    if (!preg_match('/kk=([a-z0-9\-A-Z]+)/', $cevap['son_url'], $eslesme)) {
        durdur('Mock banka URL alınamadı: ' . $cevap['son_url']);
    }
    return urldecode($eslesme[1]);
}

function mockBankaIslem(TestIstemcisi $istemci, string $konusmaKimligi, string $eylem, bool $callbackBekle): void
{
    $banka = $istemci->getir(SUNUCU . '/mock-odeme?kk=' . urlencode($konusmaKimligi));
    $csrf = $istemci->csrfCoz($banka['govde']);
    $cevap = $istemci->formGonder(SUNUCU . '/mock-odeme', [
        'csrf_jetonu' => $csrf, 'kk' => $konusmaKimligi, 'eylem' => $eylem,
    ]);
    if ($callbackBekle) {
        // Tarayıcının auto-post'unu taklit et: callback'i biz gönderiyoruz
        $istemci->formGonder(SUNUCU . '/odeme/geri-donus', ['token' => $konusmaKimligi]);
    }
}

function rapor(array $sonuclar): void
{
    echo "\n==================== SONUÇLAR ====================\n";
    $tumGecti = true;
    foreach ($sonuclar as $ad => $gecti) {
        echo sprintf("%-42s %s\n", $ad, $gecti ? 'GEÇTİ' : 'BAŞARISIZ');
        $tumGecti = $tumGecti && $gecti;
    }
    echo "==================================================\n";
    echo $tumGecti ? "TÜM ÖDEME SENARYOLARI GEÇTİ\n" : "BAŞARISIZ SENARYO VAR!\n";
    exit($tumGecti ? 0 : 1);
}

function durdur(string $mesaj): void
{
    fwrite(STDERR, "HATA: {$mesaj}\n");
    exit(1);
}

/** Çerezli basit HTTP istemcisi (her örnek ayrı müşteri oturumudur). */
final class TestIstemcisi
{
    private string $cerezDosyasi;

    public function __construct()
    {
        $this->cerezDosyasi = tempnam(sys_get_temp_dir(), 'odemetest');
    }

    /** @return array{govde: string, kod: int, son_url: string} */
    public function getir(string $url): array
    {
        return $this->calistir($url, null, null);
    }

    /** @return array{govde: string, kod: int, son_url: string} */
    public function formGonder(string $url, array $alanlar): array
    {
        return $this->calistir($url, http_build_query($alanlar), 'application/x-www-form-urlencoded');
    }

    /** @return array{govde: string, kod: int, son_url: string} */
    public function jsonGonder(string $url, array $veri): array
    {
        return $this->calistir($url, json_encode($veri), 'application/json');
    }

    public function csrfAl(string $url): string
    {
        return $this->csrfCoz($this->getir($url)['govde']);
    }

    public function csrfCoz(string $html): string
    {
        if (preg_match('/name="csrf_jetonu" value="([^"]+)"/', $html, $eslesme)
            || preg_match('/data-csrf="([^"]+)"/', $html, $eslesme)) {
            return $eslesme[1];
        }
        durdur('CSRF jetonu bulunamadı (oturum açılamadı mı?)');
        return '';
    }

    private function calistir(string $url, ?string $govde, ?string $icerikTuru): array
    {
        $kanal = curl_init($url);
        $basliklar = [];
        if ($icerikTuru !== null) {
            $basliklar[] = 'Content-Type: ' . $icerikTuru;
        }
        curl_setopt_array($kanal, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 6,
            CURLOPT_COOKIEFILE     => $this->cerezDosyasi,
            CURLOPT_COOKIEJAR      => $this->cerezDosyasi,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => $basliklar,
        ]);
        if ($govde !== null) {
            curl_setopt($kanal, CURLOPT_POST, true);
            curl_setopt($kanal, CURLOPT_POSTFIELDS, $govde);
        }
        $cevapGovde = (string) curl_exec($kanal);
        $sonuc = [
            'govde'   => $cevapGovde,
            'kod'     => (int) curl_getinfo($kanal, CURLINFO_RESPONSE_CODE),
            'son_url' => (string) curl_getinfo($kanal, CURLINFO_EFFECTIVE_URL),
        ];
        curl_close($kanal);
        return $sonuc;
    }
}
