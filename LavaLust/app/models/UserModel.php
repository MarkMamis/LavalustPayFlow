<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * UserModel
 * Encapsulates user persistence and related DB operations.
 */
class UserModel extends Model
{
    protected $table = 'users';
    protected $primary_key = 'id';

    public function __construct()
    {
        parent::__construct();
        // Database is expected to be autoloaded by the framework.
    }

    /**
     * Find user by email
     * @param string $email
     * @return array|null
     */
    public function find_by_email(string $email)
    {
        // The framework's Database wrapper exposes ->get() which may return
        // a single row or an array. Use get() and normalize the result to
        // return a single associative row or null.
        $res = $this->db->table($this->table)->where('email', $email)->get();
        if (is_array($res)) {
            // If it's a numeric-indexed array (multiple rows), return first
            if (array_keys($res) === range(0, count($res) - 1)) {
                return count($res) ? $res[0] : null;
            }
            // Otherwise assume it's an associative single row
            return $res;
        }
        return $res ?: null;
    }

    /**
     * Create a new user. Accepts plain password or password_hash.
     * Returns inserted id or false on failure.
     */
    public function create_user(array $data)
    {
        if (!empty($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }

        // Ensure password_hash key exists to avoid DB errors on schemas
        // that require the column to be present (some DB setups mark it NOT NULL).
        if (!array_key_exists('password_hash', $data)) {
            $data['password_hash'] = '';
        }

        $data['is_active'] = $data['is_active'] ?? 1;
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');

        // Avoid using transaction helpers in case the Database wrapper doesn't implement them.
        $res = $this->db->table($this->table)->insert($data);
        $id = 0;
        // Prefer a last_id helper if available
        if (method_exists($this->db, 'last_id')) {
            $id = (int)$this->db->last_id();
        }
        // If insert() returned a numeric id, use it
        if (!$id && is_numeric($res)) {
            $id = (int)$res;
        }

        // Some DB wrappers return true/false for insert(). If we got a truthy
        // result but no id, attempt to find the record by unique email to retrieve id.
        if (!$id && $res) {
            if (!empty($data['email'])) {
                $found = $this->find_by_email($data['email']);
                if ($found && isset($found['id'])) {
                    return (int)$found['id'];
                }
            }
            // return true to indicate success even if id couldn't be determined
            return true;
        }

        return $id ?: false;
    }

    public function update_user(int $id, array $data)
    {
        if (!empty($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->table($this->table)->where('id', $id)->update($data);
    }

    public function delete_user(int $id)
    {
        return $this->db->table($this->table)->where('id', $id)->delete();
    }

}
