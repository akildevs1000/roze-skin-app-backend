<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Simple key/value application settings store.
 */
class Setting extends Model
{
    protected $guarded = [];

    /** Read a setting value, or $default when the key is not set. */
    public static function get(string $key, $default = null)
    {
        $row = static::where('key', $key)->first();

        return $row ? $row->value : $default;
    }

    /** Create or update a setting value. */
    public static function put(string $key, $value): self
    {
        return static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
