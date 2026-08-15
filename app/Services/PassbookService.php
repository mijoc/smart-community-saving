<?php

namespace App\Services;

use App\Models\PassbookEntry;
use Illuminate\Support\Facades\DB;

class PassbookService
{
    public function rebuild(?int $groupId = null): array
    {
        $query = PassbookEntry::query()
            ->select('group_id', 'member_id')
            ->groupBy('group_id', 'member_id');

        if ($groupId) $query->where('group_id', $groupId);

        $pairs = $query->get();
        $groups = $pairs->pluck('group_id')->unique();

        foreach ($pairs as $row) {
            $this->rebuildOne((int) $row->group_id, (int) $row->member_id);
        }

        return ['members' => $pairs->count(), 'groups' => $groups->count()];
    }

    protected function rebuildOne(int $groupId, int $memberId): void
    {
        DB::transaction(function () use ($groupId, $memberId) {
            $balance = 0;
            PassbookEntry::where('group_id', $groupId)
                ->where('member_id', $memberId)
                ->orderBy('entry_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->each(function (PassbookEntry $e) use (&$balance) {
                    $balance += (float) $e->credit - (float) $e->debit;
                    if ((float) $e->balance !== (float) $balance) {
                        $e->balance = $balance;
                        $e->saveQuietly();
                    }
                });
        });
    }

    public function balance(int $groupId, int $memberId): float
    {
        return (float) (PassbookEntry::where('group_id', $groupId)
            ->where('member_id', $memberId)
            ->orderByDesc('entry_date')->orderByDesc('id')
            ->value('balance') ?? 0);
    }
}
