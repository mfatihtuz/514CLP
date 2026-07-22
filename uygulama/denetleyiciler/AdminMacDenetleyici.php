<?php

declare(strict_types=1);

final class AdminMacDenetleyici
{
    public function liste(array $parametreler = []): void
    {
        AdminOturumu::zorunlu();
        $maclar = Veritabani::satirlar(
            "SELECT m.*, ev.ad AS ev_ad, dep.ad AS dep_ad, ev.arma_dosya AS ev_arma, dep.arma_dosya AS dep_arma
             FROM maclar m
             JOIN takimlar ev ON ev.id = m.ev_sahibi_takim_id
             JOIN takimlar dep ON dep.id = m.deplasman_takim_id
             ORDER BY m.baslangic_zamani DESC LIMIT 100"
        );
        Sablon::goster('admin/maclar/liste', [
            'baslik'    => 'Maçlar',
            'aktifMenu' => 'maclar',
            'maclar'    => $maclar,
        ], 'duzen/admin');
    }

    public function yeniForm(array $parametreler = []): void
    {
        AdminOturumu::zorunlu();
        Sablon::goster('admin/maclar/form', [
            'baslik'    => 'Yeni Maç',
            'aktifMenu' => 'maclar',
            'takimlar'  => TakimSorgulari::aktifTakimlar(),
            'mac'       => null,
            'hatalar'   => [],
            'girdi'     => $this->varsayilanGirdi(),
        ], 'duzen/admin');
    }

    public function olustur(array $parametreler = []): void
    {
        AdminOturumu::zorunlu();
        Guvenlik::csrfZorunlu();
        [$degerler, $hatalar] = $this->girdiyiIsle($_POST);

        if ($hatalar !== []) {
            Sablon::goster('admin/maclar/form', [
                'baslik'    => 'Yeni Maç',
                'aktifMenu' => 'maclar',
                'takimlar'  => TakimSorgulari::aktifTakimlar(),
                'mac'       => null,
                'hatalar'   => $hatalar,
                'girdi'     => $_POST,
            ], 'duzen/admin');
            return;
        }

        $simdi = simdiUtc();
        Veritabani::calistir(
            'INSERT INTO maclar (ev_sahibi_takim_id, deplasman_takim_id, baslangic_zamani, kapi_acilis_zamani,
                                 kisi_basi_fiyat_kurus, paket_icerigi, iptal_saat_once, paylasimli_kontenjan,
                                 durum, kaynak, olusturma_zamani, guncelleme_zamani)
             VALUES (?,?,?,?,?,?,?,?, \'taslak\', \'manuel\', ?, ?)',
            [...array_values($degerler), $simdi, $simdi]
        );
        $macId = Veritabani::sonEklenenId();
        DenetimKaydi::yaz('mac_olusturuldu', ['mac_id' => $macId]);
        Sablon::yonlendir('/admin/maclar/' . $macId);
    }

    public function detay(array $parametreler): void
    {
        AdminOturumu::zorunlu();
        $mac = MacSorgulari::idIleBul((int) $parametreler['macId']);
        if ($mac === null) {
            http_response_code(404);
            Sablon::goster('hatalar/404', ['baslik' => 'Maç bulunamadı'], 'duzen/admin');
            return;
        }
        $mesaj = $_SESSION['tek_seferlik_mesaj'] ?? null;
        unset($_SESSION['tek_seferlik_mesaj']);   // oturum yazması render'dan (kilit bırakma) ÖNCE
        Sablon::goster('admin/maclar/form', [
            'baslik'      => $mac['ev_ad'] . ' - ' . $mac['dep_ad'],
            'aktifMenu'   => 'maclar',
            'takimlar'    => TakimSorgulari::aktifTakimlar(),
            'mac'         => $mac,
            'hatalar'     => [],
            'girdi'       => $this->mactanGirdi($mac),
            'doluluk'     => $mac['durum'] !== 'taslak' ? MacMasaSorgulari::dolulukOzeti((int) $mac['id']) : null,
            'mesaj'       => $mesaj,
        ], 'duzen/admin');
    }

    public function guncelle(array $parametreler): void
    {
        AdminOturumu::zorunlu();
        Guvenlik::csrfZorunlu();
        $macId = (int) $parametreler['macId'];
        $mac = MacSorgulari::idIleBul($macId);
        if ($mac === null) {
            http_response_code(404);
            return;
        }

        [$degerler, $hatalar] = $this->girdiyiIsle($_POST);

        if ($hatalar !== []) {
            Sablon::goster('admin/maclar/form', [
                'baslik'    => 'Maç Düzenle',
                'aktifMenu' => 'maclar',
                'takimlar'  => TakimSorgulari::aktifTakimlar(),
                'mac'       => $mac,
                'hatalar'   => $hatalar,
                'girdi'     => $_POST,
            ], 'duzen/admin');
            return;
        }

        Veritabani::calistir(
            'UPDATE maclar SET ev_sahibi_takim_id=?, deplasman_takim_id=?, baslangic_zamani=?, kapi_acilis_zamani=?,
                               kisi_basi_fiyat_kurus=?, paket_icerigi=?, iptal_saat_once=?, paylasimli_kontenjan=?,
                               guncelleme_zamani=?
             WHERE id=?',
            [...array_values($degerler), simdiUtc(), $macId]
        );
        DenetimKaydi::yaz('mac_guncellendi', ['mac_id' => $macId]);
        $_SESSION['tek_seferlik_mesaj'] = 'Maç bilgileri kaydedildi.';
        Sablon::yonlendir('/admin/maclar/' . $macId);
    }

    public function yayinla(array $parametreler): void
    {
        AdminOturumu::zorunlu();
        Guvenlik::csrfZorunlu();
        $sonuc = MacYayinlama::yayinla((int) $parametreler['macId']);
        $_SESSION['tek_seferlik_mesaj'] = $sonuc['mesaj'];
        Sablon::yonlendir('/admin/maclar/' . (int) $parametreler['macId']);
    }

    public function durumDegistir(array $parametreler): void
    {
        AdminOturumu::zorunlu();
        Guvenlik::csrfZorunlu();
        $sonuc = MacYayinlama::durumDegistir(
            (int) $parametreler['macId'],
            (string) ($_POST['yeni_durum'] ?? '')
        );
        $_SESSION['tek_seferlik_mesaj'] = $sonuc['mesaj'];
        Sablon::yonlendir('/admin/maclar/' . (int) $parametreler['macId']);
    }

    /** @return array{0: array<string, mixed>, 1: array<int, string>} */
    private function girdiyiIsle(array $girdi): array
    {
        $hatalar = [];

        $evId  = (int) ($girdi['ev_sahibi_takim_id'] ?? 0);
        $depId = (int) ($girdi['deplasman_takim_id'] ?? 0);
        if ($evId < 1 || $depId < 1) {
            $hatalar[] = 'Ev sahibi ve deplasman takımlarını seçin.';
        } elseif ($evId === $depId) {
            $hatalar[] = 'Bir takım kendisiyle oynayamaz; iki farklı takım seçin.';
        }

        $baslangicUtc = null;
        $baslangicGirdi = trim((string) ($girdi['baslangic_yerel'] ?? ''));
        if ($baslangicGirdi === '') {
            $hatalar[] = 'Maç başlangıç zamanını girin.';
        } else {
            try {
                $baslangicUtc = yerelGirdiyiUtcYap($baslangicGirdi);
            } catch (Throwable) {
                $hatalar[] = 'Başlangıç zamanı geçersiz.';
            }
        }

        $kapiUtc = null;
        $kapiGirdi = trim((string) ($girdi['kapi_acilis_yerel'] ?? ''));
        if ($kapiGirdi !== '') {
            try {
                $kapiUtc = yerelGirdiyiUtcYap($kapiGirdi);
                if ($baslangicUtc !== null && $kapiUtc >= $baslangicUtc) {
                    $hatalar[] = 'Kapı açılış zamanı maç başlangıcından önce olmalı.';
                }
            } catch (Throwable) {
                $hatalar[] = 'Kapı açılış zamanı geçersiz.';
            }
        } elseif ($baslangicUtc !== null) {
            $kapiUtc = utcKaydir($baslangicUtc, -90); // varsayılan: 1,5 saat önce kapılar açılır
        }

        $fiyatKurus = tlGirdisiniKurusaCevir((string) ($girdi['kisi_basi_fiyat'] ?? ''));
        if ($fiyatKurus === null) {
            $hatalar[] = 'Kişi başı fiyat geçersiz (örnek: 500 veya 500,50 — ücretsiz maç için 0).';
        }

        $paketSatirlari = array_values(array_filter(array_map(
            'trim',
            explode("\n", (string) ($girdi['paket_icerigi'] ?? ''))
        ), static fn(string $satir): bool => $satir !== ''));

        $iptalSaat = trim((string) ($girdi['iptal_saat_once'] ?? ''));
        $iptalSaatDeger = $iptalSaat === '' ? null : (int) $iptalSaat;
        if ($iptalSaatDeger !== null && ($iptalSaatDeger < 0 || $iptalSaatDeger > 72)) {
            $hatalar[] = 'İptal penceresi 0-72 saat arasında olmalı (boş bırakılırsa genel ayar geçerlidir).';
        }

        $kontenjan = (int) ($girdi['paylasimli_kontenjan'] ?? 0);
        if ($kontenjan < 0 || $kontenjan > 200) {
            $hatalar[] = 'Paylaşımlı kontenjan 0-200 kişi arasında olmalı.';
        }

        return [[
            'ev_sahibi_takim_id'    => $evId,
            'deplasman_takim_id'    => $depId,
            'baslangic_zamani'      => $baslangicUtc,
            'kapi_acilis_zamani'    => $kapiUtc,
            'kisi_basi_fiyat_kurus' => $fiyatKurus ?? 0,
            'paket_icerigi'         => $paketSatirlari === [] ? null : json_encode($paketSatirlari, JSON_UNESCAPED_UNICODE),
            'iptal_saat_once'       => $iptalSaatDeger,
            'paylasimli_kontenjan'  => $kontenjan,
        ], $hatalar];
    }

    /** @return array<string, mixed> Formu maç kaydından doldurmak için */
    private function mactanGirdi(array $mac): array
    {
        $paket = MacSorgulari::paketIcerigi($mac);
        return [
            'ev_sahibi_takim_id'   => $mac['ev_sahibi_takim_id'],
            'deplasman_takim_id'   => $mac['deplasman_takim_id'],
            'baslangic_yerel'      => yerelGirdiBicimle((string) $mac['baslangic_zamani']),
            'kapi_acilis_yerel'    => $mac['kapi_acilis_zamani'] ? yerelGirdiBicimle((string) $mac['kapi_acilis_zamani']) : '',
            'kisi_basi_fiyat'      => $mac['kisi_basi_fiyat_kurus'] > 0 ? rtrim(rtrim(kurusOndalikMetin((int) $mac['kisi_basi_fiyat_kurus']), '0'), '.') : '0',
            'paket_icerigi'        => implode("\n", $paket),
            'iptal_saat_once'      => $mac['iptal_saat_once'],
            'paylasimli_kontenjan' => $mac['paylasimli_kontenjan'],
        ];
    }

    /** @return array<string, mixed> */
    private function varsayilanGirdi(): array
    {
        return [
            'ev_sahibi_takim_id'   => '',
            'deplasman_takim_id'   => '',
            'baslangic_yerel'      => '',
            'kapi_acilis_yerel'    => '',
            'kisi_basi_fiyat'      => '500',
            'paket_icerigi'        => implode("\n", Ayarlar::varsayilanPaket()),
            'iptal_saat_once'      => '',
            'paylasimli_kontenjan' => (int) Ayarlar::al('paylasimli_kontenjan_varsayilan', 8),
        ];
    }
}
