<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Product images are uploaded as base64 data URLs and stored as files on the
 * public disk (storage/app/public/products); products.image keeps the file
 * path. Products saved before this change still hold the data URL itself,
 * which is returned unchanged until `php artisan products:move-images` moves
 * it to a file.
 */
class ProductImage
{
    public const DIRECTORY = 'products';

    public const MAX_BYTES = 5 * 1024 * 1024;

    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public static function disk(): Filesystem
    {
        return Storage::disk('public');
    }

    public static function isDataUrl(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, 'data:');
    }

    /**
     * Decodes a data URL and returns its bytes and file extension, or null
     * when it is not a supported image. The type is taken from the content,
     * not from the (client supplied) data URL header.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function decode(string $dataUrl): ?array
    {
        if (!preg_match('/^data:image\/[\w.+-]+;base64,/', $dataUrl, $match)) {
            return null;
        }

        $bytes = base64_decode(substr($dataUrl, strlen($match[0])), true);
        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            return null;
        }

        $info = @getimagesizefromstring($bytes);
        $extension = $info ? (self::EXTENSIONS[$info['mime']] ?? null) : null;

        return $extension ? [$bytes, $extension] : null;
    }

    // Saves a data URL as a new file and returns its path on the public disk.
    public static function store(string $dataUrl): string
    {
        $decoded = self::decode($dataUrl);
        if (!$decoded) {
            throw new RuntimeException('The image is not a valid JPEG, PNG, WEBP or GIF image.');
        }

        [$bytes, $extension] = $decoded;
        $path = self::DIRECTORY . '/' . Str::uuid() . '.' . $extension;

        if (!self::disk()->put($path, $bytes)) {
            throw new RuntimeException("Could not store the product image at {$path}.");
        }

        return $path;
    }

    // URL the clients use as image source; old base64 values are returned as is.
    public static function url(?string $value): ?string
    {
        if ($value === null || $value === '' || self::isDataUrl($value)) {
            return $value;
        }

        return self::disk()->url($value);
    }

    public static function delete(?string $value): void
    {
        if ($value !== null && $value !== '' && !self::isDataUrl($value)) {
            self::disk()->delete($value);
        }
    }

    // Files cannot be rolled back, so a replaced or deleted image is only
    // removed once the database change is committed.
    public static function deleteAfterCommit(?string $value): void
    {
        DB::afterCommit(fn () => self::delete($value));
    }
}
