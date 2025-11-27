<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class SalaryGradeModel extends Model
{
    protected $table = 'salary_grades';
    protected $primary_key = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get monthly salary for a specific grade and step
     * @param int $salary_grade
     * @param int $step
     * @return float|null
     */
    public function get_monthly_salary(int $salary_grade, int $step)
    {
        $sql = "SELECT monthly_salary FROM {$this->table} 
                WHERE salary_grade = ? AND step = ? 
                ORDER BY effective_date DESC LIMIT 1";
        
        $stmt = $this->db->raw($sql, [$salary_grade, $step]);
        
        if (!$stmt) {
            return null;
        }

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ? (float)$result['monthly_salary'] : null;
    }

    /**
     * Get all salary grades with their steps
     * @param bool $grouped Group by salary grade with steps array
     * @return array
     */
    public function get_all_grades($grouped = false): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY salary_grade ASC, step ASC";
        
        $stmt = $this->db->raw($sql);
        
        if (!$stmt) {
            return [];
        }

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (!is_array($result)) {
            return [];
        }
        
        if ($grouped) {
            // Group by salary_grade
            $grouped_data = [];
            foreach ($result as $row) {
                $grade = $row['salary_grade'];
                if (!isset($grouped_data[$grade])) {
                    $grouped_data[$grade] = [
                        'salary_grade' => $grade,
                        'steps' => []
                    ];
                }
                $grouped_data[$grade]['steps'][] = [
                    'step' => $row['step'],
                    'monthly_salary' => $row['monthly_salary'],
                    'effective_date' => $row['effective_date']
                ];
            }
            return array_values($grouped_data);
        }
        
        return $result;
    }

    /**
     * Get all steps for a specific salary grade
     * @param int $salary_grade
     * @return array
     */
    public function get_steps_for_grade(int $salary_grade): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE salary_grade = ? ORDER BY step ASC";
        
        $stmt = $this->db->raw($sql, [$salary_grade]);
        
        if (!$stmt) {
            return [];
        }

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return is_array($result) ? $result : [];
    }

    /**
     * Create or update salary grade entry
     * @param array $data
     * @return bool|int
     */
    public function upsert_salary_grade(array $data)
    {
        // Check if exists
        $sql = "SELECT id FROM {$this->table} WHERE salary_grade = ? AND step = ?";
        $stmt = $this->db->raw($sql, [$data['salary_grade'], $data['step']]);
        
        if ($stmt) {
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update
                $data['effective_date'] = $data['effective_date'] ?? date('Y-m-d');
                return $this->db->table($this->table)->where('id', $existing['id'])->update($data);
            }
        }
        
        // Insert
        $data['effective_date'] = $data['effective_date'] ?? date('Y-m-d');
        
        try {
            $res = $this->db->table($this->table)->insert($data);
            $id = 0;
            if (method_exists($this->db, 'last_id')) {
                $id = (int)$this->db->last_id();
            }
            if (!$id && is_numeric($res)) $id = (int)$res;
            return $id ?: false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Bulk insert salary grades (for initial setup)
     * @param array $grades Array of ['salary_grade' => X, 'step' => Y, 'monthly_salary' => Z]
     * @return bool
     */
    public function bulk_insert_salary_grades(array $grades): bool
    {
        if (empty($grades)) {
            return false;
        }

        try {
            foreach ($grades as $grade) {
                $this->upsert_salary_grade($grade);
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
