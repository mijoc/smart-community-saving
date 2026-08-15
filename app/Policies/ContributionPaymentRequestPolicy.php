<?php

namespace App\Policies;

use App\Models\ContributionPaymentRequest;
use App\Models\User;

class ContributionPaymentRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function review(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'group_admin', 'treasurer']);
    }
}
