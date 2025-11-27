<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
 * Ensure environment variables are loaded similar to mailer_helper
 * This makes sure getenv() and $_ENV are populated when this helper runs.
 */
if (class_exists('Dotenv\Dotenv') && file_exists(ROOT_DIR . '.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(ROOT_DIR);
    $dotenv->load();
}

/**
 * Currency helper - uses ExchangeRate API and simple file cache
 */
if (!function_exists('currency_cache_path')) {
    function currency_cache_path(string $base = 'PHP') {
        $cacheDir = ROOT_DIR . 'runtime/cache/';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        return $cacheDir . 'currency_rates_' . strtoupper($base) . '.json';
    }
}

if (!function_exists('currency_get_rates')) {
    function currency_get_rates(string $base = 'PHP', int $ttl = 3600) {
        $apiKey = getenv('EXCHANGE_RATE_API_KEY') ?: ($_ENV['EXCHANGE_RATE_API_KEY'] ?? '');
        $base = strtoupper($base ?: 'PHP');

        // Try application cache helper first (key: fx_rates_{BASE})
        if (function_exists('cache_get')) {
            $cacheKey = "fx_rates_{$base}";
            $cached = cache_get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Build API URL (ExchangeRate-API v6)
        if (empty($apiKey)) {
            return null;
        }

        $url = sprintf('https://v6.exchangerate-api.com/v6/%s/latest/%s', urlencode($apiKey), urlencode($base));

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: LavaLust-CurrencyHelper/1.0\r\n",
                'timeout' => 5
            ]
        ];

        $context = stream_context_create($opts);
        // Try file_get_contents first, fallback to curl if allow_url_fopen is disabled
        $json = false;
        if (ini_get('allow_url_fopen')) {
            $json = @file_get_contents($url, false, $context);
        }

        if ($json === false && function_exists('curl_version')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'LavaLust-CurrencyHelper/1.0');
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $json = curl_exec($ch);
            curl_close($ch);
        }

        $data = $json ? json_decode($json, true) : null;

        if (!empty($data['conversion_rates'])) {
            // Save using cache helper if available, otherwise fallback to file cache
            if (function_exists('cache_set')) {
                cache_set("fx_rates_{$base}", $data['conversion_rates'], $ttl);
            } else {
                $cacheFile = currency_cache_path($base);
                @file_put_contents($cacheFile, $json);
            }
            return $data['conversion_rates'];
        }

        return null;
    }
}

if (!function_exists('currency_convert')) {
    function currency_convert(float $amount, string $from = 'PHP', string $to = 'USD') {
        $from = strtoupper($from ?: 'PHP');
        $to = strtoupper($to ?: 'USD');

        // Try to get rates using base = from
        $rates = currency_get_rates($from);
        if ($rates && isset($rates[$to])) {
            return round($amount * floatval($rates[$to]), 2);
        }

        // If failed, try base = USD and compute cross rate
        $ratesUsd = currency_get_rates('USD');
        if ($ratesUsd && isset($ratesUsd[$from]) && isset($ratesUsd[$to])) {
            $rateFrom = floatval($ratesUsd[$from]);
            $rateTo = floatval($ratesUsd[$to]);
            if ($rateFrom > 0) {
                $converted = ($amount / $rateFrom) * $rateTo;
                return round($converted, 2);
            }
        }

        return false;
    }
}
