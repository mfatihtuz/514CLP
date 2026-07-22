<?php

declare(strict_types=1);

/**
 * Maça özel masa kopyaları (mac_masalari) sorguları.
 * Canlı durum (bos/tutuldu/rezerve/kapali) bu tablodadır.
 */
final class MacMasaSorgulari
{
    /** @return array<int, array<string, mixed>> */
    public static function macinMasalari(int $macId): array
    {
        return Veritabani::satirlar(
            'SELECT * FROM mac_masalari WHERE mac_id = ? ORDER BY ad',
            [$macId]
        );
    }

    /**
     * Müşteriye/panele sunulacak EFEKTİF durum: süresi dolmuş hold'lar
     * yazma yapılmadan 'bos' olarak sunulur (lazy expiry, VERITABANI.md).
     * @return array<int, array<string, mixed>>
     */
    public static function macinMasalariEfektif(int $macId): array
    {
        $simdi = simdiUtc();
        $masalar = self::macinMasalari($macId);
        foreach ($masalar as &$masa) {
            if ($masa['durum'] === 'tutuldu'
                && ($masa['tutma_sona_erme'] === null || (string) $masa['tutma_sona_erme'] < $simdi)) {
                $masa['durum'] = 'bos';
                $masa['tutan_rezervasyon_id'] = null;
            }
        }
        return $masalar;
    }

    /** Maçın doluluk özeti: [rezerve_masa, toplam_acik_masa, rezerve_kisi] */
    public static function dolulukOzeti(int $macId): array
    {
        $masalar = self::macinMasalariEfektif($macId);
        $rezerveMasa = 0;
        $acikMasa = 0;
        foreach ($masalar as $masa) {
            if ($masa['durum'] === 'kapali') {
                continue;
            }
            $acikMasa++;
            if ($masa['durum'] === 'rezerve') {
                $rezerveMasa++;
            }
        }
        $rezerveKisi = (int) (Veritabani::deger(
            "SELECT COALESCE(SUM(kisi_sayisi), 0) FROM rezervasyonlar WHERE mac_id = ? AND durum = 'onaylandi'",
            [$macId]
        ) ?? 0);
        return [
            'rezerve_masa' => $rezerveMasa,
            'acik_masa'    => $acikMasa,
            'rezerve_kisi' => $rezerveKisi,
        ];
    }
}
