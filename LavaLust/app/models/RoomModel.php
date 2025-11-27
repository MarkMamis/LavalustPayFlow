<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * RoomModel
 * Handles room persistence and related DB operations.
 */
class RoomModel extends Model
{
    protected $table = 'rooms';
    protected $primary_key = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get all rooms
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
     * Create room
     * @param array $data
     * @return int|false inserted id or false
     */
    public function create_room(array $data)
    {
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $data['created_at'] ?? $now;
        $data['is_active'] = $data['is_active'] ?? 1;
        
        try {
            $res = $this->db->table($this->table)->insert($data);
            $id = 0;
            if (method_exists($this->db, 'last_id')) {
                $id = (int)$this->db->last_id();
            }
            if (!$id && is_numeric($res)) {
                $id = (int)$res;
            }
            return $id ?: false;
        } catch (Exception $e) {
            error_log('[RoomModel::create_room] Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update room
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update_room(int $id, array $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        try {
            return $this->db->table($this->table)->where('id', $id)->update($data);
        } catch (Exception $e) {
            error_log('[RoomModel::update_room] Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete room
     * @param int $id
     * @return bool
     */
    public function delete_room(int $id)
    {
        try {
            return $this->db->table($this->table)->where('id', $id)->delete();
        } catch (Exception $e) {
            error_log('[RoomModel::delete_room] Exception: ' . $e->getMessage());
            return false;
        }
    }
}
