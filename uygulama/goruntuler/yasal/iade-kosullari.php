<section class="bolum">
    <div class="kap kap-dar yasal-metin">
        <h1 class="bolum-baslik"><?= ikon('banknote') ?> İptal ve İade Koşulları</h1>

        <h2>Ücretsiz İptal Penceresi</h2>
        <p>Rezervasyonunuzu, <b>maç başlangıcından <?= e((string) $iptalSaat) ?> saat
        öncesine kadar</b> (maç sayfasında farklı bir süre belirtilmişse o süre geçerlidir)
        hiçbir kesinti olmadan iptal edebilirsiniz. İptal için onay e-postanızdaki
        "Bileti Görüntüle" bağlantısından bilet sayfanıza girip "Rezervasyonu İptal Et"
        düğmesini kullanmanız yeterlidir.</p>

        <h2>İade Süreci</h2>
        <ul>
            <li>İptal ile birlikte iade OTOMATİK başlatılır; ayrıca talep gerekmez.</li>
            <li>İade, ödeme yaptığınız karta yapılır.</li>
            <li>Tutarın kartınıza yansıması, bankanıza bağlı olarak 1-7 iş günü sürebilir.</li>
            <li>İade onayı e-posta ile bildirilir.</li>
        </ul>

        <h2>Pencere Kapandıktan Sonra</h2>
        <p>İptal penceresi kapandıktan sonra yapılan iptal taleplerinde iade yapılmaz;
        ancak işletmeyi arayarak (<b><?= e($telefon) ?></b>) rezervasyonunuzu uygun
        başka bir maça taşımayı talep edebilirsiniz (müsaitlik dahilinde).</p>

        <h2>İşletme Kaynaklı İptaller</h2>
        <p>Maçın ertelenmesi, yayının gerçekleşememesi veya işletmenin rezervasyonu
        iptal etmesi hâlinde bedelin TAMAMI, pencere şartı aranmaksızın iade edilir.</p>

        <p class="yasal-imza"><?= e($isletmeUnvani) ?></p>
    </div>
</section>
