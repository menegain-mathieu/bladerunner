<?php

/**
 * Template Hierarchy should search for .blade.php files
 */
array_map(function ($type) {
    add_filter("{$type}_template_hierarchy", function ($templates) {
        $result = call_user_func_array('array_merge', array_map(function ($template) {
            $transforms = [
                '%^/?(templates)?/?%' => Bladerunner\Config::repo('bladerunner.disable_option_hack') ? 'templates/' : '',
                '%(\.blade)?(\.php)?$%' => ''
            ];
            $normalizedTemplate = preg_replace(array_keys($transforms), array_values($transforms), $template);

            $controllerClass = preg_replace('/[^a-z0-9]+/i', ' ', $normalizedTemplate);
            $controllerClass = trim($controllerClass);
            $controllerClass = ucwords($controllerClass);
            $controllerClass = str_replace(" ", "", $controllerClass);

            if ($normalizedTemplate == '404') {
                $controllerClass = 'NotFound';
            }

            $paths = apply_filters('bladerunner/controller/paths', []);
            if (!is_array($paths)) {
                $paths = [$paths];
            }
            $paths[] = get_stylesheet_directory() . '/App/Controllers';

            $controllerPaths = collect($paths)->unique()->toArray();
            foreach ($controllerPaths as $path) {
                if (!is_string($path)) {
                    continue;
                }

                $controllerPath = "{$path}/{$controllerClass}.php";
                if (file_exists($controllerPath)) {
                    add_filter('bladerunner/controllers/heap', function ($heap) use ($controllerPath) {
                        if (!in_array($controllerPath, $heap)) {
                            $heap[] = $controllerPath;
                        }
                        return $heap;
                    });
                }
            }

            return ["{$normalizedTemplate}.blade.php", "{$controllerClass}.php"];
        }, $templates));

        return $result;
    });
}, [
    'index',
    '404',
    'archive',
    'author',
    'category',
    'tag',
    'taxonomy',
    'date',
    'home',
    'frontpage',
    'page',
    'paged',
    'search',
    'single',
    'singular',
    'attachment'
]);

add_filter('template_include', function ($template) {
    $heap = apply_filters('bladerunner/controllers/heap', []);
    if ($heap) {
        foreach ($heap as $controllerFile) {
            require_once $controllerFile;
            $allClass = get_declared_classes();
            $nameFile = basename($controllerFile, '.php');
            $class = 'App\\Controllers\\'.$nameFile;
            if (in_array($class, $allClass)) {
                $controller = new $class();
                if (is_subclass_of($class, "\\Bladerunner\\Controller") && $controller->__getView()) {
                    $controller->__setup();
                    echo view($controller->__getView(), $controller->__getData());
                    return null;
                }
            }
        }
    }
    return $template;
}, PHP_INT_MAX);
