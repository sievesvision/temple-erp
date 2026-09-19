<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One physical ticket to print — ordering quantity 5 of a $5 ticket produces 5 of these, each
 * with its own unique stub_number ("TKT" + the row's own id, zero-padded — guaranteed unique
 * and collision-free since it's derived from the auto-increment id itself, the same
 * DN/GD-prefixed-id convention already used for devotee/guest donations).
 */
class TicketStub extends Model
{
    protected $fillable = [
        'ticket_order_item_id',
        'stub_number',
        'printed_at',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(TicketOrderItem::class, 'ticket_order_item_id');
    }

    /**
     * Creates $quantity stub rows for one order item, numbering each from its own id so no
     * separate sequence/lock is needed to avoid a collision.
     *
     * @return \Illuminate\Support\Collection<int, TicketStub>
     */
    public static function createForItem(TicketOrderItem $item, int $quantity): \Illuminate\Support\Collection
    {
        $stubs = collect();
        for ($i = 0; $i < $quantity; $i++) {
            // A temporary unique placeholder (the real, unique stub_number column can't hold
            // the same empty value twice in one batch) until the row's own id is known.
            $stub = self::create(['ticket_order_item_id' => $item->id, 'stub_number' => (string) \Illuminate\Support\Str::uuid()]);
            $stub->stub_number = 'TKT' . str_pad((string) $stub->id, 6, '0', STR_PAD_LEFT);
            $stub->save();
            $stubs->push($stub);
        }
        return $stubs;
    }
}
