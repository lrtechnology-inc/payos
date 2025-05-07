<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    //
    protected $fillable = [
        'customer_id',
        'route_id',
        'amount',
        'interest',
        'total_amount',
        'payment_count',
        'paid_amount',
        'remaining_amount',
        'start_date',
        'end_date',
        'frequency',
        'last_payment_date',
        'next_payment_date',
        'status',
        'quantity_installments',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
