<?php

use App\Models\Group;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Group::query()->with('rules')->each(function (Group $group) {
            if (! $group->rules->where('key', 'loan_late_fee_pct')->count()) {
                $group->rules()->create([
                    'key'         => 'loan_late_fee_pct',
                    'label'       => 'Loan late fee %',
                    'value'       => '0',
                    'type'        => 'percent',
                    'description' => 'Late penalty % charged per period on overdue flat loans. Applied once per billing period elapsed since due_on. Set to 0 to disable.',
                    'is_system'   => true,
                ]);
            }
        });
    }

    public function down(): void
    {
        \DB::table('group_rules')->where('key', 'loan_late_fee_pct')->delete();
    }
};
