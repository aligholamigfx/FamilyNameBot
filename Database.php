<?php
// ============================================
// کلاس مدیریت پایگاه داده
// ============================================

class Database {
    private $conn;
    private $lastError;
    private $lastQuery;
    
    public function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($this->conn->connect_error) {
            $this->logError('Database Connection Error: ' . $this->conn->connect_error);
            die('خطای اتصال پایگاه داده');
        }
        
        $this->conn->set_charset("utf8mb4");
        $this->conn->query("SET NAMES utf8mb4");
    }
    
    /**
     * اجرای query عام
     */
    public function query($sql, $types = "", $params = []) {
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            $this->lastError = $this->conn->error;
            $this->logError('Prepare Error: ' . $sql . ' | ' . $this->lastError);
            return null;
        }
        
        $this->lastQuery = $sql;
        
        if (!empty($types) && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            $this->lastError = $stmt->error;
            $this->logError('Execute Error: ' . $sql . ' | ' . $this->lastError);
            return null;
        }
        
        return $stmt;
    }
    
    /**
     * درج رکورد جدید
     */
    public function insert($table, $data) {
        $cols = implode(', ', array_keys($data));
        $vals = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($cols) VALUES ($vals)";
        
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            $this->lastError = $this->conn->error;
            $this->logError('Insert Prepare Error: ' . $this->lastError);
            return false;
        }
        
        $types = str_repeat('s', count($data));
        $stmt->bind_param($types, ...array_values($data));
        
        if (!$stmt->execute()) {
            $this->lastError = $stmt->error;
            $this->logError('Insert Execute Error: ' . $this->lastError);
            return false;
        }
        
        return $this->conn->insert_id;
    }
    
    /**
     * انتخاب چند رکورد
     */
    public function select($sql, $types = "", $params = []) {
        $stmt = $this->query($sql, $types, $params);
        
        if (!$stmt) {
            return [];
        }
        
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data ?: [];
    }
    
    /**
     * انتخاب یک رکورد
     */
    public function selectOne($sql, $types = "", $params = []) {
        $result = $this->select($sql, $types, $params);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * بروزرسانی رکورد
     */
    public function update($table, $data, $where) {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
        $sql = "UPDATE $table SET $set WHERE $where";
        
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            $this->lastError = $this->conn->error;
            $this->logError('Update Prepare Error: ' . $this->lastError);
            return false;
        }
        
        $types = str_repeat('s', count($data));
        $stmt->bind_param($types, ...array_values($data));
        
        if (!$stmt->execute()) {
            $this->lastError = $stmt->error;
            $this->logError('Update Execute Error: ' . $this->lastError);
            return false;
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected > 0;
    }
    
    /**
     * حذف رکورد
     */
    public function delete($table, $where) {
        $sql = "DELETE FROM $table WHERE $where";
        
        if (!$this->conn->query($sql)) {
            $this->lastError = $this->conn->error;
            $this->logError('Delete Error: ' . $this->lastError);
            return false;
        }
        
        return $this->conn->affected_rows > 0;
    }
    
    /**
     * افزایش مقدار ستون
     */
    public function incrementColumn($table, $column, $value, $where) {
        $sql = "UPDATE $table SET $column = $column + $value WHERE $where";
        
        if (!$this->conn->query($sql)) {
            $this->lastError = $this->conn->error;
            $this->logError('Increment Error: ' . $this->lastError);
            return false;
        }
        
        return true;
    }
    
    /**
     * کاهش مقدار ستون
     */
    public function decrementColumn($table, $column, $value, $where) {
        $sql = "UPDATE $table SET $column = $column - $value WHERE $where";
        
        if (!$this->conn->query($sql)) {
            $this->lastError = $this->conn->error;
            $this->logError('Decrement Error: ' . $this->lastError);
            return false;
        }
        
        return true;
    }
    
    /**
     * شمارش رکوردها
     */
    public function count($table, $where = "") {
        $sql = "SELECT COUNT(*) as count FROM $table";
        if (!empty($where)) {
            $sql .= " WHERE $where";
        }
        
        $result = $this->selectOne($sql);
        return $result['count'] ?? 0;
    }
    
    /**
     * محدودیت فهرست
     */
    public function limit($sql, $limit, $offset = 0) {
        return $sql . " LIMIT $offset, $limit";
    }
    
    /**
     * شروع تراکنش
     */
    public function beginTransaction() {
        return $this->conn->begin_transaction();
    }
    
    /**
     * ثبت تراکنش
     */
    public function commit() {
        return $this->conn->commit();
    }
    
    /**
     * لغو تراکنش
     */
    public function rollback() {
        return $this->conn->rollback();
    }
    
    /**
     * دریافت خطای آخر
     */
    public function getError() {
        return $this->lastError;
    }
    
    /**
     * دریافت query آخر
     */
    public function getLastQuery() {
        return $this->lastQuery;
    }
    
    /**
     * دریافت اتصال
     */
    public function getConn() {
        return $this->conn;
    }
    
    /**
     * ثبت خطا در فایل لاگ
     */
    private function logError($message) {
        $logFile = LOG_DIR . '/db_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Escape string برای امنیت
     */
    public function escape($string) {
        return $this->conn->real_escape_string($string);
    }
    
    /**
     * بستن اتصال
     */
    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
    
    /**
     * اجرای query خام
     */
    public function rawQuery($sql) {
        $result = $this->conn->query($sql);
        
        if (!$result) {
            $this->lastError = $this->conn->error;
            $this->logError('Raw Query Error: ' . $this->lastError);
            return false;
        }
        
        return $result;
    }
}

?>