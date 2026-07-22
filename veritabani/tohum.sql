-- ============================================================
-- Tohum verisi: 2026-27 Süper Lig takımları + varsayılan ayarlar
-- Hem MySQL hem SQLite ile uyumludur.
-- Takım listesi 22.07.2026'da doğrulandı:
--   Düşenler: Fatih Karagümrük, Kayserispor, Antalyaspor
--   Yükselenler: Erzurumspor FK, Amed SFK, Çorum FK
-- ============================================================

INSERT INTO takimlar (ad, kisa_ad, sef_ad, arma_dosya, renk1, renk2, uc_buyuk, aktif) VALUES
('Galatasaray',      'GS',  'galatasaray',     'armalar/galatasaray.svg',     '#A90432', '#FDB912', 1, 1),
('Fenerbahçe',       'FB',  'fenerbahce',      'armalar/fenerbahce.svg',      '#163962', '#FFED00', 1, 1),
('Beşiktaş',         'BJK', 'besiktas',        'armalar/besiktas.svg',        '#000000', '#FFFFFF', 1, 1),
('Trabzonspor',      'TS',  'trabzonspor',     'armalar/trabzonspor.svg',     '#641E31', '#40B4E5', 0, 1),
('Samsunspor',       'SAM', 'samsunspor',      'armalar/samsunspor.svg',      '#E30613', '#FFFFFF', 0, 1),
('Başakşehir',       'İBF', 'basaksehir',      'armalar/basaksehir.svg',      '#14387F', '#F26522', 0, 1),
('Eyüpspor',         'EYP', 'eyupspor',        'armalar/eyupspor.svg',        '#7B2D7C', '#FFD200', 0, 1),
('Göztepe',          'GÖZ', 'goztepe',         'armalar/goztepe.svg',         '#FFCC00', '#DA121A', 0, 1),
('Kasımpaşa',        'KAS', 'kasimpasa',       'armalar/kasimpasa.svg',       '#003C7E', '#FFFFFF', 0, 1),
('Konyaspor',        'KON', 'konyaspor',       'armalar/konyaspor.svg',       '#046A38', '#FFFFFF', 0, 1),
('Alanyaspor',       'ALA', 'alanyaspor',      'armalar/alanyaspor.svg',      '#F58220', '#00934A', 0, 1),
('Gaziantep FK',     'GFK', 'gaziantep-fk',    'armalar/gaziantep-fk.svg',    '#E30613', '#000000', 0, 1),
('Çaykur Rizespor',  'RİZ', 'caykur-rizespor', 'armalar/caykur-rizespor.svg', '#00925B', '#0033A0', 0, 1),
('Kocaelispor',      'KOC', 'kocaelispor',     'armalar/kocaelispor.svg',     '#00754A', '#000000', 0, 1),
('Gençlerbirliği',   'GB',  'genclerbirligi',  'armalar/genclerbirligi.svg',  '#DA121A', '#000000', 0, 1),
('Erzurumspor FK',   'ERZ', 'erzurumspor-fk',  'armalar/erzurumspor-fk.svg',  '#003DA5', '#FFFFFF', 0, 1),
('Amed SFK',         'AMD', 'amed-sfk',        'armalar/amed-sfk.svg',        '#E30613', '#009A44', 0, 1),
('Çorum FK',         'ÇOR', 'corum-fk',        'armalar/corum-fk.svg',        '#DA121A', '#000000', 0, 1);

-- Varsayılan ayarlar (değerler JSON)
INSERT INTO ayarlar (anahtar, deger) VALUES
('restoran_adi',                  '"Restoranımız"'),
('site_adi',                      '"Maç Gecesi Rezervasyon"'),
('taban_url',                     '"https://rezervasyon.mftyazilim.com"'),
('iletisim_telefon',              '""'),
('iletisim_adres',                '""'),
('varsayilan_iptal_saat',         '2'),
('hold_dakika',                   '10'),
('varsayilan_paket',              '["1 medium tavuk dürüm","1 ayran","Sınırsız çay"]'),
('paylasimli_kontenjan_varsayilan', '8'),
('masa_min_kisi_varsayilan_fark', '1');
