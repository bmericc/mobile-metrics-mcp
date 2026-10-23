<?php

namespace App\Stores;

use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Exception\MissingResourceException;

/**
 * Mağazalar ülkeyi farklı formatlarda veriyor (App Store yorumları alpha-3, raporlar alpha-2).
 * Saklanan format her zaman ISO 3166-1 alpha-2.
 */
final class Territory
{
    public static function toAlpha2(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return match (strlen($code)) {
            2 => $code,
            3 => self::fromAlpha3($code),
            default => null,
        };
    }

    private static function fromAlpha3(string $code): ?string
    {
        try {
            return Countries::getAlpha2Code($code);
        } catch (MissingResourceException) {
            return null;
        }
    }
}
