<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_code',
        'full_name',
        'position',
        'department',
        'email',
        'phone_number',
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /**
     * Get the petty cash transactions associated with this employee
     */
    public function pettyCashTransactions()
    {
        return $this->hasMany(PettyCashTransaction::class);
    }

    /**
     * Generate a unique employee code
     */
    public static function generateEmployeeCode()
    {
        $prefix = 'EMP';
        $year = date('Y');
        $month = date('m');
        
        $lastEmployee = self::where('employee_code', 'like', "{$prefix}{$year}{$month}%")
            ->orderBy('employee_code', 'desc')
            ->first();
        
        if ($lastEmployee) {
            $sequence = (int) substr($lastEmployee->employee_code, -4);
            $sequence++;
        } else {
            $sequence = 1;
        }
        
        return sprintf("%s%s%s%04d", $prefix, $year, $month, $sequence);
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        // Generate employee code if not provided
        static::creating(function ($employee) {
            if (empty($employee->employee_code)) {
                $employee->employee_code = self::generateEmployeeCode();
            }
        });
    }
}