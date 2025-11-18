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
     * انتخاب چند رکورد (سازگار با هاست‌های بدون mysqlnd)
     */
    public function select($sql, $types = "", $params = []) {
        $stmt = $this->query($sql, $types, $params);

        if (!$stmt) {
            return [];
        }

        $result = [];
        $stmt->store_result();
        $meta = $stmt->result_metadata();
        if (!$meta) {
            $stmt->close();
            return [];
        }
        $fields = [];
        while ($field = $meta->fetch_field()) {
            $fields[] = &$row[$field->name];
        }

        call_user_func_array([$stmt, 'bind_result'], $fields);

        while ($stmt->fetch()) {
            $c = [];
            foreach ($row as $key => $val) {
                $c[$key] = $val;
            }
            $result[] = $c;
        }

        $stmt->close();
        return $result;
    }

    /**
     * انتخاب یک رکورد (سازگار با هاست‌های بدون mysqlnd)
     */
    public function selectOne($sql, $types = "", $params = []) {
        $result = $this->select($sql, $types, $params);
        return !empty($result) ? $result[0] : null;
    }

    public function update($table, $data, $where, $where_types = "", $where_params = []) {
        $set = implode(', ', array_map(function($k) { return "$k = ?"; }, array_keys($data)));
        $sql = "UPDATE $table SET $set WHERE $where";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            $this->lastError = $this->conn->error;
            $this->logError('Update Prepare Error: ' . $this->lastError);
            return false;
        }

        $types = str_repeat('s', count($data)) . $where_types;
        $params = array_merge(array_values($data), $where_params);

        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $this->lastError = $stmt->error;
            $this->logError('Update Execute Error: ' . $this->lastError);
            return false;
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected > 0;
    }

    public function delete($table, $where, $types = "", $params = []) {
        $sql = "DELETE FROM $table WHERE $where";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            $this->lastError = $this->conn->error;
            $this->logError('Delete Prepare Error: ' . $this->lastError);
            return false;
        }

        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $this->lastError = $stmt->error;
            $this->logError('Delete Execute Error: ' . $this->lastError);
            return false;
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected > 0;
    }

    public function incrementColumn($table, $column, $value, $where, $where_types = "", $where_params = []) {
        $sql = "UPDATE $table SET $column = $column + ? WHERE $where";
        $types = "i" . $where_types;
        $params = array_merge([$value], $where_params);
        $this->query($sql, $types, $params);
    }

    public function decrementColumn($table, $column, $value, $where, $where_types = "", $where_params = []) {
        $sql = "UPDATE $table SET $column = $column - ? WHERE $where";
        $types = "i" . $where_types;
        $params = array_merge([$value], $where_params);
        $this->query($sql, $types, $params);
    }

    public function count($table, $where = "", $types = "", $params = []) {
        $sql = "SELECT COUNT(*) as count FROM $table";
        if (!empty($where)) {
            $sql .= " WHERE $where";
        }

        $result = $this->selectOne($sql, $types, $params);
        return $result['count'] ?? 0;
    }

    public function limit($sql, $limit, $offset = 0) {
        return $sql . " LIMIT $offset, $limit";
    }

    public function beginTransaction() {
        return $this->conn->begin_transaction();
    }

    public function commit() {
        return $this->conn->commit();
    }

    public function rollback() {
        return $this->conn->rollback();
    }

    public function getError() {
        return $this->lastError;
    }

    public function getLastQuery() {
        return $this->lastQuery;
    }

    public function getConn() {
        return $this->conn;
    }

    private function logError($message) {
        $logFile = LOG_DIR . '/db_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    public function escape($string) {
        return $this->conn->real_escape_string($string);
    }

    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }

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
