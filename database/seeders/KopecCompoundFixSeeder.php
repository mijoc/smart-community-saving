<?php

namespace Database\Seeders;

use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KopecCompoundFixSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(LoanService::class);

        // 1. Switch all KOPEC loans to compound model
        DB::table('loans')->where('group_id', 4)
            ->update(['interest_model' => 'compound']);
        echo "All KOPEC loans → interest_model = compound\n";

        // 2. Clear any existing accruals for KOPEC loans (rebuild from scratch)
        $loanIds = DB::table('loans')->where('group_id', 4)->pluck('id');
        DB::table('loan_interest_accruals')->whereIn('loan_id', $loanIds)->delete();
        echo "Cleared old accruals\n";

        // 3. Reset Innocent + Joel outstanding to principal only
        //    (backfill will compound correctly from disbursement)
        DB::table('loans')->where('reference', 'KP-LOAN-KP001-202604')
            ->update(['total_interest' => 0, 'total_repayable' => 1_000_000, 'outstanding' => 1_000_000]);
        DB::table('loans')->where('reference', 'KP-LOAN-KP004-202605')
            ->update(['total_interest' => 0, 'total_repayable' => 500_000, 'outstanding' => 500_000]);
        echo "Reset Innocent + Joel outstanding to principal only\n\n";

        // 4. Backfill compound accruals for loans with elapsed months
        //    (Innocent: Apr 7 → periods May, Jun;  Joel: May 1 → period Jun)
        //    Athanase + Salvator disbursed in June → first period is July (future, no accrual yet)
        $toBackfill = [
            'KP-LOAN-KP001-202604' => 'Innocent Munyantore',
            'KP-LOAN-KP004-202605' => 'Joel Uwamungu',
        ];

        foreach ($toBackfill as $ref => $name) {
            $loan = Loan::where('reference', $ref)->first();
            $n    = $svc->backfillMissingAccruals($loan);
            $loan->refresh();
            printf("  %-22s  → %d accrual(s)  outstanding: %s  interest accrued: %s\n",
                $name, $n,
                number_format($loan->outstanding),
                number_format($loan->total_interest));
        }

        echo "\n--- Accrual ledger ---\n";
        printf("  %-22s | %-7s | %10s × %-4s = %8s  →  %10s\n",
            'Member', 'Period', 'Bal.Before', 'Rate', 'Interest', 'Bal.After');
        echo '  ' . str_repeat('-', 72) . "\n";

        DB::table('loan_interest_accruals as a')
            ->join('loans as l', 'l.id', 'a.loan_id')
            ->join('members as m', 'm.id', 'l.member_id')
            ->orderBy('m.full_name')->orderBy('a.period')
            ->get(['m.full_name','a.period','a.balance_before','a.rate_pct','a.interest_amount','a.balance_after'])
            ->each(function ($r) {
                printf("  %-22s | %s | %10s × %3.0f%% = %8s  →  %10s\n",
                    $r->full_name, substr($r->period, 0, 7),
                    number_format($r->balance_before), $r->rate_pct,
                    number_format($r->interest_amount),
                    number_format($r->balance_after));
            });

        echo "\n--- Final loan state ---\n";
        DB::table('loans as l')
            ->join('members as m', 'm.id', 'l.member_id')
            ->where('l.group_id', 4)->orderBy('l.disbursed_on')
            ->get(['m.full_name','l.reference','l.interest_model','l.principal','l.total_interest','l.outstanding'])
            ->each(function ($r) {
                printf("  %-22s | %-10s | P=%11s  I=%8s  Out=%11s\n",
                    $r->full_name, $r->interest_model,
                    number_format($r->principal),
                    number_format($r->total_interest),
                    number_format($r->outstanding));
            });
    }
}
