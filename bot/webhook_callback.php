<?php
// ============================================
// Webhook Callback - Cryptomus
// دریافت پیام‌های پرداخت از Cryptomus
// ============================================

require_once '../config.php';
require_once '../Database.php';
require_once '../TelegramAPI.php';
require_once '../PaymentHandler.php';
require_once '../UserManager.php';

// ثبت log درخواست
$logFile = LOG_DIR . '/cryptomus_webhook_' . date('Y-m-d') . '.log';
$input = file_get_contents('php://input');
file_put_contents($logFile, date('Y-m-d H:i:s') . " | " . $input . "\n", FILE_APPEND);

// دریافت داده‌های JSON
$data = json_decode($input, true);

// اگر داده نیست
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'No data received']);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " | ERROR: No JSON data\n", FILE_APPEND);
    exit;
}

// ایجاد اتصالات
try {
    $db = new Database();
    $telegram = new TelegramAPI(BOT_TOKEN);
    $payment = new PaymentHandler($db, CRYPTOMUS_API_KEY, CRYPTOMUS_MERCHANT_UUID);
    $userManager = new UserManager($db);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Connection failed']);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " | ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    exit;
}

// ============================================
// بررسی امضا (Signature)
// ============================================

$signature = $_SERVER['HTTP_SIGN'] ?? $_GET['sign'] ?? '';

if (!empty($signature)) {
    $expectedSign = hash('sha256', json_encode($data));
    
    if (!hash_equals($signature, $expectedSign)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid signature']);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " | ERROR: Invalid signature\n", FILE_APPEND);
        exit;
    }
}

// ============================================
// پردازش Callback
// ============================================

$orderId = $data['order_id'] ?? null;
$status = $data['status'] ?? null;
$amount = $data['amount'] ?? null;
$currency = $data['currency'] ?? null;
$txid = $data['txid'] ?? null;
$uuid = $data['uuid'] ?? null;

// بررسی اطلاعات ضروری
if (!$orderId || !$status) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    file_put_transactions($logFile, date('Y-m-d H:i:s') . " | ERROR: Missing fields\n", FILE_APPEND);
    exit;
}

// دریافت پرداخت از DB
$payment_record = $db->selectOne(
    "SELECT * FROM payments WHERE order_id = ?",
    "s",
    [$orderId]
);

if (!$payment_record) {
    http_response_code(404);
    echo json_encode(['error' => 'Payment not found']);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " | ERROR: Payment not found - $orderId\n", FILE_APPEND);
    exit;
}

$userId = $payment_record['user_id'];

// ============================================
// وضعیت‌های مختلف
// ============================================

switch ($status) {
    
    // ✅ پرداخت موفق
    case 'paid':
    case 'completed':
        handleSuccessfulPayment($db, $telegram, $userManager, $payment_record, $txid, $logFile);
        break;
    
    // ⏳ در انتظار تایید
    case 'pending':
    case 'confirming':
        handlePendingPayment($db, $telegram, $userId, $payment_record, $logFile);
        break;
    
    // ❌ پرداخت ناموفق
    case 'failed':
    case 'expired':
    case 'cancelled':
        handleFailedPayment($db, $telegram, $userId, $payment_record, $status, $logFile);
        break;
    
    // وضعیت نامشخص
    default:
        file_put_contents($logFile, date('Y-m-d H:i:s') . " | WARNING: Unknown status - $status\n", FILE_APPEND);
        break;
}

// پاسخ موفق به Cryptomus
http_response_code(200);
echo json_encode(['ok' => true, 'message' => 'Webhook processed']);
exit;

// ============================================
// توابع پردازش
// ============================================

/**
 * پرداخت موفق
 */
function handleSuccessfulPayment($db, $telegram, $userManager, $payment_record, $txid, $logFile) {
    $orderId = $payment_record['order_id'];
    $userId = $payment_record['user_id'];
    $amount = $payment_record['amount'];
    
    // بررسی اگر قبلاً تایید شده
    if ($payment_record['status'] === 'completed') {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " | INFO: Payment already completed - $orderId\n", FILE_APPEND);
        return;
    }
    
    // بروزرسانی وضعیت پرداخت
    $db->update('payments',
        [
            'status' => 'completed',
            'transaction_hash' => $txid ?? '',
            'completed_at' => date('Y-m-d H:i:s')
        ],
        "order_id = '$orderId'"
    );
    
    // محاسبه سکه‌های پاداش
    $packages = [
        10 => 100,
        40 => 550,    // 500 + 50 bonus
        75 => 1150,   // 1000 + 150 bonus
        350 => 6000   // 5000 + 1000 bonus
    ];
    
    $coinAmount = $packages[$amount] ?? 0;
    
    // اضافه کردن سکه‌های پریمیوم
    if ($coinAmount > 0) {
        $user = $db->selectOne("SELECT premium_coins FROM users WHERE user_id = ?", "i", [$userId]);
        $newBalance = $user['premium_coins'] + $coinAmount;
        $db->update('users', ['premium_coins' => $newBalance], "user_id = $userId");
    }
    
    // ثبت در coin_purchases
    $db->insert('coin_purchases', [
        'user_id' => $userId,
        'amount' => $coinAmount,
        'order_id' => $orderId,
        'status' => 'completed',
        'completed_at' => date('Y-m-d H:i:s')
    ]);
    
    // ارسال پیام تایید به کاربر
    $user = $userManager->getUser($userId);
    if ($user) {
        $message = "✅ <b>پرداخت موفق!</b>\n\n";
        $message .= "💎 <b>$coinAmount سکه</b> به حسابتون اضافه شد\n";
        $message .= "💰 مبلغ: \$$amount\n";
        $message .= "📦 Order ID: $orderId\n\n";
        $message .= "🎉 حالا می‌تونید از فروشگاه خریداری کنید!";
        
        $telegram->sendMessage($userId, $message);
    }
    
    // ثبت موفقیت در لاگ
    file_put_contents($logFile, date('Y-m-d H:i:s') . " | SUCCESS: Payment completed - User: $userId, Order: $orderId, Coins: $coinAmount\n", FILE_APPEND);
}

/**
 * پرداخت در انتظار تایید
 */
function handlePendingPayment($db, $telegram, $userId, $payment_record, $logFile) {
    $orderId = $payment_record['order_id'];
    
    // بروزرسانی وضعیت
    $db->update('payments',
        ['status' => 'pending'],
        "order_id = '$orderId'"
    );
    
    // ارسال پیام انتظار
    $message = "⏳ <b>پرداخت در انتظار تایید</b>\n\n";
    $message .= "لطفاً صبر کنید...\n";
    $message .= "Order ID: $orderId";
    
    $telegram->sendMessage($userId, $message);
    
    file_put_contents($logFile, date('Y-m-d H:i:s') . " | INFO: Payment pending - User: $userId, Order: $orderId\n", FILE_APPEND);
}

/**
 * پرداخت ناموفق
 */
function handleFailedPayment($db, $telegram, $userId, $payment_record, $status, $logFile) {
    $orderId = $payment_record['order_id'];
    
    // بروزرسانی وضعیت
    $db->update('payments',
        ['status' => 'failed'],
        "order_id = '$orderId'"
    );
    
    // ارسال پیام خطا
    $errorMessages = [
        'failed' => '❌ پرداخت ناموفق!',
        'expired' => '⏰ پرداخت منقضی شد!',
        'cancelled' => '🚫 پرداخت لغو شد!'
    ];
    
    $message = ($errorMessages[$status] ?? '❌ خطا در پرداخت') . "\n\n";
    $message .= "لطفاً دوباره تلاش کنید.\n";
    $message .= "Order ID: $orderId";
    
    $telegram->sendMessage($userId, $message);
    
    file_put_contents($logFile, date('Y-m-d H:i:s') . " | ERROR: Payment failed - Status: $status, User: $userId, Order: $orderId\n", FILE_APPEND);
}

?>