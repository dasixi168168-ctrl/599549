<?php
declare(strict_types=1);

namespace App\Core;

class Csrf
{
    const SESSION_KEY = 'csrf_tokens';

    public static function token($namespace = 'default')
    {
        $tokens = Session::get(self::SESSION_KEY, array());
        if (!isset($tokens[$namespace])) {
            $tokens[$namespace] = bin2hex(random_bytes(16));
            Session::put(self::SESSION_KEY, $tokens);
        }

        return $tokens[$namespace];
    }

    public static function validate($token, $namespace = 'default')
    {
        $expected = self::token($namespace);

        return is_string($token) && hash_equals($expected, $token);
    }
}
