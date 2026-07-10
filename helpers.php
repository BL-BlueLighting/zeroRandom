<?php
/**
 * OIManka - Helper Functions
 * Loaded automatically via init_check.php after config.php.
 */

// Kaleidoscope constants (also in config.php, duplicated here since config.php is excluded from deploy)
if (!defined('KALEIDOSCOPE_NAME')) define('KALEIDOSCOPE_NAME', 'zeroRandom The Kaleidoscope');
if (!defined('KALEIDOSCOPE_ENTRY_FEE')) define('KALEIDOSCOPE_ENTRY_FEE', 1000000000000);
if (!defined('KALEIDOSCOPE_CONVERT_RATE')) define('KALEIDOSCOPE_CONVERT_RATE', 100000000);
if (!defined('KALEIDOSCOPE_DURATION')) define('KALEIDOSCOPE_DURATION', 24);

/**
 * Get the active table name for current layer.
 * Kaleidoscope mode uses ks_* prefixed tables.
 */
if (!function_exists('ks_table')) {
    function ks_table(string $base): string {
        $ksTables = ['holdings','transactions','gacha_logs','card_placements','card_market_listings','daily_checkins','card_pools','card_pool_items'];
        if (is_kaleidoscope() && in_array($base, $ksTables)) {
            return 'ks_' . $base;
        }
        return $base;
    }
}

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
    function nf($num, int $decimals = 2): string {
        $num = (float)$num;
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
