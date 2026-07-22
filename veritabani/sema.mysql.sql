-- ============================================================
-- Maç Günü Rezervasyon Sistemi — MySQL Şeması (ÜRETİM)
-- Hostinger phpMyAdmin'de "İçe Aktar" ile çalıştırılır.
-- Karakter seti: utf8mb4 (tam Türkçe destek)
-- Tüm zamanlar UTC saklanır (DATETIME), görüntüleme Europe/Istanbul.
-- Tüm para alanları KURUŞ cinsinden tam sayıdır.
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Takımlar: Süper Lig 2026-27 takımları (tohum.sql ile doldurulur)
-- ------------------------------------------------------------
CREATE TABLE takimlar (
    id                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    ad                VARCHAR(80)      NOT NULL,               -- "Galatasaray"
    kisa_ad           VARCHAR(8)       NOT NULL,               -- "GS"
    sef_ad            VARCHAR(80)      NOT NULL,               -- url dostu: "galatasaray"
    arma_dosya        VARCHAR(160)     NOT NULL,               -- "armalar/galatasaray.svg"
    renk1             CHAR(7)          NOT NULL,               -- birincil takım rengi (#RRGGBB)
    renk2             CHAR(7)          NOT NULL,               -- ikincil takım rengi
    uc_buyuk          TINYINT(1)       NOT NULL DEFAULT 0,     -- GS/FB/BJK = 1 (fikstür hedefi)
    aktif             TINYINT(1)       NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY tekil_sef_ad (sef_ad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Maçlar
-- ------------------------------------------------------------
CREATE TABLE maclar (
    id                    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    ev_sahibi_takim_id    INT UNSIGNED     NOT NULL,
    deplasman_takim_id    INT UNSIGNED     NOT NULL,
    baslangic_zamani      DATETIME         NOT NULL,           -- UTC
    kapi_acilis_zamani    DATETIME         NULL,               -- UTC (boşsa: başlangıç - 1 saat)
    kisi_basi_fiyat_kurus INT UNSIGNED     NOT NULL DEFAULT 0, -- 0 = ücretsiz maç (ödeme adımı atlanır)
    paket_icerigi         TEXT             NULL,               -- JSON dizi: ["1 medium tavuk dürüm", ...]
    iptal_saat_once       INT              NULL,               -- NULL → ayarlar.varsayilan_iptal_saat
    paylasimli_kontenjan  INT UNSIGNED     NOT NULL DEFAULT 0, -- salon girişi (masasız) kişi kapasitesi, 0 = kapalı
    durum                 VARCHAR(16)      NOT NULL DEFAULT 'taslak',
                          -- taslak | satista | satis_kapali | iptal | tamamlandi
    kaynak                VARCHAR(10)      NOT NULL DEFAULT 'manuel',   -- manuel | fikstur
    fikstur_anahtari      VARCHAR(120)     NULL,               -- scrape tekrarını önleyen tekil anahtar
    olusturma_zamani      DATETIME         NOT NULL,
    guncelleme_zamani     DATETIME         NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY tekil_fikstur (fikstur_anahtari),
    KEY ix_baslangic (baslangic_zamani),
    KEY ix_durum (durum),
    CONSTRAINT fk_mac_ev  FOREIGN KEY (ev_sahibi_takim_id)  REFERENCES takimlar (id),
    CONSTRAINT fk_mac_dep FOREIGN KEY (deplasman_takim_id) REFERENCES takimlar (id),
    CONSTRAINT ck_mac_durum CHECK (durum IN ('taslak','satista','satis_kapali','iptal','tamamlandi')),
    CONSTRAINT ck_mac_kaynak CHECK (kaynak IN ('manuel','fikstur'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Masalar: ANA KAT PLANI (şablon). Kroki 24x16 sanal grid üzerindedir.
-- min_kisi: bu masayı seçebilecek en küçük grup (varsayılan: kapasite - 1)
-- ------------------------------------------------------------
CREATE TABLE masalar (
    id                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    ad                VARCHAR(20)      NOT NULL,               -- "M1", "M2"...
    kapasite          INT UNSIGNED     NOT NULL,
    min_kisi          INT UNSIGNED     NOT NULL DEFAULT 1,
    sekil             VARCHAR(10)      NOT NULL DEFAULT 'kare',  -- kare | yuvarlak
    konum_x           INT              NOT NULL DEFAULT 0,     -- grid hücresi
    konum_y           INT              NOT NULL DEFAULT 0,
    genislik          INT              NOT NULL DEFAULT 2,     -- grid hücresi
    yukseklik         INT              NOT NULL DEFAULT 2,
    aktif             TINYINT(1)       NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY tekil_masa_ad (ad),
    CONSTRAINT ck_masa_sekil CHECK (sekil IN ('kare','yuvarlak'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Maç masaları: maç satışa alınırken ana plandan KOPYALANIR (snapshot).
-- Sonrası bağımsızdır: maça özel ekstra masa, kapatma, taşıma ana planı etkilemez.
-- Eşzamanlılık bu tablo üzerindeki koşullu UPDATE (CAS) ile garanti edilir.
-- ------------------------------------------------------------
CREATE TABLE mac_masalari (
    id                     INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    mac_id                 INT UNSIGNED     NOT NULL,
    kaynak_masa_id         INT UNSIGNED     NULL,              -- NULL = maça özel eklenen ekstra masa
    ad                     VARCHAR(20)      NOT NULL,
    kapasite               INT UNSIGNED     NOT NULL,
    min_kisi               INT UNSIGNED     NOT NULL DEFAULT 1,
    sekil                  VARCHAR(10)      NOT NULL DEFAULT 'kare',
    konum_x                INT              NOT NULL DEFAULT 0,
    konum_y                INT              NOT NULL DEFAULT 0,
    genislik               INT              NOT NULL DEFAULT 2,
    yukseklik              INT              NOT NULL DEFAULT 2,
    durum                  VARCHAR(10)      NOT NULL DEFAULT 'bos',
                           -- bos | tutuldu | rezerve | kapali
    tutma_sona_erme        DATETIME         NULL,              -- UTC; hold bitişi
    tutan_rezervasyon_id   INT UNSIGNED     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY tekil_mac_masa_ad (mac_id, ad),
    KEY ix_mac (mac_id),
    CONSTRAINT fk_macmasa_mac FOREIGN KEY (mac_id) REFERENCES maclar (id) ON DELETE CASCADE,
    CONSTRAINT ck_macmasa_durum CHECK (durum IN ('bos','tutuldu','rezerve','kapali'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Rezervasyonlar (üyeliksiz misafir)
-- tur = 'masa'  → masa seçmeli rezervasyon (rezervasyon_masalari üzerinden)
-- tur = 'salon' → paylaşımlı kontenjan / salon girişi (masasız; yeri işletme belirler)
-- ------------------------------------------------------------
CREATE TABLE rezervasyonlar (
    id                    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    kod                   CHAR(8)          NOT NULL,           -- karışmayan alfabe: 23456789ABCDEFHJKMNPRTUVWXYZ
    mac_id                INT UNSIGNED     NOT NULL,
    tur                   VARCHAR(8)       NOT NULL DEFAULT 'masa',   -- masa | salon
    ad_soyad              VARCHAR(120)     NOT NULL,
    telefon               VARCHAR(20)      NOT NULL,           -- normalize: 05XXXXXXXXX
    eposta                VARCHAR(160)     NOT NULL,
    kisi_sayisi           INT UNSIGNED     NOT NULL,
    toplam_tutar_kurus    INT UNSIGNED     NOT NULL DEFAULT 0,
    durum                 VARCHAR(16)      NOT NULL DEFAULT 'odeme_bekliyor',
                          -- odeme_bekliyor | onaylandi | iptal_edildi | suresi_doldu
    hold_sona_erme        DATETIME         NULL,               -- UTC; salon türü için kontenjan kilidi
    kvkk_onay_zamani      DATETIME         NULL,               -- UTC; ispat için
    sozlesme_onay_zamani  DATETIME         NULL,               -- UTC; mesafeli satış onayı
    qr_nonce              CHAR(16)         NOT NULL,           -- HMAC girdisi; kod sızsa bile QR taklit edilemez
    checkin_zamani        DATETIME         NULL,               -- UTC
    checkin_admin_id      INT UNSIGNED     NULL,
    olusturma_zamani      DATETIME         NOT NULL,
    guncelleme_zamani     DATETIME         NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY tekil_kod (kod),
    KEY ix_rez_mac_durum (mac_id, durum),
    KEY ix_rez_eposta (eposta),
    KEY ix_rez_telefon (telefon),
    CONSTRAINT fk_rez_mac FOREIGN KEY (mac_id) REFERENCES maclar (id),
    CONSTRAINT ck_rez_tur CHECK (tur IN ('masa','salon')),
    CONSTRAINT ck_rez_durum CHECK (durum IN ('odeme_bekliyor','onaylandi','iptal_edildi','suresi_doldu'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Rezervasyon-masa bağı (bir rezervasyon birden çok masa alabilir)
-- ------------------------------------------------------------
CREATE TABLE rezervasyon_masalari (
    rezervasyon_id    INT UNSIGNED     NOT NULL,
    mac_masa_id       INT UNSIGNED     NOT NULL,
    PRIMARY KEY (rezervasyon_id, mac_masa_id),
    UNIQUE KEY tekil_mac_masa (mac_masa_id),   -- bir masa yalnız bir kesin rezervasyona bağlanabilir
    CONSTRAINT fk_rm_rez  FOREIGN KEY (rezervasyon_id) REFERENCES rezervasyonlar (id) ON DELETE CASCADE,
    CONSTRAINT fk_rm_masa FOREIGN KEY (mac_masa_id)    REFERENCES mac_masalari (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Ödemeler
-- ------------------------------------------------------------
CREATE TABLE odemeler (
    id                    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    rezervasyon_id        INT UNSIGNED     NOT NULL,
    saglayici             VARCHAR(16)      NOT NULL,           -- iyzico | mock (yenisi eklenebilir)
    tutar_kurus           INT UNSIGNED     NOT NULL,
    para_birimi           CHAR(3)          NOT NULL DEFAULT 'TRY',
    durum                 VARCHAR(16)      NOT NULL DEFAULT 'baslatildi',
                          -- baslatildi | basarili | basarisiz | iade_edildi
    konusma_kimligi       VARCHAR(64)      NOT NULL,           -- sağlayıcıya giden conversationId
    saglayici_odeme_id    VARCHAR(64)      NULL,               -- sağlayıcının verdiği paymentId
    ham_cevap             TEXT             NULL,               -- JSON; sağlayıcının son ham yanıtı
    iade_tutar_kurus      INT UNSIGNED     NULL,
    iade_zamani           DATETIME         NULL,               -- UTC
    olusturma_zamani      DATETIME         NOT NULL,
    guncelleme_zamani     DATETIME         NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY tekil_konusma (konusma_kimligi),
    KEY ix_odeme_rez (rezervasyon_id),
    KEY ix_odeme_durum (durum),
    CONSTRAINT fk_odeme_rez FOREIGN KEY (rezervasyon_id) REFERENCES rezervasyonlar (id),
    CONSTRAINT ck_odeme_durum CHECK (durum IN ('baslatildi','basarili','basarisiz','iade_edildi'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Ayarlar (anahtar-değer; değerler JSON)
-- ------------------------------------------------------------
CREATE TABLE ayarlar (
    anahtar           VARCHAR(60)      NOT NULL,
    deger             TEXT             NOT NULL,               -- JSON
    PRIMARY KEY (anahtar)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Admin kullanıcıları (betikler/admin-olustur.php ile eklenir)
-- ------------------------------------------------------------
CREATE TABLE admin_kullanicilar (
    id                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    eposta            VARCHAR(160)     NOT NULL,
    sifre_ozeti       VARCHAR(255)     NOT NULL,               -- password_hash()
    ad                VARCHAR(80)      NOT NULL,
    son_giris_zamani  DATETIME         NULL,                   -- UTC
    PRIMARY KEY (id),
    UNIQUE KEY tekil_eposta (eposta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Denetim kaydı (para işlemleri ve kritik admin eylemleri)
-- ------------------------------------------------------------
CREATE TABLE denetim_kayitlari (
    id                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    admin_id          INT UNSIGNED     NULL,                   -- NULL = sistem/müşteri eylemi
    islem             VARCHAR(60)      NOT NULL,               -- "iade_yapildi", "mac_yayinlandi"...
    detay             TEXT             NULL,                   -- JSON
    zaman             DATETIME         NOT NULL,               -- UTC
    PRIMARY KEY (id),
    KEY ix_denetim_zaman (zaman)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
