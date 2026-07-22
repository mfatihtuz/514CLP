# VERİTABANI

Üretim şeması: `veritabani/sema.mysql.sql` — yerel eşleniği: `sema.sqlite.sql`.
**İki dosya her zaman birlikte güncellenir.** Tohum verisi: `tohum.sql`.

Genel kurallar:
- Zamanlar `DATETIME`/`TEXT` olarak **UTC** saklanır ("Y-m-d H:i:s").
- Para alanları **kuruş cinsinden tam sayıdır** (`*_kurus`).
- Karakter seti `utf8mb4` (tam Türkçe destek).

## Tablolar

| Tablo | Görev |
|---|---|
| `takimlar` | 2026-27 Süper Lig takımları: ad, kısa ad, arma yolu, renkler, `uc_buyuk` bayrağı |
| `maclar` | Maç: takımlar, zaman, kişi başı fiyat, paket içeriği (JSON), paylaşımlı kontenjan, durum |
| `masalar` | ANA kat planı şablonu: kapasite, `min_kisi`, 24x16 grid konumu |
| `mac_masalari` | Maça özel masa KOPYALARI + canlı durum (`bos/tutuldu/rezerve/kapali`) |
| `rezervasyonlar` | Misafir rezervasyonu: kod, tür (`masa`/`salon`), kişi, tutar, durum, QR nonce |
| `rezervasyon_masalari` | Rezervasyon-masa bağı (bir rezervasyon birden çok masa alabilir) |
| `odemeler` | Ödeme denemeleri: sağlayıcı, `konusma_kimligi` (UNIQUE), ham cevap, iade alanları |
| `ayarlar` | Anahtar-değer (JSON): iptal saati, hold süresi, restoran bilgileri… |
| `admin_kullanicilar` | Panel girişi (password_hash) |
| `denetim_kayitlari` | Para işlemleri ve kritik admin eylemlerinin izi |

## Durum Makineleri

```
mac:          taslak → satista → satis_kapali → tamamlandi
                          ↘ iptal
mac_masalari: bos ⇄ tutuldu → rezerve        (kapali: admin kararı)
rezervasyon:  odeme_bekliyor → onaylandi → (iptal_edildi)
                     ↘ suresi_doldu
odeme:        baslatildi → basarili → iade_edildi
                     ↘ basarisiz
```

## Eşzamanlılık: Masa Kilitleme (HOLD)

Çift rezervasyonun TEK güvencesi veritabanındaki koşullu UPDATE'tir
(compare-and-swap). Uygulama Faz 2'de `servisler/rezervasyon/masaTut()` içinde:

```sql
UPDATE mac_masalari
SET durum = 'tutuldu', tutma_sona_erme = :bitis, tutan_rezervasyon_id = :rezId
WHERE id IN (...) AND mac_id = :macId
  AND ( durum = 'bos'
        OR (durum = 'tutuldu' AND tutma_sona_erme < :simdi) )
```

- Etkilenen satır sayısı istenen masa sayısına eşit değilse → ROLLBACK →
  müşteriye "masa az önce kapıldı" mesajı + güncel kroki.
- Aynı anda gelen iki istek: ikincisi satır kilidinde bekler, koşul yeniden
  değerlendirilir, 0 satır etkiler. Çift rezervasyon imkânsızdır.
- Onay adımı aynı desenle `tutuldu → rezerve` çevirir
  (`tutan_rezervasyon_id = :rezId` şartıyla).
- `rezervasyon_masalari.mac_masa_id` üzerindeki UNIQUE kısıt son emniyet
  kemeridir.

### Hold süresi ve temizlik (lazy expiry)

- Hold süresi `ayarlar.hold_dakika` (varsayılan 10 dk).
- Süresi dolan hold'lar ÜÇ yerde etkisizleşir; cron'a bel bağlanmaz:
  1. Kroki okuma uç noktası dolmuş hold'ları `bos` olarak SUNAR (yazmadan),
  2. Yeni hold alma dolmuş hold'un üzerine yazabilir (yukarıdaki WHERE),
  3. `betikler/cron-temizlik.php` (10 dk'da bir) tabloyu hijyen için toplar:
     dolmuş hold'lar `bos`a, bağlı `odeme_bekliyor` rezervasyonlar
     `suresi_doldu`na çevrilir.

### Paylaşımlı kontenjan (salon) eşzamanlılığı

`salon` türü rezervasyonda masa satırı yoktur; kontenjan kontrolü
maç satırı kilitlenerek yapılır (`SELECT ... FOR UPDATE` MySQL'de;
SQLite'ta tek yazar zaten serileştirir):
satılan + aktif hold'lu kişi toplamı `paylasimli_kontenjan`ı aşamaz.

## Kat Planı Kopyalama Kuralları

- Maç `taslak → satista` geçerken aktif `masalar` satırları `mac_masalari`na
  tek transaction'da kopyalanır (`kaynak_masa_id` doldurulur).
- Maça özel eklenen ekstra masada `kaynak_masa_id = NULL`.
- Satıştaki maçta masa SİLİNEMEZ; yalnızca `kapali` yapılabilir.
- Rezervasyonu olan masa kapatılamaz (önce rezervasyon iptal edilmelidir).
