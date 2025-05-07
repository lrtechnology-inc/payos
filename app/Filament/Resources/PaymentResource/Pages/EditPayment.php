<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $loan = Loan::find($record->loan_id);

        if ($data['payment_amount'] > $loan->total_amount) {
            Notification::make()
                ->title('El monto del pago excede el saldo total del préstamo')
                ->body('El monto del pago no puede ser mayor al saldo total del préstamo.')
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'payment_amount' => 'El monto del pago no puede ser mayor al saldo total del préstamo.',
            ]);
        } elseif ($data['payment_amount'] < $loan->total_amount && $data['payment_amount'] != $record->payment_amount) {

            try {
                return DB::transaction(function () use ($data, $record) {

                    $loan = Loan::find($data['loan_id']);

                    if ($data['payment_amount'] > $record->payment_amount && ($data['payment_amount'] - $record->payment_amount) <= $loan->remaining_amount) {

                        $lstpy = $record->payment_amount;

                        $pyschsum = PaymentSchedule::where('payment_id', $record->id)
                            ->whereIn('payment_status', ['paid', 'partial'])->sum('paid_amount');

                        $partAmnt = $pyschsum - $lstpy;

                        $pycta = PaymentSchedule::where('payment_id', $record->id)
                            ->first();

                        $estimado = $pyschsum - ($pyschsum % $pycta->installment_amount) + $record->payment_amount - (floor($pyschsum / $pycta->installment_amount) * $pycta->installment_amount);

                        $lstpymt = Payment::where('loan_id', $record->loan_id)
                            ->where('id', '!=', $record->id)
                            ->orderby('id', 'desc')
                            ->first();

                        $pycta->update([
                            'payment_status' => 'partial',
                            'payment_date' => $lstpymt->payment_date,
                            'paid_amount' => $estimado,
                            'payment_id' => $lstpymt->id
                        ]);

                        PaymentSchedule::where('payment_id', $record->id)
                            ->update([
                                'payment_status' => 'pending',
                                'payment_date' => null,
                                'paid_amount' => null,
                                'payment_id' => null
                            ]);

                        $pysch = PaymentSchedule::where('loan_id', $record->loan_id)
                            ->where('payment_status', '!=', 'paid')
                            ->orderBy('due_date', 'asc')
                            ->get();

                        $pyam = $data['payment_amount'];

                        $ctacp = 0;

                        foreach ($pysch as $pyschItem) {

                            if ($pyam >= $pyschItem->installment_amount) {

                                if ($pyschItem->payment_status == 'partial') {

                                    $dif = $pyschItem->installment_amount - $pyschItem->paid_amount;

                                    $pyschItem->update([
                                        'payment_status' => 'paid',
                                        'payment_date' => $data['payment_date'],
                                        'paid_amount' => $pyschItem->installment_amount,
                                        'payment_id' => $record->id,
                                    ]);

                                    $pyam = $pyam - $dif;

                                    $ctacp += 1;
                                } else {

                                    $pyschItem->update([
                                        'payment_status' => 'paid',
                                        'payment_date' => $data['payment_date'],
                                        'paid_amount' => $pyschItem->installment_amount,
                                        'payment_id' => $record->id,
                                    ]);

                                    $pyam = $pyam - $pyschItem->installment_amount;

                                    $ctacp += 1;
                                }
                            } elseif ($pyam < $pyschItem->installment_amount && $pyam > 0) {

                                $pyschItem->update([
                                    'payment_status' => 'partial',
                                    'payment_date' => $data['payment_date'],
                                    'paid_amount' => $pyam,
                                    'payment_id' => $record->id,
                                ]);

                                $pyam = 0;
                            }
                        }

                        $loan->update([
                            'payment_count' => $loan->payment_count + $ctacp - floor($pyschsum / $pycta->installment_amount),
                            'paid_amount' => $loan->paid_amount + $data['payment_amount'] - $record->payment_amount,
                            'remaining_amount' => $loan->remaining_amount - $data['payment_amount'] + $record->payment_amount,
                            'last_payment_date' => $data['payment_date']
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

                        $npysch = PaymentSchedule::where('loan_id', $data['loan_id'])
                            ->where('payment_status', '!=', 'paid')
                            ->orderBy('due_date', 'asc')
                            ->get();

                        //dd($loan, $npysch);

                        $record->update($data);
                    } elseif ($data['payment_amount'] < $record->payment_amount && $data['payment_amount'] > 0) {

                        $lstpy = $record->payment_amount;

                        $pyschsum = PaymentSchedule::where('payment_id', $record->id)
                            ->whereIn('payment_status', ['paid', 'partial'])->sum('paid_amount');

                        $partAmnt = $pyschsum - $lstpy;

                        if ($pyschsum > $lstpy) {

                            $pgs = Payment::where('loan_id', $record->loan_id)
                                ->where('id', '!=', $record->id)
                                ->orderby('id', 'desc')
                                ->first();

                            $pyschpro = PaymentSchedule::where('payment_id', $record->id)
                                ->orderby('id', 'asc')->first();

                            $pyschpro->update([
                                'payment_status' => 'partial',
                                'payment_date' => $pgs->payment_date,
                                'paid_amount' => $partAmnt,
                                'payment_id' => $pgs->id
                            ]);

                            PaymentSchedule::where('payment_id', $record->id)
                                ->update([
                                    'payment_status' => 'pending',
                                    'payment_date' => null,
                                    'paid_amount' => null,
                                    'payment_id' => null
                                ]);

                            $pysch = PaymentSchedule::where('loan_id', $record->loan_id)
                                ->where('payment_status', '!=', 'paid')
                                ->distinct('id')->count();

                            $nxtdt = PaymentSchedule::where('loan_id', $record->loan_id)
                                ->where('payment_status', '!=', 'paid')
                                ->orderBy('due_date', 'asc')->first();

                            $loan->update([
                                'payment_count' => $loan->quantity_installments - $pysch,
                                'paid_amount' => $loan->paid_amount - $record->payment_amount,
                                'remaining_amount' => $loan->remaining_amount + $record->payment_amount,
                                'last_payment_date' => $pgs->payment_date,
                                'next_payment_date' => $nxtdt->due_date
                            ]);

                            $pysch = PaymentSchedule::where('loan_id', $record->loan_id)
                                ->where('payment_status', '!=', 'paid')
                                ->orderBy('due_date', 'asc')->get();

                            $pyam = $data['payment_amount'];

                            $ctacp = 0;

                            foreach ($pysch as $pyschItem) {

                                if ($pyschItem->payment_status == 'partial') {

                                    $dif = $pyschItem->installment_amount - $pyschItem->paid_amount;

                                    //dd('entro', $dif, $pyam);

                                    if ($dif > $pyam) {

                                        $pyschItem->update([
                                            'payment_status' => 'partial',
                                            'payment_date' => $data['payment_date'],
                                            'paid_amount' => $pyschItem->paid_amount + $pyam,
                                            'payment_id' => $record->id,
                                        ]);

                                        $pyam = 0;
                                    } elseif ($dif <= $pyam) {

                                        $pyschItem->update([
                                            'payment_status' => 'paid',
                                            'payment_date' => $data['payment_date'],
                                            'paid_amount' => $pyschItem->installment_amount,
                                            'payment_id' => $record->id,
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
                                            'payment_id' => $record->id,
                                        ]);

                                        $pyam = $pyam - $pyschItem->installment_amount;

                                        $ctacp += 1;
                                    } elseif ($pyam < $pyschItem->installment_amount && $pyam > 0) {

                                        $pyschItem->update([
                                            'payment_status' => 'partial',
                                            'payment_date' => $data['payment_date'],
                                            'paid_amount' => $pyam,
                                            'payment_id' => $record->id,
                                        ]);

                                        $pyam = 0;
                                    }

                                    if ($pyam == 0) {
                                        break;
                                    }
                                }
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

                            $record->update($data);
                        } else {

                            $cntcpan = PaymentSchedule::where('payment_id', $record->id)
                                ->where('payment_status', '!=', 'paid')->count();


                            PaymentSchedule::where('payment_id', $record->id)
                                ->update([
                                    'payment_status' => 'pending',
                                    'payment_date' => null,
                                    'paid_amount' => null,
                                    'payment_id' => null
                                ]);

                            $npysch = PaymentSchedule::where('loan_id', $record->loan_id)
                                ->where('payment_status', '!=', 'paid')
                                ->orderBy('due_date', 'asc')
                                ->get();

                            $loan->update([
                                'payment_count' => $loan->payment_count - $cntcpan,
                                'paid_amount' => $loan->paid_amount - $record->payment_amount,
                                'remaining_amount' => $loan->remaining_amount + $record->payment_amount,
                            ]);

                            $pyam = $data['payment_amount'];

                            $ctacp = 0;

                            foreach ($npysch as $pyschItem) {

                                if ($pyam >= $pyschItem->installment_amount) {

                                    $pyschItem->update([
                                        'payment_status' => 'paid',
                                        'payment_date' => $data['payment_date'],
                                        'paid_amount' => $pyschItem->installment_amount,
                                        'payment_id' => $record->id,
                                    ]);

                                    $pyam = $pyam - $pyschItem->installment_amount;

                                    $ctacp += 1;
                                } elseif ($pyam < $pyschItem->installment_amount && $pyam > 0) {

                                    $pyschItem->update([
                                        'payment_status' => 'partial',
                                        'payment_date' => $data['payment_date'],
                                        'paid_amount' => $pyam,
                                        'payment_id' => $record->id,
                                    ]);

                                    $pyam = 0;
                                }

                                if ($pyam == 0) {
                                    break;
                                }
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

                            $record->update($data);
                        }
                    } elseif ($data['payment_amount'] == 0) {
                    }

                    $record->update($data);

                    return $record;
                });
            } catch (Throwable $e) {
                Notification::make()
                    ->title('Error al actualizar el pago')
                    ->body('Ocurrió un error al actualizar el pago. Por favor, inténtelo de nuevo.')
                    ->danger()
                    ->persistent()
                    ->send();

                throw ValidationException::withMessages([
                    'payment_amount' => 'Ocurrió un error al actualizar el pago. Por favor, inténtelo de nuevo.',
                ]);
            }
        } elseif ($data['payment_amount'] === $record->payment_amount) {
            Notification::make()
                ->title('No se ha modificado el valor del pago')
                ->body('El monto del pago el es mismo que el anterior.')
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'payment_amount' => 'El monto del pago el es mismo que el anterior.',
            ]);
        }

        return $record;
    }
}
