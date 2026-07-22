<section class="bolum">
    <div class="kap kap-dar yasal-metin">
        <h1 class="bolum-baslik"><?= ikon('shield-check') ?> KVKK Aydınlatma Metni</h1>

        <p>Bu aydınlatma metni, 6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK")
        uyarınca, veri sorumlusu sıfatıyla <b><?= e($isletmeUnvani) ?></b>
        ("İşletme") tarafından hazırlanmıştır.</p>

        <h2>1. İşlenen Kişisel Veriler</h2>
        <p>Rezervasyon sırasında yalnızca şu veriler toplanır:</p>
        <ul>
            <li>Ad ve soyad</li>
            <li>Cep telefonu numarası</li>
            <li>E-posta adresi</li>
            <li>Rezervasyon bilgileri (maç, masa, kişi sayısı, tutar)</li>
        </ul>
        <p>Ödeme kart bilgileriniz İşletme tarafından GÖRÜLMEZ ve SAKLANMAZ;
        ödeme, BDDK lisanslı ödeme kuruluşunun güvenli sayfasında gerçekleşir.</p>

        <h2>2. İşleme Amaçları ve Hukuki Sebep</h2>
        <p>Verileriniz; rezervasyonun oluşturulması ve yönetilmesi, biletin iletilmesi,
        girişte kimlik doğrulaması, iptal ve iade işlemleri ile yasal yükümlülüklerin
        yerine getirilmesi amacıyla, KVKK m.5/2-c ("sözleşmenin kurulması ve ifası")
        ve m.5/2-ç ("hukuki yükümlülük") hukuki sebeplerine dayanılarak işlenir.</p>

        <h2>3. Aktarım</h2>
        <p>Verileriniz; ödeme işlemi için ödeme kuruluşuna (iyzico), e-posta iletimi
        için barındırma sağlayıcısına aktarılır. Bunun dışında üçüncü kişilerle
        paylaşılmaz, pazarlama amacıyla kullanılmaz.</p>

        <h2>4. Saklama Süresi</h2>
        <p>Rezervasyon verileri, mali mevzuattan doğan yükümlülükler saklı kalmak
        üzere maç tarihinden itibaren en geç 2 yıl içinde silinir veya anonim hale getirilir.</p>

        <h2>5. Haklarınız</h2>
        <p>KVKK m.11 kapsamında; verilerinize erişme, düzeltilmesini veya silinmesini
        isteme, işlemeye itiraz etme haklarına sahipsiniz. Talepleriniz için:
        <b><?= e($telefon) ?></b> — <?= e($adres) ?></p>

        <p class="yasal-imza"><?= e($isletmeUnvani) ?> — <?= e($vergiBilgisi) ?></p>
    </div>
</section>
