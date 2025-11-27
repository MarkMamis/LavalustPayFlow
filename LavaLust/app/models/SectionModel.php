<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * SectionModel
 * Handles section persistence and related DB operations.
 */
class SectionModel extends Model
{
    protected $table = 'class_sections';
    protected $primary_key = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get all sections
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
     * Check if name exists
     * @param string $name
     * @param int|null $exclude_id section id to exclude from check
     * @return bool
     */
    public function name_exists($name, $exclude_id = null)
    {
        $query = $this->db->table($this->table)->where('name', $name);
        if ($exclude_id !== null) {
            $query->where('id !=', $exclude_id);
        }
        return $query->get() !== null;
    }

    /**
     * Create section
     * @param array $data
     * @return int|false inserted id or false
     */
    public function create_section($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return $this->db->table($this->table)->insert($data);
    }

    /**
     * Update section
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update_section($id, $data) {
        return $this->db->table('class_sections')->where('id', $id)->update($data);
    }
}