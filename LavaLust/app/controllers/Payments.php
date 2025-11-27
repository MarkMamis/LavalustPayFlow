<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Payments extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model('PaymentsModel');
        $this->call->model('PayrollModel');
        $this->call->model('EmployeeModel');
        $this->call->helper('stripe');
        $this->call->helper('currency');
    }

    /**
     * GET /api/payments
     * Fetch all payments
     */
    public function index()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        try {
            $payments = $this->PaymentsModel->get_all();
            http_response_code(200);
            echo json_encode(['payments' => $payments]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch payments: ' . $e->getMessage()]);
        }
    }

    /**
     * POST /api/payments/disburse
     * Expected JSON: { payroll_id, employee_id, currency, amount }
     */
    public function disburse()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $payroll_id = isset($data['payroll_id']) ? (int)$data['payroll_id'] : null;
        $employee_id = isset($data['employee_id']) ? (int)$data['employee_id'] : null;
        $currency = isset($data['currency']) ? strtoupper($data['currency']) : 'PHP';
        $amount = isset($data['amount']) ? floatval($data['amount']) : null;

        if (!$payroll_id || !$employee_id) {
            http_response_code(400);
            echo json_encode(['error' => 'payroll_id and employee_id are required']);
            return;
        }

        try {
            // Prevent duplicate disbursement for the same payroll
            $existingPayment = $this->PaymentsModel->find_by_payroll((int)$payroll_id);
            if ($existingPayment) {
                http_response_code(409);
                echo json_encode(['error' => 'Payment already exists for this payroll', 'existing' => $existingPayment]);
                return;
            }
            // If amount not provided, fetch payroll and use net_salary
            if ($amount === null) {
                // No amount provided by client: use payroll.net_salary (assumed PHP) and convert if needed
                $pay = $this->PayrollModel->get_by_id($payroll_id);
                if (!$pay) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Payroll record not found']);
                    return;
                }
                $raw = str_replace(',', '', $pay['net_salary'] ?? '0');
                $amountPhp = floatval($raw);
                if ($currency !== 'PHP') {
                    $converted = currency_convert($amountPhp, 'PHP', $currency);
                    if ($converted === false) {
                        http_response_code(500);
                        echo json_encode(['error' => 'Currency conversion failed']);
                        return;
                    }
                    $amount = $converted;
                } else {
                    $amount = $amountPhp;
                }
            } else {
                // Amount provided by client is assumed to already be in the requested currency.
                // Do not perform any further conversion here to avoid double-conversion.
                $amount = floatval($amount);
            }

            // Create a Stripe PaymentIntent to record the disbursement (no connected accounts in this flow)
            // In test mode, confirm immediately so the intent becomes a succeeded charge (uses test PM)
            $pi = stripe_create_payment_intent($amount, $currency, ['payroll_id' => $payroll_id, 'employee_id' => $employee_id], true);

            // Compute amount_minor (cents for USD, etc.)
            $amountMinor = stripe_amount_to_minor($amount, $currency);

            // Save to payments table (simplified schema)
            $paymentData = [
                'payroll_id' => $payroll_id,
                'employee_id' => $employee_id,
                'amount' => number_format((float)$amount, 2, '.', ''),
                'currency' => $currency,
                'amount_minor' => $amountMinor,
                'stripe_payment_id' => $pi->id ?? null,
                'status' => $pi->status ?? 'pending',
                'notes' => 'Disbursement via Stripe PaymentIntent'
            ];

            $insertId = $this->PaymentsModel->create_payment($paymentData);

            // Mark payroll record as paid since disbursement succeeded
            try {
                if ($insertId && $payroll_id) {
                    $this->PayrollModel->update_payroll($payroll_id, ['status' => 'paid']);
                }
            } catch (Exception $e) {
                // ignore payroll update failures but log if you have logging
            }

            http_response_code(200);
            echo json_encode([
                'payment_id' => $insertId,
                'stripe_client_secret' => $pi->client_secret ?? null,
                'status' => $pi->status ?? 'pending'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create disbursement: ' . $e->getMessage()]);
        }
    }

    /**
     * PUT /api/payments/:id
     * Expected JSON: { status, notes } (optional fields to update)
     */
    public function update($id = null)
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $id = (int)$id;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Payment ID is required']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        try {
            $payment = $this->PaymentsModel->find_by_id($id);
            if (!$payment) {
                http_response_code(404);
                echo json_encode(['error' => 'Payment not found']);
                return;
            }

            $updateData = [];
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            if (isset($data['notes'])) {
                $updateData['notes'] = $data['notes'];
            }

            if (empty($updateData)) {
                http_response_code(400);
                echo json_encode(['error' => 'No fields to update']);
                return;
            }

            $success = $this->PaymentsModel->update_payment($id, $updateData);
            if ($success) {
                http_response_code(200);
                echo json_encode(['message' => 'Payment updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update payment']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * DELETE /api/payments/:id
     */
    public function delete($id = null)
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $id = (int)$id;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Payment ID is required']);
            return;
        }

        try {
            $payment = $this->PaymentsModel->find_by_id($id);
            if (!$payment) {
                http_response_code(404);
                echo json_encode(['error' => 'Payment not found']);
                return;
            }

            $success = $this->PaymentsModel->delete_payment($id);
            if ($success) {
                http_response_code(200);
                echo json_encode(['message' => 'Payment deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete payment']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /api/exchange-rates
     * Fetch PHP to other currency exchange rates
     */
    public function exchange_rates()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        try {
            // Get exchange rates using the currency helper
            $rates = currency_get_rates('PHP', 86400); // Cache for 24 hours

            if ($rates === null) {
                http_response_code(503);
                echo json_encode(['error' => 'Exchange rates temporarily unavailable']);
                return;
            }

            // Filter only the currencies we want to display
            $currenciesWanted = ['USD', 'EUR', 'SGD', 'JPY', 'AUD', 'GBP'];
            $filteredRates = [];
            
            foreach ($currenciesWanted as $currency) {
                if (isset($rates[$currency])) {
                    $filteredRates[$currency] = $rates[$currency];
                }
            }

            if (empty($filteredRates)) {
                http_response_code(503);
                echo json_encode(['error' => 'No exchange rates available']);
                return;
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'base' => 'PHP',
                'rates' => $filteredRates,
                'timestamp' => time()
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch exchange rates: ' . $e->getMessage()]);
        }
    }
}
