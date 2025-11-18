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
        $sql = "SELECT * FROM shop_items WHERE is_active = 1";
        $params = [];
        $types = "";

        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
            $types .= "s";
        }

        $sql .= " ORDER BY price ASC";

        return $this->db->select($sql, $types, $params);
    }

    /**
     * دریافت یک آیتم
     */
    public function getItemById($itemId) {
        return $this->db->selectOne("SELECT * FROM shop_items WHERE id = ? AND is_active = 1", "i", [$itemId]);
    }

    /**
     * خرید آیتم
     */
    public function purchaseItem($userId, $itemId) {
        $item = $this->getItemById($itemId);
        if (!$item) {
            return false;
        }

        if (!$this->userManager->spendCoins($userId, $item['price'])) {
            return false;
        }

        $purchaseId = $this->db->insert('purchases', [
            'user_id' => $userId,
            'item_id' => $itemId,
            'quantity' => 1,
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
     * دریافت بسته‌های سکه
     */
    public function getCoinPackages() {
        // This can be moved to the database in the future
        return [
            10 => ['price' => 10, 'coins' => 100, 'bonus' => 0, 'label' => '100 سکه'],
            40 => ['price' => 40, 'coins' => 500, 'bonus' => 50, 'label' => '500 سکه + 50 پاداش ✨'],
            75 => ['price' => 75, 'coins' => 1000, 'bonus' => 150, 'label' => '1000 سکه + 150 پاداش ⭐'],
            350 => ['price' => 350, 'coins' => 5000, 'bonus' => 1000, 'label' => '5000 سکه + 1000 پاداش 🔥'],
        ];
    }

    /**
     * بروزرسانی آیتم
     */
    public function updateItem($itemId, $data) {
        return $this->db->update('shop_items', $data, "id = ?", "i", [$itemId]);
    }

    /**
     * غیرفعال کردن آیتم
     */
    public function disableItem($itemId) {
        return $this->db->update('shop_items', ['is_active' => 0], "id = ?", "i", [$itemId]);
    }

    /**
     * فعال کردن آیتم
     */
    public function enableItem($itemId) {
        return $this->db->update('shop_items', ['is_active' => 1], "id = ?", "i", [$itemId]);
    }

    /**
     * حذف آیتم
     */
    public function deleteItem($itemId) {
        return $this->db->delete('shop_items', "id = ?", "i", [$itemId]);
    }

    /**
     * تعداد خریدهای کاربر
     */
    public function getUserPurchaseCount($userId) {
        return $this->db->count('purchases', "user_id = ?", "i", [$userId]);
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
}
