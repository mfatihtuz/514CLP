#!/usr/bin/env bash
# ============================================================
# Hostinger (public_html) için DÜZ YERLEŞİM dağıtım paketi hazırlar.
#
# Sırlar bu betikte YOKTUR; veritabanı bilgileri ortam değişkeniyle verilir,
# gizli anahtarlar çalışma anında üretilir ve YALNIZCA çıktı .env'ine yazılır.
#
# Kullanım:
#   VT_AD=... VT_KULLANICI=... VT_SIFRE=... TABAN_URL=https://site \
#     bash betikler/dagitim-hazirla.sh <cikti_klasoru>
#
# Test için: VT_SURUCU=sqlite ... (mysql creds gerekmez)
# ============================================================
set -euo pipefail

KOK="$(cd "$(dirname "$0")/.." && pwd)"
CIKTI="${1:-$KOK/gecici/dagitim}"
BUILD="$CIKTI/paket"

VT_SURUCU="${VT_SURUCU:-mysql}"
VT_SUNUCU="${VT_SUNUCU:-localhost}"
TABAN_URL="${TABAN_URL:-https://rezervasyon.mftyazilim.com}"

echo "Dağıtım paketi hazırlanıyor: $BUILD ($VT_SURUCU)"
rm -rf "$CIKTI"
mkdir -p "$BUILD"

# --- Uygulama kodu (web'e kapalı klasörler) ---
for d in uygulama vendor veritabani betikler docs; do
    cp -r "$KOK/$d" "$BUILD/$d"
done
# dağıtım şablonları paket içinde tekrar taşınmasın
rm -rf "$BUILD/betikler/dagitim"

# --- Public varlıkları köke ---
cp -r "$KOK/public/varliklar" "$BUILD/varliklar"
cp -r "$KOK/public/armalar" "$BUILD/armalar"
cp "$KOK/public/simge.svg" "$BUILD/simge.svg"

# --- Düz ön denetleyici + sertleştirilmiş kök .htaccess + sihirbaz ---
cp "$KOK/betikler/dagitim/index-duz.php" "$BUILD/index.php"
cp "$KOK/betikler/dagitim/htaccess-kok" "$BUILD/.htaccess"
cp "$KOK/betikler/dagitim/kurulum.php" "$BUILD/kurulum.php"

# --- Hassas klasörlere ek "deny" .htaccess ---
for d in uygulama vendor veritabani betikler docs; do
    cp "$KOK/betikler/dagitim/htaccess-engelle" "$BUILD/$d/.htaccess"
done

# --- Geliştirme artıklarını temizle ---
find "$BUILD" -name '.git' -prune -exec rm -rf {} + 2>/dev/null || true
find "$BUILD" -name '*.sqlite' -delete 2>/dev/null || true
find "$BUILD" -name '*.sqlite-journal' -delete 2>/dev/null || true
rm -rf "$BUILD/gecici" 2>/dev/null || true

# --- .env (gizli anahtarlar burada üretilir) ---
QR="$(php -r 'echo bin2hex(random_bytes(24));')"
CRON="$(php -r 'echo bin2hex(random_bytes(12));')"
KUR="$(php -r 'echo bin2hex(random_bytes(12));')"

HEDEF="$BUILD/.env" \
VT_AD="$VT_AD" VT_KULLANICI="$VT_KULLANICI" VT_SIFRE="${VT_SIFRE:-}" \
VT_SURUCU="$VT_SURUCU" VT_SUNUCU="$VT_SUNUCU" TABAN_URL="$TABAN_URL" \
QR="$QR" CRON="$CRON" KUR="$KUR" \
php -r '
$satirlar = [
    "ORTAM=uretim",
    "TABAN_URL=" . getenv("TABAN_URL"),
    "",
    "VT_SURUCU=" . getenv("VT_SURUCU"),
    "VT_SUNUCU=" . getenv("VT_SUNUCU"),
    "VT_AD=" . getenv("VT_AD"),
    "VT_KULLANICI=" . getenv("VT_KULLANICI"),
    "VT_SIFRE=" . getenv("VT_SIFRE"),
    "",
    "# Sanal POS anahtarlari gelene kadar ucretsiz maclarla yayindasiniz.",
    "# Anahtarlar gelince asagidaki 2 satiri doldurun; canli icin TEMEL_URL i degistirin.",
    "ODEME_SAGLAYICI=iyzico",
    "IYZICO_API_ANAHTARI=",
    "IYZICO_GIZLI_ANAHTAR=",
    "IYZICO_TEMEL_URL=https://sandbox-api.iyzipay.com",
    "",
    "QR_IMZA_ANAHTARI=" . getenv("QR"),
    "",
    "# E-posta: hPanelde rezervasyon@mftyazilim.com hesabi acip 2 satiri doldurun.",
    "SMTP_SUNUCU=smtp.hostinger.com",
    "SMTP_PORT=465",
    "SMTP_KULLANICI=",
    "SMTP_SIFRE=",
    "SMTP_GONDEREN_AD=Mac Gecesi Rezervasyon",
    "",
    "FIKSTUR_API_ANAHTARI=",
    "CRON_GIZLI_ANAHTAR=" . getenv("CRON"),
    "KURULUM_ANAHTARI=" . getenv("KUR"),
    "",
];
file_put_contents(getenv("HEDEF"), implode("\n", $satirlar));
'

chmod 600 "$BUILD/.env"

# --- Zip (icerik kokte olacak sekilde) ---
ZIP="$CIKTI/rezervasyon-hostinger-hazir.zip"
( cd "$BUILD" && zip -rqX "$ZIP" . -x '*.DS_Store' )

echo "----"
echo "Paket: $ZIP"
echo "KURULUM_ANAHTARI=$KUR"
echo "CRON_GIZLI_ANAHTAR=$CRON"
echo "Dosya sayisi: $(cd "$BUILD" && find . -type f | wc -l)"
