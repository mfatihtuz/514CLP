# KURULUM

## Yerel Geliştirme

Gereksinim: PHP 8.2+ (pdo_sqlite, mbstring, curl, gd uzantılarıyla).

```bash
cp .env.ornek .env               # VT_SURUCU=sqlite hazır gelir
php -r "echo bin2hex(random_bytes(24));"   # çıktıyı .env QR_IMZA_ANAHTARI'na yazın
php betikler/vt-kur.php          # şema + 18 takım + varsayılan ayarlar
php betikler/rozet-uret.php      # arma yoksa jenerik rozetleri üretir
php betikler/admin-olustur.php eposta@ornek.com "Ad Soyad"
bash betikler/yerel-sunucu.sh    # http://localhost:8514
```

Veritabanını sıfırlamak (yalnız yerel): `php betikler/vt-kur.php --sifirla`

## Hostinger'a Kurulum (rezervasyon.mftyazilim.com)

### 1. Alt alan adı
hPanel → **Alan Adları → Alt Alan Adları** → `rezervasyon` oluşturun.
Document root olarak bu deponun `public/` klasörünü gösterin
(ör. `domains/mftyazilim.com/public_html/rezervasyon` içine depo yüklenir ve
alt alan adı kökü `.../rezervasyon/public` yapılır*).

*Alt alan adı kökü değiştirilemiyorsa: depo `public_html` DIŞINA yüklenir,
`public/` içeriği alt alan adı köküne kopyalanır ve `index.php` içindeki
`dirname(__DIR__)` yolu uygulama klasörüne göre düzeltilir.

### 2. Veritabanı
hPanel → **Veritabanları → MySQL Veritabanları** → veritabanı + kullanıcı oluşturun.
phpMyAdmin'i açın, sırasıyla **İçe Aktar** ile çalıştırın:
1. `veritabani/sema.mysql.sql`
2. `veritabani/tohum.sql`

### 3. Ortam dosyası
Sunucuda depo köküne `.env` oluşturun (FTP/Dosya Yöneticisi):
```
ORTAM=uretim
TABAN_URL=https://rezervasyon.mftyazilim.com
VT_SURUCU=mysql
VT_SUNUCU=localhost
VT_AD=<hPanel'deki veritabanı adı>
VT_KULLANICI=<veritabanı kullanıcısı>
VT_SIFRE=<şifre>
ODEME_SAGLAYICI=mock          ← iyzico anahtarları gelince "iyzico" yapılır
QR_IMZA_ANAHTARI=<48 karakter rastgele>
SMTP_SUNUCU=smtp.hostinger.com
SMTP_PORT=465
SMTP_KULLANICI=rezervasyon@mftyazilim.com
SMTP_SIFRE=<e-posta şifresi>
SMTP_GONDEREN_AD=Maç Gecesi Rezervasyon
CRON_GIZLI_ANAHTAR=<rastgele dizge>
```

### 4. E-posta hesabı
hPanel → **E-postalar** → `rezervasyon@mftyazilim.com` hesabı açın;
şifresini `.env`'e yazın. SPF/DKIM kayıtları Hostinger'da alan adı için
otomatik yönetilir.

### 5. Admin kullanıcısı
- SSH varsa: `php betikler/admin-olustur.php eposta "Ad Soyad"`
- SSH yoksa: aynı komutu yerelde MySQL bilgileriyle (`.env`'i geçici olarak
  uzak MySQL'e yönlendirerek) çalıştırın; Hostinger MySQL'i uzak bağlantıya
  hPanel → **Uzak MySQL** ekranından açılabilir.

### 6. Cron işleri (Faz 4'te aktifleşir)
hPanel → **Gelişmiş → Cron İşleri**:
```
*/10 * * * *  php /home/<kullanici>/<depo-yolu>/betikler/cron-temizlik.php
0 6 * * *     php /home/<kullanici>/<depo-yolu>/betikler/cron-fikstur.php
```

### 7. SSL
hPanel alt alan adına otomatik Let's Encrypt SSL tanımlar; **HTTPS zorunludur**
(iyzico callback'leri ve QR check-in bunun üzerinden çalışır).

## Güncelleme Yayınlama

Depo GitHub'dadır. Hostinger hPanel → **Gelişmiş → GIT** ekranından depo
bağlanarak tek tıkla çekme yapılabilir; ya da dosyalar FTP ile yüklenir.
`vendor/` depoda olduğundan sunucuda ek komut GEREKMEZ.
