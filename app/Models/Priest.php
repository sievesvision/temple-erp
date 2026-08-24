<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Priest extends Model
{
    protected $table = 'priests';

    protected $primaryKey = 'priest_id';

    protected $guarded = [];
}