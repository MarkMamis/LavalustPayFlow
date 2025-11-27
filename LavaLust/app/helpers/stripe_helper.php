<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Stripe helper
 * - Initializes Stripe SDK with secret key from env
 * - Provides minor-unit conversion helper
 */
if (!function_exists('stripe_init')) {
    function stripe_init()
    {
        // Load env like other helpers
        $dotenv = Dotenv\Dotenv::createImmutable(ROOT_DIR);
        $dotenv->load();

        $secret = $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY');
        if (!$secret) {
            return false;
        }

        \Stripe\Stripe::setApiKey($secret);
        return true;
    }
}

if (!function_exists('stripe_amount_to_minor')) {
    function stripe_amount_to_minor(float $amount, string $currency): int
    {
        // List of zero-decimal currencies (no minor unit)
        $zeroDecimal = [
            'BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF'
        ];
        $currency = strtoupper($currency);
        $exp = in_array($currency, $zeroDecimal) ? 0 : 2;
        $factor = (int) pow(10, $exp);
        return (int) round($amount * $factor);
    }
}

if (!function_exists('stripe_create_payment_intent')) {
    /**
     * Create a PaymentIntent
     * @param float $amount
     * @param string $currency
     * @param array $metadata
     * @param bool $confirm whether to confirm immediately (useful in test mode)
     * @param string|null $payment_method optional payment method id to use when confirming
     */
    function stripe_create_payment_intent(float $amount, string $currency, array $metadata = [], bool $confirm = false, ?string $payment_method = null)
    {
        if (!stripe_init()) {
            throw new Exception('Stripe secret key not configured');
        }

        $amountInt = stripe_amount_to_minor($amount, $currency);

        $params = [
            'amount' => $amountInt,
            'currency' => strtolower($currency),
            'metadata' => $metadata,
            // automatic methods allow multiple payment methods if ever needed
            // disable redirects to avoid requiring return_url in test mode
            'automatic_payment_methods' => [
                'enabled' => true,
                'allow_redirects' => 'never'
            ]
        ];

        if ($confirm) {
            $params['confirm'] = true;
            if ($payment_method) {
                $params['payment_method'] = $payment_method;
            } else {
                // If running with a test key, use a test PM so PaymentIntent is confirmed immediately
                $secret = $_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY');
                if ($secret && strpos($secret, 'sk_test') === 0) {
                    $params['payment_method'] = 'pm_card_visa';
                }
            }
        }

        return \Stripe\PaymentIntent::create($params);
    }
}
