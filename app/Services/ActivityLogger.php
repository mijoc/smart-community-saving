<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * Record a group activity. Visible to every user who can access the group.
     *
     *   ActivityLogger::log(
     *       groupId:    $member->groups->first()->id,
     *       type:       'member.created',
     *       description: "added member {$member->full_name}",
     *       subject:    $member,
     *       icon:       'user-plus',
     *       color:      'green',
     *       data:       ['member_no' => $member->member_no],
     *   );
     */
    public static function log(
        int $groupId,
        string $type,
        string $description,
        ?Model $subject = null,
        ?int $actorUserId = null,
        string $icon = 'activity',
        string $color = 'blue',
        array $data = [],
    ): Activity {
        return Activity::create([
            'group_id'      => $groupId,
            'actor_user_id' => $actorUserId ?? auth()->id(),
            'type'          => $type,
            'icon'          => $icon,
            'color'         => $color,
            'description'   => $description,
            'subject_type'  => $subject ? $subject::class : null,
            'subject_id'    => $subject?->getKey(),
            'data'          => $data ?: null,
        ]);
    }
}
