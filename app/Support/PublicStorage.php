<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Store public uploads in Laravel storage and mirror them under
 * public/images so they work on XAMPP/cPanel without relying on
 * the public/storage symlink or a correct APP_URL.
 */
class PublicStorage
{
    public static function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, 'public');
        self::mirrorToPublic($path);

        return $path;
    }

    public static function delete(?string $relativePath): void
    {
        if (! filled($relativePath)) {
            return;
        }

        Storage::disk('public')->delete($relativePath);
        File::delete(public_path('images/'.$relativePath));
    }

    public static function url(?string $relativePath): ?string
    {
        if (! filled($relativePath)) {
            return null;
        }

        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        if (self::mirrorToPublic($relativePath) || is_file(public_path('images/'.$relativePath))) {
            return self::assetPath('images/'.$relativePath);
        }

        if (is_file(storage_path('app/public/'.$relativePath))) {
            return self::assetPath('storage/'.$relativePath);
        }

        return null;
    }

    public static function mirrorToPublic(string $relativePath): bool
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $source = storage_path('app/public/'.$relativePath);

        if (! is_file($source)) {
            return false;
        }

        $target = public_path('images/'.$relativePath);
        $targetDir = dirname($target);

        if (! is_dir($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        if (! is_file($target) || filemtime($source) > filemtime($target)) {
            File::copy($source, $target);
        }

        return true;
    }

    public static function assetPath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (! app()->runningInConsole() && request()->hasHeader('Host')) {
            $base = rtrim(request()->getSchemeAndHttpHost().request()->getBaseUrl(), '/');

            return $base.'/'.$path;
        }

        return asset($path);
    }
}
