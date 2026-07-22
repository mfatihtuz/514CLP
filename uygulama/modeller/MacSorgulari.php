<?php

declare(strict_types=1);

final class MacSorgulari
{
    /**
     * Ana sayfadaki satıştaki maçlar: başlamamış (veya son 3 saattir oynanan) maçlar.
     * @return array<int, array<string, mixed>>
     */
    public static function satistakiMaclar(): array
    {
        return Veritabani::satirlar(
            "SELECT m.*,
                    ev.ad  AS ev_ad,  ev.kisa_ad  AS ev_kisa,  ev.arma_dosya  AS ev_arma,  ev.renk1 AS ev_renk1,  ev.renk2 AS ev_renk2,
                    dep.ad AS dep_ad, dep.kisa_ad AS dep_kisa, dep.arma_dosya AS dep_arma, dep.renk1 AS dep_renk1, dep.renk2 AS dep_renk2
             FROM maclar m
             JOIN takimlar ev  ON ev.id  = m.ev_sahibi_takim_id
             JOIN takimlar dep ON dep.id = m.deplasman_takim_id
             WHERE m.durum = 'satista' AND m.baslangic_zamani > ?
             ORDER BY m.baslangic_zamani ASC",
            [utcKaydir(simdiUtc(), -180)]
        );
    }

    /** @return array<string, mixed>|null Takım bilgileriyle tek maç */
    public static function idIleBul(int $macId): ?array
    {
        return Veritabani::satir(
            "SELECT m.*,
                    ev.ad  AS ev_ad,  ev.kisa_ad  AS ev_kisa,  ev.arma_dosya  AS ev_arma,  ev.renk1 AS ev_renk1,  ev.renk2 AS ev_renk2,
                    dep.ad AS dep_ad, dep.kisa_ad AS dep_kisa, dep.arma_dosya AS dep_arma, dep.renk1 AS dep_renk1, dep.renk2 AS dep_renk2
             FROM maclar m
             JOIN takimlar ev  ON ev.id  = m.ev_sahibi_takim_id
             JOIN takimlar dep ON dep.id = m.deplasman_takim_id
             WHERE m.id = ?",
            [$macId]
        );
    }

    /** Maçın etkin iptal penceresi (saat): maça özel değer yoksa genel ayar. */
    public static function iptalSaatPenceresi(array $mac): int
    {
        return $mac['iptal_saat_once'] !== null
            ? (int) $mac['iptal_saat_once']
            : Ayarlar::varsayilanIptalSaat();
    }

    /** @return array<int, string> Maçın paket içeriği (JSON çözülmüş) */
    public static function paketIcerigi(array $mac): array
    {
        if (!empty($mac['paket_icerigi'])) {
            $paket = json_decode((string) $mac['paket_icerigi'], true);
            if (is_array($paket)) {
                return $paket;
            }
        }
        return Ayarlar::varsayilanPaket();
    }
}
