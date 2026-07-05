<?php
declare(strict_types=1);

namespace App\Services;

class CacheService extends Service
{
    public function path($key)
    {
        return $this->app->basePath('storage/cache/' . preg_replace('/[^A-Za-z0-9_\\-]/', '_', $key) . '.cache.php');
    }

    public function put($key, $value)
    {
        $path = $this->path($key);
        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        if (!is_dir($directory) || !is_writable($directory)) {
            return false;
        }

        $payload = array(
            'stored_at' => time(),
            'value' => $value,
        );

        $content = "<?php\nreturn " . var_export($payload, true) . ";\n";
        $tempPath = $path . '.' . getmypid() . '.' . str_replace('.', '', uniqid('', true)) . '.tmp';
        $written = @file_put_contents($tempPath, $content, LOCK_EX);
        if ($written === false) {
            return false;
        }

        return @rename($tempPath, $path);
    }

    public function get($key, $default = null, $ttl = null)
    {
        $path = $this->path($key);
        if (!is_file($path) || !is_readable($path)) {
            return $default;
        }

        $bufferLevel = ob_get_level();
        try {
            ob_start();
            $payload = @include $path;
        } catch (\Throwable $e) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            return $default;
        } catch (\Exception $e) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            return $default;
        }
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }

        if (!is_array($payload) || !array_key_exists('value', $payload)) {
            return $default;
        }

        if ($ttl !== null && isset($payload['stored_at']) && (time() - (int) $payload['stored_at']) > (int) $ttl) {
            @unlink($path);
            return $default;
        }

        return $payload['value'];
    }

    public function forget($key)
    {
        $path = $this->path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function clearAll()
    {
        $directory = $this->app->basePath('storage/cache');
        $files = glob($directory . DIRECTORY_SEPARATOR . '*');
        if ($files === false) {
            return 0;
        }

        $count = 0;
        foreach ($files as $file) {
            if (is_file($file) && @unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    public function sizeInBytes()
    {
        $directory = $this->app->basePath('storage/cache');
        $files = glob($directory . DIRECTORY_SEPARATOR . '*');
        if ($files === false) {
            return 0;
        }

        $size = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                $size += filesize($file);
            }
        }

        return $size;
    }
}
