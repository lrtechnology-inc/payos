<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoanResource\Pages;
use App\Filament\Resources\LoanResource\RelationManagers;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Route;
use App\Models\PaymentSchedule;
use Filament\Forms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;

    protected static ?string $navigationGroup = 'Negocio';
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static ?string $label = 'Préstamo';
    protected static ?string $pluralLabel = 'Préstamos';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();

        switch ($user->role->name) {

            case 'Prestamista':
                $query->whereHas('route', function ($q) use ($user) {
                    $q->where('company_id', $user->company_id);
                });
                break;

            case 'Cobrador':
                $query->whereHas('route', function ($q) use ($user) {
                    $q->where('collector_id', $user->id);
                });
                break;
        }

        return $query;
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()->schema([
                    Fieldset::make('Cliente y Ruta')->schema([
                        Forms\Components\Select::make('customer_id')
                            ->options(function () {

                                $user = Auth::user();

                                $customersInDebt = Loan::whereHas('route', function ($q) use ($user) {
                                    $q->where('company_id', $user->company_id);
                                })
                                    ->where('status', 'overdue')
                                    ->pluck('customer_id');

                                $customersInDebtDev = Loan::where('status', 'overdue')
                                    ->pluck('customer_id');


                                switch ($user->role->name) {

                                    case 'Desarrollador':

                                        return Customer::where('active', true)
                                            ->whereNotIn('id', $customersInDebtDev)
                                            ->selectRaw("id, CONCAT(first_name, ' ', last_name, ' - ', dni) as full_info")
                                            ->pluck('full_info', 'id');

                                    case 'Prestamista':

                                        $lenderRoutesIds = Route::where('company_id', $user->company_id)
                                            ->where('active', true)
                                            ->pluck('id');

                                        $availableCustomerIds = Customer::query()
                                            ->where('active', true)
                                            ->where('company_id', $user->company_id)
                                            ->whereNotIn('id', $customersInDebt)
                                            ->where(function ($query) use ($lenderRoutesIds) {
                                                $query
                                                    ->whereDoesntHave('loans')
                                                    ->orWhereHas('loans.route', function ($q) use ($lenderRoutesIds) {
                                                        $q->whereIn('id', $lenderRoutesIds);
                                                    })
                                                    ->orWhere(function ($sub) use ($lenderRoutesIds) {
                                                        $sub->where('multiple_routes', true)
                                                            ->whereHas('loans.route', function ($q) use ($lenderRoutesIds) {
                                                                $q->whereNotIn('id', $lenderRoutesIds);
                                                            });
                                                    });
                                            })
                                            ->pluck('id'); // Solo obtener los IDs

                                        return Customer::where('company_id', $user->company_id)
                                            ->where('active', true)
                                            ->whereIn('id', $availableCustomerIds->isEmpty() ? [0] : $availableCustomerIds)
                                            ->selectRaw("id, CONCAT(first_name, ' ', last_name, ' - ', dni) as full_info")
                                            ->pluck('full_info', 'id');

                                    case 'Cobrador':
                                        // Obtener los IDs de las rutas activas asignadas al cobrador
                                        $collectorRouteIds = Route::where('collector_id', $user->id)
                                            ->where('active', true)
                                            ->pluck('id');

                                        // Obtener los IDs de clientes disponibles
                                        $availableCustomerIds = Customer::query()
                                            ->where('active', true)
                                            ->where('company_id', $user->company_id)
                                            ->whereNotIn('id', $customersInDebt)
                                            ->where(function ($query) use ($collectorRouteIds) {
                                                $query
                                                    ->whereDoesntHave('loans')
                                                    ->orWhereHas('loans.route', function ($q) use ($collectorRouteIds) {
                                                        $q->whereIn('id', $collectorRouteIds);
                                                    })
                                                    ->orWhere(function ($sub) use ($collectorRouteIds) {
                                                        $sub->where('multiple_routes', true)
                                                            ->whereHas('loans.route', function ($q) use ($collectorRouteIds) {
                                                                $q->whereNotIn('id', $collectorRouteIds);
                                                            });
                                                    });
                                            })
                                            ->pluck('id'); // Solo obtener los IDs

                                        // Devolver la lista de clientes con su información formateada
                                        return Customer::where('company_id', $user->company_id)
                                            ->where('active', true)
                                            ->whereIn('id', $availableCustomerIds->isEmpty() ? [0] : $availableCustomerIds)
                                            ->selectRaw("id, CONCAT(first_name, ' ', last_name, ' - ', dni) as full_info")
                                            ->pluck('full_info', 'id');
                                }
                            })
                            ->label('Cliente')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('route_id')
                            ->label('Ruta')
                            ->searchable()
                            ->preload()
                            ->options(
                                function () {
                                    $user = Auth::user();

                                    $CustomerAllowed = 90;

                                    switch ($user->role->name) {

                                        case 'Desarrollador':
                                            return Route::where('active', true)
                                                ->where('customers_count', '<', $CustomerAllowed)
                                                ->pluck('name', 'id');

                                        case 'Prestamista':
                                            return Route::where('company_id', $user->company_id)
                                                ->where('active', true)
                                                ->where('customers_count', '<', $CustomerAllowed)
                                                ->pluck('name', 'id');

                                        case 'Cobrador':
                                            return Route::where('company_id', $user->company_id)
                                                ->where('collector_id', $user->id)
                                                ->where('active', true)
                                                ->where('customers_count', '<', $CustomerAllowed)
                                                ->pluck('name', 'id');
                                    }
                                }
                            )
                            ->required(),
                    ])->columns(3),
                ]),
                Section::make()->schema([
                    Fieldset::make('Datos del Préstamo')
                        ->schema([
                            Forms\Components\TextInput::make('amount')
                                ->label('Monto')
                                ->required()
                                ->placeholder('Monto a Prestar')
                                ->numeric(),
                            Forms\Components\TextInput::make('interest')
                                ->label('Interés')
                                ->maxValue(100)
                                ->minValue(0)
                                ->step(1)
                                ->default(20)
                                ->required()
                                ->suffix('%')
                                ->numeric(),
                            Forms\Components\Select::make('frequency')
                                ->label('Frecuencia')
                                ->options([
                                    'daily' => 'Diario',
                                    'weekly' => 'Semanal',
                                    'biweekly' => 'Quincenal',
                                    'monthly' => 'Mensual',
                                ])
                                ->preload()
                                ->default('daily')
                                ->required(),
                            Forms\Components\TextInput::make('quantity_installments')
                                ->label('Cuotas')
                                ->step(1)
                                ->numeric()
                                ->default(20)
                                ->required()
                        ])->columns([
                            'sm' => 1,
                            'md' => 2,
                            'lg' => 2,
                            'xl' => 3,
                            '2xl' => 4,
                        ]),
                ])
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.first_name')
                    ->label('Nombre Cliente')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.last_name')
                    ->label('Apellido Cliente')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.dni')
                    ->label('Documento')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer.phone')
                    ->label('Teléfono')
                    ->searchable(),
                Tables\Columns\TextColumn::make('route.name')
                    ->label('Ruta')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto Prestado')
                    ->prefix('$ ')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('interest')
                    ->label('Interés')
                    ->suffix(' %')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total a Pagar')
                    ->prefix('$ ')
                    ->numeric(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(
                        fn(string $state): string => match ($state) {
                            'active' => 'Activo',
                            'paid' => 'Pagado',
                            'canceled' => 'Cancelado',
                            'overdue' => 'Vencido',
                        }
                    )
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'paid' => 'primary',
                        'canceled' => 'danger',
                        'overdue' => 'warning',
                    }),
                Tables\Columns\TextColumn::make('Valor Cuota')
                    ->label('Valor Cuota')
                    ->prefix('$ ')
                    ->numeric()
                    ->getStateUsing(
                        function (Loan $record): string {
                            $cuota = $record->total_amount / $record->quantity_installments;
                            return number_format($cuota, 2);
                        }
                    )
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('payment_count')
                    ->label('Cuotas Pagadas')
                    ->numeric(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Monto Pagado')
                    ->prefix('$ ')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Monto Restante')
                    ->prefix('$ ')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Fecha Inicio')
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fecha Fin')
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('frequency')
                    ->label('Frecuencia')
                    ->badge()
                    ->formatStateUsing(
                        fn(string $state): string => match ($state) {
                            'daily' => 'Diario',
                            'weekly' => 'Semanal',
                            'biweekly' => 'Quincenal',
                            'monthly' => 'Mensual',
                        }
                    )
                    ->color(fn(string $state): string => match ($state) {
                        'daily' => 'success',
                        'weekly' => 'warning',
                        'biweekly' => 'info',
                        'monthly' => 'danger',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_payment_date')
                    ->label('Último Pago')
                    ->dateTimeTooltip()
                    ->sortable(),
                Tables\Columns\TextColumn::make('next_payment_date')
                    ->label('Próximo Pago')
                    ->dateTimeTooltip()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(function () {
                        $user = Auth::user();
                        $canView = false;
                        switch ($user->role->name) {

                            case 'Desarrollador':
                                $canView = true;
                                break;
                            default:
                                $canView = false;
                                break;
                        }

                        return $canView;
                    }),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(function () {
                        $user = Auth::user();
                        $canView = false;
                        switch ($user->role->name) {

                            case 'Desarrollador':
                                $canView = true;
                                break;

                            default:
                                $canView = false;
                                break;
                        }

                        return $canView;
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()
                        ->action(function ($record) {

                            PaymentSchedule::where('loan_id', $record->id)->delete();

                            $route = Route::find($record->route_id);

                            $route->customers_count = $route->customers_count - 1;

                            $route->save();

                            $record->delete();
                        })
                ])
                    ->tooltip('Acciones')
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }




    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'edit' => Pages\EditLoan::route('/{record}/edit'),
        ];
    }
}
