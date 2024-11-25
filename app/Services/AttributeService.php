<?php

namespace App\Services;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Exception;

class AttributeService
{
    public function getAllAttributes(): Collection
    {
        return Attribute::all();
    }

    public function createAttribute(array $data): Attribute
    {
        return Attribute::create($data);
    }

    public function updateAttribute(Attribute $attribute, array $data): bool
    {
        return $attribute->update($data);
    }

    public function deleteAttribute(Attribute $attribute): bool
    {
        if ($attribute->products()->exists()) {
            throw new Exception('Cannot delete attribute that is in use by products');
        }

        return $attribute->delete();
    }

    public function getAttributeWithProducts(Attribute $attribute): Attribute
    {
        return $attribute->load('products');
    }
}