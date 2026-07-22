<?php

declare(strict_types=1);

final class AdminRaporDenetleyici
{
    public function goster(array $parametreler = []): void
    {
        AdminOturumu::zorunlu();

        // Maç bazında doluluk + ciro (son 20 maç)
        $maclar = Veritabani::satirlar(
            "SELECT m.*, ev.ad AS ev_ad, dep.ad AS dep_ad
             FROM maclar m
             JOIN takimlar ev ON ev.id = m.ev_sahibi_takim_id
             JOIN takimlar dep ON dep.id = m.deplasman_takim_id
             WHERE m.durum IN ('satista', 'satis_kapali', 'tamamlandi')
             ORDER BY m.baslangic_zamani DESC LIMIT 20"
        );

        $rapor = [];
        foreach ($maclar as $mac) {
            $macId = (int) $mac['id'];
            $doluluk = MacMasaSorgulari::dolulukOzeti($macId);

            $brut = (int) (Veritabani::deger(
                "SELECT COALESCE(SUM(o.tutar_kurus), 0)
                 FROM odemeler o JOIN rezervasyonlar r ON r.id = o.rezervasyon_id
                 WHERE r.mac_id = ? AND o.durum IN ('basarili', 'iade_edildi')",
                [$macId]
            ) ?? 0);
            $iade = (int) (Veritabani::deger(
                "SELECT COALESCE(SUM(o.iade_tutar_kurus), 0)
                 FROM odemeler o JOIN rezervasyonlar r ON r.id = o.rezervasyon_id
                 WHERE r.mac_id = ? AND o.durum = 'iade_edildi'",
                [$macId]
            ) ?? 0);
            $girenKisi = (int) (Veritabani::deger(
                "SELECT COALESCE(SUM(kisi_sayisi), 0) FROM rezervasyonlar
                 WHERE mac_id = ? AND durum = 'onaylandi' AND checkin_zamani IS NOT NULL",
                [$macId]
            ) ?? 0);
            $salonKisi = (int) (Veritabani::deger(
                "SELECT COALESCE(SUM(kisi_sayisi), 0) FROM rezervasyonlar
                 WHERE mac_id = ? AND durum = 'onaylandi' AND tur = 'salon'",
                [$macId]
            ) ?? 0);

            $rapor[] = [
                'mac'        => $mac,
                'doluluk'    => $doluluk,
                'salon_kisi' => $salonKisi,
                'giren_kisi' => $girenKisi,
                'brut'       => $brut,
                'iade'       => $iade,
                'net'        => $brut - $iade,
            ];
        }

        // Bekleyen iadeler: tahsilat başarılı ama rezervasyon iptal edilmiş
        $bekleyenIadeler = Veritabani::satirlar(
            "SELECT o.*, r.kod AS rezervasyon_kodu, r.ad_soyad
             FROM odemeler o
             JOIN rezervasyonlar r ON r.id = o.rezervasyon_id
             WHERE o.durum = 'basarili' AND r.durum = 'iptal_edildi'
             ORDER BY o.id DESC"
        );

        $mesaj = $_SESSION['tek_seferlik_mesaj'] ?? null;
        unset($_SESSION['tek_seferlik_mesaj']);
        Sablon::goster('admin/raporlar', [
            'baslik'          => 'Raporlar',
            'aktifMenu'       => 'raporlar',
            'rapor'           => $rapor,
            'bekleyenIadeler' => $bekleyenIadeler,
            'sonKayitlar'     => DenetimKaydi::sonKayitlar(30),
            'mesaj'           => $mesaj,
        ], 'duzen/admin');
    }
}
