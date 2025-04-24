<?php

namespace App\Services;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Exception;

class UserService
{
    /**
     * Get all users with optional filtering
     */
    public function getAllUsers(array $filters = [], int $perPage = null): Collection|LengthAwarePaginator
    {
        $query = User::query();
        
        // Apply search filter if provided
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply role filter if provided
        if (!empty($filters['role']) && $filters['role'] !== 'all') {
            $query->where('role', $filters['role']);
        }

        // Sort by created date if not specified
        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Create a new user
     */
    public function createUser(array $data): User
    {
        try {
            DB::beginTransaction();
            
            // Check if username or email already exists
            if (User::where('username', $data['username'])->exists()) {
                throw new Exception('Username already exists');
            }
            
            if (User::where('email', $data['email'])->exists()) {
                throw new Exception('Email already exists');
            }
            
            // Create the user
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role']
            ]);
            
            DB::commit();
            return $user;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to create user: ' . $e->getMessage());
        }
    }

    /**
     * Get user by ID
     */
    public function getUserById(int $id): User
    {
        $user = User::find($id);
        
        if (!$user) {
            throw new Exception("User with ID {$id} not found");
        }

        return $user;
    }

    /**
     * Update user
     */
    public function updateUser(int $id, array $data): User
    {
        try {
            DB::beginTransaction();

            $user = $this->getUserById($id);
            
            // Check if username already exists (except for current user)
            if (!empty($data['username']) && 
                User::where('username', $data['username'])
                    ->where('id', '!=', $id)
                    ->exists()) {
                throw new Exception('Username already exists');
            }
            
            // Check if email already exists (except for current user)
            if (!empty($data['email']) && 
                User::where('email', $data['email'])
                    ->where('id', '!=', $id)
                    ->exists()) {
                throw new Exception('Email already exists');
            }

            // Update user data
            $updateData = [
                'name' => $data['name'] ?? $user->name,
                'username' => $data['username'] ?? $user->username,
                'email' => $data['email'] ?? $user->email,
                'role' => $data['role'] ?? $user->role
            ];
            
            // Only update password if provided
            if (!empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }
            
            $user->update($updateData);

            DB::commit();
            return $user;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to update user: ' . $e->getMessage());
        }
    }

    /**
     * Delete user
     */
    public function deleteUser(int $id): bool 
    {
        try {
            DB::beginTransaction();
            
            $user = $this->getUserById($id);
            
            // Prevent deleting the last admin user
            if ($user->role === 'admin') {
                $adminCount = User::where('role', 'admin')->count();
                
                if ($adminCount <= 1) {
                    throw new Exception('Cannot delete the last admin user');
                }
            }
            
            $user->delete();
            
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to delete user: ' . $e->getMessage());
        }
    }

    /**
     * Get user statistics
     */
    public function getUserStats(): array
    {
        return [
            'total_users' => User::count(),
            'admin_users' => User::where('role', 'admin')->count(),
            'sales_users' => User::where('role', 'sales')->count(),
            'cashier_users' => User::where('role', 'cashier')->count(),
            'staff_users' => User::where('role', 'staff')->count(),
        ];
    }
}