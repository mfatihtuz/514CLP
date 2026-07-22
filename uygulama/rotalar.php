<?php

declare(strict_types=1);

/**
 * Rota tanımları. $yonlendirici public/index.php içinde oluşturulur.
 * Yeni fazlarda rotalar bu dosyaya eklenir (müşteri + admin + api).
 *
 * @var Yonlendirici $yonlendirici
 */

// ---- Müşteri ----
$yonlendirici->get('/', AnaSayfaDenetleyici::class, 'listele');

// ---- Admin: oturum ----
$yonlendirici->get('/admin/giris', AdminGirisDenetleyici::class, 'form');
$yonlendirici->post('/admin/giris', AdminGirisDenetleyici::class, 'girisYap');
$yonlendirici->post('/admin/cikis', AdminGirisDenetleyici::class, 'cikisYap');

// ---- Admin: panel ----
$yonlendirici->get('/admin', AdminPanoDenetleyici::class, 'goster');
$yonlendirici->get('/admin/maclar', AdminMacDenetleyici::class, 'liste');
$yonlendirici->get('/admin/maclar/yeni', AdminMacDenetleyici::class, 'yeniForm');   // {macId}'den ÖNCE
$yonlendirici->post('/admin/maclar', AdminMacDenetleyici::class, 'olustur');
$yonlendirici->get('/admin/maclar/{macId}', AdminMacDenetleyici::class, 'detay');
$yonlendirici->post('/admin/maclar/{macId}', AdminMacDenetleyici::class, 'guncelle');
$yonlendirici->post('/admin/maclar/{macId}/yayinla', AdminMacDenetleyici::class, 'yayinla');
$yonlendirici->post('/admin/maclar/{macId}/durum', AdminMacDenetleyici::class, 'durumDegistir');
$yonlendirici->get('/admin/kroki', AdminKrokiDenetleyici::class, 'anaPlanEditoru');
$yonlendirici->get('/admin/ayarlar', AdminAyarDenetleyici::class, 'form');
$yonlendirici->post('/admin/ayarlar', AdminAyarDenetleyici::class, 'kaydet');

// ---- Admin: JSON API (kroki editörü) ----
$yonlendirici->get('/admin/api/kroki', AdminKrokiDenetleyici::class, 'anaPlanVeri');
$yonlendirici->post('/admin/api/kroki', AdminKrokiDenetleyici::class, 'anaPlanKaydet');
$yonlendirici->get('/admin/api/maclar/{macId}/kroki', AdminKrokiDenetleyici::class, 'macKrokiVeri');
$yonlendirici->post('/admin/api/maclar/{macId}/kroki', AdminKrokiDenetleyici::class, 'macKrokiKaydet');
$yonlendirici->post('/admin/api/mac-masa/{macMasaId}/durum', AdminKrokiDenetleyici::class, 'macMasaDurum');
