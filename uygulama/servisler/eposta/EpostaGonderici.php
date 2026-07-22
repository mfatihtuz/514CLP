<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

/**
 * E-posta gönderimi (PHPMailer + Hostinger SMTP).
 *
 * Kurallar:
 *  - Gönderim hatası rezervasyonu ASLA bozmaz: hata loglanır, akış sürer
 *    (bilet linki her durumda ekranda gösterilir).
 *  - SMTP ayarları boşsa (yerel geliştirme) e-posta gecici/ klasörüne
 *    .html dosyası olarak yazılır; böylece şablonlar gerçek gönderim
 *    olmadan incelenebilir.
 */
final class EpostaGonderici
{
    /**
     * @param array<string, string> $gomuluResimler cid → ham bayt (ör. QR PNG)
     * @return bool gönderim (veya dosyaya yazım) başarılı mı
     */
    public static function gonder(string $kime, string $konu, string $htmlGovde, array $gomuluResimler = []): bool
    {
        $smtpKullanici = (string) (Cevre::al('SMTP_KULLANICI', '') ?? '');

        if ($smtpKullanici === '') {
            return self::dosyayaYaz($kime, $konu, $htmlGovde, $gomuluResimler);
        }

        try {
            $posta = new PHPMailer(true);
            $posta->CharSet = PHPMailer::CHARSET_UTF8;    // Türkçe karakter garantisi
            $posta->Timeout = 10;                          // yanlış/yavaş SMTP 300sn asılı kalmasın
            $posta->SMTPKeepAlive = false;
            $posta->isSMTP();
            $posta->Host = Cevre::al('SMTP_SUNUCU', 'smtp.hostinger.com');
            $posta->Port = (int) (Cevre::al('SMTP_PORT', '465') ?? 465);
            $posta->SMTPSecure = $posta->Port === 465
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $posta->SMTPAuth = true;
            $posta->Username = $smtpKullanici;
            $posta->Password = (string) (Cevre::al('SMTP_SIFRE', '') ?? '');
            $posta->setFrom($smtpKullanici, (string) (Cevre::al('SMTP_GONDEREN_AD', Ayarlar::siteAdi()) ?? ''));
            $posta->addAddress($kime);
            $posta->Subject = $konu;
            $posta->isHTML(true);
            $posta->Body = $htmlGovde;
            $posta->AltBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlGovde) ?? '');

            foreach ($gomuluResimler as $cid => $baytlar) {
                $posta->addStringEmbeddedImage($baytlar, $cid, $cid . '.png', PHPMailer::ENCODING_BASE64, 'image/png');
            }

            $posta->send();
            return true;
        } catch (Throwable $hata) {
            error_log('E-posta gönderilemedi (' . $kime . '): ' . $hata->getMessage());
            DenetimKaydi::yaz('eposta_hatasi', ['kime' => $kime, 'konu' => $konu, 'hata' => $hata->getMessage()]);
            return false;
        }
    }

    /** Yerel geliştirme çıktısı: gecici/eposta-*.html */
    private static function dosyayaYaz(string $kime, string $konu, string $htmlGovde, array $gomuluResimler): bool
    {
        $klasor = DIZIN_KOK . '/gecici';
        if (!is_dir($klasor)) {
            mkdir($klasor, 0775, true);
        }
        // cid: referanslarını data URI'ye çevir ki dosya tarayıcıda tam görünsün
        foreach ($gomuluResimler as $cid => $baytlar) {
            $htmlGovde = str_replace('cid:' . $cid, 'data:image/png;base64,' . base64_encode($baytlar), $htmlGovde);
        }
        $dosya = $klasor . '/eposta-' . gmdate('Ymd-His') . '-' . substr(md5($kime . $konu . mt_rand()), 0, 6) . '.html';
        file_put_contents($dosya, "<!-- Kime: {$kime} | Konu: {$konu} -->\n" . $htmlGovde);
        error_log('Geliştirme e-postası dosyaya yazıldı: ' . $dosya);
        return true;
    }
}
