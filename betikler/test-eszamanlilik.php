<?php

/**
 * EŞZAMANLILIK TESTİ (CLAUDE.md kural 15 — para/eşzamanlılık değişikliklerinde zorunlu).
 *
 * Aynı masaya aynı anda N istek gönderir; TAM 1 tanesi kazanmalıdır.
 * Çalışan yerel sunucu gerektirir:  bash betikler/yerel-sunucu.sh
 *
 * Kullanım: php betikler/test-eszamanlilik.php [istekSayisi=6]
 */

declare(strict_types=1);

require dirname(__DIR__) . '/uygulama/baslat.php';

$istekSayisi = max(2, (int) ($argv[1] ?? 6));
$sunucu = 'http://localhost:8514';

// ---- Hazırlık: test maçı + tek masa ----
$simdi = simdiUtc();
Veritabani::calistir("DELETE FROM rezervasyon_masalari WHERE rezervasyon_id IN (SELECT id FROM rezervasyonlar WHERE eposta = 'yaris@test.local')");
Veritabani::calistir("DELETE FROM rezervasyonlar WHERE eposta = 'yaris@test.local'");
Veritabani::calistir("DELETE FROM mac_masalari WHERE mac_id IN (SELECT id FROM maclar WHERE fikstur_anahtari = 'yaris-testi')");
Veritabani::calistir("DELETE FROM maclar WHERE fikstur_anahtari = 'yaris-testi'");

Veritabani::calistir(
    "INSERT INTO maclar (ev_sahibi_takim_id, deplasman_takim_id, baslangic_zamani, kisi_basi_fiyat_kurus,
                         paylasimli_kontenjan, durum, kaynak, fikstur_anahtari, olusturma_zamani, guncelleme_zamani)
     VALUES (1, 2, ?, 50000, 4, 'satista', 'manuel', 'yaris-testi', ?, ?)",
    [utcKaydir($simdi, 60 * 24), $simdi, $simdi]
);
$macId = Veritabani::sonEklenenId();
Veritabani::calistir(
    "INSERT INTO mac_masalari (mac_id, ad, kapasite, min_kisi, sekil, konum_x, konum_y, genislik, yukseklik, durum)
     VALUES (?, 'YT1', 4, 3, 'kare', 0, 0, 3, 2, 'bos')",
    [$macId]
);
$masaId = Veritabani::sonEklenenId();
echo "Test maçı #{$macId}, masa #{$masaId} hazırlandı.\n";

// ---- CSRF jetonu al (oturum çerezli) ----
$cerezDosyasi = tempnam(sys_get_temp_dir(), 'yaris');
$anaSayfa = curlCalistir($sunucu . '/mac/' . $macId, null, $cerezDosyasi);
if (!preg_match('/data-csrf="([^"]+)"/', $anaSayfa['govde'], $eslesme)) {
    fwrite(STDERR, "HATA: CSRF jetonu alınamadı. Yerel sunucu çalışıyor mu? (bash betikler/yerel-sunucu.sh)\n");
    exit(1);
}
$csrf = $eslesme[1];

// ---- Aynı masaya eşzamanlı hold istekleri (curl_multi) ----
// Not: oran sınırı oturum bazlıdır; her istek ayrı çerezle YENİ oturum açar
// (gerçek dünyada farklı müşteriler = farklı oturumlar).
$coklu = curl_multi_init();
$kanallar = [];
for ($i = 0; $i < $istekSayisi; $i++) {
    $ayriCerez = tempnam(sys_get_temp_dir(), 'yaris' . $i);
    $on = curlCalistir($sunucu . '/mac/' . $macId, null, $ayriCerez); // oturum + csrf
    preg_match('/data-csrf="([^"]+)"/', $on['govde'], $e);
    $kanal = curl_init($sunucu . '/api/hold');
    curl_setopt_array($kanal, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'csrf_jetonu' => $e[1] ?? '',
            'mac_id'      => $macId,
            'tur'         => 'masa',
            'masa_idler'  => [$masaId],
            'kisi_sayisi' => 4,
        ]),
        CURLOPT_COOKIEFILE     => $ayriCerez,
        CURLOPT_COOKIEJAR      => $ayriCerez,
        CURLOPT_TIMEOUT        => 15,
    ]);
    curl_multi_add_handle($coklu, $kanal);
    $kanallar[] = $kanal;
}

do {
    $durum = curl_multi_exec($coklu, $calisan);
    if ($calisan) {
        curl_multi_select($coklu, 0.05);
    }
} while ($calisan && $durum === CURLM_OK);

$kazanan = 0;
$reddedilen = 0;
foreach ($kanallar as $kanal) {
    $cevap = json_decode((string) curl_multi_getcontent($kanal), true) ?: [];
    if (!empty($cevap['tamam'])) {
        $kazanan++;
    } else {
        $reddedilen++;
    }
    curl_multi_remove_handle($coklu, $kanal);
}
curl_multi_close($coklu);

// ---- Veritabanı son durumu ----
$masaDurum = Veritabani::satir('SELECT durum, tutan_rezervasyon_id FROM mac_masalari WHERE id = ?', [$masaId]);
$holdSayisi = (int) Veritabani::deger(
    "SELECT COUNT(*) FROM rezervasyonlar WHERE mac_id = ? AND durum = 'odeme_bekliyor'",
    [$macId]
);

echo "\nSonuç: {$istekSayisi} eşzamanlı istek → kazanan: {$kazanan}, reddedilen: {$reddedilen}\n";
echo "Masa durumu: {$masaDurum['durum']} (tutan rezervasyon: {$masaDurum['tutan_rezervasyon_id']})\n";
echo "Aktif hold'lu rezervasyon sayısı: {$holdSayisi}\n";

$basarili = $kazanan === 1 && $masaDurum['durum'] === 'tutuldu' && $holdSayisi === 1;
echo $basarili
    ? "\nTEST GEÇTİ: çift rezervasyon oluşmadı; tam 1 istek kazandı.\n"
    : "\nTEST BAŞARISIZ! Kilit mekanizması incelenmeli.\n";
exit($basarili ? 0 : 1);

/** @return array{govde: string, kod: int} */
function curlCalistir(string $url, ?array $veri, string $cerezDosyasi): array
{
    $kanal = curl_init($url);
    curl_setopt_array($kanal, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE     => $cerezDosyasi,
        CURLOPT_COOKIEJAR      => $cerezDosyasi,
        CURLOPT_TIMEOUT        => 15,
    ]);
    if ($veri !== null) {
        curl_setopt($kanal, CURLOPT_POST, true);
        curl_setopt($kanal, CURLOPT_POSTFIELDS, $veri);
    }
    $govde = (string) curl_exec($kanal);
    $kod = (int) curl_getinfo($kanal, CURLINFO_RESPONSE_CODE);
    curl_close($kanal);
    return ['govde' => $govde, 'kod' => $kod];
}
