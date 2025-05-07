<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Filament\Resources\LoanResource;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\Route;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index'); // Redirige al listado
    }


    public function mutateFormDataBeforeCreate(array $data): array
    {
        $data['total_amount'] = $data['amount'] + ($data['amount'] * ($data['interest'] / 100));
        $data['payment_count'] = 0;
        $data['paid_amount'] = 0;
        $data['remaining_amount'] = $data['total_amount'];
        $data['last_payment_date'] = null;
        $data['status'] = 'active';
        $data['start_date'] = now();

        $installments = (int) $data['quantity_installments'];

        switch ($data['frequency']) {
            case 'daily':
                $data['next_payment_date'] = now()->addDay();
                $data['end_date'] = now()->addDays($installments);
                break;
            case 'weekly':
                $data['next_payment_date'] = now()->addWeek();
                $data['end_date'] = now()->addWeeks($installments);
                break;
            case 'biweekly':
                $data['next_payment_date'] = now()->addWeeks(2);
                $data['end_date'] = now()->addWeeks($installments * 2);
                break;
            case 'monthly':
                $data['next_payment_date'] = now()->addMonth();
                $data['end_date'] = now()->addMonths($installments);
                break;
        }

        return $data;
    }

    public function afterCreate()
    {
        $route = Route::find($this->record->route_id);

        $CustomerLoans = Loan::where('customer_id', $this->record->customer_id)->where('route_id', $this->record->route_id)->count();

        if ($CustomerLoans == 1) {
            $route->customers_count = $route->customers_count + 1;
            $route->save();
        }

        switch ($this->record->frequency) {
            case 'daily':
                for ($i = 1; $i <= $this->record->quantity_installments; $i++) {
                    PaymentSchedule::create([
                        'loan_id' => $this->record->id,
                        'due_date' => now()->addDays($i),
                        'installment_amount' => $this->record->total_amount / $this->record->quantity_installments,
                    ]);
                }

                break;
            case 'weekly':
                for ($i = 1; $i <= $this->record->quantity_installments; $i++) {
                    PaymentSchedule::create([
                        'loan_id' => $this->record->id,
                        'due_date' => now()->addWeeks($i),
                        'installment_amount' => $this->record->total_amount / $this->record->quantity_installments,
                    ]);
                }

                break;
            case 'biweekly':
                for ($i = 1; $i <= $this->record->quantity_installments; $i++) {
                    PaymentSchedule::create([
                        'loan_id' => $this->record->id,
                        'due_date' => now()->addWeeks($i * 2),
                        'installment_amount' => $this->record->total_amount / $this->record->quantity_installments,
                    ]);
                }

                break;
            case 'monthly':
                for ($i = 1; $i <= $this->record->quantity_installments; $i++) {
                    PaymentSchedule::create([
                        'loan_id' => $this->record->id,
                        'due_date' => now()->addMonths($i),
                        'installment_amount' => $this->record->total_amount / $this->record->quantity_installments,
                    ]);
                }

                break;
        }
    }
}
