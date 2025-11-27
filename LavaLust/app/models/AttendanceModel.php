<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class AttendanceModel extends Model
{
    protected $table = 'attendance';
    protected $primary_key = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    public function find_by_employee_date(int $employee_id, string $date)
    {
        $res = $this->db->table($this->table)->where('employee_id', $employee_id)->where('attendance_date', $date)->get();
        if (!$res) return null;
        if (is_array($res) && array_keys($res) === range(0, count($res) - 1)) {
            return count($res) ? $res[0] : null;
        }
        return $res;
    }

    public function get_by_date(string $date)
    {
        $res = $this->db->table($this->table)->where('attendance_date', $date)->get_all();
        if (!$res) return [];
        if (array_keys($res) !== range(0, count($res) - 1)) return [$res];
        return $res;
    }

    public function get_between_dates(string $start, string $end)
    {
        // Use raw query to ensure BETWEEN works across DB drivers
        try {
            $sql = "SELECT * FROM {$this->table} WHERE attendance_date BETWEEN ? AND ? ORDER BY attendance_date, employee_id";
            $stmt = $this->db->raw($sql, [$start, $end]);
            if (!$stmt) return [];
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (Exception $e) {
            return [];
        }
    }

    public function get_by_employee(int $employee_id)
    {
        $res = $this->db->table($this->table)->where('employee_id', $employee_id)->get_all();
        if (!$res) return [];
        if (array_keys($res) !== range(0, count($res) - 1)) return [$res];
        return $res;
    }

    public function create_attendance(array $data)
    {
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $data['created_at'] ?? $now;
        $data['attendance_date'] = $data['attendance_date'] ?? date('Y-m-d');

        try {
            $res = $this->db->table($this->table)->insert($data);
            $id = 0;
            if (method_exists($this->db, 'last_id')) {
                $id = (int)$this->db->last_id();
            }
            if (!$id && is_numeric($res)) $id = (int)$res;
            // If insert succeeded but id unknown, try to find by employee+date
            if (!$id && $res) {
                if (!empty($data['employee_id']) && !empty($data['attendance_date'])) {
                    $found = $this->find_by_employee_date((int)$data['employee_id'], $data['attendance_date']);
                    if ($found && isset($found['id'])) return (int)$found['id'];
                }
                return true;
            }
            return $id ?: false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function update_attendance(int $id, array $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->table($this->table)->where('id', $id)->update($data);
    }

    public function delete_attendance(int $id)
    {
        return $this->db->table($this->table)->where('id', $id)->delete();
    }
}
