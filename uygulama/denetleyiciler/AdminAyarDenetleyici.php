<?php

declare(strict_types=1);

final class AdminAyarDenetleyici
{
    /** Ayarlar ekranındaki alanlar: anahtar → [etiket, tür, açıklama] */
    private const ALANLAR = [
        'restoran_adi'                    => ['Restoran adı', 'metin', 'Sitede ve e-postalarda görünen işletme adı.'],
        'site_adi'                        => ['Site adı', 'metin', 'Tarayıcı sekmesi ve marka alanı.'],
        'iletisim_telefon'                => ['İletişim telefonu', 'metin', 'Alt bilgide gösterilir.'],
        'iletisim_adres'                  => ['Adres', 'metin', 'Alt bilgide gösterilir.'],
        'isletme_unvani'                  => ['İşletme ticari unvanı', 'metin', 'Yasal sayfalarda ve alt bilgide görünür (ör. X Gıda Ltd. Şti.). iyzico başvurusu için zorunludur.'],
        'vergi_bilgisi'                   => ['Vergi dairesi ve numarası', 'metin', 'Yasal sayfalarda görünür (ör. Kadıköy VD 1234567890).'],
        'varsayilan_iptal_saat'           => ['İptal penceresi (saat)', 'sayi', 'Maç başlangıcından kaç saat öncesine kadar ücretsiz iptal edilebilir. Maç bazında değiştirilebilir.'],
        'hold_dakika'                     => ['Masa bekletme süresi (dakika)', 'sayi', 'Müşteri masa seçince ödeme için tanınan süre.'],
        'paylasimli_kontenjan_varsayilan' => ['Paylaşımlı kontenjan varsayılanı (kişi)', 'sayi', 'Yeni maçta önerilecek "Salon Girişi" kapasitesi. 1-2 kişilik gruplar masa seçemez, bu kontenjandan yer alır.'],
        'masa_min_kisi_varsayilan_fark'   => ['Masa minimum kişi farkı', 'sayi', 'Yeni masada minimum grup = kapasite - bu değer (4 kişilik masa, fark 1 → en az 3 kişi).'],
    ];

    public function form(array $parametreler = []): void
    {
        AdminOturumu::zorunlu();
        $degerler = [];
        foreach (array_keys(self::ALANLAR) as $anahtar) {
            $degerler[$anahtar] = Ayarlar::al($anahtar, '');
        }
        Sablon::goster('admin/ayarlar', [
            'baslik'    => 'Ayarlar',
            'aktifMenu' => 'ayarlar',
            'alanlar'   => self::ALANLAR,
            'degerler'  => $degerler,
            'paket'     => implode("\n", Ayarlar::varsayilanPaket()),
            'mesaj'     => $_SESSION['tek_seferlik_mesaj'] ?? null,
        ], 'duzen/admin');
        unset($_SESSION['tek_seferlik_mesaj']);
    }

    public function kaydet(array $parametreler = []): void
    {
        AdminOturumu::zorunlu();
        Guvenlik::csrfZorunlu();

        foreach (self::ALANLAR as $anahtar => [$etiket, $tur]) {
            if (!isset($_POST[$anahtar])) {
                continue;
            }
            $ham = trim((string) $_POST[$anahtar]);
            if ($tur === 'sayi') {
                $deger = max(0, (int) $ham);
            } else {
                $deger = mb_substr($ham, 0, 200);
            }
            Ayarlar::kaydet($anahtar, $deger);
        }

        $paketSatirlari = array_values(array_filter(array_map(
            'trim',
            explode("\n", (string) ($_POST['varsayilan_paket'] ?? ''))
        ), static fn(string $satir): bool => $satir !== ''));
        Ayarlar::kaydet('varsayilan_paket', $paketSatirlari);

        DenetimKaydi::yaz('ayarlar_guncellendi', []);
        $_SESSION['tek_seferlik_mesaj'] = 'Ayarlar kaydedildi.';
        Sablon::yonlendir('/admin/ayarlar');
    }
}
