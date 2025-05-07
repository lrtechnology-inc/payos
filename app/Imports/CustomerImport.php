<?php

namespace App\Imports;

use App\Models\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CustomerImport implements ToCollection, WithHeadingRow
{
    /**
     * @param Collection $collection
     */
    public function collection(Collection $rows)
    {
        $company_id = Auth::user()->company->id;

        foreach ($rows as $row) {

            Customer::create([
                'first_name' => $row['nombres'],
                'last_name' => $row['apellidos'],
                'company_id' => $company_id,
                'active' => $row['activo'],
                'dni_type' => $row['tipo_documento'],
                'dni' => $row['documento'],
                'address' => $row['direccion'],
                'city' => $row['ciudad'],
                'country' => $row['pais'],
                'state' => $row['estado'],
                'phone' => $row['telefono'],
                'email' => $row['correo'],
                'multiple_routes' => $row['multiple']

            ]);
        }
    }
}
