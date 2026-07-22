<section class="bolum">
    <div class="kap kap-dar yasal-metin">
        <h1 class="bolum-baslik"><?= ikon('file-text') ?> Mesafeli Satış Sözleşmesi</h1>

        <h2>1. Taraflar</h2>
        <p><b>Satıcı:</b> <?= e($isletmeUnvani) ?> — <?= e($adres) ?> — <?= e($telefon) ?> — <?= e($vergiBilgisi) ?><br>
        <b>Alıcı:</b> Rezervasyon formunda ad-soyad, telefon ve e-posta bilgilerini veren misafir.</p>

        <h2>2. Konu</h2>
        <p>İşbu sözleşme, Alıcı'nın web sitesi üzerinden elektronik ortamda rezerve ettiği
        maç günü yeme-içme paketli oturma hizmetinin (masa veya salon girişi) satışı ve
        ifası ile ilgili olarak 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve
        Mesafeli Sözleşmeler Yönetmeliği hükümleri gereğince tarafların hak ve
        yükümlülüklerini düzenler.</p>

        <h2>3. Hizmetin Niteliği ve Bedeli</h2>
        <p>Hizmet; rezervasyon ekranında seçilen maç, masa/salon, kişi sayısı ve
        "fiyata dahil" olarak listelenen menü kalemlerinden oluşur. Toplam bedel,
        ödeme adımında kişi sayısı ile birlikte açıkça gösterilir ve online tahsil edilir.
        Paket dışı ek siparişler işletmede ayrıca ücretlendirilir.</p>

        <h2>4. İfa</h2>
        <p>Hizmet, rezervasyona konu maçın tarih ve saatinde işletme adresinde ifa edilir.
        Alıcı, girişte e-postasına gönderilen QR kodu veya rezervasyon kodunu ibraz eder.
        Yayının teknik nedenlerle (yayıncı kuruluş arızası vb.) gerçekleşememesi hâlinde
        Satıcı bedelin tamamını iade eder.</p>

        <h2>5. Cayma ve İptal Hakkı</h2>
        <p>Mesafeli Sözleşmeler Yönetmeliği m.15/1-g uyarınca belirli bir tarihte
        yapılması gereken eğlence ve dinlenme amaçlı hizmetlerde cayma hakkı istisnası
        uygulanır. Bununla birlikte Satıcı, Alıcı'ya şu sözleşmesel iptal hakkını tanır:
        <b>maç başlangıcından <?= e((string) $iptalSaat) ?> saat öncesine kadar</b>
        (maç sayfasında farklı bir süre belirtilmedikçe) bilet sayfasındaki "İptal Et"
        düğmesiyle yapılan iptallerde bedelin tamamı iade edilir. Ayrıntı:
        <a href="/yasal/iade-kosullari">İptal ve İade Koşulları</a>.</p>

        <h2>6. Uyuşmazlık</h2>
        <p>Uyuşmazlıklarda Alıcı'nın yerleşim yerindeki Tüketici Hakem Heyetleri ve
        Tüketici Mahkemeleri yetkilidir.</p>

        <p>Alıcı, ödeme adımında işbu sözleşmeyi okuduğunu ve kabul ettiğini beyan eder;
        onay zamanı elektronik olarak kayıt altına alınır.</p>
    </div>
</section>
