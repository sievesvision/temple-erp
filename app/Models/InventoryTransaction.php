<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    protected $table = 'inventory_transactions';
    protected $primaryKey = 'transaction_id';

    protected $fillable = [
        'item_id',
        'transaction_type',
        'quantity',
        'remarks',
        'transaction_date',
    ];

    /**
     * Get the inventory item related to this transaction.
     */
    public function item()
    {
        return $this->belongsTo(Inventory::class, 'item_id', 'item_id');
    }
}
