<?php

namespace App\Support;

/**
 * Satu-satunya tempat aturan kuantitas dan aritmetika desimal.
 *
 * - Validasi: angka positif, maksimal N angka di belakang koma (N = materials.qty_decimals).
 * - Aritmetika: memakai bcmath pada string, BUKAN float (0.1 + 0.2 != 0.3 pada float).
 *   Nilai float yang masuk dinormalisasi dulu (lihat normalize()), tidak pernah dipotong ke int.
 */
final class Quantity
{
    /** Sesuai kolom decimal(15,3). */
    public const SCALE = 3;

    /**
     * Mengembalikan pesan kesalahan, atau null bila kuantitas valid.
     *
     * @param  int  $decimals  materials.qty_decimals (0-3)
     */
    public static function error(mixed $value, int $decimals, string $label = 'Kuantitas'): ?string
    {
        $value = trim((string) $value);

        // Hanya digit, titik sebagai pemisah desimal, maksimal 12 digit bulat (muat di decimal(15,3)).
        if (! preg_match('/^\d{1,12}(\.\d+)?$/', $value)) {
            return "{$label} harus berupa angka positif (gunakan titik sebagai pemisah desimal).";
        }

        // Angka nol di akhir tidak dihitung: "12.500" setara "12.5", "3.0" setara "3".
        [, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = rtrim($fraction, '0');

        if (strlen($fraction) > $decimals) {
            return $decimals === 0
                ? "{$label} hanya boleh bilangan bulat."
                : "{$label} maksimal {$decimals} angka di belakang koma.";
        }

        if (bccomp($value, '0', self::SCALE) <= 0) {
            return "{$label} harus lebih dari 0.";
        }

        return null;
    }

    public static function isValid(mixed $value, int $decimals): bool
    {
        return self::error($value, $decimals) === null;
    }

    public static function add(string|int|float|null $a, string|int|float|null $b): string
    {
        return bcadd(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function sub(string|int|float|null $a, string|int|float|null $b): string
    {
        return bcsub(self::normalize($a), self::normalize($b), self::SCALE);
    }

    /** -1 jika a < b, 0 jika sama, 1 jika a > b. */
    public static function compare(string|int|float|null $a, string|int|float|null $b): int
    {
        return bccomp(self::normalize($a), self::normalize($b), self::SCALE);
    }

    /** Untuk tampilan saja: 12.5 menjadi "12,500" bila material 3 desimal. */
    public static function format(string|int|float|null $value, int $decimals): string
    {
        return number_format((float) self::normalize($value), $decimals, ',', '.');
    }

    /**
     * Mengubah nilai apa pun menjadi string desimal yang aman untuk bcmath.
     *
     * Wajib ada karena:
     * - SQLite (dipakai test) mengembalikan kolom decimal sebagai FLOAT, dan PHP akan
     *   memotong float ke int bila diterima parameter bertipe string|int (12.5 menjadi 12).
     * - Model yang baru dibuat belum memuat nilai default dari database (null).
     */
    private static function normalize(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_float($value)) {
            return number_format($value, self::SCALE, '.', '');
        }

        return (string) $value;
    }
}
