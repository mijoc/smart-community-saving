<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'body', 'link', 'icon', 'color', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * Notification links may have been saved as absolute URLs by an older
     * workspace. Return only the path/query/fragment so links always stay on
     * the current Replit preview host.
     */
    public function getLinkAttribute(?string $value): ?string
    {
        if (! $value || ! parse_url($value, PHP_URL_HOST)) {
            return $value;
        }

        $parts = parse_url($value);
        $link = $parts['path'] ?? '/';

        if (! empty($parts['query'])) {
            $link .= '?'.$parts['query'];
        }

        if (! empty($parts['fragment'])) {
            $link .= '#'.$parts['fragment'];
        }

        return $link;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * Send a personal notification to one user.
     */
    public static function notify(
        int    $userId,
        string $type,
        string $title,
        string $body  = '',
        string $link  = '',
        string $icon  = 'bell',
        string $color = 'blue',
    ): static {
        return static::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'body'    => $body,
            'link'    => $link,
            'icon'    => $icon,
            'color'   => $color,
        ]);
    }

    /**
     * Send to every user that has one of the given roles inside a group.
     */
    public static function notifyGroupRoles(
        int    $groupId,
        array  $roles,
        string $type,
        string $title,
        string $body  = '',
        string $link  = '',
        string $icon  = 'bell',
        string $color = 'blue',
    ): void {
        $users = User::whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->whereHas('groups', fn ($q) => $q->where('groups.id', $groupId))
            ->pluck('id');

        foreach ($users as $uid) {
            static::create([
                'user_id' => $uid,
                'type'    => $type,
                'title'   => $title,
                'body'    => $body,
                'link'    => $link,
                'icon'    => $icon,
                'color'   => $color,
            ]);
        }
    }
}
