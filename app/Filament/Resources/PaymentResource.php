<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Filament\Resources\PaymentResource\RelationManagers;
use App\Models\Customer;
use App\Models\PaymentSchedule;
use App\Models\Route;
use App\Models\Loan;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Split;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static ?string $navigationGroup = 'Negocio';
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $label = 'Pago';
    protected static ?string $pluralLabel = 'Pagos';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();

        switch ($user->role->name) {

            case 'Cobrador':

                $routes = Route::where('collector_id', $user->id)->pluck('id');

                $loans = Loan::whereIn('route_id', $routes)->whereNotIn('status', ['paid', 'canceled'])->pluck('id');

                $query->whereIn('loan_id', $loans);
                break;

            case 'Prestamista':

                $routes = Route::where('company_id', $user->company_id)->pluck('id');

                $loans = Loan::whereIn('route_id', $routes)->whereNotIn('status', ['paid', 'canceled'])->pluck('id');

                $query->whereIn('loan_id', $loans)
                    //->where('payment_date', '>=', Carbon::today())
                ;

                break;
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Split::make([
                    Section::make([
                        Forms\Components\DatePicker::make('payment_date')
                            ->default(now())
                            ->readonly()
                            ->label('Fecha Pago'),
                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente')
                            ->live()
                            ->required()
                            ->preload()
                            ->searchable()
                            ->afterStateUpdated(
                                function (callable $set, $state) {
                                    $set('loan_id', null);
                                    $set('payment_amount', null);
                                }
                            )->options(

                                function () {

                                    $user = Auth::user();

                                    switch ($user->role->name) {

                                        case 'Desarrollador':

                                            $loans = Loan::whereNotIn('status', ['paid', 'canceled'])
                                                ->distinct()
                                                ->pluck('customer_id');

                                            $customers = Customer::whereIn('id', $loans)
                                                ->selectRaw("id, CONCAT(first_name, ' ', last_name, ' - ', dni) as full_info")
                                                ->pluck('full_info', 'id');
                                            break;

                                        case 'Prestamista':

                                            $routes = Route::where('company_id', $user->company_id)
                                                ->pluck('id');

                                            $loans = Loan::whereIn('route_id', $routes)
                                                ->whereNotIn('status', ['paid', 'canceled'])
                                                ->distinct()
                                                ->pluck('customer_id');

                                            $customers = Customer::whereIn('id', $loans)
                                                ->selectRaw("id, CONCAT(first_name, ' ', last_name, ' - ', dni) as full_info")
                                                ->pluck('full_info', 'id');
                                            break;

                                        case 'Cobrador':

                                            $routes = Route::where('collector_id', $user->id)
                                                ->pluck('id');

                                            $loans = Loan::whereIn('route_id', $routes)
                                                ->whereNotIn('status', ['paid', 'canceled'])
                                                ->distinct()
                                                ->pluck('customer_id');

                                            $customers = Customer::whereIn('id', $loans)
                                                ->selectRaw("id, CONCAT(first_name, ' ', last_name, ' - ', dni) as full_info")
                                                ->pluck('full_info', 'id');
                                            break;

                                        default:
                                            $customers = [];
                                            break;
                                    }

                                    return $customers;
                                }
                            ),
                        Forms\Components\Select::make('loan_id')
                            ->label('Préstamo')
                            ->required()
                            ->preload()
                            ->options(
                                function (callable $get) {
                                    return Loan::where('customer_id', $get('customer_id'))
                                        ->whereNotIn('status', ['paid', 'canceled'])
                                        ->with('route') // Eager load the related route
                                        ->get()
                                        ->mapWithKeys(function ($loan) {
                                            $routeName = $loan->route ? $loan->route->name : 'Sin Ruta';
                                            $cuota = $loan->total_amount / $loan->quantity_installments;
                                            return [$loan->id => "Ruta-> {$routeName} - (Balance \$ {$loan->paid_amount} / \$ {$loan->total_amount}) - (Cuotas {$loan->payment_count}/{$loan->quantity_installments}) - (Cuota \$ {$cuota})"];
                                        });
                                }
                            ),
                        Forms\Components\TextInput::make('payment_amount')
                            ->label('Valor Pago')
                            ->required()
                            ->numeric()
                            ->prefix('$ ')
                            ->minValue(0)
                            ->reactive(),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('loan.customer.first_name')
                    ->label('Nombre')
                    ->description(
                        fn($record): string => $record->loan->customer->last_name
                    )
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('loan.route.name')
                    ->label('Ruta')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_amount')
                    ->label('Valor Pagado')
                    ->numeric()
                    ->prefix('$ ')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Fecha Pago')
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
