<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSchedule extends Model
{
    //

    protected $fillable = [
        'loan_id',
        'due_date',
        'installment_amount', // Debería ser igual a la cuota diaria calculada en el préstamo
        'payment_status', // 'pending', 'paid', 'overdue' (inicialmente todos 'pending')
        'payment_id',
        'payment_date',
        'paid_amount', // Monto pagado en la fecha de pago
    ];


    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
