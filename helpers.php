<?php
/**
 * OIManka - Helper Functions
 * Loaded automatically via init_check.php after config.php.
 */

if (!function_exists('nf')) {
    function nf(float $num, int $decimals = 2): string {
        if ($num >= 100000000) {
            return number_format($num / 100000000, $decimals) . '亿';
        } elseif ($num >= 10000) {
            return number_format($num / 10000, $decimals) . '万';
        }
        return number_format($num, $decimals);
    }
}
