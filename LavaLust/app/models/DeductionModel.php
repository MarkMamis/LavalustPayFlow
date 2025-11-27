<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class DeductionModel extends Model
{
    protected $table = 'deduction_rates';
    protected $primary_key = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get all deduction rates
     */
    public function get_all(): array
    {
        $sql = "SELECT * FROM deduction_rates ORDER BY deduction_type, created_at DESC";
        $stmt = $this->db->raw($sql);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return is_array($result) ? $result : [];
    }

    /**
     * Get deduction rates by type
     */
    public function get_by_type($type): array
    {
        $sql = "SELECT * FROM deduction_rates WHERE deduction_type = ? ORDER BY created_at DESC";
        $stmt = $this->db->raw($sql, [$type]);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return is_array($result) ? $result : [];
    }

    /**
     * Get single deduction rate
     */
    public function find_by_id($id)
    {
        $sql = "SELECT * FROM deduction_rates WHERE id = ?";
        $stmt = $this->db->raw($sql, [$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Create deduction rate
     */
    public function create($data): bool
    {
        $sql = "INSERT INTO deduction_rates 
                (deduction_type, description, rate_type, rate_value, min_amount, max_amount, 
                 salary_min, salary_max, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->raw($sql, [
            $data['deduction_type'] ?? null,
            $data['description'] ?? null,
            $data['rate_type'] ?? 'percentage',
            $data['rate_value'] ?? 0,
            $data['min_amount'] ?? null,
            $data['max_amount'] ?? null,
            $data['salary_min'] ?? null,
            $data['salary_max'] ?? null,
            $data['is_active'] ?? 1
        ]);
        
        return $stmt !== false;
    }

    /**
     * Update deduction rate
     */
    public function update($id, $data): bool
    {
        $sql = "UPDATE deduction_rates SET 
                deduction_type = ?,
                description = ?,
                rate_type = ?,
                rate_value = ?,
                min_amount = ?,
                max_amount = ?,
                salary_min = ?,
                salary_max = ?,
                is_active = ?
                WHERE id = ?";
        
        $stmt = $this->db->raw($sql, [
            $data['deduction_type'] ?? null,
            $data['description'] ?? null,
            $data['rate_type'] ?? 'percentage',
            $data['rate_value'] ?? 0,
            $data['min_amount'] ?? null,
            $data['max_amount'] ?? null,
            $data['salary_min'] ?? null,
            $data['salary_max'] ?? null,
            $data['is_active'] ?? 1,
            $id
        ]);
        
        return $stmt !== false;
    }

    /**
     * Delete deduction rate
     */
    public function delete($id): bool
    {
        $sql = "DELETE FROM deduction_rates WHERE id = ?";
        $stmt = $this->db->raw($sql, [$id]);
        return $stmt !== false;
    }

    /**
     * Calculate deduction amount based on salary and type
     */
    public function calculate_deduction($type, $salary)
    {
        $sql = "SELECT * FROM deduction_rates 
                WHERE deduction_type = ? 
                AND is_active = 1
                AND (salary_min IS NULL OR ? >= salary_min)
                AND (salary_max IS NULL OR ? <= salary_max)
                ORDER BY salary_min DESC
                LIMIT 1";
        
        $stmt = $this->db->raw($sql, [$type, $salary, $salary]);
        $rate = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$rate) {
            return 0.00;
        }
        
        $amount = 0;
        if ($rate['rate_type'] === 'percentage') {
            $amount = ($salary * $rate['rate_value']) / 100;
        } else {
            $amount = $rate['rate_value'];
        }
        
        if ($rate['min_amount'] !== null && $amount < $rate['min_amount']) {
            $amount = $rate['min_amount'];
        }
        if ($rate['max_amount'] !== null && $amount > $rate['max_amount']) {
            $amount = $rate['max_amount'];
        }
        
        return round($amount, 2);
    }
}
