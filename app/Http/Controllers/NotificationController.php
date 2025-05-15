<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get notifications for the authenticated user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'type' => $request->type,
                'is_read' => $request->is_read
            ];

            $notifications = $this->notificationService->getUserNotifications(
                $filters,
                $request->per_page
            );

            return response()->json([
                'status' => 'success',
                'data' => $notifications,
                'message' => 'Notifications retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread notifications count
     *
     * @return JsonResponse
     */
    public function getUnreadCount(): JsonResponse
    {
        try {
            $count = $this->notificationService->getUnreadCount();

            return response()->json([
                'status' => 'success',
                'data' => ['count' => $count],
                'message' => 'Unread count retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving unread count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark a notification as read
     *
     * @param int $id
     * @return JsonResponse
     */
    public function markAsRead(int $id): JsonResponse
    {
        try {
            $result = $this->notificationService->markAsRead($id);

            return response()->json([
                'status' => 'success',
                'data' => ['marked' => $result],
                'message' => 'Notification marked as read successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error marking notification as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     *
     * @return JsonResponse
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $count = $this->notificationService->markAllAsRead();

            return response()->json([
                'status' => 'success',
                'data' => ['count' => $count],
                'message' => 'All notifications marked as read successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error marking all notifications as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a notification
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->notificationService->deleteNotification($id);

            return response()->json([
                'status' => 'success',
                'data' => ['deleted' => $result],
                'message' => 'Notification deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}