-- ============================================================
-- Maç Günü Rezervasyon Sistemi — SQLite Şeması (YEREL GELİŞTİRME)
-- Üretim şeması sema.mysql.sql'dir; bu dosya birebir eşleniğidir.
-- İki dosya HER ZAMAN birlikte güncellenir.
-- ============================================================

PRAGMA foreign_keys = ON;

CREATE TABLE takimlar (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    ad                TEXT    NOT NULL,
    kisa_ad           TEXT    NOT NULL,
    sef_ad            TEXT    NOT NULL UNIQUE,
    arma_dosya        TEXT    NOT NULL,
    renk1             TEXT    NOT NULL,
    renk2             TEXT    NOT NULL,
    uc_buyuk          INTEGER NOT NULL DEFAULT 0,
    aktif             INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE maclar (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    ev_sahibi_takim_id    INTEGER NOT NULL REFERENCES takimlar (id),
    deplasman_takim_id    INTEGER NOT NULL REFERENCES takimlar (id),
    baslangic_zamani      TEXT    NOT NULL,            -- UTC "YYYY-MM-DD HH:MM:SS"
    kapi_acilis_zamani    TEXT    NULL,
    kisi_basi_fiyat_kurus INTEGER NOT NULL DEFAULT 0,
    paket_icerigi         TEXT    NULL,
    iptal_saat_once       INTEGER NULL,
    paylasimli_kontenjan  INTEGER NOT NULL DEFAULT 0,
    durum                 TEXT    NOT NULL DEFAULT 'taslak'
                          CHECK (durum IN ('taslak','satista','satis_kapali','iptal','tamamlandi')),
    kaynak                TEXT    NOT NULL DEFAULT 'manuel' CHECK (kaynak IN ('manuel','fikstur')),
    fikstur_anahtari      TEXT    NULL UNIQUE,
    olusturma_zamani      TEXT    NOT NULL,
    guncelleme_zamani     TEXT    NOT NULL
);
CREATE INDEX ix_baslangic ON maclar (baslangic_zamani);
CREATE INDEX ix_durum ON maclar (durum);

CREATE TABLE masalar (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    ad                TEXT    NOT NULL UNIQUE,
    kapasite          INTEGER NOT NULL,
    min_kisi          INTEGER NOT NULL DEFAULT 1,
    sekil             TEXT    NOT NULL DEFAULT 'kare' CHECK (sekil IN ('kare','yuvarlak')),
    konum_x           INTEGER NOT NULL DEFAULT 0,
    konum_y           INTEGER NOT NULL DEFAULT 0,
    genislik          INTEGER NOT NULL DEFAULT 2,
    yukseklik         INTEGER NOT NULL DEFAULT 2,
    aktif             INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE mac_masalari (
    id                     INTEGER PRIMARY KEY AUTOINCREMENT,
    mac_id                 INTEGER NOT NULL REFERENCES maclar (id) ON DELETE CASCADE,
    kaynak_masa_id         INTEGER NULL,
    ad                     TEXT    NOT NULL,
    kapasite               INTEGER NOT NULL,
    min_kisi               INTEGER NOT NULL DEFAULT 1,
    sekil                  TEXT    NOT NULL DEFAULT 'kare',
    konum_x                INTEGER NOT NULL DEFAULT 0,
    konum_y                INTEGER NOT NULL DEFAULT 0,
    genislik               INTEGER NOT NULL DEFAULT 2,
    yukseklik              INTEGER NOT NULL DEFAULT 2,
    durum                  TEXT    NOT NULL DEFAULT 'bos'
                           CHECK (durum IN ('bos','tutuldu','rezerve','kapali')),
    tutma_sona_erme        TEXT    NULL,
    tutan_rezervasyon_id   INTEGER NULL,
    UNIQUE (mac_id, ad)
);
CREATE INDEX ix_mac ON mac_masalari (mac_id);

CREATE TABLE rezervasyonlar (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    kod                   TEXT    NOT NULL UNIQUE,
    mac_id                INTEGER NOT NULL REFERENCES maclar (id),
    tur                   TEXT    NOT NULL DEFAULT 'masa' CHECK (tur IN ('masa','salon')),
    ad_soyad              TEXT    NOT NULL,
    telefon               TEXT    NOT NULL,
    eposta                TEXT    NOT NULL,
    kisi_sayisi           INTEGER NOT NULL,
    toplam_tutar_kurus    INTEGER NOT NULL DEFAULT 0,
    durum                 TEXT    NOT NULL DEFAULT 'odeme_bekliyor'
                          CHECK (durum IN ('odeme_bekliyor','onaylandi','iptal_edildi','suresi_doldu')),
    hold_sona_erme        TEXT    NULL,
    kvkk_onay_zamani      TEXT    NULL,
    sozlesme_onay_zamani  TEXT    NULL,
    qr_nonce              TEXT    NOT NULL,
    checkin_zamani        TEXT    NULL,
    checkin_admin_id      INTEGER NULL,
    olusturma_zamani      TEXT    NOT NULL,
    guncelleme_zamani     TEXT    NOT NULL
);
CREATE INDEX ix_rez_mac_durum ON rezervasyonlar (mac_id, durum);
CREATE INDEX ix_rez_eposta ON rezervasyonlar (eposta);
CREATE INDEX ix_rez_telefon ON rezervasyonlar (telefon);

CREATE TABLE rezervasyon_masalari (
    rezervasyon_id    INTEGER NOT NULL REFERENCES rezervasyonlar (id) ON DELETE CASCADE,
    mac_masa_id       INTEGER NOT NULL UNIQUE REFERENCES mac_masalari (id),
    PRIMARY KEY (rezervasyon_id, mac_masa_id)
);

CREATE TABLE odemeler (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    rezervasyon_id        INTEGER NOT NULL REFERENCES rezervasyonlar (id),
    saglayici             TEXT    NOT NULL,
    tutar_kurus           INTEGER NOT NULL,
    para_birimi           TEXT    NOT NULL DEFAULT 'TRY',
    durum                 TEXT    NOT NULL DEFAULT 'baslatildi'
                          CHECK (durum IN ('baslatildi','basarili','basarisiz','iade_edildi')),
    konusma_kimligi       TEXT    NOT NULL UNIQUE,
    saglayici_odeme_id    TEXT    NULL,
    ham_cevap             TEXT    NULL,
    iade_tutar_kurus      INTEGER NULL,
    iade_zamani           TEXT    NULL,
    olusturma_zamani      TEXT    NOT NULL,
    guncelleme_zamani     TEXT    NOT NULL
);
CREATE INDEX ix_odeme_rez ON odemeler (rezervasyon_id);
CREATE INDEX ix_odeme_durum ON odemeler (durum);

CREATE TABLE ayarlar (
    anahtar           TEXT PRIMARY KEY,
    deger             TEXT NOT NULL
);

CREATE TABLE admin_kullanicilar (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    eposta            TEXT NOT NULL UNIQUE,
    sifre_ozeti       TEXT NOT NULL,
    ad                TEXT NOT NULL,
    son_giris_zamani  TEXT NULL
);

CREATE TABLE denetim_kayitlari (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_id          INTEGER NULL,
    islem             TEXT NOT NULL,
    detay             TEXT NULL,
    zaman             TEXT NOT NULL
);
CREATE INDEX ix_denetim_zaman ON denetim_kayitlari (zaman);
