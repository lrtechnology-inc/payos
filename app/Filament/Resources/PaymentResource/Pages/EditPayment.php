<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use Carbon\Carbon;
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
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $loan = Loan::find($record->loan_id);

        if ($data['payment_amount'] != $record->payment_amount) {

            if ($data['payment_amount'] > $loan->total_amount) {

                $this->notify(
                    'Monto excede el total del préstamo',
                    'El monto del pago no puede ser mayor al monto total del préstamo.'
                );
            } elseif ($data['payment_amount'] > ($loan->remaining_amount + $record->payment_amount)) {
                $this->notify(
                    'Monto excedería el saldo pendiente',
                    'El monto del pago no puede ser mayor al saldo restante del préstamo.'
                );
            } elseif ($data['payment_amount'] > $record->payment_amount  && $data['payment_amount'] <= ($loan->remaining_amount + $record->payment_amount)) {

                //dd('aqui');

                $record = $this->updtpysch($record, $data);

                return $record;
            } elseif ($data['payment_amount'] < $record->payment_amount && $data['payment_amount'] > 0) {

                $record = $this->updtpysch($record, $data);

                return $record;
            } elseif ($data['payment_amount'] < $record->payment_amount && $data['payment_amount'] == 0) {

                try {
                    DB::transaction(function () use ($data, $record) {

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
                            ->where('payment_id', null)
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


                        //dd('va por aqui lo que falta', $loan, $pysch);
                        $record->update($data);

                        return $record;
                    });
                } catch (Throwable $e) {

                    $this->notify(
                        'Error al actualizar el pago',
                        'Ocurrió un error al actualizar el pago. Por favor, inténtelo de nuevo.'
                    );
                }
            }
        } else {
            $this->notify(
                'No se ha modificado el valor del pago',
                'El monto del pago es el mismo que el anterior.'
            );
        }

        return $record;
    }

    private function updtpysch(Model $record, array $data)
    {
        try {
            return DB::transaction(function () use ($data, $record) {

                $pyschsum = PaymentSchedule::where('payment_id', $record->id)
                    ->where('paid_amount', '!=', null)
                    ->sum('paid_amount');

                $loan = Loan::find($record->loan_id);

                $pycta = $loan->total_amount / $loan->quantity_installments;
                //dd($loan, $pycta);
                $ctas = floor($pyschsum / $pycta);

                $valback = $pyschsum - $record->payment_amount;

                if ($pyschsum === $record->payment_amount) {
                    $pyam = $data['payment_amount'];
                } else {
                    $pyam = $data['payment_amount'] + $valback;
                }

                $pysch = PaymentSchedule::where('payment_id', $record->id)
                    ->get();

                $ctacp = 0;

                //dd($pysch, $loan);

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
                    ->get();

                //dd($pysch);

                foreach ($pysch as $pyschItem) {

                    if ($pyschItem->payment_status == 'partial') {

                        $dif = $pyschItem->installment_amount - $pyschItem->paid_amount;

                        if ($dif > $pyam) {

                            $pyschItem->update([
                                'payment_status' => 'partial',
                                'payment_date' => $record->payment_date,
                                'paid_amount' => $pyschItem->paid_amount + $pyam,
                                'payment_id' => $record->id,
                            ]);

                            $pyam = 0;
                        } elseif ($dif <= $pyam) {

                            $pyschItem->update([
                                'payment_status' => 'paid',
                                'payment_date' => $record->payment_date,
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
                                'payment_date' => $record->payment_date,
                                'paid_amount' => $pyschItem->installment_amount,
                                'payment_id' => $record->id,
                            ]);

                            $pyam = $pyam - $pyschItem->installment_amount;

                            $ctacp += 1;
                        } elseif ($pyam < $pyschItem->installment_amount && $pyam > 0) {

                            $pyschItem->update([
                                'payment_status' => 'partial',
                                'payment_date' => $record->payment_date,
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
                    'remaining_amount' => $loan->remaining_amount - $data['payment_amount'],
                    'payment_count' => $loan->payment_count + $ctacp,
                    'paid_amount' => $loan->paid_amount + $data['payment_amount']
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

                $record->update($data);

                //dd($valback, $data['payment_amount'], $pyschsum, $loan, PaymentSchedule::where('loan_id', $record->loan_id)->where('payment_id', $record->id)->get(), $record);

                return $record;
            });
        } catch (Throwable $e) {

            //dd($e);

            $this->notify(
                'Error al actualizar el pago',
                'Ocurrió un error al actualizar el pago. Por favor, inténtelo de nuevo.'
            );
        }
    }

    private function notify($title, $message)
    {
        Notification::make()
            ->title($title)
            ->body($message)
            ->danger()
            ->persistent()
            ->send();

        throw ValidationException::withMessages([
            'payment_amount' => $message,
        ]);
    }
}
