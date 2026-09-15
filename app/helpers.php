<?php

if (!function_exists('storage_url')) {
    /**
     * Generate URL ke file di storage/public yang benar,
     * menyesuaikan subdirektori aplikasi (karena APP_URL mungkin tidak termasuk subdirektori).
     * Contoh: storage_url('bukti-pembayaran/abc.jpg')
     */
    function storage_url(string $path): string
    {
        return request()->root() . '/storage/' . ltrim($path, '/');
    }
}

if (!function_exists('fmt_rupiah')) {
    /**
     * Format a money amount into Indonesian Rupiah display string.
     *
     * Examples:
     *   fmt_rupiah(5000000)       → "Rp 5.000.000"
     *   fmt_rupiah(5000000, false) → "5.000.000"
     */
    function fmt_rupiah($amount, bool $withPrefix = true): string
    {
        $int = (int) round((float) ($amount ?? 0));
        $formatted = number_format($int, 0, ',', '.');
        return $withPrefix ? 'Rp ' . $formatted : $formatted;
    }
}

if (!function_exists('fmt_qty')) {
    /**
     * Format angka qty dengan pemisah ribuan titik, desimal koma (format Indonesia).
     * Tidak menampilkan desimal nol yang tidak perlu.
     *
     * Contoh:
     *   fmt_qty(2000)      → "2.000"
     *   fmt_qty(2000000)   → "2.000.000"
     *   fmt_qty(1500.5)    → "1.500,5"
     *   fmt_qty(0.25)      → "0,25"
     */
    function fmt_qty($qty, int $maxDecimals = 3): string
    {
        $formatted = number_format((float) $qty, $maxDecimals, ',', '.');
        return rtrim(rtrim($formatted, '0'), ',');
    }
}
