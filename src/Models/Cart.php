<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Cart
{
    /**
     * 1. GET CART: Fetch complete cart items with live product prices and photos.
     * Calculates line totals and flags out-of-stock items.
     */
    public static function getByUser(int $userId): array
    {
        $db = Database::getConnection();

        $sql = "SELECT ci.id AS cart_item_id, ci.quantity, 
                       p.id AS product_id, p.name AS product_name, p.slug AS product_slug, 
                       p.sku, p.price AS unit_price, p.stock_quantity AS available_stock, p.is_active,
                       (SELECT image_url FROM product_images 
                        WHERE product_id = p.id 
                        ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1) AS product_image 
                FROM cart_items ci 
                INNER JOIN products p ON ci.product_id = p.id 
                WHERE ci.user_id = :user_id 
                ORDER BY ci.created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        $subtotal = 0.00;
        $totalQuantity = 0;

        foreach ($rows as $row) {
            $unitPrice = (float) $row['unit_price'];
            $qty = (int) $row['quantity'];
            $stock = (int) $row['available_stock'];
            $isActive = (int) $row['is_active'] === 1;

            $lineTotal = round($unitPrice * $qty, 2);
            $subtotal += $lineTotal;
            $totalQuantity += $qty;

            // Flag whether this item can currently be checked out
            $hasSufficientStock = $stock >= $qty;
            $canCheckout = $isActive && $hasSufficientStock;

            $items[] = [
                'cart_item_id'         => (int) $row['cart_item_id'],
                'product_id'           => (int) $row['product_id'],
                'product_name'         => $row['product_name'],
                'product_slug'         => $row['product_slug'],
                'sku'                  => $row['sku'],
                'product_image'        => $row['product_image'],
                'unit_price'           => $unitPrice,
                'quantity'             => $qty,
                'available_stock'      => $stock,
                'line_total'           => $lineTotal,
                'has_sufficient_stock' => $hasSufficientStock,
                'can_checkout'         => $canCheckout
            ];
        }

        return [
            'item_count'     => count($items),
            'total_quantity' => $totalQuantity,
            'subtotal'       => round($subtotal, 2),
            'items'          => $items
        ];
    }

    /**
     * 2. ADD ITEM: Adds a product to user's cart or increments quantity if already present.
     * Enforces active product check and available inventory limits.
     */
    public static function addItem(int $userId, int $productId, int $quantity = 1): array
    {
        $db = Database::getConnection();

        // Step A: Verify product exists, is active, and check its available stock
        $prodStmt = $db->prepare("SELECT id, name, price, stock_quantity, is_active FROM products WHERE id = :id LIMIT 1");
        $prodStmt->execute([':id' => $productId]);
        $product = $prodStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return ['success' => false, 'error' => 'Product not found.', 'code' => 404];
        }

        if ((int) $product['is_active'] !== 1) {
            return ['success' => false, 'error' => 'This product is currently inactive and cannot be purchased.', 'code' => 422];
        }

        $stock = (int) $product['stock_quantity'];
        if ($stock <= 0) {
            return ['success' => false, 'error' => 'Product is currently out of stock.', 'code' => 422];
        }

        // Step B: Check if product is already in user's cart
        $checkStmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE user_id = :user_id AND product_id = :product_id LIMIT 1");
        $checkStmt->execute([':user_id' => $userId, ':product_id' => $productId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newQuantity = (int) $existing['quantity'] + $quantity;

            // Enforce stock ceiling
            if ($newQuantity > $stock) {
                return [
                    'success' => false,
                    'error'   => "Cannot add {$quantity} more. Maximum available stock is {$stock} (You already have {$existing['quantity']} in your cart).",
                    'code'    => 422
                ];
            }

            $updateStmt = $db->prepare("UPDATE cart_items SET quantity = :qty WHERE id = :id");
            $updateStmt->execute([':qty' => $newQuantity, ':id' => $existing['id']]);

            return [
                'success'      => true,
                'cart_item_id' => (int) $existing['id'],
                'quantity'     => $newQuantity,
                'message'      => "Cart updated: {$product['name']} quantity increased to {$newQuantity}."
            ];
        }

        // Step C: Product is not in cart yet -> Insert new cart item
        if ($quantity > $stock) {
            return [
                'success' => false,
                'error'   => "Requested quantity ({$quantity}) exceeds available stock ({$stock}).",
                'code'    => 422
            ];
        }

        $insertStmt = $db->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (:user_id, :product_id, :qty)");
        $insertStmt->execute([
            ':user_id'    => $userId,
            ':product_id' => $productId,
            ':qty'        => $quantity
        ]);

        return [
            'success'      => true,
            'cart_item_id' => (int) $db->lastInsertId(),
            'quantity'     => $quantity,
            'message'      => "{$product['name']} added to your cart."
        ];
    }

    /**
     * 3. UPDATE QUANTITY: Modifies the quantity of an existing cart item.
     * Prevents IDOR security vulnerabilities by enforcing user_id ownership.
     */
    public static function updateQuantity(int $userId, int $cartItemId, int $newQuantity): array
    {
        $db = Database::getConnection();

        // If user sets quantity to 0, remove the item
        if ($newQuantity <= 0) {
            self::removeItem($userId, $cartItemId);
            return ['success' => true, 'message' => 'Item removed from cart.'];
        }

        // IDOR Guard: Verify this cart_item strictly belongs to the logged-in user
        $stmt = $db->prepare("SELECT ci.id, ci.quantity, p.stock_quantity, p.name 
                              FROM cart_items ci 
                              INNER JOIN products p ON ci.product_id = p.id 
                              WHERE ci.id = :id AND ci.user_id = :user_id LIMIT 1");
        $stmt->execute([':id' => $cartItemId, ':user_id' => $userId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            return ['success' => false, 'error' => 'Cart item not found in your cart.', 'code' => 404];
        }

        // Enforce inventory stock limit
        $stock = (int) $item['stock_quantity'];
        if ($newQuantity > $stock) {
            return [
                'success' => false,
                'error'   => "Cannot update quantity to {$newQuantity}. Only {$stock} units available in stock.",
                'code'    => 422
            ];
        }

        $update = $db->prepare("UPDATE cart_items SET quantity = :qty WHERE id = :id AND user_id = :user_id");
        $update->execute([':qty' => $newQuantity, ':id' => $cartItemId, ':user_id' => $userId]);

        return [
            'success'  => true,
            'quantity' => $newQuantity,
            'message'  => "Updated {$item['name']} quantity to {$newQuantity}."
        ];
    }

    /**
     * 4. REMOVE ITEM: Deletes a single item from user's cart with IDOR protection.
     */
    public static function removeItem(int $userId, int $cartItemId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM cart_items WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $cartItemId, ':user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * 5. CLEAR CART: Empties the entire cart for a user (called after successful checkout).
     */
    public static function clear(int $userId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);

        return true;
    }

    /**
     * 6. GET BADGE COUNT: Returns total count of items in cart for quick UI display.
     */
    public static function getCount(int $userId): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }
}
