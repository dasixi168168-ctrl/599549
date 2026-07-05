<?php
declare(strict_types=1);

namespace App\Core;

class Security
{
    public static function ipAddress()
    {
        $keys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        );
        $fallback = '';

        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $values = explode(',', (string) $_SERVER[$key]);
                foreach ($values as $raw) {
                    $ip = trim($raw);
                    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                        continue;
                    }

                    if ($fallback === '') {
                        $fallback = $ip;
                    }

                    if (!self::isPrivateIp($ip)) {
                        return $ip;
                    }
                }
            }
        }

        return $fallback !== '' ? $fallback : '127.0.0.1';
    }

    public static function userAgent()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? trim((string) $_SERVER['HTTP_USER_AGENT']) : 'unknown';
    }

    public static function provinceFromIp($ip)
    {
        $location = self::ipLocation($ip);

        return $location['province'];
    }

    public static function cityFromIp($ip)
    {
        $location = self::ipLocation($ip);

        return $location['city'];
    }

    public static function carrierFromIpAddress($ip)
    {
        return self::resolveIpCarrier($ip, false);
    }

    public static function ipLocationFromAddress($ip)
    {
        return self::resolveIpLocation($ip, false);
    }

    public static function ipLocation($ip)
    {
        return self::resolveIpLocation($ip, true);
    }

    public static function ipCarrier($ip)
    {
        return self::resolveIpCarrier($ip, true);
    }

    protected static function resolveIpLocation($ip, $allowHeaderLocation)
    {
        $ip = trim((string) $ip);
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return array('province' => '本地网络', 'city' => '本地访问');
        }

        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return array('province' => '未知省份', 'city' => '未知城市');
        }

        if (self::isPrivateIp($ip)) {
            return array('province' => '内网地址', 'city' => '内网访问');
        }

        if ($allowHeaderLocation) {
            $headerLocation = self::ipLocationFromHeaders();
            if (!self::isUnknownLocation($headerLocation['province']) || !self::isUnknownLocation($headerLocation['city'])) {
                return array(
                    'province' => self::isUnknownLocation($headerLocation['province']) ? '未知省份' : $headerLocation['province'],
                    'city' => self::isUnknownLocation($headerLocation['city']) ? '未知城市' : $headerLocation['city'],
                );
            }
        }

        $cached = self::readIpLocationCache($ip);
        if ($cached !== null) {
            return $cached;
        }

        $location = self::ipLocationFromMmdb($ip);
        if (!self::isUnknownLocation($location['province']) || !self::isUnknownLocation($location['city'])) {
            self::writeIpLocationCache($ip, $location);
        }

        return $location;
    }

    protected static function resolveIpCarrier($ip, $allowHeaderCarrier)
    {
        $ip = trim((string) $ip);
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return '本地网络';
        }

        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return '未知运营商';
        }

        if (self::isPrivateIp($ip)) {
            return '内网';
        }

        if ($allowHeaderCarrier) {
            $headerCarrier = self::carrierFromHeaders();
            if ($headerCarrier !== '') {
                return $headerCarrier;
            }
        }

        $carrier = self::carrierFromChinaIpRanges($ip);

        return $carrier !== '' ? $carrier : '未知运营商';
    }

    protected static function isPrivateIp($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        return false;
    }

    protected static function carrierFromHeaders()
    {
        foreach (array('HTTP_X_ISP', 'HTTP_X_CARRIER', 'HTTP_X_ASN_ORG', 'HTTP_CF_AS_ORGANIZATION') as $key) {
            $carrier = isset($_SERVER[$key]) ? self::normalizeCarrierName($_SERVER[$key]) : '';
            if ($carrier !== '') {
                return $carrier;
            }
        }

        return '';
    }

    protected static function normalizeCarrierName($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $lower = strtolower($value);
        if (strpos($value, '移动') !== false || strpos($lower, 'mobile') !== false || strpos($lower, 'cmcc') !== false) {
            return '移动';
        }
        if (strpos($value, '联通') !== false || strpos($lower, 'unicom') !== false || strpos($lower, 'cnc') !== false) {
            return '联通';
        }
        if (strpos($value, '电信') !== false || strpos($lower, 'telecom') !== false || strpos($lower, 'chinanet') !== false || strpos($lower, '163data') !== false) {
            return '电信';
        }
        if (strpos($value, '教育') !== false || strpos($lower, 'cernet') !== false) {
            return '教育网';
        }
        if (strpos($value, '广电') !== false || strpos($lower, 'cbtn') !== false || strpos($lower, 'broadcast') !== false) {
            return '广电';
        }

        return '';
    }

    protected static function carrierFromChinaIpRanges($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return '';
        }

        $ranges = array(
            '移动' => array(
                '36.128.0.0/10',
                '39.128.0.0/10',
                '111.0.0.0/10',
                '117.128.0.0/10',
                '120.192.0.0/10',
                '183.192.0.0/10',
                '223.64.0.0/10',
            ),
            '联通' => array(
                '27.8.0.0/13',
                '42.48.0.0/13',
                '58.240.0.0/12',
                '60.0.0.0/11',
                '61.48.0.0/13',
                '101.64.0.0/13',
                '112.64.0.0/14',
                '114.240.0.0/12',
                '123.112.0.0/12',
                '124.160.0.0/13',
                '175.0.0.0/12',
                '210.21.0.0/16',
                '220.192.0.0/12',
                '221.0.0.0/12',
            ),
            '电信' => array(
                '1.80.0.0/12',
                '14.16.0.0/12',
                '27.144.0.0/12',
                '36.96.0.0/11',
                '49.64.0.0/11',
                '58.32.0.0/11',
                '59.32.0.0/11',
                '61.128.0.0/10',
                '101.224.0.0/13',
                '106.80.0.0/12',
                '113.64.0.0/10',
                '116.224.0.0/12',
                '121.8.0.0/13',
                '125.64.0.0/11',
                '180.96.0.0/11',
                '180.128.0.0/10',
                '182.32.0.0/12',
                '183.0.0.0/10',
                '202.96.0.0/12',
                '222.64.0.0/11',
            ),
        );

        foreach ($ranges as $carrier => $cidrs) {
            foreach ($cidrs as $cidr) {
                if (self::ipv4InCidr($ip, $cidr)) {
                    return $carrier;
                }
            }
        }

        return '';
    }

    protected static function ipv4InCidr($ip, $cidr)
    {
        $parts = explode('/', (string) $cidr, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $network = $parts[0];
        $prefix = (int) $parts[1];
        if ($prefix < 0 || $prefix > 32) {
            return false;
        }

        $ipLong = sprintf('%u', ip2long($ip));
        $networkLong = sprintf('%u', ip2long($network));
        $mask = $prefix === 0 ? 0 : (0xffffffff << (32 - $prefix)) & 0xffffffff;

        return (((int) $ipLong & $mask) === ((int) $networkLong & $mask));
    }

    protected static function ipLocationFromHeaders()
    {
        $provinceKeys = array(
            'HTTP_X_GEOIP_REGION_NAME',
            'HTTP_X_REAL_IP_PROVINCE',
            'HTTP_X_FORWARDED_PROVINCE',
            'HTTP_X_REGION_NAME',
            'HTTP_CF_REGION',
        );
        $cityKeys = array(
            'HTTP_X_GEOIP_CITY',
            'HTTP_X_REAL_IP_CITY',
            'HTTP_X_FORWARDED_CITY',
            'HTTP_X_CITY_NAME',
            'HTTP_CF_CITY',
        );

        return array(
            'province' => self::firstHeaderLocationValue($provinceKeys, '未知省份'),
            'city' => self::firstHeaderLocationValue($cityKeys, '未知城市'),
        );
    }

    protected static function firstHeaderLocationValue(array $keys, $fallback)
    {
        foreach ($keys as $key) {
            $value = isset($_SERVER[$key]) ? self::normalizeLocationName($_SERVER[$key]) : '';
            if ($value !== '' && !self::isUnknownLocation($value)) {
                return $value;
            }
        }

        return $fallback;
    }

    protected static function ipLocationFromMmdb($ip)
    {
        $output = self::mmdbLookupOutput($ip);
        if ($output === '') {
            return array('province' => '未知省份', 'city' => '未知城市');
        }

        $province = self::localizedNameFromMmdbSection(self::mmdbSectionText($output, 'subdivisions'));
        $country = self::localizedNameFromMmdbSection(self::mmdbSectionText($output, 'country'));
        if ($province === '') {
            $province = $country;
        }

        $city = self::localizedNameFromMmdbSection(self::mmdbSectionText($output, 'city'));

        $province = self::normalizeLocationName($province);
        $country = self::normalizeLocationName($country);
        $city = self::normalizeLocationName($city);

        if (($province === '' || $province === '中国') && $city !== '') {
            $cityProvince = self::provinceFromChinaCity($city);
            if ($cityProvince !== '') {
                $province = $cityProvince;
            }
        }

        if (($province === '' || $province === '中国') && $country === '中国') {
            $coordinateLocation = self::chinaLocationFromMmdbOutput($output);
            if ($coordinateLocation !== null) {
                $province = $coordinateLocation['province'];
                $city = $city !== '' ? $city : $coordinateLocation['city'];
            }
        }

        if ($city === '' && $country === '中国') {
            $coordinateLocation = self::chinaLocationFromMmdbOutput($output);
            if ($coordinateLocation !== null) {
                $city = $coordinateLocation['city'];
            }
        }

        $province = self::normalizeChinaProvinceName($province);

        if ($city === '' && in_array($province, array('香港', '澳门'), true)) {
            $city = $province;
        }

        return array(
            'province' => $province !== '' ? $province : '未知省份',
            'city' => $city !== '' ? $city : '未知城市',
        );
    }

    protected static function mmdbLookupOutput($ip)
    {
        $binary = '/usr/bin/mmdblookup';
        $database = self::mmdbDatabasePath();
        if ($database === '' || !function_exists('shell_exec')) {
            return '';
        }
        if (self::pathAllowedByOpenBaseDir($binary) && (!@is_file($binary) || !@is_executable($binary))) {
            return '';
        }

        $command = escapeshellarg($binary) . ' --file ' . escapeshellarg($database) . ' --ip ' . escapeshellarg($ip) . ' 2>/dev/null';

        return (string) @shell_exec($command);
    }

    protected static function mmdbSectionText($output, $key)
    {
        if (!preg_match('/"' . preg_quote((string) $key, '/') . '"\s*:/u', $output, $matches, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $offset = $matches[0][1] + strlen($matches[0][0]);
        $bracePosition = strpos($output, '{', $offset);
        $bracketPosition = strpos($output, '[', $offset);
        if ($bracePosition === false && $bracketPosition === false) {
            return '';
        }

        if ($bracePosition === false || ($bracketPosition !== false && $bracketPosition < $bracePosition)) {
            $start = $bracketPosition;
        } else {
            $start = $bracePosition;
        }

        $depth = 0;
        $length = strlen($output);
        for ($index = $start; $index < $length; $index += 1) {
            $char = $output[$index];
            if ($char === '{' || $char === '[') {
                $depth += 1;
            } elseif ($char === '}' || $char === ']') {
                $depth -= 1;
                if ($depth === 0) {
                    return substr($output, $start, $index - $start + 1);
                }
            }
        }

        return '';
    }

    protected static function localizedNameFromMmdbSection($section)
    {
        if ($section === '') {
            return '';
        }

        foreach (array('zh-CN', 'ja', 'en') as $locale) {
            if (preg_match('/"' . preg_quote($locale, '/') . '"\s*:\s*"([^"]*)"/u', $section, $matches)) {
                $value = self::normalizeLocationName($matches[1]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    protected static function provinceFromChinaCity($city)
    {
        $city = trim((string) $city);
        if ($city === '') {
            return '';
        }

        $normalized = preg_replace('/市$/u', '', $city);
        $normalized = $normalized !== null ? $normalized : $city;
        $map = array(
            '北京' => '北京市',
            '上海' => '上海市',
            '天津' => '天津市',
            '重庆' => '重庆市',
            '石家庄' => '河北省',
            '太原' => '山西省',
            '呼和浩特' => '内蒙古自治区',
            '沈阳' => '辽宁省',
            '长春' => '吉林省',
            '哈尔滨' => '黑龙江省',
            '南京' => '江苏省',
            '杭州' => '浙江省',
            '合肥' => '安徽省',
            '福州' => '福建省',
            '南昌' => '江西省',
            '济南' => '山东省',
            '郑州' => '河南省',
            '武汉' => '湖北省',
            '长沙' => '湖南省',
            '广州' => '广东省',
            '南宁' => '广西壮族自治区',
            '海口' => '海南省',
            '成都' => '四川省',
            '贵阳' => '贵州省',
            '昆明' => '云南省',
            '拉萨' => '西藏自治区',
            '西安' => '陕西省',
            '兰州' => '甘肃省',
            '西宁' => '青海省',
            '银川' => '宁夏回族自治区',
            '乌鲁木齐' => '新疆维吾尔自治区',
            '香港' => '香港',
            '澳门' => '澳门',
        );

        return isset($map[$normalized]) ? $map[$normalized] : '';
    }

    protected static function normalizeChinaProvinceName($province)
    {
        $province = trim((string) $province);
        if ($province === '') {
            return '';
        }

        $map = array(
            '北京' => '北京市',
            '天津' => '天津市',
            '上海' => '上海市',
            '重庆' => '重庆市',
            '河北' => '河北省',
            '山西' => '山西省',
            '辽宁' => '辽宁省',
            '吉林' => '吉林省',
            '黑龙江' => '黑龙江省',
            '江苏' => '江苏省',
            '浙江' => '浙江省',
            '安徽' => '安徽省',
            '福建' => '福建省',
            '江西' => '江西省',
            '山东' => '山东省',
            '河南' => '河南省',
            '湖北' => '湖北省',
            '湖南' => '湖南省',
            '广东' => '广东省',
            '海南' => '海南省',
            '四川' => '四川省',
            '贵州' => '贵州省',
            '云南' => '云南省',
            '陕西' => '陕西省',
            '甘肃' => '甘肃省',
            '青海' => '青海省',
            '台湾' => '台湾',
            '内蒙古' => '内蒙古自治区',
            '广西' => '广西壮族自治区',
            '西藏' => '西藏自治区',
            '宁夏' => '宁夏回族自治区',
            '新疆' => '新疆维吾尔自治区',
        );

        return isset($map[$province]) ? $map[$province] : $province;
    }

    protected static function chinaLocationFromMmdbOutput($output)
    {
        if (!preg_match('/"latitude"\s*:\s*([-0-9.]+)/u', $output, $latitudeMatch)
            || !preg_match('/"longitude"\s*:\s*([-0-9.]+)/u', $output, $longitudeMatch)) {
            return null;
        }

        $latitude = (float) $latitudeMatch[1];
        $longitude = (float) $longitudeMatch[1];
        if ($latitude < 18 || $latitude > 54 || $longitude < 73 || $longitude > 135) {
            return null;
        }

        $locations = array(
            array('province' => '北京市', 'city' => '北京', 'lat' => 39.9042, 'lng' => 116.4074),
            array('province' => '天津市', 'city' => '天津', 'lat' => 39.3434, 'lng' => 117.3616),
            array('province' => '河北省', 'city' => '石家庄', 'lat' => 38.0428, 'lng' => 114.5149),
            array('province' => '山西省', 'city' => '太原', 'lat' => 37.8706, 'lng' => 112.5489),
            array('province' => '内蒙古自治区', 'city' => '呼和浩特', 'lat' => 40.8426, 'lng' => 111.7492),
            array('province' => '辽宁省', 'city' => '沈阳', 'lat' => 41.8057, 'lng' => 123.4315),
            array('province' => '吉林省', 'city' => '长春', 'lat' => 43.8171, 'lng' => 125.3235),
            array('province' => '黑龙江省', 'city' => '哈尔滨', 'lat' => 45.8038, 'lng' => 126.5349),
            array('province' => '上海市', 'city' => '上海', 'lat' => 31.2304, 'lng' => 121.4737),
            array('province' => '江苏省', 'city' => '南京', 'lat' => 32.0603, 'lng' => 118.7969),
            array('province' => '浙江省', 'city' => '杭州', 'lat' => 30.2741, 'lng' => 120.1551),
            array('province' => '安徽省', 'city' => '合肥', 'lat' => 31.8206, 'lng' => 117.2272),
            array('province' => '福建省', 'city' => '福州', 'lat' => 26.0745, 'lng' => 119.2965),
            array('province' => '江西省', 'city' => '南昌', 'lat' => 28.6829, 'lng' => 115.8582),
            array('province' => '山东省', 'city' => '济南', 'lat' => 36.6512, 'lng' => 117.1201),
            array('province' => '河南省', 'city' => '郑州', 'lat' => 34.7466, 'lng' => 113.6254),
            array('province' => '湖北省', 'city' => '武汉', 'lat' => 30.5928, 'lng' => 114.3055),
            array('province' => '湖南省', 'city' => '长沙', 'lat' => 28.2282, 'lng' => 112.9388),
            array('province' => '广东省', 'city' => '广州', 'lat' => 23.1291, 'lng' => 113.2644),
            array('province' => '广西壮族自治区', 'city' => '南宁', 'lat' => 22.8170, 'lng' => 108.3669),
            array('province' => '海南省', 'city' => '海口', 'lat' => 20.0440, 'lng' => 110.1999),
            array('province' => '重庆市', 'city' => '重庆', 'lat' => 29.5630, 'lng' => 106.5516),
            array('province' => '四川省', 'city' => '成都', 'lat' => 30.5728, 'lng' => 104.0668),
            array('province' => '贵州省', 'city' => '贵阳', 'lat' => 26.6470, 'lng' => 106.6302),
            array('province' => '云南省', 'city' => '昆明', 'lat' => 25.0389, 'lng' => 102.7183),
            array('province' => '西藏自治区', 'city' => '拉萨', 'lat' => 29.6500, 'lng' => 91.1000),
            array('province' => '陕西省', 'city' => '西安', 'lat' => 34.3416, 'lng' => 108.9398),
            array('province' => '甘肃省', 'city' => '兰州', 'lat' => 36.0611, 'lng' => 103.8343),
            array('province' => '青海省', 'city' => '西宁', 'lat' => 36.6171, 'lng' => 101.7782),
            array('province' => '宁夏回族自治区', 'city' => '银川', 'lat' => 38.4872, 'lng' => 106.2309),
            array('province' => '新疆维吾尔自治区', 'city' => '乌鲁木齐', 'lat' => 43.8256, 'lng' => 87.6168),
            array('province' => '香港', 'city' => '香港', 'lat' => 22.3193, 'lng' => 114.1694),
            array('province' => '澳门', 'city' => '澳门', 'lat' => 22.1987, 'lng' => 113.5439),
        );

        $best = null;
        $bestDistance = null;
        foreach ($locations as $location) {
            $latDistance = $latitude - (float) $location['lat'];
            $lngDistance = ($longitude - (float) $location['lng']) * cos(deg2rad($latitude));
            $distance = ($latDistance * $latDistance) + ($lngDistance * $lngDistance);
            if ($bestDistance === null || $distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $location;
            }
        }

        return $best ? array('province' => $best['province'], 'city' => $best['city']) : null;
    }

    protected static function mmdbDatabasePath()
    {
        $root = dirname(dirname(__DIR__));
        $paths = array(
            $root . '/storage/geoip/GeoLite2-City.mmdb',
            $root . '/storage/GeoLite2-City.mmdb',
            '/tmp/GeoLite2-City.mmdb',
            '/usr/share/GeoIP/GeoLite2-City.mmdb',
            '/www/server/panel/config/GeoLite2-City.mmdb',
        );

        foreach ($paths as $path) {
            if (!self::pathAllowedByOpenBaseDir($path)) {
                continue;
            }
            if (@is_file($path) && @is_readable($path)) {
                return $path;
            }
        }

        return '';
    }

    protected static function pathAllowedByOpenBaseDir($path)
    {
        $openBaseDir = trim((string) ini_get('open_basedir'));
        $path = str_replace('\\', '/', (string) $path);

        if ($openBaseDir === '' || $path === '') {
            return true;
        }

        if ($path[0] !== '/') {
            $path = rtrim(str_replace('\\', '/', (string) getcwd()), '/') . '/' . ltrim($path, '/');
        }
        $path = preg_replace('#/+#', '/', $path);

        foreach (explode(PATH_SEPARATOR, $openBaseDir) as $base) {
            $base = trim((string) $base);
            if ($base === '') {
                continue;
            }
            if ($base === '.') {
                $base = (string) getcwd();
            }
            $base = preg_replace('#/+#', '/', str_replace('\\', '/', $base));
            $base = rtrim($base, '/');
            if ($base === '') {
                $base = '/';
            }

            if ($path === $base || strpos($path, rtrim($base, '/') . '/') === 0) {
                return true;
            }
        }

        return false;
    }

    protected static function normalizeLocationName($value)
    {
        $value = trim((string) $value);
        if ($value === '' || self::isUnknownLocation($value)) {
            return '';
        }

        $map = array(
            'Hong Kong' => '香港',
            'Macao' => '澳门',
            'Macau' => '澳门',
            'China' => '中国',
            'Taiwan' => '台湾',
        );

        return isset($map[$value]) ? $map[$value] : $value;
    }

    protected static function isUnknownLocation($value)
    {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return true;
        }

        $unknownValues = array(
            'unknown',
            'unknown province',
            'unknown city',
            '未知',
            '未知地区',
            '未知省份',
            '未知城市',
            '-',
        );

        return in_array($value, $unknownValues, true);
    }

    protected static function readIpLocationCache($ip)
    {
        $path = self::ipLocationCachePath($ip);
        if (!is_file($path) || (time() - filemtime($path)) > 2592000) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            return null;
        }

        $province = isset($data['province']) ? trim((string) $data['province']) : '';
        $city = isset($data['city']) ? trim((string) $data['city']) : '';
        if (self::isUnknownLocation($province) && self::isUnknownLocation($city)) {
            return null;
        }

        return array(
            'province' => $province !== '' ? $province : '未知省份',
            'city' => $city !== '' ? $city : '未知城市',
        );
    }

    protected static function writeIpLocationCache($ip, array $location)
    {
        $path = self::ipLocationCachePath($ip);
        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        @file_put_contents($path, json_encode($location, JSON_UNESCAPED_UNICODE));
    }

    protected static function ipLocationCachePath($ip)
    {
        return dirname(__DIR__, 2) . '/storage/cache/ip_location/' . md5($ip) . '.json';
    }

    public static function rateLimit($app, $bucket, $maxAttempts, $windowSeconds)
    {
        $cache = $app->basePath('storage/cache/ratelimit_' . md5($bucket) . '.json');
        $now = time();
        $attempts = array();

        if (is_file($cache)) {
            $raw = json_decode((string) file_get_contents($cache), true);
            if (is_array($raw)) {
                $attempts = $raw;
            }
        }

        $attempts[] = $now;
        $attempts = array_values(array_filter($attempts, function ($value) use ($now, $windowSeconds) {
            return ($now - (int) $value) <= $windowSeconds;
        }));

        $directory = dirname($cache);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($cache, json_encode($attempts));

        return count($attempts) <= $maxAttempts;
    }

    public static function clearRateLimit($app, $bucket)
    {
        $cache = $app->basePath('storage/cache/ratelimit_' . md5($bucket) . '.json');
        if (is_file($cache)) {
            @unlink($cache);
        }
    }
}
