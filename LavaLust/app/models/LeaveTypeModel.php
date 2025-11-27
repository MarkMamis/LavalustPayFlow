<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class LeaveTypeModel extends Model
{
    protected $table = 'leave_types';
    protected $primary_key = 'id';
    protected $fillable = ['code', 'name', 'description', 'annual_credits', 'paid_percentage', 'requires_approval'];

    /**
     * Get all leave types
     */
    public function get_all_types()
    {
        $stmt = $this->db->raw("SELECT * FROM {$this->table} ORDER BY code");
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Get leave type by code
     */
    public function get_by_code($code)
    {
        $stmt = $this->db->raw("SELECT * FROM {$this->table} WHERE code = ?", [$code]);
        return $stmt ? $stmt->fetch() : null;
    }
}
