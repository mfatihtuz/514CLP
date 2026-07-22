<?php

declare(strict_types=1);

/**
 * Rezervasyonla ilgili e-postaların kurulumu (şablon + QR + gönderim).
 */
final class RezervasyonEpostalari
{
    /** Onay e-postası: QR bilet + bilet linki + iptal koşulu. */
    public static function onayGonder(string $kod): bool
    {
        $detay = RezervasyonIslemleri::kodIleDetay($kod);
        if ($detay === null || $detay['durum'] !== 'onaylandi' || $detay['eposta'] === '') {
            return false;
        }

        $masalar = RezervasyonIslemleri::masalari($detay);
        $biletUrl = self::biletUrl($detay);

        $html = Sablon::parcaOlustur('eposta/rezervasyon-onay', [
            'rezervasyon' => $detay,
            'masalar'     => $masalar,
            'paket'       => MacSorgulari::paketIcerigi($detay),
            'biletUrl'    => $biletUrl,
            'sonIptal'    => self::sonIptalMetni($detay),
        ]);

        return EpostaGonderici::gonder(
            (string) $detay['eposta'],
            'Rezervasyonunuz onaylandı — ' . $detay['ev_ad'] . ' - ' . $detay['dep_ad'] . ' (' . $detay['kod'] . ')',
            $html,
            ['qrbilet' => QrUretici::pngBaytlari($detay)]
        );
    }

    /** İptal e-postası (iade tutarı ücretli akışta Faz 3'te eklenir). */
    public static function iptalGonder(string $kod, int $iadeTutarKurus = 0): bool
    {
        $detay = RezervasyonIslemleri::kodIleDetay($kod);
        if ($detay === null || $detay['eposta'] === '') {
            return false;
        }

        $html = Sablon::parcaOlustur('eposta/rezervasyon-iptal', [
            'rezervasyon'     => $detay,
            'iadeTutarKurus'  => $iadeTutarKurus,
        ]);

        return EpostaGonderici::gonder(
            (string) $detay['eposta'],
            'Rezervasyonunuz iptal edildi — ' . $detay['kod'],
            $html
        );
    }

    /** E-postadaki bilet linki oturumsuz cihazda da açılabilsin diye imzalıdır. */
    public static function biletUrl(array $rezervasyon): string
    {
        $imza = Guvenlik::qrImzala(
            (string) $rezervasyon['kod'],
            (int) $rezervasyon['mac_id'],
            (string) $rezervasyon['qr_nonce']
        );
        return Ayarlar::tabanUrl() . '/rezervasyon/' . $rezervasyon['kod'] . '?e=' . $imza;
    }

    public static function sonIptalMetni(array $detay): string
    {
        $pencereSaat = $detay['iptal_saat_once'] !== null
            ? (int) $detay['iptal_saat_once']
            : Ayarlar::varsayilanIptalSaat();
        $sonZaman = utcKaydir((string) $detay['baslangic_zamani'], -60 * $pencereSaat);
        return macZamaniBicimle($sonZaman);
    }
}
