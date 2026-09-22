<?php

namespace App\Support\ChatGpt;

use Illuminate\Http\Request;

/**
 * Customer contact details are only returned when the calling token carries
 * the `read-pii` ability. Everything else gets a masked value, so a normal
 * "check order 61310" answer never ships a full phone number or street
 * address to a third party.
 */
class Pii
{
    public static function allowed(?Request $request = null): bool
    {
        $user = ($request ?? request())->user();

        return $user !== null && $user->tokenCan('read-pii');
    }

    public static function phone(?string $value): ?string
    {
        if (! $value) {
            return $value;
        }

        if (self::allowed()) {
            return $value;
        }

        $digits = preg_replace('/\D/', '', $value);

        if (strlen($digits) <= 3) {
            return '***';
        }

        return str_repeat('*', strlen($digits) - 3) . substr($digits, -3);
    }

    public static function email(?string $value): ?string
    {
        if (! $value) {
            return $value;
        }

        if (self::allowed()) {
            return $value;
        }

        [$local, $domain] = array_pad(explode('@', $value, 2), 2, null);

        if (! $domain) {
            return '***';
        }

        return substr($local, 0, 1) . str_repeat('*', max(strlen($local) - 1, 1)) . '@' . $domain;
    }

    /**
     * Street lines are hidden; city/state/country stay visible because they
     * are useful for delivery questions and are not identifying on their own.
     */
    public static function street(?string $value): ?string
    {
        if (! $value) {
            return $value;
        }

        return self::allowed() ? $value : '[hidden — token lacks read-pii]';
    }
}
