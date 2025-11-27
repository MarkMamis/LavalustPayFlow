<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * DepartmentModel
 * Encapsulates department persistence and related DB operations.
 */
class DepartmentModel extends Model
{
    protected $table = 'departments';
    protected $primary_key = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get all departments
     * @return array
     */
    public function get_all()
    {
        $res = $this->db->table($this->table)->get_all();
        if (!$res) return [];
        // Normalize single-row associative results to array of rows
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
     * Create department
     * @param array $data
     * @return int|false inserted id or false
     */
    public function create_department(array $data)
    {
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $data['created_at'] ?? $now;
        // generate slug if not provided
        if (empty($data['slug']) && !empty($data['name'])) {
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($data['name']));
            $slug = trim($slug, '-');
            $data['slug'] = substr($slug, 0, 120);
        }
        // Some project Database wrappers do not implement transaction helpers.
        // Avoid depending on beginTransaction/commit/rollBack to keep compatibility.
        try {
            $res = $this->db->table($this->table)->insert($data);
            // attempt to read last inserted id if available
            $id = 0;
            if (method_exists($this->db, 'last_id')) {
                $id = (int)$this->db->last_id();
            }
            // If last_id is not available but insert returned an id or truthy, try to infer
            if (!$id && is_numeric($res)) {
                $id = (int)$res;
            }
            return $id ?: false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function update_department(int $id, array $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->table($this->table)->where('id', $id)->update($data);
    }

    public function delete_department(int $id)
    {
        return $this->db->table($this->table)->where('id', $id)->delete();
    }

}
