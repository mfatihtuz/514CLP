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
$sonuc = HoldTemizligi::temizle();
echo sprintf(
    "[%s] Temizlik: %d masa serbest bırakıldı, %d rezervasyon süresi doldu olarak işaretlendi\n",
    simdiUtc(),
    $sonuc['masa'],
    $sonuc['rezervasyon']
);
