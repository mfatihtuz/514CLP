<?php

declare(strict_types=1);

/**
 * Süresi dolan hold'ların hijyen temizliği.
 * Hem CLI cron (betikler/cron-temizlik.php) hem web cron (/cron/temizlik)
 * bu sınıfı kullanır. Sistemin doğruluğu buna BAĞLI DEĞİLDİR (lazy expiry);
 * yalnızca tabloyu düzenli tutar — docs/VERITABANI.md.
 */
final class HoldTemizligi
{
    /** @return array{masa: int, rezervasyon: int} */
    public static function temizle(): array
    {
        return Veritabani::islem(function (): array {
            $simdi = simdiUtc();

            $bosaltilanMasa = Veritabani::calistir(
                "UPDATE mac_masalari
                 SET durum = 'bos', tutma_sona_erme = NULL, tutan_rezervasyon_id = NULL
                 WHERE durum = 'tutuldu' AND tutma_sona_erme IS NOT NULL AND tutma_sona_erme < ?",
                [$simdi]
            );

            $dolanRezervasyon = Veritabani::calistir(
                "UPDATE rezervasyonlar
                 SET durum = 'suresi_doldu', hold_sona_erme = NULL, guncelleme_zamani = ?
                 WHERE durum = 'odeme_bekliyor' AND hold_sona_erme IS NOT NULL AND hold_sona_erme < ?",
                [$simdi, $simdi]
            );

            return ['masa' => $bosaltilanMasa, 'rezervasyon' => $dolanRezervasyon];
        });
    }
}
