# Maç Gecesi Rezervasyon

Restoranın üst katında dev ekranda yayınlanan Süper Lig maçları için
**sinema mantığında masa seçmeli, online ödemeli** rezervasyon sitesi.

- Canlı adres (hedef): https://rezervasyon.mftyazilim.com
- Teknoloji: PHP 8 + MySQL (Hostinger paylaşımlı hosting), vanilla JS ön yüz
- Proje kuralları: [CLAUDE.md](CLAUDE.md) — palet, adlandırma, çalışma prensipleri

## Belgeler

| Belge | İçerik |
|---|---|
| [docs/MIMARI.md](docs/MIMARI.md) | Mimari kararlar, dizin yapısı, faz durumu |
| [docs/KURULUM.md](docs/KURULUM.md) | Yerel geliştirme + Hostinger'a kurulum |
| [docs/VERITABANI.md](docs/VERITABANI.md) | Şema, durum makineleri, eşzamanlılık (hold) tasarımı |
| [docs/ODEME-AKISI.md](docs/ODEME-AKISI.md) | Ödeme sağlayıcı soyutlaması, uç senaryolar, iade |
| [docs/ADLANDIRMA.md](docs/ADLANDIRMA.md) | Türkçe ASCII-güvenli adlandırma sözleşmesi |

## Hızlı Başlangıç (yerel)

```bash
cp .env.ornek .env
php betikler/vt-kur.php
php betikler/rozet-uret.php
php betikler/admin-olustur.php eposta@ornek.com "Ad Soyad"
bash betikler/yerel-sunucu.sh          # http://localhost:8514
```
