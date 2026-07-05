<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class View
{
    public static function make(Application $app, $template, array $data = array())
    {
        $viewPath = $app->viewPath($template);
        if (!is_file($viewPath)) {
            throw new RuntimeException('View not found: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $viewPath;

        return (string) ob_get_clean();
    }

    public static function render(Application $app, $template, array $data = array(), $layout = null)
    {
        $content = self::make($app, $template, $data);
        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutPath = $app->viewPath($layout);
        if (!is_file($layoutPath)) {
            throw new RuntimeException('Layout not found: ' . $layout);
        }

        extract($data, EXTR_SKIP);
        include $layoutPath;
    }
}
