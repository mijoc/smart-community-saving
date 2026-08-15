<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary', 'member']);
    }

    public function view(User $u, Payment $payment): bool
    {
        if ($u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary'])) return true;
        if ($u->member_id && $u->member_id === $payment->member_id) return true;
        return false;
    }

    public function create(User $u): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary']);
    }

    /**
     * Only the super_admin can delete a recorded payment. Deleting a payment
     * reverses its effect on the linked contribution (paid_amount + status)
     * and on the member's passbook, so it's a sensitive operation reserved
     * for fixing bad data entry.
     */
    public function delete(User $u, Payment $payment): bool
    {
        return $u->isSuperAdmin();
    }
}
