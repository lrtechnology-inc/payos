<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Role;
use App\Models\Route;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $user = Auth::user();

        switch ($user->role->name) {
            case 'Desarrollador':
                return [
                    Stat::make('Total Compañias', Company::count()),
                    Stat::make('Total Rutas', Route::count()),
                    Stat::make('Total Clientes', Customer::count()),
                ];

            case 'Prestamista':
                $loans = Loan::whereIn(
                    'customer_id',
                    Customer::where('company_id', $user->company_id)->pluck('id')
                )
                    ->whereNotIn('status', ['paid', 'cancelled'])
                    ->get();

                $capitalPagado = $loans->sum(function ($loan) {
                    return $loan->interest != 0 ? $loan->paid_amount / (1 + ($loan->interest / 100)) : 0;
                });

                $totalPagado = $loans->sum(function ($loan) {
                    return $loan->paid_amount;
                });

                $ganancia = $totalPagado - $capitalPagado;


                return [
                    Stat::make('Total Rutas', Route::where('company_id', $user->company_id)->count())
                        ->description('Rutas creadas por el prestamista'),
                    Stat::make('Clientes Activos', Customer::where('company_id', $user->company_id)->where('active', operator: true)->count()),
                    Stat::make('Clientes Inactivos', Customer::where('company_id', $user->company_id)->where('active', operator: false)->count()),
                    Stat::make('Cobradores', User::where('company_id', $user->company_id)->where('role_id', Role::where('name', 'Cobrador')->first()->id)->count()),
                    Stat::make('Préstamos Vigentes', Loan::whereIn('customer_id', Customer::where('company_id', $user->company_id)->pluck('id'))->whereNotIn('status', ['paid', 'cancelled'])->count())
                        ->description('Vencidos / Atrasados (' . Loan::whereIn('customer_id', Customer::where('company_id', $user->company_id)->pluck('id'))->where('status', 'overdue')->count() . ')')
                        ->color('danger'),
                    Stat::make('$ Capital Prestado', number_format(Loan::whereIn('customer_id', Customer::where('company_id', $user->company_id)->pluck('id'))->whereNotIn('status', ['paid', 'cancelled'])->sum('amount'), 0)),
                    Stat::make('$ Capital Pagado', number_format($capitalPagado, 0)),
                    Stat::make('$ Ganancia', number_format($ganancia, 0)),
                ];

            default:
                return [];
        }
    }
}
