<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Decode a URL-safe base64 encoded numeric ID (e.g. "MTg" → 18).
     * Returns null if $value is not valid base64 or doesn't decode to a number.
     */
    protected static function decodeBase64Id(string $value): ?int
    {
        $b64 = str_replace(['-', '_'], ['+', '/'], $value);
        $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
        $decoded = base64_decode($b64, true);
        return ($decoded !== false && is_numeric(trim($decoded))) ? (int) trim($decoded) : null;
    }
}
