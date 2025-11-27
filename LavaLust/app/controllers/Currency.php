<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Currency extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->helper('currency');
    }

    /**
     * POST /api/currency/convert
     * Body: { amount, from, to }
     */
    public function convert()
    {
        header('Content-Type: application/json');
        // Accept POST (JSON body) or GET (query params) for convenience
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $data = [];
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
        } else {
            $data = $_GET ?: [];
        }

        $amount = isset($data['amount']) ? floatval($data['amount']) : null;
        $from = isset($data['from']) ? $data['from'] : 'PHP';
        $to = isset($data['to']) ? $data['to'] : 'USD';

        if ($amount === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Amount is required']);
            return;
        }

        $apiKey = getenv('EXCHANGE_RATE_API_KEY') ?: ($_ENV['EXCHANGE_RATE_API_KEY'] ?? '');
        if (empty($apiKey)) {
            http_response_code(500);
            echo json_encode(['error' => 'Missing EXCHANGE_RATE_API_KEY in environment']);
            return;
        }

        // Check cache for conversion result (short TTL) to avoid repeated work
        $cacheKey = null;
        if (function_exists('cache_get') && function_exists('cache_set')) {
            $camount = number_format(floatval($amount), 2, '.', '');
            $cacheKey = "currency_conv_{$from}_{$to}_{$camount}";
            $cached = cache_get($cacheKey);
            if ($cached !== null) {
                echo json_encode(array_merge(['cached' => true], $cached));
                return;
            }
        }

        // Attempt to fetch rates to provide better diagnostics
        $rates = currency_get_rates(strtoupper($from));
        if (!$rates) {
            // try USD base fallback
            $ratesUsd = currency_get_rates('USD');
            if (!$ratesUsd) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to fetch exchange rates from API. Check API key and network.']);
                return;
            }
            // If USD rates are available but direct base isn't, proceed with conversion via USD
        }

        $converted = currency_convert($amount, $from, $to);

        if ($converted === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Conversion failed - no rate available for target currency']);
            return;
        }

        $response = [
            'amount' => round($amount, 2),
            'from' => strtoupper($from),
            'to' => strtoupper($to),
            'converted' => $converted
        ];

        if ($cacheKey) {
            // cache conversion for 5 minutes (300s)
            cache_set($cacheKey, $response, 300);
            $response['cached'] = false;
        }

        // Return single JSON response
        echo json_encode($response);
    }

    /**
     * GET /api/currency/ping
     * Returns whether API key is loaded (masked) and cache file presence
     */
    public function ping()
    {
        header('Content-Type: application/json');
        $apiKey = getenv('EXCHANGE_RATE_API_KEY') ?: ($_ENV['EXCHANGE_RATE_API_KEY'] ?? '');
        $hasKey = !empty($apiKey);
        $masked = $hasKey ? substr($apiKey, 0, 4) . str_repeat('*', max(0, strlen($apiKey) - 8)) . substr($apiKey, -4) : null;
        echo json_encode([
            'has_key' => $hasKey,
            'masked_key' => $masked,
            'env_loaded' => !empty($_ENV),
        ]);
    }
}
