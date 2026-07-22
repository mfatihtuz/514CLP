<section class="bolum">
    <div class="kap kap-dar yasal-metin">
        <h1 class="bolum-baslik"><?= ikon('info') ?> Ön Bilgilendirme Formu</h1>

        <p>Mesafeli Sözleşmeler Yönetmeliği m.5 uyarınca, siparişin onaylanmasından
        önce tüketiciye aşağıdaki bilgiler sunulur.</p>

        <h2>Satıcı Bilgileri</h2>
        <p><?= e($isletmeUnvani) ?><br><?= e($adres) ?><br><?= e($telefon) ?><br><?= e($vergiBilgisi) ?></p>

        <h2>Hizmetin Temel Nitelikleri</h2>
        <p>Seçilen maçın işletmede dev ekranda izlenmesi için masa veya salon girişi
        rezervasyonu. Rezervasyon ekranında listelenen menü kalemleri
        (örn. dürüm + ayran + sınırsız çay) bedele dahildir. Masa numarası,
        kişi sayısı, maç bilgisi ve toplam bedel ödeme öncesi özet ekranında gösterilir.</p>

        <h2>Bedel ve Ödeme</h2>
        <p>Bedel, kişi başı fiyat × kişi sayısı olarak hesaplanır; tüm vergiler dahildir.
        Ödeme, lisanslı ödeme kuruluşunun (iyzico) güvenli sayfasında 3D Secure ile
        kredi/banka kartından tahsil edilir. Kart bilgileri Satıcı'ya iletilmez.</p>

        <h2>İfa ve Teslim</h2>
        <p>Hizmet, maç tarih-saatinde işletme adresinde sunulur. QR kodlu bilet,
        ödemenin ardından anında e-posta ile gönderilir ve bilet sayfasından erişilebilir.</p>

        <h2>Cayma Hakkı ve İptal</h2>
        <p>Belirli tarihli eğlence hizmeti istisnası (Yönetmelik m.15/1-g) geçerlidir;
        bununla birlikte maç başlangıcından <?= e((string) $iptalSaat) ?> saat öncesine
        kadar ücretsiz iptal ve tam iade hakkı tanınır
        (<a href="/yasal/iade-kosullari">İptal ve İade Koşulları</a>).</p>

        <h2>Şikayet ve Başvuru</h2>
        <p>Şikayetlerinizi önce işletmeye iletebilir, çözülmemesi hâlinde Tüketici
        Hakem Heyeti'ne veya Tüketici Mahkemesi'ne başvurabilirsiniz.</p>
    </div>
</section>
