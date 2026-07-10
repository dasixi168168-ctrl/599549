<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Application;
use App\Core\Security;

abstract class Service
{
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    protected function db()
    {
        return $this->app->db();
    }

    protected function now()
    {
        return date('Y-m-d H:i:s');
    }

    protected function enrichLoginAreaRows(array $rows, $ipField = 'login_ip', $areaField = 'login_province')
    {
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $rows[$index][$areaField] = $this->loginAreaDisplayLabel($row[$ipField] ?? '', $row[$areaField] ?? '');
        }

        return $rows;
    }

    protected function loginAreaDisplayLabel($ipAddress, $area)
    {
        $area = trim((string) $area);
        if (!$this->loginAreaLabelIsUnknown($area)) {
            return $area;
        }

        $ipAddress = trim((string) $ipAddress);
        if ($ipAddress === '' || $ipAddress === '-') {
            return $area !== '' ? $area : '-';
        }

        $location = Security::ipLocationFromAddress($ipAddress);
        $province = trim((string) ($location['province'] ?? ''));
        $city = trim((string) ($location['city'] ?? ''));
        if (!$this->loginAreaLabelIsUnknown($province)) {
            return $province;
        }
        if (!$this->loginAreaLabelIsUnknown($city)) {
            return $city;
        }

        return filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            ? '未知地区'
            : ($area !== '' ? $area : '-');
    }

    protected function loginAreaLabelIsUnknown($label)
    {
        $value = strtolower(trim((string) $label));
        if ($value === '') {
            return true;
        }

        return in_array($value, array('未知', '未知地区', '未知省份', '未知城市', 'unknown', 'unknown province', 'unknown city', '-'), true);
    }
}
