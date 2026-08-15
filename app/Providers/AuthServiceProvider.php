<?php

namespace App\Providers;

use App\Models\CashbookEntry;
use App\Models\Contribution;
use App\Models\Group;
use App\Models\Loan;
use App\Models\Meeting;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Rotation;
use App\Policies\CashbookEntryPolicy;
use App\Policies\ContributionPolicy;
use App\Policies\GroupPolicy;
use App\Policies\LoanPolicy;
use App\Policies\MeetingPolicy;
use App\Policies\MemberPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\RotationPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Group::class         => GroupPolicy::class,
        Member::class        => MemberPolicy::class,
        Contribution::class  => ContributionPolicy::class,
        Loan::class          => LoanPolicy::class,
        Payment::class       => PaymentPolicy::class,
        CashbookEntry::class => CashbookEntryPolicy::class,
        Rotation::class      => RotationPolicy::class,
        Meeting::class       => MeetingPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
                return true;
            }
            return null;
        });
    }
}
