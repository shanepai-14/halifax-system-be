<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case CASHIER = 'cashier';
    case SALES = 'sales';
    case STAFF = 'staff';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'Administrator',
            self::CASHIER => 'Cashier',
            self::SALES => 'Sales Representative',
            self::STAFF => 'Staff Member',
        };
    }

    // Helper method to safely get role from mixed input
    public static function fromMixed(mixed $value): ?self
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof self) {
            return $value;
        }

        // Convert to lowercase string for consistent matching
        $value = strtolower((string) $value);
        return self::tryFrom($value);
    }
}