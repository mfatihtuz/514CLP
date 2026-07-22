<div class="admin-kart-baslik">
    <h1 class="admin-sayfa-baslik"><?= ikon('armchair') ?> Kat Planı</h1>
    <span class="metin-soluk">Üst katın ana masa düzeni. Yeni maç yayınlanırken bu plan maça kopyalanır;
        buradaki değişiklikler yayınlanmış maçları ETKİLEMEZ.</span>
</div>

<div class="admin-kart">
    <div id="kroki-editor"
         data-veri-url="/admin/api/kroki"
         data-kaydet-url="/admin/api/kroki"
         data-mod="ana"
         data-csrf="<?= e(Guvenlik::csrfJetonu()) ?>"></div>
</div>

<script src="/varliklar/js/kroki-editor.js"></script>
<script>krokiEditorBaslat(document.getElementById('kroki-editor'));</script>
