<?php
/**
 * OIManka - Helper Functions
 * Loaded automatically via init_check.php after config.php.
 */

/**
 * Check if current session is in Kaleidoscope (天·界) layer.
 */
if (!function_exists('is_kaleidoscope')) {
    function is_kaleidoscope(): bool {
        return ($_SESSION['layer'] ?? 'default') === 'kaleidoscope';
    }
}

if (!function_exists('nf')) {
    /**
     * Format numbers based on user's preferred style.
     * Styles: 'wan' (万/亿), '4digit' (1,2345), '3digit' (1,234)
     */
    function nf(float $num, int $decimals = 2): string {
        $style = $_SESSION['number_style'] ?? 'wan';

        if ($style === '3digit') {
            return number_format($num, $decimals);
        }

        if ($style === '4digit') {
            // Custom 4-digit grouping: 1,2345,6789.12
            $parts = explode('.', number_format($num, $decimals, '.', ''));
            $intPart = $parts[0];
            $neg = '';
            if ($intPart[0] === '-') { $neg = '-'; $intPart = substr($intPart, 1); }
            $len = strlen($intPart);
            $groups = [];
            if ($len > 4) {
                $first = $len % 4;
                if ($first > 0) $groups[] = substr($intPart, 0, $first);
                for ($i = $first; $i < $len; $i += 4) {
                    $groups[] = substr($intPart, $i, 4);
                }
            } else {
                $groups[] = $intPart;
            }
            $result = $neg . implode(',', $groups);
            if (isset($parts[1])) $result .= '.' . $parts[1];
            return $result;
        }

        // Default: wan/亿
        if ($num >= 100000000) {
            return number_format($num / 100000000, $decimals) . '亿';
        } elseif ($num >= 10000) {
            return number_format($num / 10000, $decimals) . '万';
        }
        return number_format($num, $decimals);
    }
}
