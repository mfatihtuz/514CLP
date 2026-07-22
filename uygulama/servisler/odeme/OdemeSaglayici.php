<?php

declare(strict_types=1);

/**
 * Sanal POS soyutlaması — CLAUDE.md kural 11: sağlayıcı DEĞİŞTİRİLEBİLİR olmalı.
 *
 * Yeni sağlayıcı eklemek: bu arayüzü uygulayan tek sınıf yaz
 * (ör. PaytrSaglayici) ve OdemeSecici::olustur() içine kaydet.
 *
 * Para birimi daima kuruş cinsinden tam sayıdır; ondalıklı biçime çeviri
 * sağlayıcı sınıfının kendi içinde yapılır.
 */
interface OdemeSaglayici
{
    /** Sağlayıcının kısa adı: 'mock' | 'iyzico' ... (odemeler.saglayici kolonu) */
    public function ad(): string;

    /**
     * Ödemeyi başlatır; müşterinin yönlendirileceği ödeme sayfası URL'ini döner.
     *
     * @param array<string, mixed> $rezervasyonDetay RezervasyonIslemleri::kodIleDetay çıktısı
     * @param string $konusmaKimligi odemeler.konusma_kimligi (tekil)
     * @param string $donusUrl 3DS/banka sonrası sağlayıcının döneceği adres
     * @return array{yonlendirme_url: string, ham: array<string, mixed>}
     * @throws OdemeHatasi
     */
    public function odemeBaslat(array $rezervasyonDetay, string $konusmaKimligi, string $donusUrl): array;

    /**
     * Callback'ten SONRA sonucu sağlayıcıdan sorgular.
     * KURAL: callback gövdesine asla güvenilmez; gerçek sonuç budur.
     *
     * @return array{
     *   basarili: bool,
     *   konusma_kimligi: ?string,
     *   saglayici_odeme_id: ?string,
     *   odenen_kurus: ?int,
     *   ham: array<string, mixed>
     * }
     */
    public function odemeSorgula(string $jeton, ?string $konusmaKimligi = null): array;

    /**
     * Tam veya kısmi iade.
     * @param array<string, mixed> $odeme odemeler tablosu satırı (ham_cevap dahil)
     * @return array{basarili: bool, ham: array<string, mixed>}
     */
    public function iadeYap(array $odeme, int $tutarKurus): array;
}
