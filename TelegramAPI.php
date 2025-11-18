<?php
// ============================================
// کلاس API تلگرام
// ============================================

class TelegramAPI {
    private $token;
    private $apiUrl = 'https://api.telegram.org/bot';
    private $timeout = 30;
    private $lastError;
    
    public function __construct($token) {
        if (empty($token)) {
            throw new Exception('Bot token not provided');
        }
        $this->token = $token;
    }
    
    /**
     * ارسال پیام
     */
    public function sendMessage($chatId, $text, $replyMarkup = null, $parseMode = 'HTML') {
        if (empty($text)) {
            $this->lastError = 'Message text cannot be empty';
            return false;
        }
        
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => true
        ];
        
        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }
        
        return $this->request('sendMessage', $data);
    }
    
    /**
     * ویرایش پیام
     */
    public function editMessage($chatId, $messageId, $text, $replyMarkup = null, $parseMode = 'HTML') {
        if (empty($text)) {
            $this->lastError = 'Message text cannot be empty';
            return false;
        }
        
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => true
        ];
        
        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }
        
        return $this->request('editMessageText', $data);
    }
    
    /**
     * حذف پیام
     */
    public function deleteMessage($chatId, $messageId) {
        return $this->request('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
    }
    
    /**
     * پاسخ به callback query
     */
    public function answerCallbackQuery($callbackQueryId, $text = '', $showAlert = false, $url = null) {
        $data = [
            'callback_query_id' => $callbackQueryId,
            'show_alert' => $showAlert ? true : false
        ];
        
        if (!empty($text)) {
            $data['text'] = $text;
        }
        
        if ($url) {
            $data['url'] = $url;
        }
        
        return $this->request('answerCallbackQuery', $data);
    }
    
    /**
     * ارسال اطلاعات کاربر
     */
    public function getChatMember($chatId, $userId) {
        return $this->request('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId
        ]);
    }
    
    /**
     * دریافت اطلاعات ربات
     */
    public function getMe() {
        return $this->request('getMe', []);
    }
    
    /**
     * ارسال فایل
     */
    public function sendDocument($chatId, $document, $caption = '') {
        $data = [
            'chat_id' => $chatId,
            'document' => $document
        ];
        
        if ($caption) {
            $data['caption'] = $caption;
            $data['parse_mode'] = 'HTML';
        }
        
        return $this->request('sendDocument', $data);
    }
    
    /**
     * ارسال عکس
     */
    public function sendPhoto($chatId, $photo, $caption = '') {
        $data = [
            'chat_id' => $chatId,
            'photo' => $photo
        ];
        
        if ($caption) {
            $data['caption'] = $caption;
            $data['parse_mode'] = 'HTML';
        }
        
        return $this->request('sendPhoto', $data);
    }
    
    /**
     * دریافت آپدیت‌ها
     */
    public function getUpdates($offset = 0, $limit = 100, $timeout = 30) {
        return $this->request('getUpdates', [
            'offset' => $offset,
            'limit' => $limit,
            'timeout' => $timeout
        ]);
    }
    
    /**
     * تنظیم Webhook
     */
    public function setWebhook($url) {
        return $this->request('setWebhook', [
            'url' => $url,
            'max_connections' => 100,
            'allowed_updates' => json_encode(['message', 'callback_query', 'inline_query'])
        ]);
    }
    
    /**
     * حذف Webhook
     */
    public function deleteWebhook() {
        return $this->request('deleteWebhook', []);
    }
    
    /**
     * دریافت اطلاعات Webhook
     */
    public function getWebhookInfo() {
        return $this->request('getWebhookInfo', []);
    }
    
    /**
     * درخواست عام HTTP
     */
    private function request($method, $data) {
        $url = $this->apiUrl . $this->token . '/' . $method;
        
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'TelegramBot/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            $this->lastError = "cURL Error: $error";
            $this->logError("cURL Error in $method: $error");
            return false;
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode !== 200) {
            $this->lastError = $decoded['description'] ?? "HTTP Error: $httpCode";
            $this->logError("HTTP $httpCode in $method: " . $this->lastError);
            return false;
        }
        
        if (!$decoded['ok'] ?? false) {
            $this->lastError = $decoded['description'] ?? 'Unknown error';
            return false;
        }
        
        return $decoded['result'] ?? true;
    }
    
    /**
     * دریافت خطای آخر
     */
    public function getError() {
        return $this->lastError;
    }
    
    /**
     * ثبت خطا در لاگ
     */
    private function logError($message) {
        $logFile = LOG_DIR . '/telegram_api_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}

?>