<?php

declare(strict_types=1);

/**
 * Web üzerinden cron tetikleme (CLI cron çalıştırılamayan ortamlar için yedek).
 * Koruma: ?anahtar=CRON_GIZLI_ANAHTAR eşleşmezse 404 (uç noktanın varlığı gizlenir).
 *
 * Hostinger hPanel cron alternatifi:
 *   wget -qO- "https://rezervasyon.mftyazilim.com/cron/temizlik?anahtar=..."
 */
final class CronDenetleyici
{
    public function temizlik(array $parametreler = []): void
    {
        $this->anahtarZorunlu();
        $kurtarma = OdemeYonetici::askidaKalanlariKurtar();
        $temizlik = HoldTemizligi::temizle();
        Sablon::json([
            'tamam'    => true,
            'kurtarma' => $kurtarma,
            'temizlik' => $temizlik,
            'zaman'    => simdiUtc(),
        ]);
    }

    public function fikstur(array $parametreler = []): void
    {
        $this->anahtarZorunlu();
        $sonuc = FiksturCekici::calistir();
        Sablon::json(['tamam' => $sonuc['hatalar'] === [], 'sonuc' => $sonuc, 'zaman' => simdiUtc()]);
    }

    private function anahtarZorunlu(): void
    {
        $beklenen = (string) (Cevre::al('CRON_GIZLI_ANAHTAR', '') ?? '');
        $gelen = (string) ($_GET['anahtar'] ?? '');
        if ($beklenen === '' || $gelen === '' || !hash_equals($beklenen, $gelen)) {
            http_response_code(404);
            exit;
        }
    }
}
