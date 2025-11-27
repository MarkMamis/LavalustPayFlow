<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * PositionModel
 * Handles position persistence and related DB operations.
 */
class PositionModel extends Model
{
    protected $table = 'positions';
    protected $primary_key = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get all positions with optional salary grade details
     * @param bool $with_salary Whether to join salary grade info
     * @return array
     */
    public function get_all($with_salary = false)
    {
        if ($with_salary) {
            // Join with salary_grades to get salary range for each position
            $sql = "SELECT p.*, 
                    MIN(sg.monthly_salary) as min_salary,
                    MAX(sg.monthly_salary) as max_salary
                    FROM {$this->table} p
                    LEFT JOIN salary_grades sg ON p.salary_grade = sg.salary_grade
                    GROUP BY p.id
                    ORDER BY p.id";
            $stmt = $this->db->raw($sql);
            if (!$stmt) return [];
            $res = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return is_array($res) ? $res : [];
        }
        
        $res = $this->db->table($this->table)->get_all();
        if (!$res) return [];
        // Normalize single-row associative results to array of rows
        if (is_array($res) && array_keys($res) !== range(0, count($res) - 1)) {
            return [$res];
        }
        return $res;
    }

    /**
     * Get positions by department with optional salary grade details
     * @param int $department_id
     * @param bool $with_salary Whether to join salary grade info
     * @return array
     */
    public function get_by_department($department_id, $with_salary = false)
    {
        if ($with_salary) {
            $sql = "SELECT p.*, 
                    MIN(sg.monthly_salary) as min_salary,
                    MAX(sg.monthly_salary) as max_salary
                    FROM {$this->table} p
                    LEFT JOIN salary_grades sg ON p.salary_grade = sg.salary_grade
                    WHERE p.department_id = ?
                    GROUP BY p.id
                    ORDER BY p.id";
            $stmt = $this->db->raw($sql, [$department_id]);
            if (!$stmt) return [];
            $res = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return is_array($res) ? $res : [];
        }
        
        $res = $this->db->table($this->table)
            ->where('department_id', $department_id)
            ->get_all();
        if (!$res) return [];
        if (is_array($res) && array_keys($res) !== range(0, count($res) - 1)) {
            return [$res];
        }
        return $res;
    }

    /**
     * Find by id
     */
    public function find_by_id(int $id)
    {
        $res = $this->db->table($this->table)->where('id', $id)->get();
        if (!$res) return null;
        if (is_array($res) && array_keys($res) === range(0, count($res) - 1)) {
            return count($res) ? $res[0] : null;
        }
        return $res;
    }

    /**
     * Check if title exists in department
     * @param string $title
     * @param int $department_id
     * @param int|null $exclude_id position id to exclude from check
     * @return bool
     */
    public function title_exists($title, $department_id, $exclude_id = null)
    {
        if ($exclude_id !== null) {
            // Check if title exists excluding specific ID
            $sql = "SELECT * FROM {$this->table} WHERE title = ? AND department_id = ? AND id != ?";
            $result = $this->db->raw($sql, [$title, $department_id, $exclude_id]);
        } else {
            // Check if title exists
            $result = $this->db->table($this->table)
                ->where('title', $title)
                ->where('department_id', $department_id)
                ->get();
        }
        
        return $result !== null && $result !== false && !empty($result);
    }

    /**
     * Create position
     * @param array $data
     * @return int|false inserted id or false
     */
    public function create_position($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return $this->db->table($this->table)->insert($data);
    }

    /**
     * Update position
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update_position($id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->table($this->table)
            ->where('id', $id)
            ->update($data);
    }

    /**
     * Delete position
     * @param int $id
     * @return bool
     */
    public function delete_position($id)
    {
        return $this->db->table($this->table)
            ->where('id', $id)
            ->delete();
    }
}
