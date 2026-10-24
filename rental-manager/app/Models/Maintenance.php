<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Maintenance extends Model
{
    protected $table = 'maintenance';

    protected $guarded = ['id'];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }
}
