<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    //
    public $guarded = [];

    protected $fillable = [
        'name',
        'description',
        'company_id',
        'collector_id',
        'customers_count',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function collector()
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    /*public function customers()
    {
        return $this->hasMany(Customer::class);
    }*/
}
