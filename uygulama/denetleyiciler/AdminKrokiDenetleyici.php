<?php

declare(strict_types=1);

/**
 * Kroki yönetimi:
 *  - Ana kat planı editörü (masalar tablosu) — serbest düzenleme
 *  - Maça özel kroki (mac_masalari) — kurallı düzenleme:
 *      * mevcut masa SİLİNEMEZ (yalnız kapatılır)
 *      * rezerve/tutulu masada yalnız KONUM değişebilir
 *      * yeni ekstra masa eklenebilir (kaynak_masa_id NULL)
 */
final class AdminKrokiDenetleyici
{
    public function anaPlanEditoru(array $parametreler = []): void
    {
        AdminOturumu::zorunlu();
        Sablon::goster('admin/kroki', [
            'baslik'    => 'Kat Planı',
            'aktifMenu' => 'kroki',
        ], 'duzen/admin');
    }

    public function anaPlanVeri(array $parametreler = []): void
    {
        AdminOturumu::apiZorunlu();
        Sablon::json([
            'mod'     => 'ana',
            'masalar' => MasaSorgulari::anaPlan(),
        ]);
    }

    public function anaPlanKaydet(array $parametreler = []): void
    {
        AdminOturumu::apiZorunlu();
        $govde = jsonGovdeOku();
        jsonCsrfZorunlu($govde);

        $masalar = is_array($govde['masalar'] ?? null) ? $govde['masalar'] : [];
        $hatalar = MasaSorgulari::planDogrula($masalar);
        if ($hatalar !== []) {
            Sablon::json(['tamam' => false, 'hatalar' => $hatalar], 422);
            return;
        }

        MasaSorgulari::planKaydet($masalar);
        DenetimKaydi::yaz('ana_plan_kaydedildi', ['masa_sayisi' => count($masalar)]);
        Sablon::json(['tamam' => true, 'masalar' => MasaSorgulari::anaPlan()]);
    }

    public function macKrokiVeri(array $parametreler): void
    {
        AdminOturumu::apiZorunlu();
        $macId = (int) $parametreler['macId'];
        Sablon::json([
            'mod'     => 'mac',
            'masalar' => MacMasaSorgulari::macinMasalariEfektif($macId),
        ]);
    }

    public function macKrokiKaydet(array $parametreler): void
    {
        AdminOturumu::apiZorunlu();
        $macId = (int) $parametreler['macId'];
        $govde = jsonGovdeOku();
        jsonCsrfZorunlu($govde);

        $mac = Veritabani::satir('SELECT * FROM maclar WHERE id = ?', [$macId]);
        if ($mac === null || !in_array($mac['durum'], ['satista', 'satis_kapali'], true)) {
            Sablon::json(['tamam' => false, 'hatalar' => ['Yalnızca satışta/satışı durdurulmuş maçın krokisi düzenlenebilir.']], 422);
            return;
        }

        $gelenler = is_array($govde['masalar'] ?? null) ? $govde['masalar'] : [];
        $mevcutlar = [];
        foreach (MacMasaSorgulari::macinMasalari($macId) as $satir) {
            $mevcutlar[(int) $satir['id']] = $satir;
        }

        // Kilitli masaların (rezerve/tutulu) alanlarını sunucudaki değerlerle sabitle:
        // istemci ne gönderirse göndersin yalnız konumu güncellenebilir.
        $dogrulanacak = [];
        $gelenIdler = [];
        foreach ($gelenler as $gelen) {
            $id = isset($gelen['id']) && $gelen['id'] !== null ? (int) $gelen['id'] : null;
            if ($id !== null) {
                if (!isset($mevcutlar[$id])) {
                    Sablon::json(['tamam' => false, 'hatalar' => ["Bilinmeyen masa kimliği: {$id}"]], 422);
                    return;
                }
                $gelenIdler[] = $id;
                $mevcut = $mevcutlar[$id];
                if (in_array($mevcut['durum'], ['rezerve', 'tutuldu'], true)) {
                    $gelen = array_merge($gelen, [
                        'ad'       => $mevcut['ad'],
                        'kapasite' => $mevcut['kapasite'],
                        'min_kisi' => $mevcut['min_kisi'],
                        'sekil'    => $mevcut['sekil'],
                        'genislik' => $mevcut['genislik'],
                        'yukseklik'=> $mevcut['yukseklik'],
                    ]);
                    unset($gelen['g'], $gelen['yk']);
                }
            }
            $dogrulanacak[] = $gelen;
        }

        $eksikler = array_diff(array_keys($mevcutlar), $gelenIdler);
        if ($eksikler !== []) {
            Sablon::json(['tamam' => false, 'hatalar' => ['Satıştaki maçtan masa silinemez; masayı kapatmayı kullanın.']], 422);
            return;
        }

        $hatalar = MasaSorgulari::planDogrula($dogrulanacak);
        if ($hatalar !== []) {
            Sablon::json(['tamam' => false, 'hatalar' => $hatalar], 422);
            return;
        }

        Veritabani::islem(function () use ($dogrulanacak, $mevcutlar, $macId): void {
            foreach ($dogrulanacak as $masa) {
                [$x, $y, $g, $yk] = MasaSorgulari::konumOku($masa);
                $id = isset($masa['id']) && $masa['id'] !== null ? (int) $masa['id'] : null;

                if ($id !== null) {
                    $mevcut = $mevcutlar[$id];
                    if (in_array($mevcut['durum'], ['rezerve', 'tutuldu'], true)) {
                        Veritabani::calistir(
                            'UPDATE mac_masalari SET konum_x=?, konum_y=? WHERE id=? AND mac_id=?',
                            [$x, $y, $id, $macId]
                        );
                    } else {
                        Veritabani::calistir(
                            'UPDATE mac_masalari SET ad=?, kapasite=?, min_kisi=?, sekil=?, konum_x=?, konum_y=?, genislik=?, yukseklik=? WHERE id=? AND mac_id=?',
                            [trim((string) $masa['ad']), (int) $masa['kapasite'], (int) $masa['min_kisi'],
                             (string) $masa['sekil'], $x, $y, $g, $yk, $id, $macId]
                        );
                    }
                } else {
                    Veritabani::calistir(
                        'INSERT INTO mac_masalari (mac_id, kaynak_masa_id, ad, kapasite, min_kisi, sekil, konum_x, konum_y, genislik, yukseklik, durum)
                         VALUES (?, NULL, ?,?,?,?,?,?,?,?, \'bos\')',
                        [$macId, trim((string) $masa['ad']), (int) $masa['kapasite'], (int) $masa['min_kisi'],
                         (string) $masa['sekil'], $x, $y, $g, $yk]
                    );
                }
            }
        });

        DenetimKaydi::yaz('mac_kroki_kaydedildi', ['mac_id' => $macId, 'masa_sayisi' => count($dogrulanacak)]);
        Sablon::json(['tamam' => true, 'masalar' => MacMasaSorgulari::macinMasalariEfektif($macId)]);
    }

    /** Maç masasını kapatma/açma (rezerveli/holdlu masa kapatılamaz). */
    public function macMasaDurum(array $parametreler): void
    {
        AdminOturumu::apiZorunlu();
        $govde = jsonGovdeOku();
        jsonCsrfZorunlu($govde);

        $masaId = (int) $parametreler['macMasaId'];
        $eylem  = (string) ($govde['eylem'] ?? '');
        if (!in_array($eylem, ['kapat', 'ac'], true)) {
            Sablon::json(['tamam' => false, 'hatalar' => ['Geçersiz eylem.']], 422);
            return;
        }

        if ($eylem === 'kapat') {
            // CAS: yalnız bos (veya süresi geçmiş hold) masa kapatılabilir
            $etkilenen = Veritabani::calistir(
                "UPDATE mac_masalari SET durum='kapali', tutma_sona_erme=NULL, tutan_rezervasyon_id=NULL
                 WHERE id=? AND (durum='bos' OR (durum='tutuldu' AND tutma_sona_erme < ?))",
                [$masaId, simdiUtc()]
            );
            if ($etkilenen === 0) {
                Sablon::json(['tamam' => false, 'hatalar' => ['Bu masa kapatılamaz: rezervasyonu veya aktif bekletmesi var.']], 409);
                return;
            }
        } else {
            $etkilenen = Veritabani::calistir(
                "UPDATE mac_masalari SET durum='bos' WHERE id=? AND durum='kapali'",
                [$masaId]
            );
            if ($etkilenen === 0) {
                Sablon::json(['tamam' => false, 'hatalar' => ['Yalnızca kapalı masa açılabilir.']], 409);
                return;
            }
        }

        DenetimKaydi::yaz('mac_masa_durum', ['mac_masa_id' => $masaId, 'eylem' => $eylem]);
        Sablon::json(['tamam' => true]);
    }
}
