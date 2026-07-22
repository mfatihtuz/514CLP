# MİMARİ

Maç günü rezervasyon sistemi: restoranın üst katında yayınlanan maçlar için
sinema mantığında masa seçmeli, online ödemeli rezervasyon sitesi.

## Teknoloji Yığını

| Katman | Seçim | Neden |
|---|---|---|
| Sunucu | PHP 8.2+ (framework'süz, tek giriş noktalı) | Hostinger paylaşımlı hosting'de sıfır kurulum; bu ölçek (maç başına 50-70 kişi) için en bakımı kolay yol |
| Veritabanı | MySQL 8 / MariaDB (üretim), SQLite (yerel geliştirme) | Hostinger'da phpMyAdmin ile yönetim; SQLite ile sunucusuz yerel test |
| Ön yüz | Sunucu tarafı PHP şablonları + vanilla JavaScript | Build adımı yok; kroki gibi etkileşimli ekranlar saf JS bileşenleridir |
| Ödeme | `OdemeSaglayici` arayüzü → iyzico (birincil), Mock (geliştirme) | Sanal POS değiştirilebilir olmalı (CLAUDE.md kural 11) |
| E-posta | PHPMailer + Hostinger SMTP (rezervasyon@mftyazilim.com) | Üçüncü servis bağımlılığı yok, kendi alan adından gönderim |
| QR | chillerlan/php-qrcode (vendor'da) | Sunucu tarafı üretim, e-postaya gömme |
| Zamanlanmış işler | hPanel cron (5 dk'ya kadar sıklık) | Hold temizliği + fikstür çekme |

Bağımlılıklar `vendor/` klasörüyle git'e DAHİLDİR: Hostinger'a dosya yüklemek
kurulum için yeterlidir, sunucuda Composer gerekmez.

## Dizin Yapısı

```
public/            ← Web kökü (subdomain document root BURAYA bağlanır)
  index.php        ← Ön denetleyici; tüm istekler buradan geçer (.htaccess)
  armalar/         ← Takım armaları (SVG)
  varliklar/       ← css / fontlar / ikonlar (Lucide SVG)
uygulama/          ← Web kökü DIŞINDA duran uygulama kodu
  baslat.php       ← Önyükleme: autoload, .env, oturum
  rotalar.php      ← Tüm rota tanımları
  cekirdek/        ← Yonlendirici, Veritabani, Sablon, Guvenlik, Cevre
  denetleyiciler/  ← İstek işleyicileri (…Denetleyici.php)
  modeller/        ← SQL sorgu katmanı (…Sorgulari.php, Ayarlar)
  servisler/       ← odeme/, eposta/, qr/, fikstur/ (Faz 2-4'te dolar)
  yardimcilar/     ← tarih.php, para.php, metin.php (düz fonksiyonlar)
  goruntuler/      ← PHP şablonları (duzen/, anasayfa/, hatalar/ …)
veritabani/        ← sema.mysql.sql (üretim), sema.sqlite.sql (yerel), tohum.sql
betikler/          ← CLI: vt-kur, admin-olustur, rozet-uret, armalari-indir, cron-*
docs/              ← Bu dokümantasyon
```

## Temel Kararlar

1. **Para daima kuruş cinsinden tam sayı.** Ondalık TL yalnız görüntüleme ve
   sağlayıcı API sınırında oluşur (`yardimcilar/para.php`).
2. **Zaman daima UTC saklanır.** Görüntüleme Europe/Istanbul; tüm dönüşümler
   `yardimcilar/tarih.php` üzerinden (başka yerde tarih biçimleme YASAK).
3. **Çift rezervasyon veritabanı seviyesinde engellenir.** Masa kilitleme
   koşullu UPDATE (compare-and-swap) desenidir; ayrıntı: `docs/VERITABANI.md`.
   Uygulama katmanı kontrolüne asla güvenilmez.
4. **Kat planı maça kopyalanır (snapshot).** `masalar` ana şablondur; maç
   satışa alınırken `mac_masalari`na kopyalanır. Maça özel masa ekleme/kapatma
   ana planı ve geçmiş maçları etkilemez.
5. **Masa seçme hakkı grup büyüklüğüne bağlıdır.** Her masada `min_kisi` alanı
   vardır (varsayılan: kapasite - 1; 4 kişilik masa → en az 3 kişi).
   1-2 kişilik gruplar masa seçemez; maçın `paylasimli_kontenjan`ından
   "Salon Girişi" bileti alır, yerini işletme belirler.
6. **Ücretsiz maç desteklenir.** `kisi_basi_fiyat_kurus = 0` → ödeme adımı
   atlanır, rezervasyon doğrudan onaylanır (pazarlama amaçlı küçük maçlar).
7. **Fikstür yarı otomatiktir.** Cron üç büyüklerin maçlarını çeker, TASLAK
   olarak panele düşürür; fiyatlandırıp yayınlamak daima admin kararıdır.

## Faz Durumu

- [x] Faz 0 — İskelet: çekirdek, şema, tasarım sistemi, ana sayfa
- [x] Faz 1 — Admin: giriş, maç CRUD, kroki editörü, yayınlama, ayarlar
- [x] Faz 2 — Müşteri: masa seçimi, hold, rezervasyon, QR, e-posta
- [x] Faz 3 — Ödeme: Mock + iyzico CheckoutForm, iade
- [x] Faz 4 — Operasyon: check-in, raporlar, cron'lar
- [x] Faz 5 — Cila: yasal sayfalar, geri sayım, hata sayfaları

## Canlıya Çıkış Öncesi Bekleyenler (kullanıcı aksiyonu gerekir)

1. **Gerçek armalar:** `betikler/armalari-indir.sh` normal bir bilgisayarda
   çalıştırılıp `public/armalar/` commit'lenir (bu geliştirme ortamının ağı
   Wikimedia'yı engelliyor). Bulunamayan armalar jenerik rozetle kalır.
2. **iyzico başvurusu:** sanal POS onayı gelince `.env`'de
   `ODEME_SAGLAYICI=iyzico` + anahtarlar; SANDBOX'ta 3DS test kartlarıyla
   uçtan uca test edilmeden canlıya alınmaz.
3. **Yasal metin onayı:** `/yasal/*` taslakları mali müşavir/avukat kontrolünden
   geçirilir; Ayarlar ekranından işletme unvanı + vergi bilgisi doldurulur.
4. **Hostinger kurulumu:** docs/KURULUM.md adımları (subdomain, MySQL,
   .env, SMTP hesabı, 2 cron işi).
5. **Canlı prova:** 1 TL'lik gerçek maçla ödeme + iade + QR check-in provası.
