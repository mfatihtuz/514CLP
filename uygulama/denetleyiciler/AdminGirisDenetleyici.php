<?php

declare(strict_types=1);

final class AdminGirisDenetleyici
{
    public function form(array $parametreler = []): void
    {
        if (AdminOturumu::aktifId() !== null) {
            Sablon::yonlendir('/admin');
        }
        Sablon::goster('admin/giris', ['baslik' => 'Yönetim Girişi', 'hata' => null], 'duzen/admin-giris');
    }

    public function girisYap(array $parametreler = []): void
    {
        Guvenlik::csrfZorunlu();
        $eposta = (string) ($_POST['eposta'] ?? '');
        $sifre  = (string) ($_POST['sifre'] ?? '');

        if (AdminOturumu::girisDene($eposta, $sifre)) {
            Sablon::yonlendir('/admin');
        }

        http_response_code(401);
        Sablon::goster('admin/giris', [
            'baslik' => 'Yönetim Girişi',
            'hata'   => 'E-posta veya şifre hatalı.',
            'eposta' => $eposta,
        ], 'duzen/admin-giris');
    }

    public function cikisYap(array $parametreler = []): void
    {
        Guvenlik::csrfZorunlu();
        AdminOturumu::cikis();
        Sablon::yonlendir('/admin/giris');
    }
}
