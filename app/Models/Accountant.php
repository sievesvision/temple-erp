<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Accountant extends Model
{
    protected $table = 'accountants';
    protected $primaryKey = 'accountant_id';

    protected $fillable = [
        'user_id',
        'salary',
        'employment_status',
        'current_status',
        'joining_date',
        'account_holder_name',
        'account_number',
        'ifsc_code',
        'bank_name',
        'branch_name'
    ];

    /**
     * Relationship to the user record.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
