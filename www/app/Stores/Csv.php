<?php

namespace App\Stores;

/**
 * Mağaza rapor CSV'leri için ortak okuyucu: BOM / UTF-16 çözümü ve başlık satırı tespiti.
 */
final class Csv
{
    public static function decode(string $content): string
    {
        if (str_starts_with($content, "\xFF\xFE")) {
            return mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
        }

        if (str_starts_with($content, "\xFE\xFF")) {
            return mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
        }

        return str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
    }

    /**
     * Başlık, $isHeader'ın true döndüğü ilk satırdır; öncesindeki başlık/açıklama satırları atlanır.
     * Başlık bulunamazsa header boş döner.
     *
     * @param  callable(array<string>): bool  $isHeader
     * @return array{header: array<string>, rows: array<array<string, string>>}
     */
    public static function parse(string $content, callable $isHeader, string $delimiter = ','): array
    {
        $header = [];
        $rows = [];

        foreach (preg_split('/\r\n|\n|\r/', self::decode($content)) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = array_map('trim', str_getcsv($line, $delimiter, '"', ''));

            if ($header === []) {
                if ($isHeader($values)) {
                    $header = $values;
                }

                continue;
            }

            if (count($values) === count($header)) {
                $rows[] = array_combine($header, $values);
            }
        }

        return ['header' => $header, 'rows' => $rows];
    }
}
