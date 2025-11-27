<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class WorkspaceModel extends Model
{
    protected $table = 'workspaces';
    protected $primary_key = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    public function get_all_workspaces()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY id DESC";
        $stmt = $this->db->raw($sql);
        if (!$stmt) return [];
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function find_by_id(int $id)
    {
        $res = $this->db->table($this->table)->where('id', $id)->get();
        if (!$res) return null;
        if (is_array($res) && array_keys($res) === range(0, count($res) - 1)) {
            return count($res) ? $res[0] : null;
        }
        return $res;
    }

    public function create_workspace(array $data)
    {
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $data['created_at'] ?? $now;
        $data['updated_at'] = $data['updated_at'] ?? $now;

        try {
            // Debug: log the insert attempt
            error_log('WorkspaceModel::create_workspace - Data: ' . json_encode($data));
            
            $res = $this->db->table($this->table)->insert($data);
            
            error_log('WorkspaceModel::create_workspace - Insert result: ' . json_encode($res));
            
            if ($res === false) {
                error_log('WorkspaceModel::create_workspace - Insert returned false');
                return false;
            }
            
            // Try to get the last inserted ID
            if (method_exists($this->db, 'last_id')) {
                return (int)$this->db->last_id();
            }
            if (is_numeric($res) && $res > 0) {
                return (int)$res;
            }
            return true;
        } catch (Exception $e) {
            error_log('WorkspaceModel::create_workspace - Exception: ' . $e->getMessage());
            return false;
        }
    }

    public function update_workspace(int $id, array $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->table($this->table)->where('id', $id)->update($data);
    }

    public function delete_workspace(int $id)
    {
        return $this->db->table($this->table)->where('id', $id)->delete();
    }
}
