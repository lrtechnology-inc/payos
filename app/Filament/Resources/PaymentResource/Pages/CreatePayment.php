<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Loan;
use App\Models\PaymentSchedule;
use Filament\Actions;
use Filament\Forms\ComponentContainer;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return DB::transaction(function () use ($data) {

                $loan = Loan::find($data['loan_id']);

                $pysch = PaymentSchedule::where('loan_id', $data['loan_id'])->whereIn('payment_status', ['pending', 'partial'])->orderBy('due_date', 'asc')->get();

                $payment = static::getModel()::create($data);

                if ($loan->remaining_amount >= $data['payment_amount']) {

                    $pyam = $data['payment_amount'];

                    $ctacp = 0;

                    foreach ($pysch as $pyschItem) {

                        if ($pyschItem->payment_status == 'partial') {

                            $dif = $pyschItem->installment_amount - $pyschItem->paid_amount;

                            if ($dif > $pyam) {
                                $pyschItem->update([
                                    'payment_status' => 'partial',
                                    'payment_date' => $data['payment_date'],
                                    'paid_amount' => $pyschItem->paid_amount + $pyam,
                                    'payment_id' => $payment->id,
                                ]);

                                $pyam = 0;
                            } elseif ($dif <= $pyam) {



                                $pyschItem->update([
                                    'payment_status' => 'paid',
                                    'payment_date' => $data['payment_date'],
                                    'paid_amount' => $pyschItem->installment_amount,
                                    'payment_id' => $payment->id,
                                ]);

                                $pyam = $pyam - $dif;
                                $ctacp += 1;
                            }
                        } else {

                            if ($pyam >= $pyschItem->installment_amount) {

                                $pyschItem->update([
                                    'payment_status' => 'paid',
                                    'payment_date' => $data['payment_date'],
                                    'paid_amount' => $pyschItem->installment_amount,
                                    'payment_id' => $payment->id,
                                ]);

                                $pyam = $pyam - $pyschItem->installment_amount;

                                $ctacp += 1;
                            } elseif ($pyam < $pyschItem->installment_amount && $pyam > 0) {

                                $pyschItem->update([
                                    'payment_status' => 'partial',
                                    'payment_date' => $data['payment_date'],
                                    'paid_amount' => $pyam,
                                    'payment_id' => $payment->id,
                                ]);

                                $pyam = 0;
                            }

                            if ($pyam == 0) {
                                break;
                            }
                        }

                        //dd($pyschItem, $pysch, $pyam);
                    }

                    $loan->update([
                        'payment_count' => $loan->payment_count + $ctacp,
                        'paid_amount' => $loan->paid_amount + $data['payment_amount'],
                        'remaining_amount' => $loan->remaining_amount - $data['payment_amount'],
                        'last_payment_date' => $data['payment_date'],
                    ]);

                    if ($loan->quantity_installments == $loan->payment_count && $loan->remaining_amount == 0) {
                        $loan->update([
                            'status' => 'paid',
                        ]);
                    } else {

                        $pysch = PaymentSchedule::where('loan_id', $data['loan_id'])->where('payment_status', '!=', 'paid')->orderBy('due_date', 'asc')->first();

                        $loan->update([
                            'next_payment_date' => $pysch->due_date,
                        ]);
                    }

                    return $payment;
                } elseif ($loan->remaining_amount < $data['payment_amount']) {
                    Notification::make()
                        ->title('El monto del pago excede el saldo restante del préstamo')
                        ->body('El monto del pago no puede ser mayor al saldo restante del préstamo.')
                        ->danger()
                        ->persistent()
                        ->send();

                    throw ValidationException::withMessages([
                        'payment_amount' => 'El monto del pago no puede ser mayor al saldo restante del préstamo.',
                    ]);
                }
            });
        } catch (ValidationException $e) {
            // Errores de validación controlados (por ejemplo monto mayor)
            throw $e; // Filament sabe manejar esto y no rompe nada
        } catch (Throwable $e) {
            // Cualquier otro error inesperado (base de datos, server, etc.)

            Notification::make()
                ->title('Error inesperado')
                ->body('Ocurrió un error inesperado al procesar el pago.\n ' . $e->getMessage())
                ->danger()
                ->danger()
                ->persistent()
                ->send();

            // Opcionalmente puedes volver a lanzar el error si quieres registrar logs
            report($e);

            // Lanzamos una ValidationException genérica para no mostrar errores feos al usuario
            throw ValidationException::withMessages([
                'error' => 'Ocurrió un error inesperado al procesar el pago.',
            ]);
        }
    }
}
