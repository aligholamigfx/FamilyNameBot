<?php
// ============================================
// کلاس مدیریت پرداخت‌ها و Cryptomus
// ============================================

class PaymentHandler {
    private $db;
    private $apiKey;
    private $merchantUuid;
    private $apiUrl = 'https://api.cryptomus.com/v1';
    private $lastError;
    
    public function __construct(Database $db, $apiKey, $merchantUuid) {
        $this->db = $db;
        $this->apiKey = $apiKey;
        $this->merchantUuid = $merchantUuid;
    }
    
    /**
     * ایجاد درخواست پرداخت
     */
    public function createPayment($userId, $amount, $currency = 'USDT') {
        $orderId = 'order_' . time() . '_' . $userId;
        
        $payload = [
            'amount' => (string)$amount,
            'currency' => $currency,
            'order_id' => $orderId,
            'url_callback' => WEBHOOK_URL . '?payment_callback=1',
            'lifetime' => 3600,
        ];
        
        $response = $this->sendRequest('/payment', $payload);
        
        if ($response && isset($response['result'])) {
            $this->db->insert('payments', [
                'user_id' => $userId,
                'order_id' => $orderId,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'pending',
                'payment_id' => $response['result']['uuid'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'url' => $response['result']['url'],
                'order_id' => $orderId,
                'payment_id' => $response['result']['uuid']
            ];
        }
        
        $this->lastError = $response['error'] ?? 'Unknown error';
        return ['success' => false, 'error' => $this->lastError];
    }
    
    /**
     * بررسی وضعیت پرداخت
     */
    public function verifyPayment($orderId) {
        $payment = $this->db->selectOne(
            "SELECT * FROM payments WHERE order_id = ?",
            "s",
            [$orderId]
        );
        
        if (!$payment) {
            return ['success' => false, 'error' => 'Payment not found'];
        }
        
        if ($payment['status'] === 'completed') {
            return ['success' => true, 'status' => 'completed'];
        }
        
        $response = $this->sendRequest('/payment/info', [
            'uuid' => $payment['payment_id']
        ], 'GET');
        
        if (!$response || !isset($response['result'])) {
            return ['success' => false, 'error' => 'Failed to get payment info'];
        }
        
        $paymentStatus = $response['result']['status'];
        
        if ($paymentStatus === 'paid' && $payment['status'] !== 'completed') {
            $this->completePayment($payment['user_id'], $payment['order_id'], 
                                   $payment['amount'], $response['result']['txid'] ?? null);
            
            return ['success' => true, 'status' => 'completed', 'amount' => $payment['amount']];
        }
        
        if ($paymentStatus === 'failed' || $paymentStatus === 'expired') {
            $this->db->update('payments',
                ['status' => 'failed'],
                "order_id = '{$payment['order_id']}'"
            );
            return ['success' => false, 'status' => $paymentStatus];
        }
        
        return ['success' => false, 'status' => $paymentStatus];
    }
    
    /**
     * تایید و تکمیل پرداخت
     */
    private function completePayment($userId, $orderId, $amount, $txid = null) {
        $packages = [
            10 => 100,
            40 => 550,
            75 => 1150,
            350 => 6000
        ];
        
        $coinAmount = $packages[$amount] ?? 0;
        
        if ($coinAmount > 0) {
            $user = $this->db->selectOne(
                "SELECT premium_coins FROM users WHERE user_id = ?",
                "i",
                [$userId]
            );
            
            $newBalance = $user['premium_coins'] + $coinAmount;
            $this->db->update('users',
                ['premium_coins' => $newBalance],
                "user_id = $userId"
            );
        }
        
        $this->db->update('payments',
            ['status' => 'completed', 'transaction_hash' => $txid, 'completed_at' => date('Y-m-d H:i:s')],
            "order_id = '$orderId'"
        );
        
        $this->db->insert('coin_purchases', [
            'user_id' => $userId,
            'amount' => $coinAmount,
            'order_id' => $orderId,
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s')
        ]);
        
        return true;
    }
    
    /**
     * دریافت تاریخچه پرداخت‌ها
     */
    public function getUserPaymentHistory($userId, $limit = 10) {
        return $this->db->select(
            "SELECT * FROM coin_purchases 
             WHERE user_id = ? AND status = 'completed'
             ORDER BY completed_at DESC 
             LIMIT ?",
            "ii",
            [$userId, $limit]
        );
    }
    
    /**
     * درخواست HTTP به Cryptomus
     */
    private function sendRequest($endpoint, $payload, $method = 'POST') {
        $sign = hash('sha256', json_encode($payload));
        
        $ch = curl_init($this->apiUrl . $endpoint);
        
        $headers = [
            'merchant: ' . $this->merchantUuid,
            'sign: ' . $sign,
            'Content-Type: application/json'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            $this->lastError = $error;
            return null;
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode !== 200) {
            $this->lastError = $decoded['error'] ?? "HTTP $httpCode";
            return null;
        }
        
        return $decoded;
    }
    
    /**
     * بررسی امضا برای Webhook
     */
    public function verifyWebhookSignature($payload, $signature) {
        $sign = hash('sha256', json_encode($payload));
        return hash_equals($signature, $sign);
    }
    
    /**
     * پردازش Callback
     */
    public function handleWebhookCallback($data) {
        if (!isset($data['order_id']) || !isset($data['status'])) {
            return ['success' => false, 'error' => 'Invalid callback data'];
        }
        
        $orderId = $data['order_id'];
        $status = $data['status'];
        
        if ($status === 'paid') {
            $payment = $this->db->selectOne(
                "SELECT * FROM payments WHERE order_id = ?",
                "s",
                [$orderId]
            );
            
            if (!$payment) {
                return ['success' => false, 'error' => 'Payment not found'];
            }
            
            if ($payment['status'] !== 'completed') {
                $this->completePayment($payment['user_id'], $orderId, 
                                       $payment['amount'], $data['txid'] ?? null);
            }
            
            return ['success' => true, 'message' => 'Payment confirmed'];
        }
        
        return ['success' => false, 'error' => 'Payment not confirmed'];
    }
    
    /**
     * دریافت آخرین خطا
     */
    public function getError() {
        return $this->lastError;
    }
    
    /**
     * دریافت تمام پرداخت‌ها
     */
    public function getAllPayments($limit = 100) {
        return $this->db->select(
            "SELECT p.*, u.username FROM payments p
             LEFT JOIN users u ON p.user_id = u.user_id
             ORDER BY p.created_at DESC
             LIMIT ?",
            "i",
            [$limit]
        );
    }
    
    /**
     * کل درآمد
     */
    public function getTotalRevenue() {
        $result = $this->db->selectOne(
            "SELECT SUM(amount) as total FROM payments WHERE status = 'completed'"
        );
        return $result['total'] ?? 0;
    }
    
    /**
     * درآمد امروز
     */
    public function getTodayRevenue() {
        $result = $this->db->selectOne(
            "SELECT SUM(amount) as total FROM payments 
             WHERE status = 'completed' AND DATE(completed_at) = CURDATE()"
        );
        return $result['total'] ?? 0;
    }
}

?>