<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Loan;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {

        $cntloan = Loan::where('customer_id', $record->id)->whereNotIn('status', ['paid', 'canceled'])->get()->count();

        if ($cntloan > 0 && $record->active == true && $data['active'] == false) {

            Notification::make()
                ->title('Desactivación no permitida')
                ->body('El cliente tiene prestamos activos y no puede ser desactivado hasta que estos sean gestionados')
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'payment_amount' => 'El cliente tiene prestamos activos y no puede ser desactivado hasta que estos sean gestionados',
            ]);
        }

        $record->update($data);

        return $record;
    }
}
