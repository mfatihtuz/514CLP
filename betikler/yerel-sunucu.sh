#!/usr/bin/env bash
# Yerel geliştirme sunucusu: http://localhost:8514
# Ön koşul: .env dosyasında VT_SURUCU=sqlite ve kurulmuş veritabanı
#   cp .env.ornek .env
#   php betikler/vt-kur.php
set -e
cd "$(dirname "$0")/.."
echo "http://localhost:8514 adresinde çalışıyor (durdurmak için Ctrl+C)"
php -S localhost:8514 -t public
