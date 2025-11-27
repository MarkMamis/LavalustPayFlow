<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class PayrollPeriodModel extends Model
{
    protected $table = 'payroll_periods';
    protected $primary_key = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get all payroll periods
     * @return array
     */
    public function get_all_periods(): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY start_date DESC";
        
        $stmt = $this->db->raw($sql);
        
        if (!$stmt) {
            return [];
        }

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return is_array($result) ? $result : [];
    }

    /**
     * Get period by ID
     * @param int $id
     * @return array|null
     */
    public function get_period_by_id(int $id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        
        $stmt = $this->db->raw($sql, [$id]);
        
        if (!$stmt) {
            return null;
        }

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Get current open period
     * @return array|null
     */
    public function get_current_period()
    {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'open' ORDER BY start_date DESC LIMIT 1";
        
        $stmt = $this->db->raw($sql);
        
        if (!$stmt) {
            return null;
        }

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Create a new payroll period
     * @param array $data
     * @return bool|int
     */
    public function create_period(array $data)
    {
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $data['status'] = $data['status'] ?? 'open';
        
        // Validate dates
        if (empty($data['start_date']) || empty($data['end_date'])) {
            return false;
        }
        
        // Check for overlapping periods
        $sql = "SELECT id FROM {$this->table} 
                WHERE (start_date <= ? AND end_date >= ?) 
                   OR (start_date <= ? AND end_date >= ?)";
        
        $stmt = $this->db->raw($sql, [
            $data['start_date'], $data['start_date'],
            $data['end_date'], $data['end_date']
        ]);
        
        if ($stmt) {
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($existing) {
                return false; // Overlapping period exists
            }
        }

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
     * Update period
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update_period(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->table($this->table)->where('id', $id)->update($data);
    }

    /**
     * Update period status
     * @param int $id
     * @param string $status 'open', 'locked', 'processed'
     * @return bool
     */
    public function update_status(int $id, string $status): bool
    {
        $validStatuses = ['open', 'locked', 'processed'];
        
        if (!in_array($status, $validStatuses)) {
            return false;
        }

        $data = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return $this->db->table($this->table)->where('id', $id)->update($data);
    }

    /**
     * Delete a period (only if no payroll records exist)
     * @param int $id
     * @return bool
     */
    public function delete_period(int $id): bool
    {
        // Check if payroll records exist for this period
        $sql = "SELECT COUNT(*) as count FROM payroll_records WHERE period_id = ?";
        $stmt = $this->db->raw($sql, [$id]);
        
        if ($stmt) {
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($result && $result['count'] > 0) {
                return false; // Cannot delete period with existing payroll records
            }
        }

        return $this->db->table($this->table)->where('id', $id)->delete();
    }
}
