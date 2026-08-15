<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\User;

class LoanPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary', 'member']);
    }

    public function view(User $u, Loan $loan): bool
    {
        if ($u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary'])) return true;
        if ($u->member_id && $u->member_id === $loan->member_id) return true;
        if ($u->hasRole('member')) return $u->canAccessGroup($loan->group_id);
        return false;
    }

    public function create(User $u): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary', 'member']);
    }

    public function update(User $u, Loan $loan): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer']);
    }

    public function delete(User $u, Loan $loan): bool
    {
        if ($u->isSuperAdmin()) return true;
        if ($u->hasRole('group_admin') && in_array($loan->status, ['requested', 'rejected'])) return true;
        return false;
    }

    /** Approve / reject the loan itself */
    public function decide(User $u, Loan $loan): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer']);
    }

    /**
     * Record a repayment.
     * Staff apply immediately; members submit for approval.
     */
    public function record(User $u, Loan $loan): bool
    {
        if ($u->hasAnyRole(['super_admin', 'group_admin', 'treasurer'])) return true;
        // Member may record a repayment on their own loan
        if ($u->hasRole('member') && $u->member_id && $u->member_id === $loan->member_id) return true;
        return false;
    }

    /** Approve or reject a pending repayment */
    public function approveRepayment(User $u, Loan $loan): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer']);
    }

    /** Manually flag a disbursed/repaying loan as defaulted */
    public function markDefaulted(User $u, Loan $loan): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin'])
            && in_array($loan->status, ['disbursed', 'repaying']);
    }

    /** Write off a defaulted (or active) loan */
    public function writeOff(User $u, Loan $loan): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin'])
            && in_array($loan->status, ['disbursed', 'repaying', 'defaulted']);
    }
}
