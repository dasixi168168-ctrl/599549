<?php
declare(strict_types=1);

namespace App\Services;

class SettingsService extends Service
{
    const CACHE_KEY = 'settings_all';

    protected $settingsLoaded = false;
    protected $settingsCache = array();

    public function all($force = false)
    {
        if (!$force && $this->settingsLoaded) {
            return $this->settingsCache;
        }

        if (!$force) {
            $cached = $this->app->cache()->get(self::CACHE_KEY, null, 60);
            if (is_array($cached)) {
                $this->settingsCache = $cached;
                $this->settingsLoaded = true;

                return $cached;
            }
        }

        $rows = $this->db()->fetchAll('SELECT setting_key, setting_value, setting_group, is_public FROM settings ORDER BY setting_group ASC, setting_key ASC');
        $settings = array();

        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $this->app->cache()->put(self::CACHE_KEY, $settings);
        $this->settingsCache = $settings;
        $this->settingsLoaded = true;

        return $settings;
    }

    public function grouped($group)
    {
        return $this->db()->fetchAll('SELECT * FROM settings WHERE setting_group = :group_name ORDER BY setting_key ASC', array(
            'group_name' => $group,
        ));
    }

    public function get($key, $default = '')
    {
        $settings = $this->all();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public function setMany($group, array $items, array $publicKeys = array())
    {
        $now = $this->now();
        $existingIds = array();

        if ($items !== array()) {
            $params = array();
            $placeholders = array();
            foreach (array_keys($items) as $index => $key) {
                $paramKey = 'setting_key_' . $index;
                $placeholders[] = ':' . $paramKey;
                $params[$paramKey] = (string) $key;
            }

            $rows = $this->db()->fetchAll(
                'SELECT id, setting_key FROM settings WHERE setting_key IN (' . implode(',', $placeholders) . ')',
                $params
            );

            foreach ($rows as $row) {
                $settingKey = (string) ($row['setting_key'] ?? '');
                if ($settingKey !== '') {
                    $existingIds[$settingKey] = (int) ($row['id'] ?? 0);
                }
            }
        }

        foreach ($items as $key => $value) {
            $settingId = (int) ($existingIds[(string) $key] ?? 0);

            if ($settingId > 0) {
                $this->db()->execute('UPDATE settings SET setting_value = :setting_value, setting_group = :setting_group, is_public = :is_public, updated_at = :updated_at WHERE id = :id', array(
                    'setting_value' => (string) $value,
                    'setting_group' => $group,
                    'is_public' => in_array($key, $publicKeys, true) ? 1 : 0,
                    'updated_at' => $now,
                    'id' => $settingId,
                ));
            } else {
                $this->db()->execute('INSERT INTO settings (setting_key, setting_group, setting_value, is_public, created_at, updated_at) VALUES (:setting_key, :setting_group, :setting_value, :is_public, :created_at, :updated_at)', array(
                    'setting_key' => $key,
                    'setting_group' => $group,
                    'setting_value' => (string) $value,
                    'is_public' => in_array($key, $publicKeys, true) ? 1 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ));
            }
        }

        $this->clearCache();
    }

    public function clearCache()
    {
        $this->settingsLoaded = false;
        $this->settingsCache = array();
        $this->app->cache()->forget(self::CACHE_KEY);
    }
}
