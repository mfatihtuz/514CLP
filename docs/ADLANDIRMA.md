# ADLANDIRMA SÖZLEŞMESİ

Proje sahibinin kuralı (CLAUDE.md 8-9): dokümantasyon ve kod adlandırması
Türkçe'dir; ancak identifier'lar ASCII-güvenli yazılır.

## Dönüşüm Tablosu

Identifier içinde Türkçe karakter KULLANILMAZ, şu dönüşüm uygulanır:

| Türkçe | ASCII |
|---|---|
| ı, İ | i, I |
| ş, Ş | s, S |
| ğ, Ğ | g, G |
| ö, Ö | o, O |
| ü, Ü | u, U |
| ç, Ç | c, C |

## Katman Kuralları

| Katman | Kural | Örnek |
|---|---|---|
| PHP sınıfları | BüyükDeveHarfi | `MacDenetleyici`, `OdemeSaglayici` |
| PHP fonksiyon/metod | küçükDeveHarfi | `rezervasyonOlustur()`, `masaTut()`, `holdSuresiDolduMu()` |
| PHP değişken | küçükDeveHarfi | `$kisiSayisi`, `$toplamTutarKurus` |
| Veritabanı tablo/kolon | snake_case | `mac_masalari`, `tutma_sona_erme` |
| URL yolları | küçük-tire | `/rezervasyon-sorgula`, `/admin/maclar` |
| CSS sınıfları | küçük-tire | `.mac-kart`, `.takim-arma` |
| JS fonksiyon/değişken | küçükDeveHarfi | `krokiCiz()`, `masaSec()` |
| Görünüm dosyaları | küçük-tire | `goruntuler/anasayfa/liste.php` |
| Dokümantasyon | BÜYÜK Türkçe | `MIMARI.md`, `KURULUM.md` |

## İstisnalar

- Dilin/aracın dayattığı adlar olduğu gibi kalır: `index.php`, `.htaccess`,
  `composer.json`, `vendor/`, PHP dahili fonksiyonları, PHPMailer API'si.
- UI METİNLERİ tam Türkçe'dir (ı/ş/ğ serbest): "Masanı Ayırt", "Ödeme Yap".
- Dış API alan adları sağlayıcının istediği gibi gönderilir
  (ör. iyzico `conversationId`), ama bizim tarafımızdaki kolon adı Türkçe'dir
  (`konusma_kimligi`).
