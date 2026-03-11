<?php

// Simple polyfill for PHP < 8.0
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        if ($needle === '') {
            return true;
        }
        return strpos($haystack, $needle) === 0;
    }
}

function normalize_number_za(string $raw): ?string {
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === null || $digits === '') {
        return null;
    }

    // Handle formats like +27..., 27..., 0027...
    if (str_starts_with($digits, '27')) {
        $digits = '0' . substr($digits, 2);
    } elseif (str_starts_with($digits, '0027')) {
        $digits = '0' . substr($digits, 4);
    }

    if (strlen($digits) === 10 && $digits[0] === '0') {
        return $digits;
    }

    return null;
}

function to_e164_za(string $normalized): string {
    // Input like 0821234567 -> +27821234567
    if (strlen($normalized) === 10 && $normalized[0] === '0') {
        return '+27' . substr($normalized, 1);
    }
    return $normalized;
}

