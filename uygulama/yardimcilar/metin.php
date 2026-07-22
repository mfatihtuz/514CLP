<?php

declare(strict_types=1);

/**
 * Metin yardımcıları. Görünümlerde çıktı HER ZAMAN e() ile kaçışlanır.
 */

function e(?string $metin): string
{
    return htmlspecialchars($metin ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * SVG ikonu inline gömer (public/varliklar/ikonlar/{ad}.svg).
 * currentColor kullanır; boyut CSS ile verilir. Emoji yasağının karşılığı budur.
 */
function ikon(string $ad, string $sinif = 'ikon'): string
{
    static $onbellek = [];
    if (!isset($onbellek[$ad])) {
        $dosya = DIZIN_PUBLIC . '/varliklar/ikonlar/' . $ad . '.svg';
        $ham = is_file($dosya) ? (string) file_get_contents($dosya) : '';
        if ($ham !== '') {
            // Lisans yorumu çıktıya taşınmaz (atıf: public/varliklar/ikonlar/LISANS.txt)
            $ham = trim((string) preg_replace('/<!--.*?-->/s', '', $ham));
        }
        $onbellek[$ad] = $ham;
    }
    if ($onbellek[$ad] === '') {
        return '';
    }
    $svg = (string) preg_replace('/<svg\b/', '<svg aria-hidden="true"', $onbellek[$ad], 1);
    return (string) preg_replace('/class="[^"]*"/', 'class="' . e($sinif) . '"', $svg, 1);
}

/** Telefonu normalize eder: "0 (532) 123 45 67" → "05321234567". Geçersizse null. */
function telefonNormallestir(string $telefon): ?string
{
    $rakamlar = preg_replace('/\D+/', '', $telefon) ?? '';
    if (str_starts_with($rakamlar, '90') && strlen($rakamlar) === 12) {
        $rakamlar = '0' . substr($rakamlar, 2);
    }
    if (strlen($rakamlar) === 10 && str_starts_with($rakamlar, '5')) {
        $rakamlar = '0' . $rakamlar;
    }
    return (strlen($rakamlar) === 11 && str_starts_with($rakamlar, '05')) ? $rakamlar : null;
}

function epostaGecerliMi(string $eposta): bool
{
    return filter_var($eposta, FILTER_VALIDATE_EMAIL) !== false;
}
