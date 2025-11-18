<?php
// ============================================
// کلاس مدیریت فروشگاه و سکه‌ها
// ============================================

class ShopManager {
    private $db;
    private $userManager;
    
    public function __construct(Database $db, UserManager $userManager) {
        $this->db = $db;
        $this->userManager = $userManager;
    }
    
    /**
     * دریافت آیتم‌های فروشگاه
     */
    public function getItems($category = null) {
        $query = "SELECT * FROM shop_items WHERE is_active = 1";
        
        if ($category) {
            $query .= " AND category = '" . $this->db->escape($category) . "'";
        }
        
        $query .= " ORDER BY price ASC";
        
        return $this->db->select($query);
    }
    
    /**
     * دریافت یک آیتم
     */
    public function getItemById($itemId) {
        return $this->db->selectOne(
            "SELECT * FROM shop_items WHERE id = ? AND is_active = 1",
            "i",
            [$itemId]
        );
    }
    
    /**
     * خرید آیتم
     */
    public function purchaseItem($userId, $itemId) {
        $item = $this->getItemById($itemId);
        
        if (!$item) {
            return false;
        }
        
        // بررسی موجودی
        if (!$this->userManager->spendCoins($userId, $item['price'])) {
            return false;
        }
        
        // ثبت خرید
        $purchaseId = $this->db->insert('purchases', [
            'user_id' => $userId,
            'item_id' => $itemId,
            'quantity' => '1',
            'total_cost' => $item['price'],
            'purchased_at' => date('Y-m-d H:i:s')
        ]);
        
        if (!$purchaseId) {
            // بازگردانی سکه در صورت خطا
            $this->userManager->addCoins($userId, $item['price'], 'premium');
            return false;
        }
        
        return $item;
    }
    
    /**
     * دریافت موجودی کاربر
     */
    public function getUserInventory($userId) {
        return $this->db->select(
            "SELECT si.*, p.quantity, p.purchased_at 
             FROM purchases p
             JOIN shop_items si ON p.item_id = si.id
             WHERE p.user_id = ? 
             ORDER BY p.purchased_at DESC 
             LIMIT 50",
            "i",
            [$userId]
        );
    }
    
    /**
     * دریافت تاریخچه خریدها
     */
    public function getPurchaseHistory($userId, $limit = 20) {
        return $this->db->select(
            "SELECT si.*, p.quantity, p.total_cost, p.purchased_at 
             FROM purchases p
             JOIN shop_items si ON p.item_id = si.id
             WHERE p.user_id = ? 
             ORDER BY p.purchased_at DESC 
             LIMIT ?",
            "ii",
            [$userId, $limit]
        );
    }
    
    /**
     * بسته‌های سکه
     */
    public function getCoinPackages() {
        return [
            10 => [
                'price' => 10,
                'coins' => 100,
                'bonus' => 0,
                'label' => '100 سکه - $10'
            ],
            40 => [
                'price' => 40,
                'coins' => 500,
                'bonus' => 50,
                'label' => '500 سکه + 50 پاداش - $40 ✨'
            ],
            75 => [
                'price' => 75,
                'coins' => 1000,
                'bonus' => 150,
                'label' => '1000 سکه + 150 پاداش - $75 ⭐'
            ],
            350 => [
                'price' => 350,
                'coins' => 5000,
                'bonus' => 1000,
                'label' => '5000 سکه + 1000 پاداش - $350 🔥'
            ],
        ];
    }
    
    /**
     * دریافت بسته سکه
     */
    public function getCoinPackage($price) {
        $packages = $this->getCoinPackages();
        return $packages[$price] ?? null;
    }
    
    /**
     * کل فروش‌ها
     */
    public function getTotalSales() {
        $result = $this->db->selectOne(
            "SELECT COUNT(*) as count, SUM(total_cost) as total FROM purchases"
        );
        return $result;
    }
    
    /**
     * فروش‌های امروز
     */
    public function getTodaySales() {
        $result = $this->db->selectOne(
            "SELECT COUNT(*) as count, SUM(total_cost) as total 
             FROM purchases 
             WHERE DATE(purchased_at) = CURDATE()"
        );
        return $result;
    }
    
    /**
     * اضافه کردن آیتم به فروشگاه
     */
    public function addItem($name, $description, $icon, $price, $category) {
        return $this->db->insert('shop_items', [
            'name' => $name,
            'description' => $description,
            'icon' => $icon,
            'price' => $price,
            'category' => $category,
            'is_active' => '1',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * بروزرسانی آیتم
     */
    public function updateItem($itemId, $data) {
        return $this->db->update('shop_items', $data, "id = $itemId");
    }
    
    /**
     * غیرفعال کردن آیتم
     */
    public function disableItem($itemId) {
        return $this->db->update('shop_items', ['is_active' => '0'], "id = $itemId");
    }
    
    /**
     * فعال کردن آیتم
     */
    public function enableItem($itemId) {
        return $this->db->update('shop_items', ['is_active' => '1'], "id = $itemId");
    }
    
    /**
     * حذف آیتم
     */
    public function deleteItem($itemId) {
        return $this->db->delete('shop_items', "id = $itemId");
    }
    
    /**
     * دریافت تمام آیتم‌ها
     */
    public function getAllItems() {
        return $this->db->select("SELECT * FROM shop_items ORDER BY created_at DESC");
    }
    
    /**
     * دریافت دسته‌بندی‌ها
     */
    public function getCategories() {
        $result = $this->db->select(
            "SELECT DISTINCT category FROM shop_items WHERE is_active = 1"
        );
        return array_column($result, 'category');
    }
    
    /**
     * تعداد خریدهای کاربر
     */
    public function getUserPurchaseCount($userId) {
        return $this->db->count('purchases', "user_id = $userId");
    }
    
    /**
     * کل سکه‌های خرج‌شده توسط کاربر
     */
    public function getUserTotalSpent($userId) {
        $result = $this->db->selectOne(
            "SELECT SUM(total_cost) as total FROM purchases WHERE user_id = ?",
            "i",
            [$userId]
        );
        return $result['total'] ?? 0;
    }
    
    /**
     * بسته‌بندی اطلاعات برای نمایش
     */
    public function formatItemForDisplay($item) {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'description' => $item['description'],
            'icon' => $item['icon'],
            'price' => $item['price'],
            'category' => $item['category'],
            'display' => "{$item['icon']} {$item['name']}\n💎 {$item['price']} سکه\n{$item['description']}"
        ];
    }
}

?>