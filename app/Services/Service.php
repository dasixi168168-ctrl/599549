<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Application;

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
}
