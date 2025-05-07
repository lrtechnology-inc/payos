<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    //

    public $guarded = [];

    protected $fillable = [
        'first_name',
        'last_name',
        'company_id',
        'active',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'dni_type',
        'dni',
        'multiple_routes',
    ];
    protected $casts = [
        'active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    /*public function route()
    {
        return $this->belongsTo(Route::class);
    }*/

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
