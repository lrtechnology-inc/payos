<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Role;
use App\Models\Route;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        Role::create([
            'name' => 'Desarrollador',
            'description' => 'Desarrollador del sistema',
        ]);

        Role::create([
            'name' => 'Prestamista',
            'description' => 'Usuario que presta dinero',
        ]);

        Role::create([
            'name' => 'Cobrador',
            'description' => 'Usuario que cobra dinero de las rutas de su zona',
        ]);

        Company::create([
            'name' => 'Prueba',
            'description' => 'Empresa de prueba',
            'owner' => 'Propietario de prueba',
            'phone' => '1234567890',
            'address' => 'Dirección de prueba',
            'city' => 'Ciudad de prueba',
            'state' => 'Departamento de prueba',
            'country' => 'País de prueba',
            'email' => 'empresaprueba1@gmail.com'
        ]);

        User::create([
            'name' => 'Desarrollador',
            'email' => 'jcamiloleon0786@gmail.com',
            'password' => Hash::make('12345678'),
            'role_id' => 1,
        ]);

        User::create([
            'name' => 'Prestamista',
            'email' => 'prestamista@gmail.com',
            'password' => Hash::make('12345678'),
            'role_id' => 2,
            'company_id' => 1,
        ]);

        User::create([
            'name' => 'Cobrador',
            'email' => 'cobrador@gmail.com',
            'password' => Hash::make('12345678'),
            'role_id' => 3,
            'company_id' => 1,
        ]);

        Route::create([
            'name' => 'Ruta1',
            'description' => 'Ruta de prueba',
            'collector_id' => 3,
            'company_id' => 1,
        ]);

        Customer::create([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'company_id' => 1,
            'active' => true,
            'dni_type' => 'CC',
            'dni' => '1060586537',
            'address' => 'Calle 1 # 1-1',
            'city' => 'Bogotá',
            'country' => 'Colombia',
            'state' => 'Cundinamarca',
            'phone' => '3101231234',
            'email' => 'customer@gmail.com'
        ]);
    }
}
