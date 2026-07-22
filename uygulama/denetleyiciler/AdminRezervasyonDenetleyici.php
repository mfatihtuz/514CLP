<?php

declare(strict_types=1);

final class AdminRezervasyonDenetleyici
{
    public function liste(array $parametreler = []): void
    {
        AdminOturumu::zorunlu();

        $macId = (int) ($_GET['mac'] ?? 0);
        $durum = (string) ($_GET['durum'] ?? '');
        $arama = trim((string) ($_GET['ara'] ?? ''));

        $kosullar = [];
        $degerler = [];
        if ($macId > 0) {
            $kosullar[] = 'r.mac_id = ?';
            $degerler[] = $macId;
        }
        if (in_array($durum, ['onaylandi', 'odeme_bekliyor', 'iptal_edildi', 'suresi_doldu'], true)) {
            $kosullar[] = 'r.durum = ?';
            $degerler[] = $durum;
        }
        if ($arama !== '') {
            $kosullar[] = '(r.kod LIKE ? OR r.ad_soyad LIKE ? OR r.telefon LIKE ? OR r.eposta LIKE ?)';
            $aramaDeger = '%' . $arama . '%';
            array_push($degerler, strtoupper($aramaDeger), $aramaDeger, $aramaDeger, $aramaDeger);
        }
        $nerede = $kosullar === [] ? '' : 'WHERE ' . implode(' AND ', $kosullar);

        $rezervasyonlar = Veritabani::satirlar(
            "SELECT r.*, ev.kisa_ad AS ev_kisa, dep.kisa_ad AS dep_kisa, m.baslangic_zamani
             FROM rezervasyonlar r
             JOIN maclar m ON m.id = r.mac_id
             JOIN takimlar ev ON ev.id = m.ev_sahibi_takim_id
             JOIN takimlar dep ON dep.id = m.deplasman_takim_id
             {$nerede}
             ORDER BY r.id DESC LIMIT 200",
            $degerler
        );

        // Masa adlarını toplu çek (N+1 önleme)
        $masaHaritasi = [];
        if ($rezervasyonlar !== []) {
            $idListesi = implode(',', array_map(static fn(array $satir): int => (int) $satir['id'], $rezervasyonlar));
            foreach (Veritabani::satirlar(
                "SELECT rm.rezervasyon_id, mm.ad
                 FROM rezervasyon_masalari rm JOIN mac_masalari mm ON mm.id = rm.mac_masa_id
                 WHERE rm.rezervasyon_id IN ({$idListesi}) ORDER BY mm.ad"
            ) as $bag) {
                $masaHaritasi[(int) $bag['rezervasyon_id']][] = (string) $bag['ad'];
            }
        }

        $maclar = Veritabani::satirlar(
            "SELECT m.id, m.baslangic_zamani, ev.kisa_ad AS ev_kisa, dep.kisa_ad AS dep_kisa
             FROM maclar m
             JOIN takimlar ev ON ev.id = m.ev_sahibi_takim_id
             JOIN takimlar dep ON dep.id = m.deplasman_takim_id
             ORDER BY m.baslangic_zamani DESC LIMIT 30"
        );

        $mesaj = $_SESSION['tek_seferlik_mesaj'] ?? null;
        unset($_SESSION['tek_seferlik_mesaj']);
        Sablon::goster('admin/rezervasyonlar', [
            'baslik'         => 'Rezervasyonlar',
            'aktifMenu'      => 'rezervasyonlar',
            'rezervasyonlar' => $rezervasyonlar,
            'masaHaritasi'   => $masaHaritasi,
            'maclar'         => $maclar,
            'secilen'        => ['mac' => $macId, 'durum' => $durum, 'ara' => $arama],
            'mesaj'          => $mesaj,
        ], 'duzen/admin');
    }

    /** Admin iptali: pencere kontrolü YOK, iade otomatik, denetim kaydı zorunlu. */
    public function iptalEt(array $parametreler): void
    {
        AdminOturumu::zorunlu();
        Guvenlik::csrfZorunlu();
        $rezervasyon = Veritabani::satir('SELECT * FROM rezervasyonlar WHERE id = ?', [(int) $parametreler['rezervasyonId']]);
        if ($rezervasyon === null) {
            http_response_code(404);
            return;
        }

        try {
            if ((int) $rezervasyon['toplam_tutar_kurus'] > 0) {
                OdemeYonetici::iadeliIptal((string) $rezervasyon['kod'], false);
            } else {
                RezervasyonIslemleri::iptalEt((string) $rezervasyon['kod'], false);
                RezervasyonEpostalari::iptalGonder((string) $rezervasyon['kod'], 0);
            }
            DenetimKaydi::yaz('admin_iptal', ['kod' => $rezervasyon['kod']]);
            $_SESSION['tek_seferlik_mesaj'] = $rezervasyon['kod'] . ' iptal edildi'
                . ((int) $rezervasyon['toplam_tutar_kurus'] > 0 ? ' ve iade başlatıldı.' : '.');
        } catch (RezervasyonHatasi $hata) {
            $_SESSION['tek_seferlik_mesaj'] = 'İptal edilemedi: ' . $hata->getMessage();
        }

        Sablon::yonlendir('/admin/rezervasyonlar' . ($_POST['donus'] ?? ''));
    }

    /** Bekleyen iadeyi yeniden dener (raporlar ekranından). */
    public function iadeTekrarDene(array $parametreler): void
    {
        AdminOturumu::zorunlu();
        Guvenlik::csrfZorunlu();
        $sonuc = OdemeYonetici::iadeTekrarDene((int) $parametreler['odemeId']);
        $_SESSION['tek_seferlik_mesaj'] = $sonuc['mesaj'];
        Sablon::yonlendir('/admin/raporlar');
    }

    /** Fikstürü elle tetikleme (Maçlar ekranındaki buton). */
    public function fiksturGuncelle(array $parametreler = []): void
    {
        AdminOturumu::zorunlu();
        Guvenlik::csrfZorunlu();
        // Fikstür dış siteye bağlanır (uzun sürebilir); bu sırada oturum kilidini
        // TUTMA ki paneldeki diğer tıklamalar donmasın. Sonra flash için geri al.
        oturumKilidiniBirak();
        $sonuc = FiksturCekici::calistir();
        @session_start();
        $mesaj = sprintf(
            'Fikstür: %d taslak eklendi, %d saat güncellendi, %d atlandı.',
            $sonuc['eklenen'],
            $sonuc['guncellenen'],
            $sonuc['atlanan']
        );
        if ($sonuc['hatalar'] !== []) {
            $mesaj .= ' Uyarılar: ' . implode(' | ', array_slice($sonuc['hatalar'], 0, 3));
        }
        $_SESSION['tek_seferlik_mesaj'] = $mesaj;
        Sablon::yonlendir('/admin/maclar');
    }
}
