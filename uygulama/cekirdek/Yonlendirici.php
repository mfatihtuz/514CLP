<?php

declare(strict_types=1);

/**
 * Basit yönlendirici. Rota tanımları uygulama/rotalar.php dosyasındadır.
 * Desen sözdizimi: /mac/{macId} — süslü parantezli parçalar parametre olur.
 */
final class Yonlendirici
{
    /** @var array<string, array<int, array{desen: string, duzenlilik: string, isleyici: array{0: string, 1: string}}>> */
    private array $rotalar = ['GET' => [], 'POST' => []];

    public function get(string $desen, string $sinif, string $metod): void
    {
        $this->ekle('GET', $desen, $sinif, $metod);
    }

    public function post(string $desen, string $sinif, string $metod): void
    {
        $this->ekle('POST', $desen, $sinif, $metod);
    }

    private function ekle(string $httpMetodu, string $desen, string $sinif, string $metod): void
    {
        $duzenlilik = '#^' . preg_replace('/\{([a-zA-Z]+)\}/', '(?P<$1>[^/]+)', rtrim($desen, '/') ?: '/') . '$#';
        $this->rotalar[$httpMetodu][] = [
            'desen'      => $desen,
            'duzenlilik' => $duzenlilik,
            'isleyici'   => [$sinif, $metod],
        ];
    }

    public function calistir(string $httpMetodu, string $yol): void
    {
        $yol = rtrim($yol, '/') ?: '/';

        foreach ($this->rotalar[$httpMetodu] ?? [] as $rota) {
            if (preg_match($rota['duzenlilik'], $yol, $eslesme)) {
                $parametreler = array_filter($eslesme, 'is_string', ARRAY_FILTER_USE_KEY);
                [$sinif, $metod] = $rota['isleyici'];
                (new $sinif())->{$metod}($parametreler);
                return;
            }
        }

        http_response_code(404);
        Sablon::goster('hatalar/404', ['baslik' => 'Sayfa Bulunamadı']);
    }
}
