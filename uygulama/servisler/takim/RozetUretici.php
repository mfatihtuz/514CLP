<?php

declare(strict_types=1);

/**
 * Jenerik rozet üretici: takım renkleri + kısaltmadan kalkan biçimli SVG.
 * Kullanım yerleri:
 *  - betikler/rozet-uret.php (tüm takımlar için toplu)
 *  - FiksturCekici (fikstürden gelen yeni/bilinmeyen takım için otomatik)
 * Gerçek arma yüklendiğinde dosyanın üzerine yazılır (CLAUDE.md kural 5).
 */
final class RozetUretici
{
    public static function dosyaUret(string $hedefDosya, string $kisaAd, string $renk1, string $renk2): void
    {
        $klasor = dirname($hedefDosya);
        if (!is_dir($klasor)) {
            mkdir($klasor, 0775, true);
        }
        file_put_contents($hedefDosya, self::svg($kisaAd, $renk1, $renk2));
    }

    public static function svg(string $kisaAd, string $renk1, string $renk2): string
    {
        $kisaAd = htmlspecialchars($kisaAd, ENT_XML1, 'UTF-8');
        $yaziRengi = self::renkAcikMi($renk1) ? '#0D3B66' : '#FFFFFF';
        $bantAyirici = self::renkAcikMi($renk2) ? 'rgba(0,0,0,0.28)' : 'rgba(255,255,255,0.55)';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img">
  <defs>
    <clipPath id="kalkan">
      <path d="M32 3 L57 11 V33 C57 47.5 46 57.5 32 62 C18 57.5 7 47.5 7 33 V11 Z"/>
    </clipPath>
  </defs>
  <path d="M32 1 L59 9.5 V33.5 C59 49 47 59.5 32 64 C17 59.5 5 49 5 33.5 V9.5 Z" fill="#FFFFFF" opacity="0.9"/>
  <g clip-path="url(#kalkan)">
    <rect x="0" y="0" width="64" height="64" fill="{$renk1}"/>
    <rect x="0" y="44" width="64" height="20" fill="{$renk2}"/>
    <rect x="0" y="43" width="64" height="1.4" fill="{$bantAyirici}"/>
    <path d="M0 0 L64 0 L64 10 L0 22 Z" fill="#FFFFFF" opacity="0.10"/>
  </g>
  <path d="M32 3 L57 11 V33 C57 47.5 46 57.5 32 62 C18 57.5 7 47.5 7 33 V11 Z"
        fill="none" stroke="#FFFFFF" stroke-width="2" opacity="0.85"/>
  <text x="32" y="28" text-anchor="middle" dominant-baseline="middle"
        font-family="Arial, sans-serif" font-size="16" font-weight="bold"
        fill="{$yaziRengi}">{$kisaAd}</text>
</svg>
SVG;
    }

    public static function renkAcikMi(string $hex): bool
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return false;
        }
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150;
    }
}
