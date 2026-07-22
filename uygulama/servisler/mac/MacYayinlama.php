<?php

declare(strict_types=1);

/**
 * Maç yaşam döngüsü işlemleri: yayınlama (ana plan kopyalama) ve durum geçişleri.
 * Kural (docs/VERITABANI.md): kroki snapshot'ı yayınlama ANINDA alınır;
 * sonrasında maçın krokisi ana plandan bağımsızdır.
 */
final class MacYayinlama
{
    /** @return array{tamam: bool, mesaj: string} */
    public static function yayinla(int $macId): array
    {
        return Veritabani::islem(function () use ($macId): array {
            $mac = Veritabani::satir('SELECT * FROM maclar WHERE id = ?', [$macId]);
            if ($mac === null) {
                return ['tamam' => false, 'mesaj' => 'Maç bulunamadı.'];
            }
            if ($mac['durum'] !== 'taslak') {
                return ['tamam' => false, 'mesaj' => 'Yalnızca taslak durumundaki maç yayınlanabilir.'];
            }

            $kopyaVar = (int) (Veritabani::deger(
                'SELECT COUNT(*) FROM mac_masalari WHERE mac_id = ?', [$macId]
            ) ?? 0);
            if ($kopyaVar > 0) {
                return ['tamam' => false, 'mesaj' => 'Bu maç için masa kopyaları zaten var.'];
            }

            $anaPlan = MasaSorgulari::anaPlan();
            if ($anaPlan === [] && (int) $mac['paylasimli_kontenjan'] === 0) {
                return ['tamam' => false, 'mesaj' => 'Ana kat planı boş ve paylaşımlı kontenjan 0. Önce Kroki ekranından masa ekleyin.'];
            }

            foreach ($anaPlan as $masa) {
                Veritabani::calistir(
                    'INSERT INTO mac_masalari
                        (mac_id, kaynak_masa_id, ad, kapasite, min_kisi, sekil, konum_x, konum_y, genislik, yukseklik, durum)
                     VALUES (?,?,?,?,?,?,?,?,?,?, \'bos\')',
                    [
                        $macId, $masa['id'], $masa['ad'], $masa['kapasite'], $masa['min_kisi'],
                        $masa['sekil'], $masa['konum_x'], $masa['konum_y'], $masa['genislik'], $masa['yukseklik'],
                    ]
                );
            }

            Veritabani::calistir(
                "UPDATE maclar SET durum = 'satista', guncelleme_zamani = ? WHERE id = ?",
                [simdiUtc(), $macId]
            );

            DenetimKaydi::yaz('mac_yayinlandi', ['mac_id' => $macId, 'masa_sayisi' => count($anaPlan)]);
            return ['tamam' => true, 'mesaj' => 'Maç satışa açıldı: ' . count($anaPlan) . ' masa kopyalandı.'];
        });
    }

    /** Satışı geçici kapatma / yeniden açma / iptal gibi durum geçişleri.
     *  @return array{tamam: bool, mesaj: string} */
    public static function durumDegistir(int $macId, string $yeniDurum): array
    {
        $izinli = [
            'satista'      => ['satis_kapali'],           // satışı durdur
            'satis_kapali' => ['satista', 'tamamlandi'],  // yeniden aç veya kapat
        ];
        // iptal: taslak/satista/satis_kapali her durumdan mümkün ama rezervasyon şartına bağlı
        $mac = Veritabani::satir('SELECT * FROM maclar WHERE id = ?', [$macId]);
        if ($mac === null) {
            return ['tamam' => false, 'mesaj' => 'Maç bulunamadı.'];
        }

        $eski = (string) $mac['durum'];
        $gecerli = ($yeniDurum === 'iptal' && in_array($eski, ['taslak', 'satista', 'satis_kapali'], true))
            || in_array($yeniDurum, $izinli[$eski] ?? [], true);
        if (!$gecerli) {
            return ['tamam' => false, 'mesaj' => "Geçersiz durum geçişi: {$eski} → {$yeniDurum}"];
        }

        if ($yeniDurum === 'iptal') {
            $onayliSayi = (int) (Veritabani::deger(
                "SELECT COUNT(*) FROM rezervasyonlar WHERE mac_id = ? AND durum = 'onaylandi'",
                [$macId]
            ) ?? 0);
            if ($onayliSayi > 0) {
                return [
                    'tamam' => false,
                    'mesaj' => "Bu maçta {$onayliSayi} onaylı rezervasyon var. Önce rezervasyonları iptal/iade edin (Faz 4 toplu iade ekranı).",
                ];
            }
        }

        Veritabani::calistir(
            'UPDATE maclar SET durum = ?, guncelleme_zamani = ? WHERE id = ?',
            [$yeniDurum, simdiUtc(), $macId]
        );
        DenetimKaydi::yaz('mac_durum_degisti', ['mac_id' => $macId, 'eski' => $eski, 'yeni' => $yeniDurum]);
        return ['tamam' => true, 'mesaj' => 'Maç durumu güncellendi.'];
    }
}
