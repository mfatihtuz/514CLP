<?php

declare(strict_types=1);

/** Müşteri tarafı maç ekranları: masa seçimi + kroki verisi. */
final class MacDenetleyici
{
    public function detay(array $parametreler): void
    {
        $mac = MacSorgulari::idIleBul((int) $parametreler['macId']);
        if ($mac === null || !in_array($mac['durum'], ['satista', 'satis_kapali'], true)) {
            http_response_code(404);
            Sablon::goster('hatalar/404', ['baslik' => 'Maç bulunamadı']);
            return;
        }

        Sablon::goster('mac/detay', [
            'baslik'      => $mac['ev_ad'] . ' - ' . $mac['dep_ad'],
            'mac'         => $mac,
            'paket'       => MacSorgulari::paketIcerigi($mac),
            'salonKalan'  => $this->salonKalan($mac),
            'holdDakika'  => Ayarlar::holdDakika(),
            'satisAcik'   => $mac['durum'] === 'satista' && (string) $mac['baslangic_zamani'] > simdiUtc(),
        ]);
    }

    /** Kroki JSON'u: müşteri yalnız gereken alanları görür; 10 sn'de bir poll edilir. */
    public function krokiVeri(array $parametreler): void
    {
        $macId = (int) $parametreler['macId'];
        $mac = Veritabani::satir('SELECT * FROM maclar WHERE id = ?', [$macId]);
        if ($mac === null) {
            Sablon::json(['hata' => 'Maç bulunamadı'], 404);
            return;
        }

        $masalar = array_map(static function (array $masa): array {
            return [
                'id'       => (int) $masa['id'],
                'ad'       => (string) $masa['ad'],
                'kapasite' => (int) $masa['kapasite'],
                'min_kisi' => (int) $masa['min_kisi'],
                'sekil'    => (string) $masa['sekil'],
                'konum_x'  => (int) $masa['konum_x'],
                'konum_y'  => (int) $masa['konum_y'],
                'genislik' => (int) $masa['genislik'],
                'yukseklik'=> (int) $masa['yukseklik'],
                // Müşteri yalnız 'bos' / 'dolu' / 'kapali' görür (tutuldu = dolu)
                'durum'    => $masa['durum'] === 'bos' ? 'bos' : ($masa['durum'] === 'kapali' ? 'kapali' : 'dolu'),
            ];
        }, MacMasaSorgulari::macinMasalariEfektif($macId));

        Sablon::json([
            'masalar'    => $masalar,
            'salonKalan' => $this->salonKalan($mac),
            'satisAcik'  => $mac['durum'] === 'satista' && (string) $mac['baslangic_zamani'] > simdiUtc(),
        ]);
    }

    private function salonKalan(array $mac): int
    {
        $kontenjan = (int) $mac['paylasimli_kontenjan'];
        if ($kontenjan <= 0) {
            return 0;
        }
        $dolu = (int) (Veritabani::deger(
            "SELECT COALESCE(SUM(kisi_sayisi), 0) FROM rezervasyonlar
             WHERE mac_id = ? AND tur = 'salon'
               AND ( durum = 'onaylandi'
                     OR (durum = 'odeme_bekliyor' AND hold_sona_erme IS NOT NULL AND hold_sona_erme > ?) )",
            [$mac['id'], simdiUtc()]
        ) ?? 0);
        return max(0, $kontenjan - $dolu);
    }
}
