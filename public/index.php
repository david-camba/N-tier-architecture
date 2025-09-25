<?php
// EN: /public/index.php

/**
 * =========================================================================
 *  PUNTO DE ENTRADA ÚNICO (FRONT CONTROLLER)
 * =========================================================================
 *
 * Todas las peticiones HTTP a la aplicación son dirigidas a este archivo
 * por la configuración del servidor web (.htaccess o similar).
 *
 * Sus responsabilidades son:
 *  1. Iniciar la sesión.
 *  2. Cargar la configuración específica de esta instancia.
 *  3. Configurar el entorno (como el include_path).
 *  4. Instanciar y ejecutar la aplicación principal.
 *  5. Capturar cualquier error fatal no gestionado.
 *
 */
// -------------------------------------------------------------------------
// 2. CARGAR CONFIGURACIÓN
// -------------------------------------------------------------------------
// Cargamos el archivo de configuración que vive en la raíz del proyecto.
// Este archivo define la "personalidad" de esta instalación (marca,
// jerarquía de capas, etc.).
$config = require __DIR__ . '/../config.php';

// Cargamos el router para inyectar en la App posteriormente
require_once __DIR__ . '/../lib/Router.php';
$router = new Router();   

//DEBUG FUNCTIONS
require_once __DIR__ . '/../debug.php';


// -------------------------------------------------------------------------
// 1. INICIAR SESIÓN
// -------------------------------------------------------------------------
// Hacemos que los datos de la sesión (ej. $_SESSION['user_id']) estén
// disponibles para el resto de la aplicación.
session_start();

// -------------------------------------------------------------------------
// 3. PREPARAR ENTORNO
// -------------------------------------------------------------------------
// Construimos una lista de rutas absolutas a nuestras capas (1base, 2vwgroup, etc.)
// y se la pasamos a PHP como la ruta de inclusión.
// Esto permite que `require_once 'lib/App.php'` funcione sin rutas relativas
// feas, ya que PHP buscará en todas esas carpetas.
// 3a. Construir la lista de directorios a incluir, en el orden correcto.
$includeDirs = [];
foreach ($config['layers'] as $layer) {
    $includeDirs[] = $layer['directory'];
}

// 3b. Convertir las rutas de directorio a rutas absolutas.
$absoluteIncludePaths = array_map(function($path) {
    return __DIR__ . '/../' . $path;
}, $includeDirs);

array_unshift($absoluteIncludePaths, __DIR__ . '/../'); //include root path
array_unshift($absoluteIncludePaths, __DIR__ .'/'); //include public

// 3c. Establecer el include_path.
set_include_path(implode(PATH_SEPARATOR, $absoluteIncludePaths));

// -------------------------------------------------------------------------
// 4. ARRANCAR LA APLICACIÓN
// -------------------------------------------------------------------------
// Cargamos la clase principal de nuestra aplicación.
// Gracias al include_path, PHP la encontrará en 1base/lib/
require_once __DIR__ . '/../lib/App.php';        

try {
    // Creamos una instancia de nuestra aplicación, pasándole la configuración
    // que hemos cargado. La clase App es ahora una "fábrica" genérica.
    
    $app = new App($config, $router);

    // Le damos el control. A partir de aquí, la clase App orquesta todo.
    $app->run();

} catch (Throwable $e) {
    // Esta es la última red de seguridad. Si la aplicación lanza una
    // excepción que no puede manejar, la capturamos aquí.
    // En un entorno de producción, registraríamos el error en un archivo
    // en lugar de mostrarlo en pantalla.
    error_log($e); // Registra el error completo en el log de errores de PHP.

    $errorMessage = "Critical error. Our technical team has been notified.";
    $goBack = "Go back to the homepage";

    echo '
    <div style="display:flex; flex-direction:column; justify-content:center; align-items:center; height:100vh; text-align:center;">
        <h1 style="font-size:2.5rem; margin-bottom:20px;">' . htmlspecialchars($errorMessage) . '</h1>
        <a href="/app" style="
            font-size:1.5rem;
            color:#007BFF;
            text-decoration:none;
            transition:color 0.3s ease;
        " onmouseover="this.style.color=\'#0056b3\'" onmouseout="this.style.color=\'#007BFF\'">
            ' . htmlspecialchars($goBack) . '
        </a>
    </div>';
}