<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
