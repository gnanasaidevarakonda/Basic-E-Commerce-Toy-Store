<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Middlewares\AuthMiddleware;
use App\Models\Cart;

class CartController
{
    /**
     * GET /api/v1/cart
     * View complete shopiing cart for authemnticated user
     */
    public function index(): void
    {
        $userId = AuthMiddleware::id();
        if (!$userId) {
            Response::error('Unauthorized: User session required.', 401);
        }

        $cart = Cart::getByUser($userId);
        Response::success($cart, 'Cart retrieved retrieved.');
    }


    /**
     * GET /api/v1/cart/count
     * Fast aggregate item count for navbar cart badges
     */
    public function count(): void
    {
        $userId = AuthMiddleware::id();
        if (!$userId) {
            Response::error('Unauthorized: User session required.', 401);
        }

        $count = Cart::getCount($userId);
        Response::success(['badge_count' => $count], 'Cart count retrieved.');
    }


    /**
     * POST /api/v1/cart/items
     * Add a toy to cart or incrementr qunatity if already exists
     */
    public function add(): void
    {
        $userId = AuthMiddleware::id();
        if (!$userId) {
            Response::error('Unauthorized: User session required.', 401);
        }
        $body = Request::getBody();
        $productId = isset($body['product_id']) ? (int) $body['product_id'] : 0;
        $quantity = isset($body['quantity']) ? (int)$body['quantity'] : 1;

        if ($productId <= 0) {
            Response::error('Validation is failed: A valid product id is required.', 422);
        }

        if ($quantity <= 0) {
            Response::error('Validity failed: Quantity must be atleast 1.', 422);
        }

        $result = Cart::addItem($userId, $productId, $quantity);

        if (!$result['success']) {
            Response::error($result['error'], $result['code'] ?? 422);
        }

        Response::success([
            'cart_item_id' => $result['cart_item_id'],
            'quantity' => $result['quantity']
        ], $result['message'], 201);
    }

    /**
     * PUT /api/v1/cart/items/{id}
     * Update quaniotty of an item in cart
     */

    public function update(string $id): void
    {
        $userId = AuthMiddleware::id();
        if (!$userId) {
            Response::error('Unauthorized: User session required.', 401);
        }

        $cartItemId = (int)$id;
        if ($cartItemId <= 0) {
            Response::error('Validation failed: Invalid cart item ID.', 422);
        }

        $body = Request::getBody();

        if (!isset($body['quantity'])) {
            Response::error('Validation failed: quantity is required.', 422);
        }

        $newQuantity = (int)$body['quantity'];
        $result = Cart::updateQuantity($userId, $cartItemId, $newQuantity);

        if (!$result['success']) {
            Response::error($result['error'], $result['code'] ?? 422);
        }

        Response::success([
            'cart_item_id' => $cartItemId,
            'quantity' => $result['quantity'] ?? 0
        ], $result['message']);
    }


    /**
     * DELETE /api/v1/cart/items/{id}
     * Remove a single item from cart
     */
    public function remove(string $id): void
    {
        $userId = AuthMiddleware::id();
        if (!$userId) {
            Response::error('Unauthorized: User session required.', 401);
        }

        $cartItemId = (int)$id;
        if ($cartItemId <= 0) {
            Response::error('Validation failed: Invalid cart item ID.', 422);
        }

        $deleted = Cart::removeItem($userId, $cartItemId);

        if (!$deleted) {
            Response::error('Cart Item not found or already removed.', 404);
        }

        Response::success(null, 'Item removed from cart successfully.');
    }


    /**
     * DELETE /api/v1/cart
     * Empty entire shopping cart for the user
     */

    public function clear(): void
    {
        $userId = AuthMiddleware::id();
        if (!$userId) {
            Response::error('Unauthorized: User session required.', 401);
        }

        Cart::clear($userId);
        Response::success(null, 'Cart cleared successfully.');
    }
}
