<?php

namespace App\Filament\Resources\RouteResource\Pages;

use App\Filament\Resources\RouteResource;
use App\Models\Loan;
use App\Models\Route;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditRoute extends EditRecord
{
    protected static string $resource = RouteResource::class;

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

        $cntloan = Loan::where('route_id', $record->id)->whereNotIn('status', ['paid', 'canceled'])->get()->count();

        if ($cntloan > 0 && $record->active == true && $data['active'] == false) {

            Notification::make()
                ->title('Desactivación no permitida')
                ->body('La ruta tiene prestamos activos y no puede ser desactivada hasta que estos sean gestionados')
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'payment_amount' => 'La ruta tiene prestamos activos y no puede ser desactivada hasta que estos sean gestionados',
            ]);
        }

        $record->update($data);

        return $record;
    }
}
