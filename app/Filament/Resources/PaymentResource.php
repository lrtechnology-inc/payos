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
use Illuminate\Support\Facades\DB;
use Throwable;

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

        if ($user->role != 'Desarrollador') {

            $routeuser = Route::where('company_id', $user->company_id)
                ->pluck('id')->toArray();

            $loans = Loan::whereIn('route_id', $routeuser)->pluck('id')->toarray();

            $loanover = PaymentSchedule::whereIn('loan_id', $loans)
                ->whereNotIn('payment_status', ['paid', 'canceled', 'partial'])
                ->where('due_date', '<', Carbon::today()->format('Y-m-d'))
                ->distinct('loan_id')
                ->pluck('loan_id')
                ->toArray();

            if (count($loanover) > 0) {

                try {
                    return DB::transaction(function () use ($loanover, $user, $query) {

                        foreach ($loanover as $ln) {

                            $pysch = PaymentSchedule::whereNotIn('payment_status', ['paid', 'canceled', 'partial'])
                                ->where('due_date', '<', Carbon::today()->format('Y-m-d'))
                                ->where('loan_id', $ln)
                                ->get();

                            foreach ($pysch as $schedule) {
                                $schedule->update(['payment_status' => 'overdue']);
                            }

                            $loan = Loan::find($ln);

                            $loan->update([
                                'status' => 'overdue',
                            ]);

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
                            }

                            return $query;

                            //dd($loan, $pysch);
                        }
                    });
                } catch (Throwable $e) {
                    report($e);
                    throw $e;
                }
            }
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
                    Tables\Actions\DeleteAction::make()
                        ->before(function ($record) {

                            try {
                                DB::transaction(function () use ($record) {

                                    $pyschsum = PaymentSchedule::where('payment_id', $record->id)
                                        ->where('paid_amount', '!=', null)
                                        ->sum('paid_amount');

                                    $loan = Loan::find($record->loan_id);

                                    /*$pycta = PaymentSchedule::where('payment_id', $record->id)
                                        ->first();

                                    $ctas = floor($pyschsum / $pycta->installment_amount);*/

                                    $pycta = $loan->total_amount / $loan->quantity_installments;
                                    //dd($loan, $pycta);
                                    $ctas = floor($pyschsum / $pycta);

                                    $valback = $pyschsum - $record->payment_amount;

                                    $pysch = PaymentSchedule::where('payment_id', $record->id)
                                        ->get();

                                    $ctacp = 0;

                                    $pyam = $valback;

                                    foreach ($pysch as $pyschItem) {

                                        $pyschItem->update([
                                            'payment_date' => null,
                                            'paid_amount' => null,
                                            'payment_id' => null,
                                            'payment_status' => 'pending'
                                        ]);
                                    }

                                    $loan->update([
                                        'remaining_amount' => $loan->remaining_amount + $record->payment_amount,
                                        'payment_count' => $loan->payment_count - $ctas,
                                        'paid_amount' => $loan->paid_amount - $record->payment_amount,
                                        'next_payment_date' => null
                                    ]);

                                    $pysch = PaymentSchedule::where('loan_id', $record->loan_id)
                                        ->where('paid_amount', '!=', $pycta)
                                        ->orwhere('paid_amount', null)
                                        ->orderby('due_date', 'asc')
                                        ->get();

                                    $mxpysch = PaymentSchedule::where('loan_id', $record->loan_id)
                                        ->where('payment_id', '!=', null)
                                        ->orderby('payment_id', 'desc')
                                        ->pluck('payment_id')
                                        ->first();

                                    $pmtdt = Payment::where('id', $mxpysch)->pluck('payment_date')->first();

                                    foreach ($pysch as $pyschItem) {

                                        //dd($pysch);

                                        if ($pyschItem->payment_status == 'partial') {

                                            $dif = $pyschItem->installment_amount - $pyschItem->paid_amount;

                                            if ($dif > $pyam) {

                                                $pyschItem->update([
                                                    'payment_status' => 'partial',
                                                    'payment_date' => $pmtdt,
                                                    'paid_amount' => $pyschItem->paid_amount + $pyam,
                                                    'payment_id' => $mxpysch,
                                                ]);

                                                $pyam = 0;
                                            } elseif ($dif <= $pyam) {

                                                $pyschItem->update([
                                                    'payment_status' => 'paid',
                                                    'payment_date' => $pmtdt,
                                                    'paid_amount' => $pyschItem->installment_amount,
                                                    'payment_id' => $mxpysch,
                                                ]);

                                                $pyam = $pyam - $dif;

                                                $ctacp += 1;
                                            }
                                        } else {

                                            if ($pyam >= $pyschItem->installment_amount) {

                                                $pyschItem->update([
                                                    'payment_status' => 'paid',
                                                    'payment_date' => $pmtdt,
                                                    'paid_amount' => $pyschItem->installment_amount,
                                                    'payment_id' => $mxpysch,
                                                ]);

                                                $pyam = $pyam - $pyschItem->installment_amount;

                                                $ctacp += 1;
                                            } elseif ($pyam < $pyschItem->installment_amount && $pyam > 0) {

                                                $pyschItem->update([
                                                    'payment_status' => 'partial',
                                                    'payment_date' => $pmtdt,
                                                    'paid_amount' => $pyam,
                                                    'payment_id' => $mxpysch,
                                                ]);

                                                $pyam = 0;
                                            }

                                            if ($pyam == 0) {
                                                break;
                                            }
                                        }
                                    }

                                    //$npysch = PaymentSchedule::where('loan_id', $record->loan_id)->get();

                                    $loan->update([
                                        'payment_count' => $loan->payment_count + $ctacp,
                                    ]);

                                    if ($loan->quantity_installments == $loan->payment_count && $loan->remaining_amount == 0) {

                                        $loan->update([
                                            'status' => 'paid',
                                        ]);
                                    } else {

                                        $pysch = PaymentSchedule::where('loan_id', $record->loan_id)->where('payment_status', '!=', 'paid')->orderBy('due_date', 'asc')->first();

                                        $loan->update([
                                            'next_payment_date' => $pysch->due_date,
                                        ]);

                                        $pysch = PaymentSchedule::whereNotIn('payment_status', ['paid', 'canceled', 'partial'])
                                            ->where('due_date', '<', Carbon::today()->format('Y-m-d'))
                                            ->where('loan_id', $record->loan_id)
                                            ->get();

                                        if (count($pysch) > 0) {

                                            foreach ($pysch as $schedule) {
                                                $schedule->update(['payment_status' => 'overdue']);
                                            }

                                            $loan->update([
                                                'status' => 'overdue',
                                            ]);
                                        }

                                        if ($loan->next_payment_date > Carbon::today()->format('Y-m-d')) {

                                            $loan->update([
                                                'status' => 'active',
                                            ]);
                                        }
                                    }
                                    Payment::where('id', $record->id)->delete();
                                });
                            } catch (Throwable $e) {
                                dd($e);
                            }
                        }),
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
