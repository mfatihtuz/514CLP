<?php

declare(strict_types=1);

/**
 * Ana kat planı (masalar tablosu) sorguları ve kroki doğrulama kuralları.
 * Kroki 24 sütun x 16 satırlık sanal grid üzerindedir.
 */
final class MasaSorgulari
{
    public const GRID_GENISLIK = 24;
    public const GRID_YUKSEKLIK = 16;

    /** @return array<int, array<string, mixed>> */
    public static function anaPlan(): array
    {
        return Veritabani::satirlar('SELECT * FROM masalar WHERE aktif = 1 ORDER BY ad');
    }

    /**
     * Editörden gelen tüm planı doğrular; hata listesi döner (boşsa geçerli).
     * @param array<int, array<string, mixed>> $masalar
     * @return array<int, string>
     */
    public static function planDogrula(array $masalar): array
    {
        $hatalar = [];
        $adlar = [];

        foreach ($masalar as $sira => $masa) {
            $etiket = 'Masa ' . ($masa['ad'] ?? ('#' . ($sira + 1)));
            $ad = trim((string) ($masa['ad'] ?? ''));

            if ($ad === '' || mb_strlen($ad) > 12) {
                $hatalar[] = "{$etiket}: ad boş olamaz, en fazla 12 karakter olabilir.";
            }
            if (isset($adlar[mb_strtoupper($ad)])) {
                $hatalar[] = "{$etiket}: aynı adda iki masa olamaz.";
            }
            $adlar[mb_strtoupper($ad)] = true;

            $kapasite = (int) ($masa['kapasite'] ?? 0);
            $minKisi  = (int) ($masa['min_kisi'] ?? 0);
            if ($kapasite < 1 || $kapasite > 12) {
                $hatalar[] = "{$etiket}: kapasite 1-12 arasında olmalı.";
            }
            if ($minKisi < 1 || $minKisi > $kapasite) {
                $hatalar[] = "{$etiket}: minimum kişi 1 ile kapasite arasında olmalı.";
            }
            if (!in_array($masa['sekil'] ?? '', ['kare', 'yuvarlak'], true)) {
                $hatalar[] = "{$etiket}: şekil kare veya yuvarlak olmalı.";
            }

            [$x, $y, $g, $yk] = self::konumOku($masa);
            if ($g < 2 || $g > 6 || $yk < 2 || $yk > 6) {
                $hatalar[] = "{$etiket}: masa boyutu 2-6 hücre arasında olmalı.";
            }
            if ($x < 0 || $y < 0 || $x + $g > self::GRID_GENISLIK || $y + $yk > self::GRID_YUKSEKLIK) {
                $hatalar[] = "{$etiket}: kroki alanının dışına taşıyor.";
            }
        }

        // Çakışma kontrolü (dikdörtgen kesişimi) — sunucu tarafı son sözü söyler
        $sayi = count($masalar);
        for ($i = 0; $i < $sayi; $i++) {
            for ($j = $i + 1; $j < $sayi; $j++) {
                if (self::cakisiyorMu($masalar[$i], $masalar[$j])) {
                    $hatalar[] = sprintf(
                        'Masa %s ile %s üst üste biniyor.',
                        $masalar[$i]['ad'] ?? ('#' . ($i + 1)),
                        $masalar[$j]['ad'] ?? ('#' . ($j + 1))
                    );
                }
            }
        }

        return $hatalar;
    }

    /**
     * Ana planı editörden gelen listeyle eşitler: güncelle / ekle / kaldır.
     * Kaldırma gerçek silmedir; yayınlanmış maçlar etkilenmez (onlar kopya kullanır).
     * @param array<int, array<string, mixed>> $masalar
     */
    public static function planKaydet(array $masalar): void
    {
        Veritabani::islem(function () use ($masalar): void {
            $mevcutIdler = array_map(
                static fn(array $satir): int => (int) $satir['id'],
                Veritabani::satirlar('SELECT id FROM masalar')
            );
            $gelenIdler = [];

            foreach ($masalar as $masa) {
                [$x, $y, $g, $yk] = self::konumOku($masa);
                $degerler = [
                    trim((string) $masa['ad']),
                    (int) $masa['kapasite'],
                    (int) $masa['min_kisi'],
                    (string) $masa['sekil'],
                    $x, $y, $g, $yk,
                ];
                $id = isset($masa['id']) && $masa['id'] !== null ? (int) $masa['id'] : null;

                if ($id !== null && in_array($id, $mevcutIdler, true)) {
                    $gelenIdler[] = $id;
                    Veritabani::calistir(
                        'UPDATE masalar SET ad=?, kapasite=?, min_kisi=?, sekil=?, konum_x=?, konum_y=?, genislik=?, yukseklik=?, aktif=1 WHERE id=?',
                        [...$degerler, $id]
                    );
                } else {
                    Veritabani::calistir(
                        'INSERT INTO masalar (ad, kapasite, min_kisi, sekil, konum_x, konum_y, genislik, yukseklik, aktif) VALUES (?,?,?,?,?,?,?,?,1)',
                        $degerler
                    );
                    $gelenIdler[] = Veritabani::sonEklenenId();
                }
            }

            foreach (array_diff($mevcutIdler, $gelenIdler) as $silinecekId) {
                Veritabani::calistir('DELETE FROM masalar WHERE id = ?', [$silinecekId]);
            }
        });
    }

    /** @return array{0:int,1:int,2:int,3:int} x, y, genişlik, yükseklik */
    public static function konumOku(array $masa): array
    {
        return [
            (int) ($masa['konum_x'] ?? $masa['x'] ?? 0),
            (int) ($masa['konum_y'] ?? $masa['y'] ?? 0),
            (int) ($masa['genislik'] ?? $masa['g'] ?? 2),
            (int) ($masa['yukseklik'] ?? $masa['yk'] ?? 2),
        ];
    }

    private static function cakisiyorMu(array $a, array $b): bool
    {
        [$ax, $ay, $ag, $ayk] = self::konumOku($a);
        [$bx, $by, $bg, $byk] = self::konumOku($b);
        return $ax < $bx + $bg && $bx < $ax + $ag && $ay < $by + $byk && $by < $ay + $ayk;
    }
}
