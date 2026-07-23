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
 * Türkçe-doğru "Her Kelime Büyük Harfle" (title case).
 * Türkçe özel harf kuralı: i→İ, ı→I (Unicode varsayılanı bunu yanlış yapar).
 * Örn: "1 medium tavuk dürüm" → "1 Medium Tavuk Dürüm", "izmir" → "İzmir".
 */
function turkceBaslikYap(string $metin): string
{
    $parcalar = preg_split('/(\s+)/u', trim($metin), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $sonuc = '';
    foreach ($parcalar as $parca) {
        if ($parca === '' || preg_match('/^\s+$/u', $parca)) {
            $sonuc .= $parca;
            continue;
        }
        $ilk = mb_substr($parca, 0, 1, 'UTF-8');
        $kalan = mb_substr($parca, 1, null, 'UTF-8');
        $ilkBuyuk = $ilk === 'i' ? 'İ' : ($ilk === 'ı' ? 'I' : mb_strtoupper($ilk, 'UTF-8'));
        $kalanKucuk = mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $kalan), 'UTF-8');
        $sonuc .= $ilkBuyuk . $kalanKucuk;
    }
    return $sonuc;
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

/** Maç durumunu renkli rozet olarak döner (admin ekranları). */
function macDurumRozeti(string $durum): string
{
    $harita = [
        'taslak'       => ['rozet-lacivert', 'Taslak'],
        'satista'      => ['rozet-yesil', 'Satışta'],
        'satis_kapali' => ['rozet-sari', 'Satış durduruldu'],
        'iptal'        => ['rozet-kirmizi', 'İptal'],
        'tamamlandi'   => ['rozet-lacivert', 'Tamamlandı'],
    ];
    [$sinif, $etiket] = $harita[$durum] ?? ['rozet-lacivert', $durum];
    return '<span class="rozet ' . $sinif . '">' . e($etiket) . '</span>';
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
