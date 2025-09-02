<?php
/**
 * Clase base abstracta de la que deben heredar todos los controladores.
 * Proporciona funcionalidades y propiedades comunes.
 */
abstract class Controller
{
    /** @var App */
    protected $app;
    protected $translator;
    
    /**
     * @var bool Activa o desactiva la herencia de roles jerárquica para este controlador.
     */
    protected $userLevelFallback = false;

    /**
     * El constructor recibe la instancia de la App y la almacena.
     * Esta es la ÚNICA dependencia fundamental de cualquier controlador.
     *
     * @param App $app La instancia de la aplicación.
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->translator = $this->app->getTranslator();
    }

    /**
     * Getter para la propiedad roleFallback.
     * Permite a la App preguntar si debe usar el fallback.
     */
    public function useUserLevelFallback()
    {
        return $this->userLevelFallback;
    }

    // --- MÉTODOS DE AYUDA ---

    /**
     * Atajo para obtener una View y asignarle datos iniciales.
     * Es la forma recomendada de empezar a construir una respuesta de vista.
     *
     * @param string $viewName El nombre de la vista (ej: 'login').
     * @param array $data (Opcional) Un array de datos para asignar a la vista.
     * @return View
     */
    protected function getView($viewName, array $data = [])
    {
        // 1. Obtenemos el objeto View de la App.
        $view = $this->app->getView($viewName);

        $this->injectDefaultAssets($view);

        // 2. Si se pasaron datos, los asignamos en una sola llamada.
        // Ahora podemos hacer esto porque View::set() acepta un array.
        if (!empty($data)) {
            $view->set($data);
        }

        return $view;
    }

    /**
     * Un nuevo helper que carga los assets por defecto (CSS y JS)
     * basándose en la jerarquía y el nivel del usuario.
     */
    private function injectDefaultAssets(View $view)
    {
        $layers = $this->app->getConfig('layers');
        $userLayer = $this->app->getUserLayer();
        
        $cssFiles = [];
        $jsFiles = [];

        // Iteramos desde la base hacia el hijo para construir la lista.
        // El orden en que se añaden al array es el orden en que se cargarán.
        $reversedLayers = array_reverse($layers);

        foreach ($reversedLayers as $layer) {            
            // Comprobamos el permiso de nivel.
            if ($layer['layer'] <= $userLayer) {
                
                // --- CSS ---
                // Construimos la ruta PÚBLICA y la ruta del SISTEMA DE ARCHIVOS.
                $cssWebPath = "/{$layer['directory']}/css/style.css";
                $cssFilePath = dirname(__DIR__, 2) . "/public" . $cssWebPath;
                
                if (file_exists($cssFilePath)) {
                    $cssFiles[] = $cssWebPath;
                }

                // --- JS ---
                // Hacemos lo mismo para un archivo JS global, si existe.
                $jsWebPath = "/{$layer['directory']}/js/global.js";
                $jsFilePath = dirname(__DIR__, 2) . "/public" . $jsWebPath;

                if (file_exists($jsFilePath)) {
                    $jsFiles[] = $jsWebPath;
                }
            }
        }        
        // Pasamos las listas de assets a la vista.
        // La vista se encargará de generar el HTML.
        $view->set('styles', $cssFiles);
        $view->set('scripts', $jsFiles);
    }



    /**
     * Atajo para crear una ViewResponse
     */
    protected function view($view, $userLayer=null)
    {
        return $this->app->getResponse('view', $view);
    }
    
    /**
     * Atajo para crear una JsonResponse.
     */
    protected function json(array $data = [], $success = true, $statusCode = 200)
    {
        return $this->app->getResponse('json', [
            'success' => $success,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Atajo para crear una Json Response de error.
     */
    protected function jsonError($message = '', $statusCode = 400)
    {
        return $this->app->getResponse('json', [
            'success'=> false,
            'message'=> $message
        ], $statusCode);
    }
    
    /**
     * Enriquecer una respuesta JSON antes de enviarla
     */
    function enrichJsonResponse($response, array $extraFields = []): JsonResponse
    {
        // Obtener contenido de la respuesta original
        $data = $response->getContent();

        // Añadir campos extra
        $data = array_merge($data, $extraFields);

        // Devolver la respuesta JSON final
        return $this->json($data);
    }

    /**
     * Atajo para devolver un RedirectResponse
     */
    protected function redirect(string $url, string|int $statusCode = 200)
    {
        $this->app->redirect($url, $statusCode);
        exit();
    }

    /**
     * Atajo para obtener una cadena de texto traducida.
     */
    protected function translate($key, array $replacements = [])
    {
        return $this->translator->get($key, $replacements);
    }


    /* METODOS FACHADA, ENCAPSULANDO LA NECESIDAD DE LLAMAR A APP */
    protected function getModel($modelName, array $constructorArgs=[], $userLayer=null, $cache=false)
    {
        return $this->app->getModel($modelName, $constructorArgs, $userLayer, $cache);
    }
    
    protected function getService($serviceName, ...$args)
    {
        return $this->app->getService($serviceName, ...$args);
    }

    protected function getHelper($serviceName, ...$args)
    {
        return $this->app->getHelper($serviceName, ...$args);
    }

    protected function getConfig($key, $default = null)
    {
        return $this->app->getConfig($key, $default);
    }
    
    protected function getContext($key, $default = null)
    {
        return $this->app->getContext($key, $default);
    }

    public function setContext($key, $value)
    {
        return $this->app->setContext($key, $value);
    }

    /**
     * Ejecuta el mismo método en la clase padre y devuelve su respuesta.
     * Detecta automáticamente el nombre del método que lo llamó y ajusta los argumentos según el padre.
     * 
     * @param string|null $type Opcional. Tipo esperado de respuesta ('view', 'json', etc.).
     * @return mixed La respuesta del método padre (ViewResponse, JsonResponse, etc.).
     * @throws LogicException Si no se puede determinar el método padre o la respuesta no coincide con $type.
     */
    protected function parentResponse(?string $type = null) : mixed
    {
        $parentResponse = $this->app->callParent($this);
        // 4. Validar tipo si se especifica
        if ($type !== null) {
            $expectedClass = ucfirst($type) . 'Response';
            if (!$parentResponse instanceof $expectedClass) {
                throw new LogicException(
                    "El método padre '{$parentResponse}' no devolvió una instancia de {$expectedClass}."
                );
            }
        }
        return $parentResponse->getContent();
    }
}