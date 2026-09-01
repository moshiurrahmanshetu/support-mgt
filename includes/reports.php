<?php
/**
 * Reports & Analytics Helper (support-mgt Phase 07)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/settings.php';

/**
 * Parse and validate report date range filters
 *
 * @param array $queryParams Usually $_GET
 * @param string $defaultPreset Default preset if none provided
 * @return array
 */
function get_report_date_range(array $queryParams = [], string $defaultPreset = 'last_30_days'): array {
    $preset = trim($queryParams['date_range'] ?? $defaultPreset);
    $fromDate = trim($queryParams['from_date'] ?? '');
    $toDate = trim($queryParams['to_date'] ?? '');

    $today = new DateTime('today');
    $start = clone $today;
    $end = clone $today;
    $label = 'Last 30 Days';

    switch ($preset) {
        case 'today':
            $label = 'Today (' . $today->format('M d, Y') . ')';
            break;

        case 'yesterday':
            $start->modify('-1 day');
            $end->modify('-1 day');
            $label = 'Yesterday (' . $start->format('M d, Y') . ')';
            break;

        case 'last_7_days':
            $start->modify('-6 days');
            $label = 'Last 7 Days (' . $start->format('M d') . ' - ' . $end->format('M d, Y') . ')';
            break;

        case 'this_month':
            $start->modify('first day of this month');
            $label = 'This Month (' . $start->format('F Y') . ')';
            break;

        case 'last_month':
            $start->modify('first day of last month');
            $end->modify('last day of last month');
            $label = 'Last Month (' . $start->format('F Y') . ')';
            break;

        case 'custom':
            $validFrom = DateTime::createFromFormat('Y-m-d', $fromDate);
            $validTo = DateTime::createFromFormat('Y-m-d', $toDate);

            if ($validFrom && $validTo && $validFrom->format('Y-m-d') === $fromDate && $validTo->format('Y-m-d') === $toDate) {
                if ($validFrom > $validTo) {
                    // Swap if reversed
                    $temp = $validFrom;
                    $validFrom = $validTo;
                    $validTo = $temp;
                }
                $start = $validFrom;
                $end = $validTo;
                $label = 'Custom Range (' . $start->format('M d, Y') . ' - ' . $end->format('M d, Y') . ')';
            } else {
                // Fallback to default
                $preset = 'last_30_days';
                $start->modify('-29 days');
                $label = 'Last 30 Days (' . $start->format('M d') . ' - ' . $end->format('M d, Y') . ')';
            }
            break;

        case 'last_30_days':
        default:
            $preset = 'last_30_days';
            $start->modify('-29 days');
            $label = 'Last 30 Days (' . $start->format('M d') . ' - ' . $end->format('M d, Y') . ')';
            break;
    }

    return [
        'preset'    => $preset,
        'from_date' => $start->format('Y-m-d'),
        'to_date'   => $end->format('Y-m-d'),
        'from'      => $start->format('Y-m-d') . ' 00:00:00',
        'to'        => $end->format('Y-m-d') . ' 23:59:59',
        'label'     => $label
    ];
}

/**
 * Format duration in seconds into human-readable representation
 *
 * @param int|float|null $seconds
 * @return string
 */
function format_duration($seconds): string {
    if ($seconds === null || $seconds === false || $seconds === '') {
        return 'N/A';
    }

    $sec = (int)round((float)$seconds);

    if ($sec <= 0) {
        return '0m';
    }

    if ($sec < 60) {
        return '< 1m';
    }

    if ($sec < 3600) {
        $mins = (int)floor($sec / 60);
        return "{$mins}m";
    }

    if ($sec < 86400) {
        $hours = (int)floor($sec / 3600);
        $mins = (int)floor(($sec % 3600) / 60);
        return ($mins > 0) ? "{$hours}h {$mins}m" : "{$hours}h";
    }

    $days = (int)floor($sec / 86400);
    $remHours = (int)floor(($sec % 86400) / 3600);
    return ($remHours > 0) ? "{$days}d {$remHours}h" : "{$days}d";
}

/**
 * Sanitize CSV cell values to prevent Formula Injection (=, +, -, @)
 *
 * @param mixed $value
 * @return string
 */
function sanitize_csv_cell($value): string {
    if ($value === null) {
        return '';
    }

    $str = (string)$value;
    $trimmed = trim($str);

    // If starts with potential formula characters, prepend single quote
    if (strlen($trimmed) > 0 && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
        return "'" . $str;
    }

    return $str;
}

/**
 * Calculate safe percentage without division by zero
 *
 * @param int|float $numerator
 * @param int|float $denominator
 * @param int $precision
 * @return float
 */
function safe_percentage($numerator, $denominator, int $precision = 1): float {
    if ($denominator <= 0) {
        return 0.0;
    }
    return round(($numerator / $denominator) * 100, $precision);
}
