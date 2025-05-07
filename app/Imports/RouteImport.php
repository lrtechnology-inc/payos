<?php

namespace App\Imports;

use App\Models\Route;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RouteImport implements ToCollection, WithHeadingRow
{
    /**
     * @param Collection $collection
     */
    public function collection(Collection $rows)
    {
        //

        $company_id = Auth::user()->company->id;
        //dd($rows);

        foreach ($rows as $row) {
            //dd($row);
            if ($row['nombre'] == null || $row['descripcion'] == null || $row['activa'] == null) {
                Notification::make()
                    ->title('Error inesperado')
                    ->body('Ocurrió un error inesperado al importar las rutas, uno de los campos esta vacio')
                    ->danger()
                    ->danger()
                    ->persistent()
                    ->send();
                break;
            } else {
                Route::create([
                    'company_id' => $company_id,
                    'name' => $row['nombre'],
                    'description' => $row['descripcion'],
                    'active' => $row['activa']
                ]);
            }
        }
    }
}
