<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per user holding the "Ticket Controller" role — a flat grant (unlike
 * event_coordinators, which needs one row per user *per event*) since the Ticket module is
 * standalone, with nothing to scope a level to. See TicketControllerLevel for the
 * view/entry/admin tier this level column holds.
 */
class TicketControllerGrant extends Model
{
    protected $table = 'ticket_controllers';

    protected $fillable = ['user_id', 'level'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
