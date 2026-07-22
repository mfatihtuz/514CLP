<?php

/**
 * Cron: süresi dolan hold'ların hijyen temizliği.
 *
 * ÖNEMLİ (docs/VERITABANI.md): sistemin doğruluğu bu cron'a BAĞLI DEĞİLDİR.
 * Dolmuş hold'lar okuma anında 'bos' sunulur ve yeni hold onları ezebilir.
 * Bu betik yalnızca tabloyu düzenli tutar ve bekleyen rezervasyonları kapatır.
 *
 * Hostinger hPanel cron örneği (10 dakikada bir; yıldız-bölü-10 dört yıldız):
 *   "her 10 dakikada"  /usr/bin/php /home/KULLANICI/DEPO/betikler/cron-temizlik.php
 * Tam satır docs/KURULUM.md dosyasındadır.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/uygulama/baslat.php';

// 1) RECONCILIATION ÖNCE: 'baslatildi' kalmış ödemeler sağlayıcıdan sorgulanır;
//    para çekildiyse rezervasyon kurtarılır, kurtarılamazsa iade edilir.
//    (Callback kaybı sigortası — docs/ODEME-AKISI.md)
$kurtarma = OdemeYonetici::askidaKalanlariKurtar();
echo sprintf(
    "[%s] Ödeme kurtarma: %d kontrol, %d kurtarıldı, %d iade edildi\n",
    simdiUtc(),
    $kurtarma['kontrol_edilen'],
    $kurtarma['kurtarilan'],
    $kurtarma['iade_edilen']
);

// 2) Hijyen: dolmuş hold'lar
$sonuc = temizlikYap();
echo sprintf(
    "[%s] Temizlik: %d masa serbest bırakıldı, %d rezervasyon süresi doldu olarak işaretlendi\n",
    simdiUtc(),
    $sonuc['masa'],
    $sonuc['rezervasyon']
);

/** @return array{masa: int, rezervasyon: int} */
function temizlikYap(): array
{
    return Veritabani::islem(function (): array {
        $simdi = simdiUtc();

        $bosaltilanMasa = Veritabani::calistir(
            "UPDATE mac_masalari
             SET durum = 'bos', tutma_sona_erme = NULL, tutan_rezervasyon_id = NULL
             WHERE durum = 'tutuldu' AND tutma_sona_erme IS NOT NULL AND tutma_sona_erme < ?",
            [$simdi]
        );

        // NOT (Faz 3): ödemesi 'baslatildi' durumda kalan rezervasyonlar burada
        // 'suresi_doldu' yapılmadan ÖNCE sağlayıcıdan sorgulanır (reconciliation);
        // para çekildiyse rezervasyon kurtarılır. Ödeme katmanı bağlanınca eklenecek.
        $dolanRezervasyon = Veritabani::calistir(
            "UPDATE rezervasyonlar
             SET durum = 'suresi_doldu', hold_sona_erme = NULL, guncelleme_zamani = ?
             WHERE durum = 'odeme_bekliyor' AND hold_sona_erme IS NOT NULL AND hold_sona_erme < ?",
            [$simdi, $simdi]
        );

        return ['masa' => $bosaltilanMasa, 'rezervasyon' => $dolanRezervasyon];
    });
}
