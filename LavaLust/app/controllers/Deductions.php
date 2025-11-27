<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Deductions extends Controller
{
    protected $DeductionModel;
    protected $TaxBracketModel;

    public function __construct()
    {
        parent::__construct();
        $this->DeductionModel = new DeductionModel();
        $this->TaxBracketModel = new TaxBracketModel();
    }

    /**
     * GET /api/deductions
     * Get all deduction rates
     */
    public function index()
    {
        header('Content-Type: application/json');
        
        try {
            $deductions = $this->DeductionModel->get_all();
            
            echo json_encode([
                'success' => true,
                'data' => $deductions,
                'message' => 'Deductions retrieved successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error retrieving deductions: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * GET /api/deductions/type/:type
     * Get deductions by type
     */
    public function get_by_type($type = null)
    {
        header('Content-Type: application/json');
        
        if (!$type) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Deduction type is required'
            ]);
            return;
        }

        try {
            $deductions = $this->DeductionModel->get_by_type($type);
            
            echo json_encode([
                'success' => true,
                'data' => $deductions,
                'message' => 'Deductions retrieved successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error retrieving deductions: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * GET /api/deductions/:id
     * Get single deduction rate
     */
    public function view($id = null)
    {
        header('Content-Type: application/json');
        
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Deduction ID is required'
            ]);
            return;
        }

        try {
            $deduction = $this->DeductionModel->find_by_id($id);
            
            if (!$deduction) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Deduction not found'
                ]);
                return;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $deduction
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error retrieving deduction: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * POST /api/deductions
     * Create new deduction rate
     */
    public function create()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        if (!isset($input['deduction_type']) || !isset($input['rate_value'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Deduction type and rate value are required'
            ]);
            return;
        }

        try {
            $data = [
                'deduction_type' => $input['deduction_type'],
                'description' => $input['description'] ?? '',
                'rate_type' => $input['rate_type'] ?? 'percentage',
                'rate_value' => floatval($input['rate_value']),
                'min_amount' => floatval($input['min_amount'] ?? 0),
                'max_amount' => floatval($input['max_amount'] ?? 0),
                'salary_min' => floatval($input['salary_min'] ?? 0),
                'salary_max' => floatval($input['salary_max'] ?? 0),
                'is_active' => $input['is_active'] ?? 1
            ];

            $result = $this->DeductionModel->create($data);

            if ($result) {
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Deduction created successfully'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create deduction'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error creating deduction: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * PUT/POST /api/deductions/:id
     * Update deduction rate
     */
    public function update($id = null)
    {
        header('Content-Type: application/json');
        
        // Accept id from route parameter or request body
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }
        
        // Use id from route, or fall back to id from request body
        if (!$id && isset($input['id'])) {
            $id = $input['id'];
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Deduction ID is required'
            ]);
            return;
        }

        try {
            $deduction = $this->DeductionModel->find_by_id($id);
            
            if (!$deduction) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Deduction not found'
                ]);
                return;
            }

            $data = [
                'deduction_type' => $input['deduction_type'] ?? $deduction['deduction_type'],
                'description' => $input['description'] ?? $deduction['description'],
                'rate_type' => $input['rate_type'] ?? $deduction['rate_type'],
                'rate_value' => isset($input['rate_value']) ? floatval($input['rate_value']) : $deduction['rate_value'],
                'min_amount' => isset($input['min_amount']) ? floatval($input['min_amount']) : $deduction['min_amount'],
                'max_amount' => isset($input['max_amount']) ? floatval($input['max_amount']) : $deduction['max_amount'],
                'salary_min' => isset($input['salary_min']) ? floatval($input['salary_min']) : $deduction['salary_min'],
                'salary_max' => isset($input['salary_max']) ? floatval($input['salary_max']) : $deduction['salary_max'],
                'is_active' => isset($input['is_active']) ? $input['is_active'] : $deduction['is_active']
            ];

            $result = $this->DeductionModel->update($id, $data);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Deduction updated successfully'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update deduction'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error updating deduction: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * DELETE/POST /api/deductions/:id or /api/deductions/:id/delete
     * Delete deduction rate
     */
    public function delete($id = null)
    {
        header('Content-Type: application/json');
        
        // Accept id from route parameter or request body
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }
        
        // Use id from route, or fall back to id from request body
        if (!$id && isset($input['id'])) {
            $id = $input['id'];
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Deduction ID is required'
            ]);
            return;
        }

        try {
            $deduction = $this->DeductionModel->find_by_id($id);
            
            if (!$deduction) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Deduction not found'
                ]);
                return;
            }

            $result = $this->DeductionModel->delete($id);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Deduction deleted successfully'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete deduction'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error deleting deduction: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * GET /api/deductions/calculate/:type/:salary
     * Calculate deduction amount
     */
    public function calculate($type = null, $salary = null)
    {
        header('Content-Type: application/json');
        
        if (!$type || !$salary) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Deduction type and salary are required'
            ]);
            return;
        }

        try {
            $amount = $this->DeductionModel->calculate_deduction($type, floatval($salary));
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'type' => $type,
                    'salary' => floatval($salary),
                    'amount' => round($amount, 2)
                ],
                'message' => 'Deduction calculated successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error calculating deduction: ' . $e->getMessage()
            ]);
        }
    }

    // ==================== TAX BRACKET ENDPOINTS ====================

    /**
     * GET /api/deductions/tax-brackets
     * Get all tax brackets
     */
    public function tax_brackets()
    {
        header('Content-Type: application/json');
        
        try {
            $brackets = $this->TaxBracketModel->get_all();
            
            echo json_encode([
                'success' => true,
                'data' => $brackets,
                'message' => 'Tax brackets retrieved successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error retrieving tax brackets: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * GET /api/deductions/tax-brackets/:id
     * Get single tax bracket
     */
    public function view_tax_bracket($id = null)
    {
        header('Content-Type: application/json');
        
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Tax bracket ID is required'
            ]);
            return;
        }

        try {
            $bracket = $this->TaxBracketModel->find_by_id($id);
            
            if (!$bracket) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Tax bracket not found'
                ]);
                return;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $bracket
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error retrieving tax bracket: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * POST /api/deductions/tax-brackets
     * Create new tax bracket
     */
    public function create_tax_bracket()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        if (!isset($input['income_from']) || !isset($input['income_to']) || !isset($input['rate_percentage'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Income from, income to, and rate percentage are required'
            ]);
            return;
        }

        try {
            $data = [
                'income_from' => floatval($input['income_from']),
                'income_to' => floatval($input['income_to']),
                'base_tax' => floatval($input['base_tax'] ?? 0),
                'rate_percentage' => floatval($input['rate_percentage']),
                'excess_over' => floatval($input['excess_over'] ?? 0),
                'is_active' => $input['is_active'] ?? 1
            ];

            $result = $this->TaxBracketModel->create($data);

            if ($result) {
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Tax bracket created successfully'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create tax bracket'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error creating tax bracket: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * PUT/POST /api/deductions/tax-brackets/:id
     * Update tax bracket
     */
    public function update_tax_bracket($id = null)
    {
        header('Content-Type: application/json');
        
        // Accept id from route parameter or request body
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }
        
        // Use id from route, or fall back to id from request body
        if (!$id && isset($input['id'])) {
            $id = $input['id'];
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Tax bracket ID is required'
            ]);
            return;
        }

        try {
            $bracket = $this->TaxBracketModel->find_by_id($id);
            
            if (!$bracket) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Tax bracket not found'
                ]);
                return;
            }

            $data = [
                'income_from' => isset($input['income_from']) ? floatval($input['income_from']) : $bracket['income_from'],
                'income_to' => isset($input['income_to']) ? floatval($input['income_to']) : $bracket['income_to'],
                'base_tax' => isset($input['base_tax']) ? floatval($input['base_tax']) : $bracket['base_tax'],
                'rate_percentage' => isset($input['rate_percentage']) ? floatval($input['rate_percentage']) : $bracket['rate_percentage'],
                'excess_over' => isset($input['excess_over']) ? floatval($input['excess_over']) : $bracket['excess_over'],
                'is_active' => isset($input['is_active']) ? $input['is_active'] : $bracket['is_active']
            ];

            $result = $this->TaxBracketModel->update($id, $data);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Tax bracket updated successfully'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update tax bracket'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error updating tax bracket: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * DELETE/POST /api/deductions/tax-brackets/:id or /api/deductions/tax-brackets/:id/delete
     * Delete tax bracket
     */
    public function delete_tax_bracket($id = null)
    {
        header('Content-Type: application/json');
        
        // Accept id from route parameter or request body
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }
        
        // Use id from route, or fall back to id from request body
        if (!$id && isset($input['id'])) {
            $id = $input['id'];
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Tax bracket ID is required'
            ]);
            return;
        }

        try {
            $bracket = $this->TaxBracketModel->find_by_id($id);
            
            if (!$bracket) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Tax bracket not found'
                ]);
                return;
            }

            $result = $this->TaxBracketModel->delete($id);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Tax bracket deleted successfully'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete tax bracket'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error deleting tax bracket: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * GET /api/deductions/tax-brackets/calculate/:income
     * Calculate tax for given income
     */
    public function calculate_tax($income = null)
    {
        header('Content-Type: application/json');
        
        if (!$income) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Income is required'
            ]);
            return;
        }

        try {
            $tax = $this->TaxBracketModel->calculate_tax(floatval($income));
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'income' => floatval($income),
                    'tax' => round($tax, 2)
                ],
                'message' => 'Tax calculated successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error calculating tax: ' . $e->getMessage()
            ]);
        }
    }
}
