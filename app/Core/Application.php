<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\CacheService;
use App\Services\AdminService;
use App\Services\InstallService;
use App\Services\LogService;
use App\Services\PostService;
use App\Services\PredictionService;
use App\Services\SettingsService;
use App\Services\StatisticsService;
use App\Services\SupportService;
use App\Services\UploadService;
use App\Services\UserService;
use RuntimeException;

class Application
{
    protected static $instance;
    protected $basePath;
    protected $config = array();
    protected $instances = array();
    protected $currentUser;
    protected $installing = false;
    protected $databaseConfigLoaded = false;
    protected $databaseConfigMtime = null;

    public function __construct($basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->loadConfig();
        Session::start();
        static::$instance = $this;
    }

    public static function getInstance()
    {
        if (!static::$instance instanceof self) {
            throw new RuntimeException('Application has not been bootstrapped.');
        }

        return static::$instance;
    }

    protected function loadConfig()
    {
        $appConfig = $this->basePath('config/app.php');
        $this->config['app'] = is_file($appConfig) ? require $appConfig : array();
        $this->config['database'] = $this->databaseConfig();
    }

    public function basePath($path = '')
    {
        return $this->basePath . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }

    public function config($group = null, $key = null, $default = null)
    {
        if ($group === null) {
            return $this->config;
        }

        if (!isset($this->config[$group])) {
            return $default;
        }

        if ($key === null) {
            return $this->config[$group];
        }

        return array_key_exists($key, $this->config[$group]) ? $this->config[$group][$key] : $default;
    }

    public function databaseConfig()
    {
        $configFile = $this->basePath('config/database.php');
        if (!is_file($configFile)) {
            $this->config['database'] = null;
            $this->databaseConfigLoaded = true;
            $this->databaseConfigMtime = null;

            return null;
        }

        $mtime = @filemtime($configFile);
        $mtime = $mtime === false ? 0 : (int) $mtime;
        if ($this->databaseConfigLoaded && $this->databaseConfigMtime === $mtime) {
            return $this->config['database'] ?? null;
        }

        $config = require $configFile;
        $this->config['database'] = $config;
        $this->databaseConfigLoaded = true;
        $this->databaseConfigMtime = $mtime;

        return $config;
    }

    public function isInstalled()
    {
        return is_file($this->basePath('storage/install.lock')) && is_array($this->databaseConfig());
    }

    public function db()
    {
        if (!$this->isInstalled() && !$this->installing) {
            throw new RuntimeException('Application is not installed.');
        }

        if (!isset($this->instances['db'])) {
            $this->instances['db'] = new Database($this->databaseConfig());
        }

        return $this->instances['db'];
    }

    public function useDatabaseConfig(array $config)
    {
        $this->installing = true;
        $this->config['database'] = $config;
        $this->databaseConfigLoaded = true;
        $configFile = $this->basePath('config/database.php');
        $mtime = is_file($configFile) ? @filemtime($configFile) : false;
        $this->databaseConfigMtime = $mtime === false ? null : (int) $mtime;
        $this->instances['db'] = new Database($config);
    }

    public function make($name)
    {
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        switch ($name) {
            case 'auth':
                $this->instances[$name] = new Auth($this);
                break;
            case 'cache':
                $this->instances[$name] = new CacheService($this);
                break;
            case 'support':
                $this->instances[$name] = new SupportService($this);
                break;
            case 'admins':
                $this->instances[$name] = new AdminService($this);
                break;
            case 'install':
                $this->instances[$name] = new InstallService($this);
                break;
            case 'logs':
                $this->instances[$name] = new LogService($this);
                break;
            case 'posts':
                $this->instances[$name] = new PostService($this);
                break;
            case 'prediction':
                $this->instances[$name] = new PredictionService($this);
                break;
            case 'settings':
                $this->instances[$name] = new SettingsService($this);
                break;
            case 'stats':
                $this->instances[$name] = new StatisticsService($this);
                break;
            case 'uploads':
                $this->instances[$name] = new UploadService($this);
                break;
            case 'users':
                $this->instances[$name] = new UserService($this);
                break;
            default:
                throw new RuntimeException('Unknown service [' . $name . '].');
        }

        return $this->instances[$name];
    }

    public function auth()
    {
        return $this->make('auth');
    }

    public function cache()
    {
        return $this->make('cache');
    }

    public function settings()
    {
        return $this->make('settings');
    }

    public function admins()
    {
        return $this->make('admins');
    }

    public function users()
    {
        return $this->make('users');
    }

    public function uploads()
    {
        return $this->make('uploads');
    }

    public function posts()
    {
        return $this->make('posts');
    }

    public function support()
    {
        return $this->make('support');
    }

    public function stats()
    {
        return $this->make('stats');
    }

    public function logs()
    {
        return $this->make('logs');
    }

    public function prediction()
    {
        return $this->make('prediction');
    }

    public function install()
    {
        return $this->make('install');
    }

    public function currentUser()
    {
        return $this->auth()->user();
    }

    public function viewPath($template)
    {
        return $this->basePath('app/Views/' . $template . '.php');
    }
}
