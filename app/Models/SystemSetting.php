<?php

namespace App\Models;

use App\Support\PublicStorage;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $row = static::where('key', $key)->first();
        self::$cache[$key] = $row ? $row->value : $default;
        return self::$cache[$key];
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        self::$cache[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset(self::$cache[$key]);
    }

    /**
     * Resolve an uploaded image without preserving an old localhost/Replit
     * hostname in the database. During cPanel uploads, public/images may be
     * used as a portable copy; Laravel storage remains the normal fallback.
     */
    public static function publicUrl(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        // Already a full external URL (e.g. ui-avatars).
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        $path = parse_url($value, PHP_URL_PATH) ?: $value;
        $path = '/'.ltrim(str_replace('\\', '/', (string) $path), '/');
        $storageMarker = '/storage/';
        $storagePosition = strpos($path, $storageMarker);

        if ($storagePosition !== false) {
            $relative = ltrim(substr($path, $storagePosition + strlen($storageMarker)), '/');

            if ($relative !== '') {
                return PublicStorage::url($relative);
            }
        }

        if (str_starts_with($path, '/images/')) {
            return PublicStorage::assetPath(ltrim($path, '/'));
        }

        return PublicStorage::url($value);
    }
}
