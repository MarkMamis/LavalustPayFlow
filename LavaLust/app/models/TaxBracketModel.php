<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class TaxBracketModel extends Model
{
    protected $table = 'tax_brackets';
    protected $primary_key = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get all tax brackets
     */
    public function get_all(): array
    {
        $sql = "SELECT * FROM tax_brackets ORDER BY income_from ASC";
        $stmt = $this->db->raw($sql);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return is_array($result) ? $result : [];
    }

    /**
     * Get single tax bracket
     */
    public function find_by_id($id)
    {
        $sql = "SELECT * FROM tax_brackets WHERE id = ?";
        $stmt = $this->db->raw($sql, [$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Create tax bracket
     */
    public function create($data): bool
    {
        $sql = "INSERT INTO tax_brackets 
                (income_from, income_to, base_tax, rate_percentage, excess_over, is_active) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->raw($sql, [
            $data['income_from'] ?? 0,
            $data['income_to'] ?? 0,
            $data['base_tax'] ?? 0,
            $data['rate_percentage'] ?? 0,
            $data['excess_over'] ?? 0,
            $data['is_active'] ?? 1
        ]);
        
        return $stmt !== false;
    }

    /**
     * Update tax bracket
     */
    public function update($id, $data): bool
    {
        $sql = "UPDATE tax_brackets SET 
                income_from = ?,
                income_to = ?,
                base_tax = ?,
                rate_percentage = ?,
                excess_over = ?,
                is_active = ?
                WHERE id = ?";
        
        $stmt = $this->db->raw($sql, [
            $data['income_from'] ?? 0,
            $data['income_to'] ?? 0,
            $data['base_tax'] ?? 0,
            $data['rate_percentage'] ?? 0,
            $data['excess_over'] ?? 0,
            $data['is_active'] ?? 1,
            $id
        ]);
        
        return $stmt !== false;
    }

    /**
     * Delete tax bracket
     */
    public function delete($id): bool
    {
        $sql = "DELETE FROM tax_brackets WHERE id = ?";
        $stmt = $this->db->raw($sql, [$id]);
        return $stmt !== false;
    }

    /**
     * Calculate tax for given income
     */
    public function calculate_tax($income)
    {
        $sql = "SELECT * FROM tax_brackets 
                WHERE is_active = 1
                AND ? >= income_from 
                AND ? <= income_to
                LIMIT 1";
        
        $stmt = $this->db->raw($sql, [$income, $income]);
        $bracket = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$bracket) {
            return 0.00;
        }
        
        $tax = floatval($bracket['base_tax']) + 
               (($income - floatval($bracket['excess_over'])) * (floatval($bracket['rate_percentage']) / 100));
        
        return round(max(0, $tax), 2);
    }
}
