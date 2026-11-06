<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Media
{
    public static function disk(): string
    {
        return config('filesystems.media_disk', 'public');
    }

    public static function store(UploadedFile $file, string $folder): string
    {
        return Storage::disk(self::disk())->putFileAs($folder, $file, Str::uuid() . '.' . $file->extension());
    }

    public static function url(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::disk(self::disk())->url($path);
    }

    public static function urls(?array $paths): array
    {
        return array_values(array_filter(array_map(fn($p) => self::url($p), $paths ?? [])));
    }

    public static function delete(string | array | null $paths): void
    {
        $paths = array_values(array_filter((array) $paths, fn($p) => $p && !Str::startsWith($p, ['http://', 'https://'])));

        if ($paths) {
            Storage::disk(self::disk())->delete($paths);
        }
    }
}
