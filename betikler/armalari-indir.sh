#!/usr/bin/env bash
# ============================================================
# Orijinal takım armalarını (SVG) Wikimedia Commons'tan indirir.
#
# ÖNEMLİ NOTLAR:
# - Bu geliştirme ortamının ağ politikası wikimedia.org'u ENGELLİYOR;
#   betik normal bir bilgisayarda / Hostinger SSH'ta çalıştırılmalıdır.
# - Dosya adları en iyi tahminlerdir ve FAZ 5'TE DOĞRULANACAKTIR:
#   404 dönen takımlar raporlanır, o takımlar için jenerik rozet
#   (betikler/rozet-uret.php) kullanılmaya devam eder.
# - Telif notu: kulüp armaları tescilli markadır; ticari kullanım
#   kulüp iznine tabi olabilir. İtiraz durumunda rozet-uret.php
#   çıktılarına dönülür (CLAUDE.md kural 5).
# ============================================================
set -u
cd "$(dirname "$0")/../public/armalar"

indir() {
    sefad="$1"; dosyaadi="$2"
    url="https://commons.wikimedia.org/wiki/Special:FilePath/${dosyaadi}"
    gecici="${sefad}.indiriliyor"
    if curl -sfL --max-time 30 "$url" -o "$gecici"; then
        if head -c 200 "$gecici" | grep -qi "<svg\|<?xml"; then
            mv "$gecici" "${sefad}.svg"
            echo "TAMAM : ${sefad}.svg"
            return 0
        fi
    fi
    rm -f "$gecici"
    echo "HATA  : ${sefad} — ${url} bulunamadı (jenerik rozet kullanılmaya devam edecek)"
    return 1
}

# sef_ad  →  Wikimedia Commons dosya adı (URL kodlu)
indir galatasaray     "Galatasaray_Sports_Club_Logo.svg"
indir fenerbahce      "Fenerbah%C3%A7e_SK.svg"
indir besiktas        "Besiktas_JK.svg"
indir trabzonspor     "Trabzonspor_logo.svg"
indir samsunspor      "Samsunspor_logo.svg"
indir basaksehir      "%C4%B0stanbul_Ba%C5%9Fak%C5%9Fehir_FK.svg"
indir eyupspor        "Ey%C3%BCpspor_logo.svg"
indir goztepe         "G%C3%B6ztepe_SK_logo.svg"
indir kasimpasa       "Kas%C4%B1mpa%C5%9Fa_SK_logo.svg"
indir konyaspor       "Konyaspor_logo.svg"
indir alanyaspor      "Alanyaspor_logo.svg"
indir gaziantep-fk    "Gaziantep_FK_logo.svg"
indir caykur-rizespor "%C3%87aykur_Rizespor_logo.svg"
indir kocaelispor     "Kocaelispor_logo.svg"
indir genclerbirligi  "Gen%C3%A7lerbirli%C4%9Fi_SK_logo.svg"
indir erzurumspor-fk  "Erzurumspor_FK_logo.svg"
indir amed-sfk        "Amed_SK_logo.svg"
indir corum-fk        "%C3%87orum_FK_logo.svg"

echo ""
echo "Bitti. HATA satırı varsa o takımların Commons dosya adları Faz 5'te elle bulunmalıdır."
