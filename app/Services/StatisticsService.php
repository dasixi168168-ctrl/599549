<?php
declare(strict_types=1);

namespace App\Services;

class StatisticsService extends Service
{
    public function dashboardMetrics()
    {
        $cacheKey = 'stats_dashboard_metrics';
        $cached = $this->app->cache()->get($cacheKey, null, 20);
        if (is_array($cached)) {
            return $cached;
        }

        $today = date('Y-m-d');
        $metrics = array(
            'members' => (int) $this->db()->fetch('SELECT COUNT(*) AS total_count FROM users WHERE status = :status', array('status' => 'active'))['total_count'],
            'posts' => (int) $this->db()->fetch('SELECT COUNT(*) AS total_count FROM posts WHERE status = :status', array('status' => 'published'))['total_count'],
            'visits_today' => (int) $this->db()->fetch('SELECT COUNT(*) AS total_count FROM page_views WHERE viewed_on = :viewed_on', array('viewed_on' => $today))['total_count'],
            'open_threads' => $this->tableExists('customer_service_sessions')
                ? (int) $this->db()->fetch('SELECT COUNT(*) AS total_count FROM customer_service_sessions WHERE status IN (:waiting_status, :open_status)', array('waiting_status' => 'waiting', 'open_status' => 'open'))['total_count']
                : 0,
        );

        $this->app->cache()->put($cacheKey, $metrics);

        return $metrics;
    }

    public function forecastParticipationMetrics($region)
    {
        $region = in_array((string) $region, array('macau', 'hongkong'), true) ? (string) $region : 'macau';
        $cacheKey = 'stats_forecast_participation_' . $region;
        $cached = $this->app->cache()->get($cacheKey, null, 30);
        if (is_array($cached)) {
            return $cached;
        }

        $today = date('Y-m-d');
        $previousDay = date('Y-m-d', strtotime('-1 day'));
        $todayParticipants = $this->forecastParticipationTotal($region, $today, false);
        $previousParticipationDate = $this->latestForecastParticipationDateBefore($region, $today);
        $previousDate = $previousParticipationDate !== '' ? $previousParticipationDate : $previousDay;
        $previousParticipants = $this->forecastParticipationTotal($region, $previousDate, true);

        if ($previousParticipants <= 0) {
            $previousParticipants = $this->forecastPreviousParticipationFallback($region, $todayParticipants, $previousDay);
        }

        $previousWinners = $previousParticipants > 0
            ? min($previousParticipants, max(1, (int) floor($previousParticipants * 0.96)))
            : 0;

        $metrics = array(
            'today_participants' => $todayParticipants,
            'previous_participants' => $previousParticipants,
            'previous_winners' => $previousWinners,
        );

        $this->app->cache()->put($cacheKey, $metrics);

        return $metrics;
    }

    protected function tableExists($tableName)
    {
        static $tableExistsCache = array();

        $tableName = (string) $tableName;
        if (array_key_exists($tableName, $tableExistsCache)) {
            return $tableExistsCache[$tableName];
        }

        $databaseConfig = $this->app->databaseConfig();
        $databaseName = is_array($databaseConfig) ? (string) ($databaseConfig['database'] ?? '') : '';

        if ($databaseName === '') {
            $tableExistsCache[$tableName] = false;
            return false;
        }

        $row = $this->db()->fetch(
            'SELECT table_name
             FROM information_schema.tables
             WHERE table_schema = :table_schema
               AND table_name = :table_name
             LIMIT 1',
            array(
                'table_schema' => $databaseName,
                'table_name' => $tableName,
            )
        );

        $tableExistsCache[$tableName] = $row !== null;

        return $tableExistsCache[$tableName];
    }

    protected function forecastParticipationTotal($region, $date, $finalized)
    {
        $rows = $this->forecastParticipationRows($region, $date, $finalized);
        $total = count($rows);

        foreach ($rows as $row) {
            $total += $this->forecastReleasedParticipationBoost($row, $finalized);
        }

        return max(0, (int) $total);
    }

    protected function latestForecastParticipationDateBefore($region, $beforeDate)
    {
        if (!$this->tableExists('ai_prediction_participations')) {
            return '';
        }

        $beforeTimestamp = strtotime((string) $beforeDate);
        if ($beforeTimestamp === false) {
            return '';
        }

        $row = $this->db()->fetch(
            'SELECT MAX(participated_on) AS participated_on
             FROM ai_prediction_participations
             WHERE region = :region
               AND participated_on < :before_date',
            array(
                'region' => (string) $region,
                'before_date' => date('Y-m-d', $beforeTimestamp),
            )
        );

        return is_array($row) && !empty($row['participated_on']) ? (string) $row['participated_on'] : '';
    }

    protected function forecastPreviousParticipationFallback($region, $todayParticipants, $date)
    {
        $todayParticipants = (int) $todayParticipants;
        if ($todayParticipants <= 0) {
            return 0;
        }

        $seed = implode('|', array(
            (string) $region,
            (string) $date,
            (string) $todayParticipants,
            'forecast-previous-participation-fallback',
        ));
        $hash = (int) sprintf('%u', crc32($seed));
        $delta = ($hash % 5) - 2;

        return max(1, $todayParticipants + $delta);
    }

    protected function forecastParticipationRows($region, $date, $finalized)
    {
        if (!$this->tableExists('ai_prediction_participations')) {
            return array();
        }

        $dateTimestamp = strtotime((string) $date);
        if ($dateTimestamp === false) {
            return array();
        }

        $dayStart = date('Y-m-d ' . ($finalized ? '00:01:00' : '00:00:00'), $dateTimestamp);
        $dayEndTimestamp = strtotime(date('Y-m-d 23:59:59', $dateTimestamp));
        $nowTimestamp = time();
        if (!$finalized && $nowTimestamp < $dayEndTimestamp) {
            $dayEndTimestamp = $nowTimestamp;
        }
        $dayEnd = date('Y-m-d H:i:s', $dayEndTimestamp);

        return $this->db()->fetchAll(
            'SELECT id, region, actor_type, actor_key, user_id, participated_on, created_at
             FROM ai_prediction_participations
             WHERE region = :region
               AND participated_on = :participated_on
               AND created_at >= :day_start
               AND created_at <= :day_end
             ORDER BY id ASC',
            array(
                'region' => (string) $region,
                'participated_on' => date('Y-m-d', $dateTimestamp),
                'day_start' => $dayStart,
                'day_end' => $dayEnd,
            )
        );
    }

    protected function forecastReleasedParticipationBoost(array $row, $finalized)
    {
        $plannedBoost = $this->forecastParticipationBoostCount($row);
        if ($finalized) {
            return $plannedBoost;
        }

        $createdAtTimestamp = strtotime((string) ($row['created_at'] ?? ''));
        if ($createdAtTimestamp === false) {
            return 0;
        }

        $nowTimestamp = time();
        if ($nowTimestamp >= $createdAtTimestamp + 1800) {
            return $plannedBoost;
        }

        if ($plannedBoost > 200) {
            $elapsedSeconds = max(0, min(1800, $nowTimestamp - $createdAtTimestamp));

            return min($plannedBoost, (int) floor($plannedBoost * $elapsedSeconds / 1800));
        }

        $seed = implode('|', array(
            (string) ($row['region'] ?? ''),
            (string) ($row['id'] ?? ''),
            (string) ($row['actor_type'] ?? ''),
            (string) ($row['actor_key'] ?? ''),
            (string) ($row['created_at'] ?? ''),
        ));
        $released = 0;
        for ($index = 1; $index <= $plannedBoost; $index++) {
            $offsetSeconds = 1 + ((int) sprintf('%u', crc32($seed . '|boost-release|' . $index)) % 1800);
            if ($createdAtTimestamp + $offsetSeconds <= $nowTimestamp) {
                $released++;
            }
        }

        return min($plannedBoost, $released);
    }

    protected function forecastParticipationBoostCount(array $row)
    {
        try {
            $config = $this->app->admins()->forecastPricingSettings();
            if (isset($config['participation_increment'])) {
                return max(0, min(9999, (int) $config['participation_increment']));
            }
        } catch (\Throwable $exception) {
            // Keep statistics available even if settings are temporarily unreadable.
        }

        $seed = implode('|', array(
            (string) ($row['region'] ?? ''),
            (string) ($row['id'] ?? ''),
            (string) ($row['actor_type'] ?? ''),
            (string) ($row['actor_key'] ?? ''),
            (string) ($row['created_at'] ?? ''),
            'forecast-participation-boost-count',
        ));
        $hash = (int) sprintf('%u', crc32($seed));

        return 7 + ($hash % 5);
    }

    public function growthMetrics()
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $todayPosts = (int) $this->db()->fetch("SELECT COUNT(*) AS total_count FROM posts WHERE DATE(created_at) = :today AND status <> 'deleted'", array('today' => $today))['total_count'];
        $yesterdayPosts = (int) $this->db()->fetch("SELECT COUNT(*) AS total_count FROM posts WHERE DATE(created_at) = :yesterday AND status <> 'deleted'", array('yesterday' => $yesterday))['total_count'];
        $todayUsers = (int) $this->db()->fetch('SELECT COUNT(*) AS total_count FROM users WHERE DATE(created_at) = :today', array('today' => $today))['total_count'];
        $yesterdayUsers = (int) $this->db()->fetch('SELECT COUNT(*) AS total_count FROM users WHERE DATE(created_at) = :yesterday', array('yesterday' => $yesterday))['total_count'];

        return array(
            'post_growth' => $yesterdayPosts > 0 ? round((($todayPosts - $yesterdayPosts) / $yesterdayPosts) * 100, 1) : 100,
            'user_growth' => $yesterdayUsers > 0 ? round((($todayUsers - $yesterdayUsers) / $yesterdayUsers) * 100, 1) : 100,
        );
    }

    public function trafficSeries($days = 7)
    {
        $rows = $this->db()->fetchAll('SELECT viewed_on, COUNT(*) AS total_count, COUNT(DISTINCT ip_address) AS unique_visitors FROM page_views WHERE viewed_on >= DATE_SUB(CURDATE(), INTERVAL ' . (int) max(1, $days - 1) . ' DAY) GROUP BY viewed_on ORDER BY viewed_on ASC');
        $indexed = array();

        foreach ($rows as $row) {
            $indexed[$row['viewed_on']] = $row;
        }

        $series = array();

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = date('Y-m-d', strtotime('-' . $offset . ' day'));
            $row = isset($indexed[$date]) ? $indexed[$date] : array('total_count' => 0, 'unique_visitors' => 0);
            $series[] = array(
                'date' => $date,
                'views' => (int) $row['total_count'],
                'unique' => (int) $row['unique_visitors'],
            );
        }

        return $series;
    }

    public function topRoutes($limit = 6)
    {
        return $this->db()->fetchAll('SELECT route_name, COUNT(*) AS total_count FROM page_views GROUP BY route_name ORDER BY total_count DESC LIMIT ' . (int) $limit);
    }

    public function latestLoginLogs($limit = 12)
    {
        return $this->app->logs()->recentLoginLogs($limit);
    }
}
