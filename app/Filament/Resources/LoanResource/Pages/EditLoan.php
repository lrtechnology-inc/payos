<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Filament\Resources\LoanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLoan extends EditRecord
{
    protected static string $resource = LoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index'); // Redirige al listado
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['total_amount'] = $data['amount'] + ($data['amount'] * ($data['interest'] / 100));
        $data['payment_count'] = 0;
        $data['paid_amount'] = 0;
        $data['remaining_amount'] = $data['total_amount'];
        $data['last_payment_date'] = null;
        $data['status'] = 'active';
        $data['start_date'] = now();

        $installmenst = (int) $data['quantity_installments'];

        switch ($data['frequency']) {
            case 'daily':
                $data['next_payment_date'] = now()->addDay();
                $data['end_date'] = now()->addDays($installmenst);
                break;
            case 'weekly':
                $data['next_payment_date'] = now()->addWeek();
                $data['end_date'] = now()->addWeeks($installmenst);

                break;
            case 'biweekly':
                $data['next_payment_date'] = now()->addWeeks(2);
                $data['end_date'] = now()->addWeeks($installmenst * 2);
                break;
            case 'monthly':
                $data['next_payment_date'] = now()->addMonth();
                $data['end_date'] = now()->addMonths($installmenst);
                break;
        }

        return $data;
    }
}
