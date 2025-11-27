<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class PaymentsModel extends Model
{
    protected $table = 'payments';
    protected $primary_key = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    public function create_payment(array $data)
    {
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
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

    public function update_payment(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->table($this->table)->where('id', $id)->update($data);
    }

    public function find_by_id(int $id)
    {
        $stmt = $this->db->raw("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
        if (!$stmt) return null;
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function get_all()
    {
        $sql = "SELECT p.*, 
                    CONCAT(IFNULL(u.first_name,''), ' ', IFNULL(u.last_name,'')) AS employee_name
                FROM {$this->table} p
                LEFT JOIN employees e ON p.employee_id = e.id
                LEFT JOIN users u ON e.user_id = u.id
                ORDER BY p.created_at DESC";

        $stmt = $this->db->raw($sql);
        if (!$stmt) return [];
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function delete_payment(int $id): bool
    {
        return $this->db->table($this->table)->where('id', $id)->delete();
    }

    public function find_by_payroll(int $payroll_id)
    {
        $stmt = $this->db->raw("SELECT * FROM {$this->table} WHERE payroll_id = ? LIMIT 1", [$payroll_id]);
        if (!$stmt) return null;
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

}
