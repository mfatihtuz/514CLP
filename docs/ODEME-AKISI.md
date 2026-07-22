# ÖDEME AKIŞI

Uygulama Faz 3'te yapılır; bu belge tasarımın tek kaynağıdır.

## Sağlayıcı Soyutlaması

Sanal POS değiştirilebilirdir (CLAUDE.md kural 11). Tüm sağlayıcılar
`uygulama/servisler/odeme/OdemeSaglayici.php` arayüzünü uygular:

```php
interface OdemeSaglayici {
    /** Ödemeyi başlatır; müşterinin yönlendirileceği URL/form döner. */
    public function odemeBaslat(OdemeBaslatGirdisi $girdi): OdemeBaslatSonucu;

    /** Callback sonrası sonucu SAĞLAYICIDAN sorgular (callback gövdesine güvenilmez). */
    public function odemeSorgula(string $jeton, string $konusmaKimligi): OdemeSonucu;

    /** Tam veya kısmi iade. */
    public function iadeYap(string $saglayiciOdemeId, int $tutarKurus): IadeSonucu;
}
```

- `MockSaglayici` — geliştirme/test: sahte banka sayfası gösterir
  ("Onayla / Reddet" düğmeleri), gerçek callback uç noktasına POST eder.
  `ORTAM=uretim` iken `ODEME_SAGLAYICI=mock` seçilirse uygulama AÇILIŞTA hata verir.
- `IyzicoSaglayici` — iyzico **CheckoutForm** (Ödeme Formu) API'si:
  3D Secure iyzico sayfasında yürür, kart verisi bize hiç uğramaz (PCI kapsamı dışı).

## Mutlu Yol

1. Müşteri masaları seçer (veya salon bileti), kişi sayısını girer
   → `POST /api/hold` → rezervasyon `odeme_bekliyor` + 10 dk hold.
2. Misafir formu: ad soyad, telefon, e-posta + KVKK ve mesafeli satış onayı
   (onay zamanları rezervasyona yazılır — ispat yükü).
3. Fiyat 0 TL ise → ödeme tamamen atlanır → adım 6.
4. `POST /odeme/baslat` → `odemeler` kaydı (`baslatildi`, tekil `konusma_kimligi`)
   → sağlayıcı initialize → müşteri sağlayıcı sayfasına gider (3DS/OTP orada).
5. Sağlayıcı `POST /odeme/geri-donus` adresine jeton yollar. Sunucu:
   - jetonu `odemeSorgula()` ile SAĞLAYICIDAN doğrular,
   - `paymentStatus = SUCCESS`, tutar eşleşmesi ve `konusma_kimligi` kontrolü yapar,
   - işlem İDEMPOTENTTİR: ödeme zaten `basarili` ise hiçbir şey yapmadan başarı döner.
6. Tek transaction: `odeme → basarili`, `rezervasyon → onaylandi`,
   masalar CAS ile `tutuldu → rezerve`.
7. QR biletli onay e-postası gönderilir; müşteri bilet sayfasına yönlenir.
   (E-posta düşerse rezervasyon BOZULMAZ; bilet linki ekranda daima gösterilir.)

## Yarıda Kalma Senaryoları

| Senaryo | Davranış |
|---|---|
| Müşteri sağlayıcı sayfasını kapattı | Rezervasyon `odeme_bekliyor` kalır; hold dolunca masa serbest |
| Para çekildi ama callback kaybold | Cron + sonuç sayfası, `baslatildi` ödemeleri sağlayıcıdan sorgular (reconciliation); başarılıysa rezervasyon kurtarılır; masa bu arada kapıldıysa OTOMATİK İADE + özür e-postası |
| Ödeme reddedildi | `odeme → basarisiz`; hold korunur, süre bitene dek tekrar denenebilir |
| Aynı rezervasyona ikinci "başlat" | Yeni `konusma_kimligi` ile yeni ödeme satırı; çifte tahsilat oluşursa ikincisi otomatik iade + denetim kaydı |
| Çifte callback | İdempotency: ikinci istek no-op |

## İade Akışı

- **Müşteri iptali** (`/iptal/{kod}`): kod + telefon doğrulaması.
  Koşul: şimdi < maç başlangıcı - iptal penceresi (maça özel veya genel ayar).
  Tek transaction: rezervasyon `iptal_edildi`, masalar `bos`; ardından
  `iadeYap()` (transaction DIŞINDA — sağlayıcı çağrısı uzun sürebilir).
  İade çağrısı düşerse: `denetim_kayitlari`na yazılır, admin panosunda
  "bekleyen iade" uyarısı çıkar, elle yeniden tetiklenebilir.
- **Admin iptali**: saat kısıtı yok; kısmi iade girilebilir; denetim kaydı zorunlu.
- iyzico'da gün içi işlemler `cancel`, sonrası `refund` API'sidir:
  implementasyon önce cancel dener, reddedilirse refund'a düşer.

## Güvenlik Kuralları

1. Callback gövdesine ASLA güvenilme; sonuç daima sağlayıcıdan `retrieve` edilir.
2. Tutar karşılaştırması kuruş cinsinden tam eşitliktir.
3. `konusma_kimligi` tekildir (UNIQUE); tekrar kullanılamaz.
4. Ham sağlayıcı cevabı `odemeler.ham_cevap`a yazılır (uyuşmazlık çözümü için).
5. Fiyat İSTEMCİDEN ASLA gelmez; sunucu maç kaydından hesaplar.
