<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $table = 'inventories';
    protected $primaryKey = 'item_id';

    protected $fillable = [
        'item_name',
        'category',
        'quantity',
        'unit',
        'minimum_threshold',
        'last_restocked',
    ];

    /**
     * Get transactions associated with the inventory item.
     */
    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class, 'item_id', 'item_id');
    }

    /**
     * Get stock status text and styling badge.
     */
    public function getStockStatusAttribute()
    {
        if ($this->quantity <= 0) {
            return [
                'text' => 'Out of Stock',
                'class' => 'danger',
            ];
        }

        if ($this->quantity <= $this->minimum_threshold) {
            return [
                'text' => 'Low Stock',
                'class' => 'warning',
            ];
        }

        return [
            'text' => 'In Stock',
            'class' => 'success',
        ];
    }
}
