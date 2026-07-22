<?php

declare(strict_types=1);

/**
 * SQL dosyalarını güvenli biçimde ifadelere böler.
 *
 * Tırnak ve yorum DUYARLIDIR:
 *  - Tek tırnaklı dizgeler ('...') içindeki ; # -- korunur (ör. '#A90432' renk değeri,
 *    JSON içeriği). Naif explode(';') bu değerleri bozardı.
 *  - Satır yorumları (-- ... ve # ...) ve blok yorumları (slash-yıldız) atılır.
 *  - Böylece "-- UTC; hold bitişi" gibi içinde ; geçen yorumlar ifadeyi bölmez.
 *
 * @return array<int, string> boş olmayan, kırpılmış SQL ifadeleri
 */
function sqlIfadeleriBol(string $sql): array
{
    $ifadeler = [];
    $tampon = '';
    $uzunluk = strlen($sql);
    $i = 0;
    $dizgeIci = false;

    while ($i < $uzunluk) {
        $c = $sql[$i];

        if ($dizgeIci) {
            $tampon .= $c;
            if ($c === '\\' && $i + 1 < $uzunluk) {      // \' \\ gibi kaçışlar
                $tampon .= $sql[$i + 1];
                $i += 2;
                continue;
            }
            if ($c === "'") {
                if ($i + 1 < $uzunluk && $sql[$i + 1] === "'") { // '' kaçışı
                    $tampon .= "'";
                    $i += 2;
                    continue;
                }
                $dizgeIci = false;
            }
            $i++;
            continue;
        }

        // Satır yorumu: -- (ardından boşluk/satır sonu) → satır sonuna kadar at
        if ($c === '-' && $i + 1 < $uzunluk && $sql[$i + 1] === '-'
            && ($i + 2 >= $uzunluk || ctype_space($sql[$i + 2]))) {
            $satirSonu = strpos($sql, "\n", $i);
            $i = $satirSonu === false ? $uzunluk : $satirSonu + 1;
            $tampon .= ' ';
            continue;
        }

        // Satır yorumu: # → satır sonuna kadar at
        if ($c === '#') {
            $satirSonu = strpos($sql, "\n", $i);
            $i = $satirSonu === false ? $uzunluk : $satirSonu + 1;
            $tampon .= ' ';
            continue;
        }

        // Blok yorumu: /* ... */
        if ($c === '/' && $i + 1 < $uzunluk && $sql[$i + 1] === '*') {
            $son = strpos($sql, '*/', $i + 2);
            $i = $son === false ? $uzunluk : $son + 2;
            continue;
        }

        if ($c === "'") {
            $dizgeIci = true;
            $tampon .= $c;
            $i++;
            continue;
        }

        if ($c === ';') {
            $ifade = trim($tampon);
            if ($ifade !== '') {
                $ifadeler[] = $ifade;
            }
            $tampon = '';
            $i++;
            continue;
        }

        $tampon .= $c;
        $i++;
    }

    $ifade = trim($tampon);
    if ($ifade !== '') {
        $ifadeler[] = $ifade;
    }
    return $ifadeler;
}

/**
 * Bir SQL dosyasındaki tüm ifadeleri çalıştırır (şema/tohum kurulumu).
 * @throws RuntimeException dosya yoksa
 */
function sqlDosyasiCalistir(string $yol): void
{
    if (!is_file($yol)) {
        throw new RuntimeException('SQL dosyası bulunamadı: ' . basename($yol));
    }
    foreach (sqlIfadeleriBol((string) file_get_contents($yol)) as $ifade) {
        Veritabani::baglanti()->exec($ifade);
    }
}
