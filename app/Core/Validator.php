<?php
declare(strict_types=1);

namespace App\Core;

class Validator
{
    public static function username($value)
    {
        $value = trim((string) $value);
        if ($value === '' || mb_strlen($value, 'UTF-8') < 3 || mb_strlen($value, 'UTF-8') > 16) {
            return '用户名需为 3-16 位字符。';
        }

        if (!preg_match('/^[\\p{Han}A-Za-z0-9_]+$/u', $value)) {
            return '用户名仅支持中文、字母、数字和下划线。';
        }

        return null;
    }

    public static function password($value)
    {
        $value = trim((string) $value);
        if (!preg_match('/^[A-Za-z0-9]{6,20}$/', $value)) {
            return '密码需为 6-20 位字母或数字。';
        }

        return null;
    }

    public static function required($value, $label)
    {
        if (trim((string) $value) === '') {
            return $label . '不能为空。';
        }

        return null;
    }
}
