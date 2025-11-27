<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ScheduleModel extends Model
{
    protected $table = 'class_schedules';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get all schedules with JOINs for subject, employee, section, room details
     */
    public function get_all_schedules(): array
    {
        $sql = "SELECT 
                    cs.*,
                    s.code as subject_code,
                    s.name as subject_name,
                    s.units as subject_units,
                    COALESCE(u.first_name, '') as teacher_firstname,
                    COALESCE(u.last_name, '') as teacher_lastname,
                    e.employee_code as teacher_code,
                    sec.name as section_name,
                    r.name as room_name,
                    r.floor as room_floor,
                    r.type as room_type
                FROM class_schedules cs
                LEFT JOIN subjects s ON cs.subject_id = s.id
                LEFT JOIN employees e ON cs.employee_id = e.id
                LEFT JOIN users u ON e.user_id = u.id
                LEFT JOIN class_sections sec ON cs.section_id = sec.id
                LEFT JOIN rooms r ON cs.room_code = r.code
                ORDER BY cs.day_of_week, cs.start_time";
        
        $stmt = $this->db->raw($sql);
        
        if (!$stmt) {
            return [];
        }

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return is_array($result) ? $result : [];
    }

    /**
     * Get schedule by ID with all details
     */
    public function find_by_id(int $id)
    {
        $sql = "SELECT 
                    cs.*,
                    s.code as subject_code,
                    s.name as subject_name,
                    COALESCE(u.first_name, '') as teacher_firstname,
                    COALESCE(u.last_name, '') as teacher_lastname,
                    sec.name as section_name,
                    r.name as room_name
                FROM class_schedules cs
                LEFT JOIN subjects s ON cs.subject_id = s.id
                LEFT JOIN employees e ON cs.employee_id = e.id
                LEFT JOIN users u ON e.user_id = u.id
                LEFT JOIN class_sections sec ON cs.section_id = sec.id
                LEFT JOIN rooms r ON cs.room_code = r.code
                WHERE cs.id = ?";
        
        $stmt = $this->db->raw($sql, [$id]);
        if (!$stmt) return null;
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Get schedules by employee ID
     */
    public function get_by_employee(int $employee_id): array
    {
        $sql = "SELECT 
                    cs.*,
                    s.code as subject_code,
                    s.name as subject_name,
                    sec.name as section_name,
                    r.name as room_name
                FROM class_schedules cs
                LEFT JOIN subjects s ON cs.subject_id = s.id
                LEFT JOIN class_sections sec ON cs.section_id = sec.id
                LEFT JOIN rooms r ON cs.room_code = r.code
                WHERE cs.employee_id = ? AND cs.is_active = 1
                ORDER BY cs.day_of_week, cs.start_time";
        
        $stmt = $this->db->raw($sql, [$employee_id]);
        if (!$stmt) return [];
        
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return is_array($result) ? $result : [];
    }

    /**
     * Get schedules by section ID
     */
    public function get_by_section(int $section_id): array
    {
        $sql = "SELECT 
                    cs.*,
                    s.code as subject_code,
                    s.name as subject_name,
                    COALESCE(u.first_name, '') as teacher_firstname,
                    COALESCE(u.last_name, '') as teacher_lastname,
                    r.name as room_name
                FROM class_schedules cs
                LEFT JOIN subjects s ON cs.subject_id = s.id
                LEFT JOIN employees e ON cs.employee_id = e.id
                LEFT JOIN users u ON e.user_id = u.id
                LEFT JOIN rooms r ON cs.room_code = r.code
                WHERE cs.section_id = ? AND cs.is_active = 1
                ORDER BY cs.day_of_week, cs.start_time";
        
        $stmt = $this->db->raw($sql, [$section_id]);
        if (!$stmt) return [];
        
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return is_array($result) ? $result : [];
    }

    /**
     * Check for schedule conflicts (same room, day, overlapping time)
     */
    public function has_conflict(array $data, ?int $exclude_id = null): bool
    {
        $sql = "SELECT COUNT(*) as count
                FROM class_schedules
                WHERE room_code = ?
                AND day_of_week = ?
                AND is_active = 1
                AND start_time < ?
                AND end_time > ?";
        
        $params = [
            $data['room_code'],
            $data['day_of_week'],
            $data['end_time'],    // Existing start must be before new end
            $data['start_time']   // Existing end must be after new start
        ];
        
        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }
        
        $stmt = $this->db->raw($sql, $params);
        if (!$stmt) return false;
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result && $result['count'] > 0;
    }

    /**
     * Check if teacher has conflict (same teacher, day, overlapping time)
     * Prevents assigning a teacher to two different rooms/sections at the same time on the same day.
     */
    public function teacher_has_conflict(array $data, ?int $exclude_id = null): bool
    {
        $sql = "SELECT COUNT(*) as count
                FROM class_schedules
                WHERE employee_id = ?
                AND day_of_week = ?
                AND is_active = 1
                AND start_time < ?
                AND end_time > ?";

        $params = [
            $data['employee_id'],
            $data['day_of_week'],
            $data['end_time'],    // Existing start must be before new end
            $data['start_time']   // Existing end must be after new start
        ];

        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }

        $stmt = $this->db->raw($sql, $params);
        if (!$stmt) return false;

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result && $result['count'] > 0;
    }

    /**
     * Check if teacher is already assigned to this subject+section combination
     */
    public function teacher_subject_section_exists(array $data, ?int $exclude_id = null): bool
    {
        // Only treat this as a duplicate assignment if the teacher is assigned to the
        // same subject+section on the SAME DAY and the times overlap. This allows
        // the same teacher to teach the same section on different days or at
        // non-overlapping times.
        $sql = "SELECT COUNT(*) as count
                FROM class_schedules
                WHERE employee_id = ?
                AND subject_id = ?
                AND section_id = ?
                AND day_of_week = ?
                AND is_active = 1
                AND start_time < ?
                AND end_time > ?";

        $params = [
            $data['employee_id'],
            $data['subject_id'],
            $data['section_id'],
            $data['day_of_week'],
            $data['end_time'],    // Existing start must be before new end
            $data['start_time']   // Existing end must be after new start
        ];

        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }

        $stmt = $this->db->raw($sql, $params);
        if (!$stmt) return false;

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result && $result['count'] > 0;
    }

    /**
     * Create a new schedule
     */
    public function create_schedule(array $data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return $this->db->table($this->table)->insert($data);
    }

    /**
     * Update schedule
     */
    public function update_schedule(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->table($this->table)
                        ->where('id', $id)
                        ->update($data);
    }

    /**
     * Delete schedule (soft delete by setting is_active = 0)
     */
    public function delete_schedule(int $id): bool
    {
        return $this->db->table($this->table)
                        ->where('id', $id)
                        ->update(['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Permanently delete schedule
     */
    public function hard_delete_schedule(int $id): bool
    {
        return $this->db->table($this->table)
                        ->where('id', $id)
                        ->delete();
    }
}
