<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Exception;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of users
     */
    public function index(Request $request): JsonResponse
    {
        try {

            $filters = [
                'search' => $request->search,
                'role' => $request->role,
                'sort_by' => $request->sort_by,
                'sort_order' => $request->sort_order
            ];

            $users = $this->userService->getAllUsers(
                $filters,
                $request->per_page
            );

            return response()->json([
                'status' => 'success',
                'data' => $users,
                'message' => 'Users retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request): JsonResponse
    {
        try {

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'username' => 'required|string|max:255|unique:users',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => ['required', 'confirmed', Password::defaults()],
                'role' => 'required|in:admin,sales,cashier'
            ]);

            $user = $this->userService->createUser($validated);

            return response()->json([
                'status' => 'success',
                'data' => $user,
                'message' => 'User created successfully'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified user
     */
    public function show(int $id): JsonResponse
    {
        try {

            $user = $this->userService->getUserById($id);

            return response()->json([
                'status' => 'success',
                'data' => $user,
                'message' => 'User retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'username' => 'sometimes|required|string|max:255|unique:users,username,' . $id,
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
                'password' => ['sometimes', 'confirmed', Password::defaults()],
                'role' => 'sometimes|required|in:admin,sales,cashier'
            ]);

            $user = $this->userService->updateUser($id, $validated);

            return response()->json([
                'status' => 'success',
                'data' => $user,
                'message' => 'User updated successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified user
     */
    public function destroy(int $id): JsonResponse
    {
        try {

            // Prevent users from deleting themselves
            if (Auth::id() === $id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You cannot delete your own account'
                ], 422);
            }

            $this->userService->deleteUser($id);

            return response()->json([
                'status' => 'success',
                'message' => 'User deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics
     */
    public function getStats(): JsonResponse
    {
        try {

            $stats = $this->userService->getUserStats();

            return response()->json([
                'status' => 'success',
                'data' => $stats,
                'message' => 'User statistics retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving user statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}