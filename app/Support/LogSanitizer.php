<?php

namespace App\Support;

/**
 * Makes values safe and small enough to store in audit and error logs:
 * secrets are masked, base64 images become a short marker (stored image
 * paths are kept) and long strings are truncated.
 */
class LogSanitizer
{
    public const MASK = '••••••';

    private const SECRET_KEYS = [
        'current_password',
        'password',
        'password_confirmation',
        'remember_token',
        'token',
    ];

    private const MAX_STRING_LENGTH = 1000;

    public static function clean(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), self::SECRET_KEYS, true)) {
                $values[$key] = $value === null || $value === '' ? $value : self::MASK;
            } elseif ($key === 'image' && ProductImage::isDataUrl($value)) {
                $values[$key] = self::describeImage($value);
            } elseif (is_array($value)) {
                $values[$key] = self::clean($value);
            } elseif (is_string($value) && mb_strlen($value) > self::MAX_STRING_LENGTH) {
                $values[$key] = mb_substr($value, 0, self::MAX_STRING_LENGTH)
                    . '… (' . mb_strlen($value) . ' chars)';
            }
        }

        return $values;
    }

    // "[image 142 KB · a1b2c3d4]": size plus a short hash, so a changed image
    // still shows up as a different value.
    public static function describeImage(string $value): string
    {
        $kilobytes = max(1, (int) round(strlen($value) / 1024));

        return sprintf('[image %s KB · %s]', number_format($kilobytes), substr(sha1($value), 0, 8));
    }
}
