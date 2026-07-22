# HOSTINGER'A KURULUM (Hazır Paket)

Bu, `public_html`'e sürükle-bırak yüklenip tarayıcıdan tek tık kurulan
"düz yerleşim" paketidir. hPanel'de document root ayarıyla uğraşmak GEREKMEZ.

Paket `betikler/dagitim-hazirla.sh` ile üretilir; içinde ayarları önceden
doldurulmuş `.env` ve tek seferlik `kurulum.php` sihirbazı bulunur.
Gizli anahtarlar (QR imza, cron, kurulum) her üretimde yeniden oluşturulur ve
YALNIZCA pakete yazılır — bu depoya asla girmez.

## Yerleşim

Paket açıldığında dosyalar köktedir:

```
index.php          ← düz ön denetleyici (uygulama/ ile aynı seviyede)
.htaccess          ← yönlendirme + sertleştirme (hassas klasör/dosya erişimi kapalı)
.env               ← üretim ayarları (DB, gizli anahtarlar) — web'e kapalı
kurulum.php        ← tek seferlik sihirbaz (kurulum sonrası SİLİNİR)
varliklar/ armalar/ simge.svg   ← public varlıklar
uygulama/ vendor/ veritabani/ betikler/ docs/   ← her birinde "deny" .htaccess
```

Güvenlik: `.env`, `veritabani/*.sql`, `uygulama/*` gibi hassas içerikler hem kök
`.htaccess` kuralları hem de her klasördeki `Require all denied` ile web'e
kapalıdır; PHP bunları dosya sisteminden okumaya devam eder.

## Adımlar

1. **Yükle:** Paketi aç, TÜM içeriği (gizli `.htaccess` ve `.env` dahil) FTP ile
   `public_html` (subdomain kök) klasörüne yükle. Varsa eski `index.html` /
   "coming soon" dosyasını sil.
2. **Kur:** Tarayıcıda kurulum sihirbazını aç (adres + anahtar ayrıca verilir):
   `https://rezervasyon.mftyazilim.com/kurulum.php?anahtar=...`
   Ekranda DB bağlantısı doğrulanır, tablolar + 18 takım kurulur, admin hesabını
   oluşturursun.
3. **Sil:** Sihirbaz "tamamlandı" der demez `kurulum.php` dosyasını sunucudan SİL.
4. **Gir:** `https://rezervasyon.mftyazilim.com/admin/giris`

## Kurulum sonrası ayarlar (panelden veya .env)

- **E-posta (onay + QR gönderimi):** hPanel'de `rezervasyon@mftyazilim.com`
  hesabı aç; `.env` içindeki `SMTP_KULLANICI` ve `SMTP_SIFRE` satırlarını doldur.
  Doldurulana kadar onay e-postaları gönderilmez; müşteri bileti yine ekrandaki
  bağlantıdan görür.
- **Sanal POS:** iyzico anahtarları gelene kadar maçları **ücretsiz (0 TL)**
  aç — ödeme adımı otomatik atlanır. Anahtar gelince `.env`'de
  `IYZICO_API_ANAHTARI` / `IYZICO_GIZLI_ANAHTAR` doldur, sandbox testinden sonra
  `IYZICO_TEMEL_URL`'i canlıya çevir.
- **İşletme bilgileri:** Panel → Ayarlar'dan işletme ünvanı, vergi bilgisi,
  adres, telefon gir (yasal sayfalar ve iyzico başvurusu için gerekli).

## Cron (opsiyonel ama önerilir)

hPanel → Gelişmiş → Cron İşleri. PHP CLI varsa:

```
*/10 * * * *  /usr/bin/php /home/KULLANICI/domains/mftyazilim.com/public_html/betikler/cron-temizlik.php
0 6 * * *     /usr/bin/php /home/KULLANICI/domains/mftyazilim.com/public_html/betikler/cron-fikstur.php
```

PHP CLI yoksa URL ile (anahtar `.env`'deki `CRON_GIZLI_ANAHTAR`):

```
*/10 * * * *  wget -qO- "https://rezervasyon.mftyazilim.com/cron/temizlik?anahtar=..."
0 6 * * *     wget -qO- "https://rezervasyon.mftyazilim.com/cron/fikstur?anahtar=..."
```

Not: Sistem doğruluğu cron'a bağlı değildir (süresi dolan masa kilidi okuma
anında serbest sayılır); cron yalnız tablo hijyeni ve fikstür içindir.

## Güvenlik hatırlatmaları

- Kurulumdan sonra `kurulum.php`'yi sil.
- FTP ve MySQL şifreni sohbet/paylaşım sonrası hPanel'den değiştir.
