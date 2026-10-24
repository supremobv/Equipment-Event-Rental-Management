<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $guarded = ['id'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function maintenance()
    {
        return $this->hasMany(Maintenance::class);
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }
}
