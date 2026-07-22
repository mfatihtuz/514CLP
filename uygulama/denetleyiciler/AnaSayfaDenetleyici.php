<?php

declare(strict_types=1);

final class AnaSayfaDenetleyici
{
    /** @param array<string, string> $parametreler */
    public function listele(array $parametreler = []): void
    {
        Sablon::goster('anasayfa/liste', [
            'baslik' => 'Yaklaşan Maçlar',
            'maclar' => MacSorgulari::satistakiMaclar(),
        ]);
    }
}
