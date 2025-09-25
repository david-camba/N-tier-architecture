<?php
/** 
* App class 
* 
* The core of the application: Service Locator 
* Act to create components (controllers, models, etc.) and as the orchestrador 
* Main that manages the life cycle of an HTTP request. 
*/
class App
{
    private static ?App $instance = null;

    public string $rootPath;

    private Router $router;

    public static function getInstance(): App {
        if (self::$instance === null) {
            throw new RuntimeException("App has not been initialized");
        }
        return self::$instance;
    }
    /**
     * Store the complete configuration of the application loaded from Config.php.
     * @var array
     */
    private array $config;

    /**
     * Store the user context. It's set by the AuthService
     * @var array
     */
    private array $context;

    private $userLayer = null; //control access to the 3 layers (vertical layers)
    private $userLevel = null; //control access to user role capabilities (horizontal layers)

    private $modelFactory = null; //guardamos la fábrica la primera vez que se genera para cachear las conexiones con bases de datos

    private array $buildingStack = [];
    protected array $cachedComponents = [];

    //private $isApiRoute = false; // New property to remember the type of route.
    /**
     * The builder stores the configuration
     * @param array $config Application configuration.
     */
    public function __construct(array $config, Router $router, ?string $rootPath = null)
    {
        $this->config = $config;
        $this->router = $router;
        $this->rootPath = $rootPath ?? dirname(__DIR__);
        self::$instance = $this;     
        
        //TO-DO: No Class should receive App

        //TO-DO: AuthService shouldn't set User, Layer and Level, it should return the instruccion to App to handle

        //TO-DO: New Class: LOADER - Refactor getComponent, buildComponent, findFiles to new class Loader. Then, inyect on App, and from there to Translator, View. This Loader could be used as support class for testing, minimizing/eliminating the need of "require/use" also in that envioroment.    
    }

    /** 
    * The main method that executes the application. 
    * Orchestra the routing, session security and execution 
    * of the corresponding controller or script. 
    */
    public function run()
    {
        try {
            $this->prepareDebugging();
            // 1. Obtain the router's action plan.
            $requestedRouteInfo = $this->router->getRouteInfo();   

            // 2. Apply the session security logic.
            require_once 'lib/components/Component.php';
            $finalRouteInfo = $this->buildService('AuthService')->authenticateRequest($requestedRouteInfo);

            //detect if is JSON request
            /*if (!empty($requestedRouteInfo['api_route'])) {                
                $this->isApiRoute = true;
            }*/
            
            // 3. Decide what to do based on the type of route.
            switch ($finalRouteInfo['type']) {
                case 'mvc_action':
                    // If it is a modern route, we execute the MVC flow.

                    // 1. We obtain the Responsible object prepared by the Dispatcher.
                    $response = $this->dispatchAction($finalRouteInfo);

                    // 2. Here we could apply Middlewares to the answer
                    $this->debugResponse($response);

                    $this->sendResponse($response);
                    break;

                case 'legacy_script':
                    // If it is a legacy route, we execute the script.
                    $scriptPath = $this->rootPath.'/'.$finalRouteInfo['script_path'];
                    
                    // We verify that the file exists in that fixed location.
                    if (file_exists($scriptPath)) {
                        // 3. We execute it.
                        require_once $scriptPath;
                        exit();
                    } else {
                        // If the script does not exist, it is a 404 error.
                        throw new Exception("Script legacy no encontrado: {$finalRouteInfo['script_name']}", 404);
                    }
                    break;
                default:
                    // If the Router returns an unknown, it is an internal error.
                    throw new Exception("Tipo de ruta desconocido: '{$finalRouteInfo['type']}'", 500);   
            }
        } catch (Throwable $e) {
            // Captures any error or exception that occurs during execution
            // And it passes it to our central error handler.
            $this->logError($e);
            throw $e;
        }
    }

    public function setUserLayer($userLayer)
    {
        //Only set the layer once
        if ($this->userLayer !== null) {
            return;
        }
        $this->userLayer = (int) $userLayer;
    }
    public function getUserLayer()
    {
        return $this->userLayer;
    }

    public function setUserLevel($userLevel)
    {
        //Only set the level once
        if ($this->userLevel !== null) {
            return;
        }
        $this->userLevel = (int) $userLevel;
    }
    public function getUserLevel()
    {
        return $this->userLevel;
    }

    public function setContext($key, $value)
    {
        $this->context[$key] = $value;
    }

    public function getContext($key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }

    /** 
    * The new intelligent "dispatcher". 
    * Find and execute the most specific controller/action implementation 
    * Based on the vertical layer and the horizontal role of the user. 
    */
    private function dispatchAction(array $routeInfo) : Response
    {
    $controllerName = $routeInfo['controller'];
    $actionName = $routeInfo['action'];
    $params = $routeInfo['params'];
    $userLevel = $this->getUserLevel();
    
    // 1. Determine the user's role suffix (if you have it).
    $roleSuffix = ($userLevel != 0 && ($role = $this->getConfig(['user_roles', $userLevel])) !== null)
        ? $role
        : '';

    // 2. I try to: look for a specialized controller for the role
    // This is useful to create specific functionalities for roles, like "Emissions_Manager.php".
    // Only managers will be able to access this specific controller.
    $specializedControllerName = "{$controllerName}_{$roleSuffix}";
    try {
        // We try to obtain the controller with the suffix of the role.
        $controller = $this->buildController($specializedControllerName);        
        // If we succeed, we use it. We do not need to check the method.
        return $controller->{$actionName}(...$params);
    } catch (Exception $e) {
        // No problem. It means that the specialized controller does not exist.
    }

    // 3. I try B: Use the normal controller, but look for the specialized method of the user's role.  
    $controller = $this->buildController($controllerName);
    $specializedActionName = "{$actionName}_{$roleSuffix}";

    
    if ($roleSuffix && method_exists($controller, $specializedActionName)) {
        // We find a specialized method! We execute it.
        $response = $controller->{$specializedActionName}(...$params);
        return $response;
    }
    // If the controller has the property fallbackRole = true, I'm looking for a fallback of the levels below

    // 4. I try C: Use the normal controller, but look for a specialized method of roles of users with lower privileges   
    if($controller->useUserLevelFallback()){
        $fallbackLevel = $userLevel - 1;
        while($fallbackLevel > 0){
            $currentRoleSuffix = $this->getConfig('user_roles')[$fallbackLevel];
            $currentActionName = "{$actionName}_{$currentRoleSuffix}";

            if ($roleSuffix && method_exists($controller, $currentActionName)) {
                $response = $controller->{$currentActionName}(...$params);
                return $response;
            }

            $fallbackLevel--;
        }
    }


    // 5. Fallback: Use the normal controller and method.
    if (method_exists($controller, $actionName)) {
        return $controller->{$actionName}(...$params);        
    }
    
    // 6. If none of the above works, it is a 404.
    throw new Exception("Acción no encontrada para la ruta: {$controllerName}->{$actionName}", 404);
    }

    
    public function getModel(string $modelName, array $constructorArgs=[], ?int $userLayer = null, bool $cache = false) : ORM
    {
        $connectionType = $this->getConfig(['model_connections',$modelName]);           

        // CASO 1: Override explícito. Sin caché.
        if ($userLayer !== null && !$cache) {
            $factory = $this->getComponent('factory', 'ModelFactory', [$this], $userLayer);
            return $factory->create($modelName, $connectionType, $constructorArgs, $userLayer);
        }

        // CASO 2: Override explícito. Con cache.
        if ($userLayer !== null && $cache)  {
            $this->modelFactory = $this->getComponent('factory', 'ModelFactory', [$this], $userLayer);
            return $this->modelFactory->create($modelName, $connectionType, $constructorArgs, $userLayer);
        }
        
        // CASO 3: Usuario autenticado. Con caché.
        if ($this->userLayer) {            
            if ($this->modelFactory === null) {
                $this->modelFactory = $this->getComponent('factory', 'ModelFactory', [$this], $this->userLayer);
            }
            return $this->modelFactory->create($modelName, $connectionType, $constructorArgs, $this->userLayer);
        }
        
        // CASO 4: Invitado. Nivel 1, sin caché.
        $guestFactory = $this->getComponent('factory', 'ModelFactory', [$this], 1);
        return $guestFactory->create($modelName, $connectionType, $constructorArgs, 1);
    }

    /**
     * Crea y devuelve una instancia de un Controlador.
     * Inyecta automáticamente la App en el constructor. 
     * Nunca recibe argumentos aparte de "App", los parámetros recibidos se gestionan desde el método llamado, no desde la función
     */
    public function buildController(string $controllerName) {
        require_once 'lib/components/Controller.php';
        return $this->buildComponent("controller", $controllerName);        
    }

    public function buildService(string $serviceName) {
        require_once 'lib/components/Service.php';
        return $this->buildComponent('service', $serviceName);        
    }

    public function buildHelper(string $helperName) {
        require_once 'lib/components/Helper.php';
        return $this->buildComponent('helper', $helperName);        
    }
    
    public function buildComponent(string $type, string $name, ?int $userLayer = null, bool $exactLayerOnly = false)
    {        
        if (isset($this->buildingStack[$type.$name])) {
            throw new Exception(
                "Circular dependency detected for component: {$type}{$name}\n" .
                "Current build stack: " . implode(" -> ", array_keys($this->buildingStack)).
                " -> {$type}{$name}"
            );
        }
        $this->buildingStack[$type.$name] = 'building';

        $componentInfo = $this->findFiles($type, $name, $userLayer, $exactLayerOnly);

        if (!$componentInfo) {
            throw new Exception(ucfirst($type) . " no encontrado: {$name}");
        }

        // 3. Construir el nombre de la clase final.
        $className = "{$name}_{$componentInfo['suffix']}";

        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();
        $injectDependencies = [];
        if ($constructor) {
            $dependencies = $constructor->getParameters();

            foreach ($dependencies as $dependency) {
                $dependencyClass = $dependency->getType()->getName();                

                if (isset($this->cachedComponents[$dependencyClass])) {
                    $injectDependencies[] = $this->cachedComponents[$dependencyClass];
                }
                else{
                    $component = match (true) {
                        $dependencyClass === 'App' 
                            => $this,
                        str_ends_with($dependencyClass, 'Service')
                            => $this->buildService($dependencyClass),
                        str_ends_with($dependencyClass, 'Helper') 
                            // => $this->getHelper(substr($dependencyClass, 0, -strlen('Helper'))),
                            => $this->buildHelper($dependencyClass),
                        default 
                            => $this->getModel($dependencyClass), //By convention, we assume model
                    };
                    $injectDependencies[] = $component;
                    
                    $isModel = !str_ends_with($dependencyClass, 'Service') && !str_ends_with($dependencyClass, 'Helper') && $dependencyClass !== 'App';

                    if(!$isModel) $this->cachedComponents[$dependencyClass] = $component;
                }
            }
        }
        $builtComponent = new $className(...$injectDependencies);
        $this->cachedComponents[$name] = $builtComponent;
        unset($this->buildingStack[$type.$name]);
        return $builtComponent;              
    }


     /**
     * The main "factory" method.
     * Create and return an instance of any hierarchical component.
     *
     * @param string $type The type of component ('Controller', 'Model', etc.).
     * @param string $name The base name of the component ('Auth Controller').
     * @param array $constructorArgs The builder's entry arguments will be passed in an array.
     * @param integer|null $userLayer Get a component from a specific layer  
     * @param boolean $exactLayerOnly Get only the component from the fixed layer (don't use if it needs their parents)
     * @return object The instance of the requested object.
     */
    public function getComponent(string $type, string $name, array $constructorArgs = [], ?int $userLayer = null, bool $exactLayerOnly = false) : object
    {
        // 1. Encontrar la información del archivo y la capa.
        $componentInfo = $this->findFiles($type, $name, $userLayer, $exactLayerOnly);

        if (!$componentInfo) {
            throw new Exception(ucfirst($type) . " no encontrado: {$name}");
        }

        // 3. Construir el nombre de la clase final.
        $className = "{$name}_{$componentInfo['suffix']}";

        // 4. Verificar que la clase existe.
        if (!class_exists($className)) {
            throw new Exception("Error de Carga: La clase '{$className}' no está definida en el archivo '{$componentInfo['path']}'.");
        }

        // 5. Instanciar la clase con argumentos (si los hay).
        if (empty($constructorArgs)) {
            return new $className();
        } else {
            $reflection = new ReflectionClass($className);
            return $reflection->newInstanceArgs($constructorArgs); //reflection nos permite N argumentos en un array
        }
    }

    /**
     * The unique "search engine". Find the route to a component file
     * touring the hierarchy defined in the configuration.
     *
     * @param string $type The type of component ('controller', 'model').
     * @param string $name The base name of the component.
     * 
     * 
     * @param boolean $loadFiles If true, classes are automatically loaded with require_once. 
     *                           If false, only paths are returned; the caller must handle loading. 
     * @return array|null An array with ['path', 'suffix'] or null if it is not found.
     */
    public function findFiles(string $type, string $name, ?int $userLayer = null, bool $exactLayerOnly = false, bool $loadFiles=true) : ?array
    {
        if ($userLayer === null) { //if null, we get the user layer from context
            //this allow us to call with specific range.
            $userLayer = $this->userLayer ? $this->getUserLayer() : 1;
        }

        // Obtain the component subfolder from the configuration (ej: 'controllers').
        $componentSubdir = $this->getConfig(['component_types',$type]) ?? null;
        if (!$componentSubdir) {
            throw new Exception("Tipo de componente desconocido: {$type}");
        }
        $extension = ($type === 'view') ? '.xsl' : '.php';
        $relativePath = "{$componentSubdir}/{$name}{$extension}";

        $foundFile = null;
        $loadFiles = $loadFiles ? [] : false; //if loadFiles, we create an empty array to load them in the reverse order they are found to handle inheritance
        
        // Iterate about the hierarchy of this installation
        foreach ($this->getConfig('layers') as $layerKey => $layerInfo) {

            if ($userLayer < $layerInfo['layer']){     //if the userLayer is lower than the current layer, we wont look for a file           
                continue;
            }

            // If we want exact level, and this is not, we skip it.
            if ($exactLayerOnly && $layerInfo['layer'] != $userLayer) {
                continue; 
            }

            // Obtain the layer information from the configuration.
            $layerDir = $layerInfo['directory'];

            $filePath = "{$layerDir}/{$relativePath}";
            
            $absolutePath = $this->rootPath . '/' . $filePath;  //We check if the file exists

            if (file_exists($absolutePath)) {
                if (is_array($loadFiles)) {
                    $loadFiles[] = $absolutePath;
                }

                if ($foundFile === null){
                    // Found! We return the information of the most specific layer.
                    $foundFile = [
                        'path'   => $absolutePath,
                        'suffix' => $layerInfo['suffix'],
                        'name' => $name,
                        'level' => $layerInfo['layer']
                    ];
                }
                continue; 
            }

            //If we are at the exact level we return
            if ($exactLayerOnly && $layerInfo['layer'] == $userLayer) {
                if (is_array($loadFiles) && !empty($loadFiles)) {
                    require_once $loadFiles[0];
                }
                return $foundFile;
            }
        }
        
        // we load the files from the base to the highest layer
        if (is_array($loadFiles) && !empty($loadFiles)) {
            foreach (array_reverse($loadFiles) as $file) {
                require_once $file;
            }
        }
        return $foundFile;
    }   

    /**
     * It is a parent :: calling method () dynamic that facilitates syntax
     * Execute the same method of the object in the father class and return your answer.
     * Automatically detects the name of the method that called it and adjusts the arguments according to the father.
     * @param object $callerObject Receive the object that calls it to be able to execute the method maintaining the State
     * @return mixed The response of the Padre del Controller method, Service, Model ...
     */
    public function callParent(object $callerObject) : mixed
    {
        // 1. detect the method and class that called us
        $backtrace = debug_backtrace(0, 5); // We get the last 5 calls to ensure us to find the real caller

        // 2. We ignore the intermediate methods (if they exist) to obtain the real call method
        $callHelpers = $this->getConfig('parent_call_helpers');
        // If the amount of assistant methods could be extended, it could be put in a config

        $callerInfo = null;
        // We start in index 1, because 0 is always the current method.
        for ($i = 1; $i < count($backtrace); $i++) {
            $frame = $backtrace[$i];            
            // If the name of the current function is not on our list of assistants, then we have found the "real caller."
            if (!in_array($frame['function'], $callHelpers)) {
                $callerInfo = $frame;
                break; 
            }
        }

        if (!$callerInfo) {
            throw new LogicException("No se pudo determinar el método llamante real.");
        }

        $callerClassName = $callerInfo['class'] ?? null;
        $callerMethodName = $callerInfo['function'] ?? null;
        $callerArgs = $callerInfo['args'] ?? [];

        if (!$callerMethodName) {
            throw new LogicException("No se pudo determinar el método o clase que llama a getParentView.");
        }        

        // 2. get the real father and prepare the method
        $parentClass = get_parent_class($callerClassName);
        if (!method_exists($parentClass, $callerMethodName)) {
            throw new LogicException("El método {$callerMethodName} no existe en la clase padre {$parentClass}.");
        }        

        // 3. Execute the father's method in the context of the current $this
        $method = new ReflectionMethod($parentClass, $callerMethodName); // We create a reflection of the method

        $numParams = $method->getNumberOfParameters(); 
        $callerArgs = array_slice($callerArgs, 0, $numParams);

        $method->setAccessible(true); //Remove the protect for the reflection method can be launched

        $response = $method->invokeArgs($callerObject, $callerArgs); //We invoke about $this (The object of the upper layer you have called, allows us to maintain the State, and we pass the arguments)
        return $response;
    }

    /**
     * Create and return an response object, loading the file of your class only when necessary.
     *
     * @param string $type The type of response (ej. 'json', 'view').
     * @param mixed ...$args The arguments for the response builder
     * @return Response
     */
    public function getResponse(string $type, mixed ...$args) : Response
    {
        // 1. We build the name of the class and the route to the file.
        require_once "lib/response/Response.php";
        $className = ucfirst($type) . 'Response'; // Assuming the convention with suffix
        $filePath = "lib/response/{$className}.php";  // We use class name as file name

        // 2. We load the file
        require_once $filePath;

        // 3. We verify that the load was successful and the class exists.
        if (!class_exists($className)) {
            // This error would only happen if the file does not exist or has an incorrect class name.
            throw new Exception("No se pudo cargar la clase de respuesta: {$className}");
        }
        
        // 4. We instant the class.
        return new $className(...$args);
    }

    /**
     * Access a configuration key using type path type 'a.b.c' or array ['a','b','c'].
     *
     * @param string|array $path
     * @param mixed $default
     * @return mixed
     */
    public function getConfig(string|array $path, mixed $default = null): mixed {
        
        $keys = is_string($path) ? explode('.', $path) : $path;

        $value = $this->config;


        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Create and return an instance of a service.
     * Automatically injects the app instance as the first argument of the service builder.
     *
     * @param string $serviceName The base name of the service.
     * @param mixed ...$args (Optional) Additional arguments for specific service
     * @return object The service instance.
     */
    public function getService($serviceName, ...$args)
    {
        // 1. We create an array with the app as first element.
        $constructorArgs = [$this];
        
        // 2. We fuse the additional arguments
        $constructorArgs = array_merge($constructorArgs, $args);

        // 3. We call the GET Component generic with the list of full arguments.
        return $this->getComponent('service', $serviceName.'Service', $constructorArgs);
    }

    public function getHelper($helperName, ...$args)
    {
        // 1. We create an array with the app as first element.
        $constructorArgs = [$this];
        
        // 2. We merge the additional arguments that the developer passed.
        $constructorArgs = array_merge($constructorArgs, $args);

        return $this->getComponent('helper', $helperName.'Helper', $constructorArgs);
    }
    
    public function redirect(string $url, $statusCode = 200) : never
    {
        $redirectResponse = $this->getResponse('redirect', $url, $statusCode);
        $this->sendResponse($redirectResponse);
        exit();
    }

    protected function sendResponse(Response $response)
    {
        // --- Step 1: Send headers ---
        
        // We send the HTTP status code (ej. 200, 404, 302).
        http_response_code($response->getStatusCode());

        // We send all the headwaters defined in the response object
        foreach ($response->getHeaders() as $name => $value) {
            header("{$name}: {$value}");
        }

        // --- PASO 2: ENVIAR CONTENIDO (basado en el tipo de respuesta) ---
        
        $content = $response->getContent();

        if ($response instanceof ViewResponse) {
            // If the answer is a view, we call your render method ().
            $viewRendered = $content->render($response->getUserLayer());
            echo $viewRendered;
        } elseif ($response instanceof JsonResponse) {
            // If it is JSON, we encode the content (which is an array/object) and printed it.
            echo json_encode($content);

        } elseif ($response instanceof FileResponse) {
            // If it is a file, we read its content and send it directly.
            // $content It is the route to the file.
            readfile($content);

        } elseif ($response instanceof RedirectResponse) {
            // For a redirection, there is no content to send. The header
            // 'Location' That we already send is all that is needed.

        } else {
            // For a base or unknown response, we simply print the content.
            echo $content;
        }
        exit();
    }

    /**
     * Set the layers for the request if they they were set by the debugging panel of "debug.php" 
     *
     * @return void
     */
    private function prepareDebugging() : void
    {
        if (!defined('DEBUG_ON') || !DEBUG_ON || !defined('DEBUG_PANEL') || !DEBUG_PANEL) return;

        //IMPERSONATE DEBUGGING: if Debug Panel is active, we override with the requested layer and user level
        if(defined('FIXED_USER_LAYER') && FIXED_USER_LAYER){
            $this->setUserLayer(FIXED_USER_LAYER);
        }
        if(defined('FIXED_USER_LEVEL') && FIXED_USER_LEVEL){
            $this->setUserLevel(FIXED_USER_LEVEL);
        }        
    }

    private function debugResponse($response){
        //IMPERSONATE DEBUGGING: we render the debug panel if not a JSON request (it would mess our JSON response)
        if (!defined('DEBUG_ON') || !DEBUG_ON || !defined('DEBUG_PANEL') || !DEBUG_PANEL) return;

        if (is_object($response) && $response instanceof ViewResponse) {
            $GLOBALS['renderDebugPanel']();
        }
    }

    protected function logError(Throwable $e)
    {
        if (empty($this->config['error_log_path'])) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $argLimit = $this->config['log_arg_length_limit'] ?? 512; // Un límite por defecto si no está en config

        // Construcción del Stack Trace
        $traceLines = [];
        $trace = $e->getTrace();
        
        foreach ($trace as $i => $frame) {
            $traceLines[] = sprintf(
                "#%d %s(%d): %s",
                $i,
                $frame['file'] ?? '[internal function]',
                $frame['line'] ?? '?',
                $this->formatTraceFunctionCall($frame, $argLimit) // Pasamos el límite
            );
        }
        // Añadimos la línea final {main}
        $traceLines[] = '#' . (count($trace)) . ' {main}';
        
        $customTraceString = implode("\n", $traceLines);

        // Mensaje de log final
        $logMessage = sprintf(
            "[%s]\n%s: \"%s\" in %s:%d\n\nStack trace:\n%s\n",
            $timestamp,
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $customTraceString
        );
        
        $logEntry = $logMessage . str_repeat('-', 80) . "\n\n"; // Doble salto de línea para más espacio

        @file_put_contents($this->rootPath . $this->config['error_log_path'], $logEntry, FILE_APPEND);
    }

    /**
     * Formatea una llamada a función/método desde un frame del stack trace.
     */
    private function formatTraceFunctionCall(array $frame, int $argLimit): string
    {
        $call = '';
        if (isset($frame['class'])) $call .= $frame['class'];
        if (isset($frame['type'])) $call .= $frame['type'];
        if (isset($frame['function'])) $call .= $frame['function'];
        
        $call .= '(' . $this->formatArgs($frame['args'] ?? [], $argLimit) . ')';

        return $call;
    }

    /**
     * Formatea los argumentos de una función para el log.
     */
    private function formatArgs(array $args, int $argLimit): string
    {
        if (empty($args)) {
            return '';
        }

        $output = [];
        foreach ($args as $arg) {
            // Usamos JSON_PRETTY_PRINT para una legibilidad espectacular
            $argString = json_encode($arg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            // Si el argumento es un objeto complejo o un array, puede tener saltos de línea.
            // Lo indentamos para que quede bien en el log.
            if (strpos($argString, "\n") !== false) {
                $argString = str_replace("\n", "\n    ", $argString); // Indenta cada línea
            }

            // Aplicamos el límite de longitud configurable (solo si es mayor que 0)
            if ($argLimit > 0 && strlen($argString) > $argLimit) {
                $argString = substr($argString, 0, $argLimit) . '... (truncated)';
            }
            $output[] = $argString;
        }

        $formattedArgs = implode(', ', $output);

        // Si los argumentos son multilínea, los formateamos de forma especial
        if (strpos($formattedArgs, "\n") !== false) {
            return "\n    " . $formattedArgs . "\n";
        }

        return $formattedArgs;
    }
}