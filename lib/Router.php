<?php
class Router
{
    private $routes = [];

    public $rootFinder = '/..';

    public function __construct()
    {
        $this->routes = require __DIR__ . $this->rootFinder .'/routes.php';
    }

    /**
     * Analiza la petición HTTP actual y la traduce a un plan de acción.
     *
     * Sigue un orden de prioridad:
     * 1. Comprueba si la ruta corresponde a un script legacy.
     * 2. Si no, busca una coincidencia en las rutas MVC modernas.
     * 3. Si no encuentra nada, devuelve una ruta para un error 404.
     *
     * @return array Un array estructurado con el plan de acción.
     */
    public function getRouteInfo()
    {
        // Obtener la ruta limpia y el método de la petición.
        $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $method = $_SERVER['REQUEST_METHOD'];

        // Ignoring automatic roots to improve debugging
        // without using .htaccess redirections
        $ignoredPaths = [
            '/favicon.ico',
            '/.well-known/appspecific/com.chrome.devtools.json',
        ];

        // If the request is for one of these routes, we cut it from the root.
        if (in_array($path, $ignoredPaths)) {
            // We send an answer 404 Not Found to be correct.
            http_response_code(404); 
            // We finish the script. Nothing else will be executed. There will be no app.
            exit(); 
        }

    // --- ¡NUEVO "GUARDIA DE ASSETS"! ---
        // 1. Definimos las extensiones de archivo que consideramos "assets estáticos".
        $staticFileExtensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'ico', 'webp', 'woff', 'woff2'];        
        // 2. Extraemos la extensión de la ruta solicitada.
        $pathExtension = pathinfo($path, PATHINFO_EXTENSION);
        // 3. Si la extensión está en nuestra lista de assets...
        if (in_array(strtolower($pathExtension), $staticFileExtensions)) {
            // ...significa que el navegador pidió un asset que no se encontró físicamente.
            // En lugar de procesarlo como una ruta de la app, devolvemos un 404 y terminamos.            
            http_response_code(404);
            debug("Error 404: Asset not found", $path, false);
            exit(); 
        }

        // --- 1. COMPROBACIÓN DE RUTA LEGACY ---
        // Si la ruta empieza con '/app/' y termina en '.php', es legacy.
        if (strpos($path, '/app/') === 0 && substr($path, -4) === '.php') {
            return [
                'type'        => 'legacy_script',
                // Devolvemos la ruta del script relativa a la raíz de una capa.
                // ej: '/app/clients.php' -> 'legacy/clients.php'
                'script_path' => 'legacy' . substr($path, 4) 
            ];
        }        

        // --- 2. BÚSQUEDA EN RUTAS MODERNAS (MVC) ---
        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $routePattern => $routeInfo) {
                // Convertimos el patrón (ej: '/pedidos/ver/(\d+)') en una expresión regular.
                $regex = '#^' . $routePattern . '$#';

                if (preg_match($regex, $path, $matches)) {
                    // Eliminamos la coincidencia completa para quedarnos solo con los parámetros.
                    array_shift($matches);
                    $apiRoute = $routeInfo['api_route'] ?? false;
                    // Devolvemos la información de la ruta MVC, añadiendo los parámetros y el tipo.
                    return [
                        'type'       => 'mvc_action',
                        'controller' => $routeInfo['controller'],
                        'action'     => $routeInfo['action'],
                        'params'     => $matches,
                        'api_route'  => $apiRoute
                    ];
                }
            }
        }

        // --- 3. SI NO HAY COINCIDENCIA, ERROR 404 ---
        // Si llegamos aquí, es que no se encontró ni una ruta legacy ni una moderna.
        return [
            'type'       => 'mvc_action', // Lo tratamos como una acción MVC para que muestre una página de error.
            'controller' => 'ErrorController',
            'action'     => 'showNotFound',
            'params'     => [$path]
        ];
    }
}