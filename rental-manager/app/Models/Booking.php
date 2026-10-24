<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function netPaid(): int
    {
        return (int) $this->payments->where('kind', 'rental')->sum('amount') - (int) $this->payments->where('kind', 'refund')->sum('amount');
    }

    public function depositHeld(): int
    {
        return (int) $this->payments->where('kind', 'deposit')->sum('amount') - (int) $this->payments->where('kind', 'deposit_refund')->sum('amount');
    }

    public function balance(): int
    {
        return max(0, ($this->status === 'cancelled' ? 0 : $this->total) - $this->netPaid());
    }
}
