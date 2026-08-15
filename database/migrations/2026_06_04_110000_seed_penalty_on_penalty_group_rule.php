<?php

use App\Models\Group;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Group::query()->with('rules')->each(function (Group $group) {
            if (! $group->rules->where('key', 'penalty_on_penalty')->count()) {
                $group->rules()->create([
                    'key'         => 'penalty_on_penalty',
                    'label'       => 'Penalty on penalty',
                    'value'       => '0',
                    'type'        => 'boolean',
                    'description' => 'When enabled, each period\'s late fee is calculated on the total outstanding amount (principal + unpaid prior fees) instead of just the original expected amount, creating a compounding penalty effect.',
                    'is_system'   => true,
                ]);
            }
        });
    }

    public function down(): void
    {
        \DB::table('group_rules')->where('key', 'penalty_on_penalty')->delete();
    }
};
