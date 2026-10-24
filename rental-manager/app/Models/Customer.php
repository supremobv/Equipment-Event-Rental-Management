<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $guarded = ['id'];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
