<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class NotificationService
{
    /**
     * Create a new notification
     *
     * @param array $data
     * @return Notification
     */
    public function createNotification(array $data): Notification
    {
        try {
            return Notification::create([
                'user_id' => $data['user_id'] ?? Auth::id(),
                'title' => $data['title'],
                'message' => $data['message'],
                'type' => $data['type'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'is_read' => false,
                'data' => $data['data'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? now()->addDays(30)
            ]);
        } catch (Exception $e) {
            throw new Exception('Failed to create notification: ' . $e->getMessage());
        }
    }

    /**
     * Get notifications for the authenticated user
     *
     * @param array $filters
     * @param int|null $perPage
     * @return mixed
     */
    public function getUserNotifications(array $filters = [], ?int $perPage = null)
    {
        $query = Notification::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_read'])) {
            $query->where('is_read', filter_var($filters['is_read'], FILTER_VALIDATE_BOOLEAN));
        }

        // Return paginated or all
        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Get unread notifications count for the authenticated user
     *
     * @return int
     */
    public function getUnreadCount(): int
    {
        return Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->count();
    }

    /**
     * Mark notification as read
     *
     * @param int $id
     * @return bool
     */
    public function markAsRead(int $id): bool
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return $notification->markAsRead();
    }

    /**
     * Mark all notifications as read
     *
     * @return int
     */
    public function markAllAsRead(): int
    {
        return Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    /**
     * Delete a notification
     *
     * @param int $id
     * @return bool
     */
    public function deleteNotification(int $id): bool
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return $notification->delete();
    }

    /**
     * Check inventory and create notifications if needed
     *
     * @param int $productId
     * @param int $newQuantity
     * @return void
     */
public function checkInventoryLevels(int $productId, int $newQuantity): void
{
    try {
        $product = Product::findOrFail($productId);
        $inventory = Inventory::where('product_id', $productId)->first();

        if (!$inventory) {
            return;
        }

        // Get all admin users to notify
        $adminUsers = User::where('role', 'admin')->get();
        
        // If out of stock (quantity is exactly 0)
        if ($newQuantity === 0) {
            foreach ($adminUsers as $admin) {
                $this->createNotification([
                    'user_id' => $admin->id,
                    'title' => 'Out of Stock Alert',
                    'message' => "Product {$product->product_name} is now out of stock.",
                    'type' => Notification::TYPE_INVENTORY_OUT,
                    'reference_type' => 'product',
                    'reference_id' => $productId,
                    'data' => [
                        'product_id' => $productId,
                        'product_name' => $product->product_name,
                        'product_code' => $product->product_code,
                        'quantity' => $newQuantity
                    ]
                ]);
            }
        }
        // If below reorder level but not zero
        elseif ($newQuantity > 0 && $newQuantity <= $product->reorder_level) {
            foreach ($adminUsers as $admin) {
                $this->createNotification([
                    'user_id' => $admin->id,
                    'title' => 'Low Stock Alert',
                    'message' => "Product {$product->product_name} is below reorder level ({$product->reorder_level}).",
                    'type' => Notification::TYPE_INVENTORY_LOW,
                    'reference_type' => 'product',
                    'reference_id' => $productId,
                    'data' => [
                        'product_id' => $productId,
                        'product_name' => $product->product_name,
                        'product_code' => $product->product_code,
                        'quantity' => $newQuantity,
                        'reorder_level' => $product->reorder_level
                    ]
                ]);
            }
        }
    } catch (Exception $e) {
        // Log the error but don't prevent the main process from continuing
    
    }
}

    /**
     * Check all products after a sale
     *
     * @param Sale $sale
     * @return void
     */
    public function checkInventoryAfterSale(Sale $sale): void
    {
        try {
            foreach ($sale->items as $item) {
                $inventory = Inventory::where('product_id', $item->product_id)->first();
                if ($inventory) {
                    $this->checkInventoryLevels($item->product_id, $inventory->quantity);
                }
            }
        } catch (Exception $e) {
            // Log::error('Failed to check inventory after sale: ' . $e->getMessage());
        }
    }
}