<?php

namespace App\Policies;

use App\Models\PaymentSchedule;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Auth;

class PaymentSchedulePolicy
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

            default:
                $canView = false;
                break;
        }

        return $canView;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PaymentSchedule $paymentSchedule): bool
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

            default:
                $canView = false;
                break;
        }

        return $canView;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PaymentSchedule $paymentSchedule): bool
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
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PaymentSchedule $paymentSchedule): bool
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
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PaymentSchedule $paymentSchedule): bool
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
    public function forceDelete(User $user, PaymentSchedule $paymentSchedule): bool
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
