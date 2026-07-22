<?php

declare(strict_types=1);

final class AdminPanoDenetleyici
{
    public function goster(array $parametreler = []): void
    {
        AdminOturumu::zorunlu();

        $simdi = simdiUtc();
        $yaklasanMaclar = Veritabani::satirlar(
            "SELECT m.*, ev.ad AS ev_ad, dep.ad AS dep_ad, ev.arma_dosya AS ev_arma, dep.arma_dosya AS dep_arma
             FROM maclar m
             JOIN takimlar ev ON ev.id = m.ev_sahibi_takim_id
             JOIN takimlar dep ON dep.id = m.deplasman_takim_id
             WHERE m.durum IN ('taslak','satista','satis_kapali') AND m.baslangic_zamani > ?
             ORDER BY m.baslangic_zamani ASC LIMIT 6",
            [utcKaydir($simdi, -180)]
        );
        foreach ($yaklasanMaclar as &$mac) {
            $mac['doluluk'] = MacMasaSorgulari::dolulukOzeti((int) $mac['id']);
        }
        unset($mac);

        $sayilar = [
            'satista'    => (int) (Veritabani::deger("SELECT COUNT(*) FROM maclar WHERE durum = 'satista'") ?? 0),
            'taslak'     => (int) (Veritabani::deger("SELECT COUNT(*) FROM maclar WHERE durum = 'taslak'") ?? 0),
            'bugunRez'   => (int) (Veritabani::deger(
                "SELECT COUNT(*) FROM rezervasyonlar WHERE durum = 'onaylandi' AND olusturma_zamani > ?",
                [utcKaydir($simdi, -1440)]
            ) ?? 0),
            'masaSayisi' => (int) (Veritabani::deger('SELECT COUNT(*) FROM masalar WHERE aktif = 1') ?? 0),
        ];

        Sablon::goster('admin/pano', [
            'baslik'         => 'Pano',
            'aktifMenu'      => 'pano',
            'yaklasanMaclar' => $yaklasanMaclar,
            'sayilar'        => $sayilar,
            'sonKayitlar'    => DenetimKaydi::sonKayitlar(8),
        ], 'duzen/admin');
    }
}
