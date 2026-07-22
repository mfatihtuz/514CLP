<?php

declare(strict_types=1);

/**
 * Tarih-saat yardımcıları — projede tarih işlemi YALNIZCA buradan yapılır.
 * Kural: veritabanında UTC ("Y-m-d H:i:s"), görüntüleme Europe/Istanbul.
 */

const TR_SAAT_DILIMI = 'Europe/Istanbul';

const TR_AYLAR = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
    'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

const TR_GUNLER = [1 => 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];

/** Şu anın UTC damgası: "2026-07-22 14:05:00" */
function simdiUtc(): string
{
    return gmdate('Y-m-d H:i:s');
}

/** UTC damgayı İstanbul saatli DateTimeImmutable'a çevirir. */
function utcNesne(string $utcDamga): DateTimeImmutable
{
    return (new DateTimeImmutable($utcDamga, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone(TR_SAAT_DILIMI));
}

/** İstanbul yerel girdisini ("2026-08-14T21:00" gibi, admin formundan) UTC damgaya çevirir. */
function yerelGirdiyiUtcYap(string $yerelGirdi): string
{
    $nesne = new DateTimeImmutable($yerelGirdi, new DateTimeZone(TR_SAAT_DILIMI));
    return $nesne->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

/** "21:45" — İstanbul saati */
function saatBicimle(string $utcDamga): string
{
    return utcNesne($utcDamga)->format('H:i');
}

/** "14 Ağustos 2026" */
function tarihBicimle(string $utcDamga): string
{
    $nesne = utcNesne($utcDamga);
    return $nesne->format('j') . ' ' . TR_AYLAR[(int) $nesne->format('n')] . ' ' . $nesne->format('Y');
}

/** "Cuma, 14 Ağustos — 21:45" (maç kartlarının ana biçimi) */
function macZamaniBicimle(string $utcDamga): string
{
    $nesne = utcNesne($utcDamga);
    return TR_GUNLER[(int) $nesne->format('N')] . ', '
        . $nesne->format('j') . ' ' . TR_AYLAR[(int) $nesne->format('n')]
        . ' — ' . $nesne->format('H:i');
}

/** Admin formu datetime-local girdisi için: "2026-08-14T21:00" (İstanbul) */
function yerelGirdiBicimle(string $utcDamga): string
{
    return utcNesne($utcDamga)->format('Y-m-d\TH:i');
}

/** UTC damgaya saat/dakika ekler-çıkarır: eksi değer geçmişe gider. */
function utcKaydir(string $utcDamga, int $dakika): string
{
    return (new DateTimeImmutable($utcDamga, new DateTimeZone('UTC')))
        ->modify(($dakika >= 0 ? '+' : '') . $dakika . ' minutes')
        ->format('Y-m-d H:i:s');
}

/** İki UTC damga arasındaki saniye farkı (a - b). */
function utcFarkSaniye(string $utcA, string $utcB): int
{
    return (new DateTimeImmutable($utcA, new DateTimeZone('UTC')))->getTimestamp()
        - (new DateTimeImmutable($utcB, new DateTimeZone('UTC')))->getTimestamp();
}
