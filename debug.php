<?php
define('DEBUG_ON', true);
define('DEBUG_PANEL', true);
define('DEBUG_VIEWS_CACHE', false); //false for debugging

if(!defined(DEBUG_ON) || !DEBUG_ON ||!defined(DEBUG_PANEL) || !DEBUG_PANEL){
    $prepareDebugPanel = 
        function() use ($config){
            //if (!defined('DEBUG_PANEL') && !DEBUG_PANEL) return;

            //define('CONFIG', $config);

            $url = $_SERVER['REQUEST_URI'];
            
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if($referer !== ''){
                $queryParams = [];
                $queryString = parse_url($referer, PHP_URL_QUERY);
                if ($queryString) {
                    parse_str($queryString, $queryParams);
                }
            }            

            $fixedUserLayer = $_GET['user_layer'] ?? '';
            if($fixedUserLayer === '' && isset($queryParams['user_layer'])){
                $fixedUserLayer = $queryParams['user_layer'];
            }

            if($fixedUserLayer && $fixedUserLayer != "auto"){
                define('FIXED_USER_LAYER', $fixedUserLayer);
            }

            $fixedUserLevel = $_GET['user_level'] ?? ''; 
            if($fixedUserLevel === '' && isset($queryParams['user_level'])){
                $fixedUserLevel = $queryParams['user_level'];
            }
            if($fixedUserLevel && $fixedUserLevel != "auto"){
                define('FIXED_USER_LEVEL', $fixedUserLevel);
            }
            
            return ["fixedUserLayer"=>$fixedUserLayer, "fixedUserLevel"=>$fixedUserLevel];
        };
    $layerLevelFixed = $prepareDebugPanel();

    /**
     * Renderiza un panel de depuración en la parte inferior de la página
     * que permite cambiar el layerLevel y userLevel para la petición actual.
     *
     * Lee la configuración de capas y roles del config global.
     */
    //function renderDebugPanel()

    $GLOBALS['renderDebugPanel'] = 
    function() use ($config, $layerLevelFixed){
        // Si el modo debug no está activado en la configuración, no hacemos nada.
        //if (!defined('DEBUG_ON') || !DEBUG_ON) return;
        // Obtenemos los valores actuales de la URL para pre-seleccionarlos.

        $currentUserLayer = $layerLevelFixed['fixedUserLayer'] ?? '';
        $currentUserLevel = $layerLevelFixed['fixedUserLevel'] ?? '';      
        
        // --- INICIO DEL BLOQUE HEREDOC PARA HTML Y CSS ---
        // Heredoc (<<<HTML) es una forma limpia de escribir bloques largos de HTML/CSS en PHP.
        echo <<<HTML
        <div id="debug-panel-container" style="position:fixed; bottom:10px; right:10px; z-index:9999; font-family: Arial, sans-serif; font-size: 14px; background: rgba(0,0,0,0.8); color: white; padding: 15px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.5);">
            <strong style="display: block; margin-bottom: 10px; border-bottom: 1px solid #444; padding-bottom: 5px;">Debug Panel</strong>
            
            <form id="debug-form" style="display: flex; align-items: center; gap: 15px;">
                <div>
                    <label for="debug_user_layer" style="display: block; font-size: 12px; margin-bottom: 5px;">Layer Level:</label>
                    <select id="debug_user_layer" name="user_layer" style="padding: 5px;">
                        <option value="auto">-- Auto --</option>
    HTML;

        // --- GENERAR OPCIONES PARA LAYER LEVEL DINÁMICAMENTE ---
        foreach ($config['layers'] as $layerKey => $layerInfo) {
            $layer = $layerInfo['layer'];
            $name = ucfirst($layerKey);
            // Marcamos como 'selected' si coincide con el valor actual de la URL.
            $selected = ($currentUserLayer !== '' && $currentUserLayer == $layer) ? 'selected' : '';
            echo "<option value=\"{$layer}\" {$selected}>{$layer} - {$name}</option>";
        }

        echo <<<HTML
                    </select>
                </div>

                <div>
                    <label for="debug_user_level" style="display: block; font-size: 12px; margin-bottom: 5px;">User Level:</label>
                    <select id="debug_user_level" name="user_level" style="padding: 5px;">
                        <option value="auto">-- Auto --</option>
    HTML;

        // --- GENERAR OPCIONES PARA USER LEVEL DINÁMICAMENTE ---
        // Invertimos y ordenamos para que los roles se muestren de mayor a menor.
        $roles = $config['user_roles'];
        krsort($roles); // Ordena por clave (nivel) de mayor a menor
        foreach ($roles as $level => $name) {
            $selected = ($currentUserLevel !== '' && $currentUserLevel == $level) ? 'selected' : '';
            echo "<option value=\"{$level}\" {$selected}>{$level} - {$name}</option>";
        }
        // Añadimos una opción para "Invitado"
        $selectedGuest = ($currentUserLevel === '0') ? 'selected' : '';
        echo "<option value=\"0\" {$selectedGuest}>0 - Guest</option>";


        echo <<<HTML
                    </select>
                </div>
                
                <button type="submit" style="padding: 8px 12px; margin-top: 20px; cursor: pointer;">Set</button>
            </form>
        </div>

        <script>
            // Pequeño script para manejar el envío del formulario.
            document.getElementById('debug-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const layerLevel = document.getElementById('debug_user_layer').value;
                const userLevel = document.getElementById('debug_user_level').value;
                
                // Construimos una nueva URL a partir de la actual.
                const currentUrl = new URL(window.location.href);
                
                // Añadimos o eliminamos los parámetros de depuración.
                if (layerLevel) {
                    currentUrl.searchParams.set('user_layer', layerLevel);
                } else {
                    currentUrl.searchParams.delete('user_layer');
                }
                
                if (userLevel) {
                    currentUrl.searchParams.set('user_level', userLevel);
                } else {
                    currentUrl.searchParams.delete('user_level');
                }
                
                // Recargamos la página con la nueva URL.
                window.location.href = currentUrl.toString();
            });
        </script>
    HTML;
        // --- FIN DEL BLOQUE HEREDOC ---
    };
} 

if (defined('DEBUG_ON') && DEBUG_ON){
    
}

function debug(string $mensaje = '', $var = null, bool $printHtml = true, bool $exit = false) {
    if (!defined('DEBUG_ON') || !DEBUG_ON) return;

    static $rootCut = null;
    if ($rootCut === null) {
        $rootCut = str_replace('\\', '/', __DIR__);
        $pos = strpos($rootCut, 'Proyecto - Replica Imaweb');
        if ($pos !== false) {
            $rootCut = substr($rootCut, 0, $pos + strlen('Proyecto - Replica Imaweb'));
        }
        $logFile = $rootCut . DIRECTORY_SEPARATOR . 'log_debug.md';
        $handle = fopen($logFile, 'r');
        $firstLine = fgets($handle);
        fclose($handle);
        $timestamp = strtotime(trim($firstLine));
        if (time() - $timestamp > 3) {
            file_put_contents($logFile, date('Y-m-d H:i:s')."\n\n");
        }
    }

    $backtrace = debug_backtrace();

    // Nivel actual (debug)
    $file = isset($backtrace[0]['file']) ? str_replace('\\', '/', $backtrace[0]['file']) : 'unknown';
    $line = $backtrace[0]['line'] ?? 'unknown';
    $shortFile = str_replace($rootCut . '/', '', $file);

    // Nivel 1: función/método que llamó a debug()
    $caller = $backtrace[1] ?? null;
    $function = $caller['function'] ?? 'global';
    $class = $caller['class'] ?? '';
    $callInfo = $class ? "$class::$function" : $function;

    // Nivel 2: quien llamó a la función/método anterior
    $callerCaller = $backtrace[2] ?? null;
    $callerCallerFile = isset($callerCaller['file']) ? str_replace('\\', '/', $callerCaller['file']) : 'unknown';
    $callerCallerLine = $callerCaller['line'] ?? 'unknown';
    $shortCallerCallerFile = str_replace($rootCut . '/', '', $callerCallerFile);
    $callerCallerFunction = $callerCaller['function'] ?? 'global';
    $callerCallerClass = $callerCaller['class'] ?? '';
    $callerCallerInfo = $callerCallerClass ? "$callerCallerClass::$callerCallerFunction" : $callerCallerFunction;

    // Salida HTML
    if ($printHtml) {
        ob_start();
        echo "<pre style='background:#eee; padding:10px; border:1px solid #ccc'>";
        echo "<b>Impresion HTML desde línea $line en archivo $shortFile</b>\n";
        echo "<b>Función/método: $callInfo</b>\n";
        echo "<b>Llamada original desde línea $callerCallerLine en archivo $shortCallerCallerFile</b>\n";
        echo "<b>Función/método original: $callerCallerInfo</b>\n";

        if ($mensaje !== '') {
            echo "\n$mensaje\n";
        }

        if (func_num_args() >= 2) {
            echo "\n";
            print_r($var);
        }
        echo "</pre>";

        $htmlOutput = ob_get_clean();
        echo $htmlOutput;
    }

    // --- Log plano ---
    $logOutput = "DEBUG - " . date('Y-m-d H:i:s') . PHP_EOL;
    $logOutput .= "Archivo: $shortFile Línea: $line" . PHP_EOL;
    $logOutput .= "Función/Método: $callInfo" . PHP_EOL;
    $logOutput .= "Llamada original: Archivo: $shortCallerCallerFile Línea: $callerCallerLine" . PHP_EOL;
    $logOutput .= "Función/Método original: $callerCallerInfo" . PHP_EOL;

    if ($mensaje !== '') {
        $logOutput .= "Mensaje: $mensaje" . PHP_EOL;
    }

    if (func_num_args() >= 2) {
        $logOutput .= "Variable: " . print_r($var, true) . PHP_EOL;
    }

    $logOutput .= str_repeat('-', 80) . PHP_EOL;

    $logFile = $rootCut . DIRECTORY_SEPARATOR . 'log_debug.md';
    file_put_contents($logFile, $logOutput, FILE_APPEND);

    if ($exit) exit;
}