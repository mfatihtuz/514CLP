# CLAUDE.md — Proje Hafızası ve Değişmez Kurallar

Bu dosya, proje sahibinin (Fatih) koyduğu ve HER oturumda geçerli olan kuralları içerir.
Bu kurallar tartışmaya açık değildir; değişiklik yalnızca proje sahibinin açık talebiyle yapılır.

## Çalışma Prensipleri

1. **%99.9 kuralı:** Bir özellik veya karar konusunda %99.9 emin olmadan adım atma.
   Emin olmak için proje sahibine MUTLAKA sor. Varsayımla ilerleme; sor, netleştir, öyle yaz.
2. **AI slop yasak:** Şablonvari, genel geçer, özensiz içerik ve kod üretme.
   Her ekran, her metin, her fonksiyon bu projeye özel ve düşünülmüş olmalı.
   UI metinleri doğal, insan Türkçesi olsun; **noktalı virgül (;) kullanma** —
   cümleyi böl ya da virgül kullan. Kısa, samimi, gündelik dil tercih edilir.
   Fiyata dahil paket her zaman gerçek içerikle yazılır ("Menü" gibi genel
   ifade değil; ör. "1 medium tavuk dürüm + 1 ayran + Sınırsız çay dahil").
3. **Emoji yasak:** Kullanıcı arayüzünde ve üretilen içerikte emoji kullanılmaz.
   Yalnızca profesyonel SVG ikonlar kullanılır (`public/varliklar/ikonlar/` altında).

## Tasarım Kuralları

4. **Renk paleti (kesin ve değişmez):**
   - Lacivert `#0D3B66` — zemin, başlıklar
   - Krem `#FAF0CA` — açık zemin, metin
   - Sarı `#F4D35E` — vurgu, ikincil eylemler, seçili masa
   - Turuncu `#EE964B` — hover, geçiş durumları
   - Kırmızı-turuncu `#F95738` — birincil eylem (CTA), dolu masa, hata
5. **Takım armaları:** Orijinal SVG dosyaları kullanılır (`public/armalar/`).
   Armalar sabit boyutlu kapsayıcıda, oranı bozulmadan gösterilir (`.takim-arma` bileşeni).
   Gerçek arma yüklenemeyen takım için takım renkli jenerik rozet fallback'i vardır.
6. **Tasarım dili:** Modern, eğlenceli, müşteriyi rezervasyona teşvik eden,
   mobil öncelikli "maç gecesi" atmosferi. Geri sayım, canlı doluluk, akıcı geçişler.
7. **Türkçe karakter desteği:** UI'daki tüm metinler tam Türkçe karakter (ı İ ş Ş ğ Ğ ö Ö ü Ü ç Ç) destekli.
   Font seçimi `latin-ext` kapsar; encoding her katmanda UTF-8 (`utf8mb4`).

## Adlandırma Kuralları

8. **Dokümantasyon Türkçe adlandırılır:** `MIMARI.md`, `KURULUM.md`, `VERITABANI.md` gibi.
9. **Fonksiyon/değişken adları Türkçe ve ASCII-güvenli:** `rezervasyonOlustur()`, `masaTut()`, `holdSuresiDolduMu()`.
   Identifier içinde ı/ş/ğ/ö/ü/ç KULLANILMAZ; i/s/g/o/u/c'ye çevrilir.
   Veritabanı tablo/kolonları `snake_case` ASCII: `mac_masalari`, `tutma_sona_erme`.
   Dilin/aracın dayattığı adlar (`index.php`, `composer.json`, vendor API'leri) olduğu gibi kalır.

## Teknik Kararlar (proje sahibi onaylı)

10. **Altyapı:** Hostinger paylaşımlı hosting + PHP 8 + MySQL (phpMyAdmin ile yönetim).
    Alan adı: `rezervasyon.mftyazilim.com`. Yerel geliştirmede SQLite kullanılabilir; üretim MySQL'dir.
11. **Ödeme:** Sanal POS DEĞİŞTİRİLEBİLİR olmalı. `OdemeSaglayici` arayüzü arkasında
    iyzico birincil sağlayıcı; MockSaglayici geliştirme/test için; yeni sağlayıcı tek sınıfla eklenir.
12. **Üyelik yok:** Rezervasyon üyeliksiz misafir akışıyla yapılır (ad + telefon + e-posta).
13. **Fikstür:** Üç büyüklerin maçları otomatik çekilir (cron + scrape), TASLAK olarak panele düşer;
    fiyatı belirleyip yayınlamak her zaman admin onayıyla olur. Manuel maç girişi daima açıktır.
14. **Masa kuralları dinamiktir:** Her masanın kapasitesi VE minimum grup şartı vardır
    (varsayılan: 4 kişilik masa → en az 3 kişi). 1-2 kişilik gruplar masa seçemez;
    "Salon Girişi" paylaşımlı kontenjanından yer alır (yerini işletme belirler).
    Tüm bu değerler admin panelindeki Ayarlar'dan ve masa bazında değiştirilebilir.

## Doğrulama Alışkanlıkları

15. Para ve eşzamanlılık içeren her değişiklikte paralel/yarış testi çalıştırılmadan iş bitmiş sayılmaz.
16. Tarih-saat işlemleri yalnızca `uygulama/yardimcilar/tarih.php` üzerinden yapılır;
    veritabanında UTC saklanır, görüntüleme Europe/Istanbul'dur.
