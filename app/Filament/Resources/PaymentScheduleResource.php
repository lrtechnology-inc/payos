<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentScheduleResource\Pages;
use App\Filament\Resources\PaymentScheduleResource\RelationManagers;
use App\Models\Loan;
use App\Models\PaymentSchedule;
use App\Models\Route;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class PaymentScheduleResource extends Resource
{
    protected static ?string $model = PaymentSchedule::class;

    protected static ?string $navigationGroup = 'Negocio';
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $label = 'Calendario Pago';
    protected static ?string $pluralLabel = 'Calendario Pagos';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $pysch = PaymentSchedule::whereNotIn('payment_status', ['paid', 'canceled', 'partial'])
            ->where('due_date', '<', Carbon::today()->format('Y-m-d'))
            ->get();

        if (count($pysch) > 0) {

            try {
                return DB::transaction(function () use ($pysch) {

                    foreach ($pysch as $schedule) {
                        $schedule->update(['payment_status' => 'overdue']);
                    }

                    dd($pysch[0]->loan_id);
                });
            } catch (Throwable $e) {
                report($e);
                throw $e;
            }

            //dd($pysch);
        }

        $user = Auth::user();

        switch ($user->role->name) {

            case 'Cobrador':

                $routes = Route::where('collector_id', $user->id)->pluck('id');

                $loans = Loan::whereIn('route_id', $routes)->whereNotIn('status', ['paid', 'canceled'])->pluck('id');

                $query->whereIn('loan_id', $loans)->where('payment_status', '!=', 'paid')->where('due_date', Carbon::today()->format('Y-m-d'));
                break;

            case 'Prestamista':

                $routes = Route::where('company_id', $user->company_id)->pluck('id');

                $loans = Loan::whereIn('route_id', $routes)->whereNotIn('status', ['paid', 'canceled'])->pluck('id');

                $query->whereIn('loan_id', $loans)
                    ->where('payment_status', '!=', 'paid')
                    ->where('due_date', '<=', Carbon::today()->format('Y-m-d'))
                    ->orWhere('due_date', '<', Carbon::today()->format('Y-m-d'));
        }


        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('loan.customer.first_name')
                    ->label('Cliente')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('loan.customer.dni')
                    ->label('Documento')
                    ->searchable(),
                Tables\Columns\TextColumn::make('loan.customer.phone')
                    ->label('Teléfono')
                    ->searchable(),
                Tables\Columns\TextColumn::make('loan.route.name')
                    ->label('Ruta')
                    ->searchable()
                    ->visible(
                        function () {

                            $visible = false;

                            $user = Auth::user();

                            switch ($user->role->name) {
                                case 'Desarrollador':
                                    $visible = true;
                                    break;
                                case 'Prestamista':
                                    $visible = true;
                                    break;
                            }

                            return $visible;
                        }
                    ),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Vencimiento')
                    ->dateTimeTooltip()
                    ->sortable(),
                Tables\Columns\TextColumn::make('installment_amount')
                    ->label('Valor a Pagar')
                    ->prefix('$ ')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Estado')
                    ->badge()
                    ->badge()
                    ->formatStateUsing(
                        fn(string $state): string => match ($state) {
                            'active' => 'Activo',
                            'paid' => 'Pagado',
                            'canceled' => 'Cancelado',
                            'overdue' => 'Vencido',
                            'pending' => 'Pendiente',
                            'partial' => 'Parcial',
                        }
                    )
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'paid' => 'success',
                        'canceled' => 'gray',
                        'overdue' => 'danger',
                        'pending' => 'info',
                        'partial' => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Valor Pagado')
                    ->prefix('$ ')
                    ->numeric(),
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Día Pago')
                    ->dateTimeTooltip(),
            ])
            ->filters([])
            ->actions([])
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
            'index' => Pages\ListPaymentSchedules::route('/'),
            'create' => Pages\CreatePaymentSchedule::route('/create'),
            'edit' => Pages\EditPaymentSchedule::route('/{record}/edit'),
        ];
    }
}
