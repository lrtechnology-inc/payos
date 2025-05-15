<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Auth;

class PaymentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $user = Auth::user();

        $canView = false;

        switch ($user->role->name) {

            case 'Desarrollador':
                $canView = true;
                break;
            case 'Prestamista':
                $canView = true;
                break;
            case 'Cobrador':
                $canView = true;
                break;

            default:
                $canView = false;
                break;
        }

        return $canView;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Payment $payment): bool
    {
        $user = Auth::user();

        $canView = false;

        switch ($user->role->name) {

            case 'Desarrollador':
                $canView = true;
                break;
            case 'Prestamista':
                $canView = true;
                break;
            case 'Cobrador':
                $canView = true;
                break;

            default:
                $canView = false;
                break;
        }

        return $canView;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $user = Auth::user();

        $canView = false;

        switch ($user->role->name) {

            case 'Desarrollador':
                $canView = true;
                break;
            case 'Prestamista':
                $canView = true;
                break;
            case 'Cobrador':
                $canView = true;
                break;

            default:
                $canView = false;
                break;
        }

        return $canView;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Payment $payment): bool
    {

        $user = Auth::user();

        $canView = false;

        $lastPayment = Payment::where('loan_id', $payment->loan_id)
            ->orderby('id', 'desc')
            ->first();

        //dd($lastPayment);
        switch ($user->role->name) {

            case 'Desarrollador':
                $canView = true;
                break;
            case 'Prestamista':
                $canView = $payment->id === $lastPayment->id;
                break;
            case 'Cobrador':
                $canView = $payment->id === $lastPayment->id;
                break;

            default:
                $canView = false;
                break;
        }

        return $canView;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Payment $payment): bool
    {
        $user = Auth::user();

        $canView = false;

        $lastPayment = Payment::where('loan_id', $payment->loan_id)
            ->orderby('id', 'desc')
            ->first();

        //dd($lastPayment);
        switch ($user->role->name) {

            case 'Desarrollador':
                $canView = true;
                break;
            case 'Prestamista':
                $canView = $payment->id === $lastPayment->id;
                break;
            case 'Cobrador':
                $canView = $payment->id === $lastPayment->id;
                break;

            default:
                $canView = false;
                break;
        }

        return $canView;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Payment $payment): bool
    {
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
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Payment $payment): bool
    {
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
    }
}
