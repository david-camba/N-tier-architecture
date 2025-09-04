<?php
/**
 * Clase base abstracta de la que deben heredar todos los controladores.
 * Proporciona funcionalidades y propiedades comunes.
 */
abstract class Controller extends Component
{    
    /**
     * @var bool Activa o desactiva la herencia de roles jerárquica para este controlador.
     */
    protected $userLevelFallback = false;

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
     * Create and return an instance of the view class, configured
     * with the route to the most specific XSLT template.
     *
     * @param string $viewName The name of the view to render (eg: 'login').
     * @param array $data (Optional) A data array to assign.
     * @return View
     */
    protected function getView(string $viewName, array $data = [])
    {
        // 1. Obtenemos el objeto View de la App.
        require_once 'lib/View.php';
        $view = new View($viewName);

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
        $layers = $this->getConfig('layers');
        $userLayer = App::getInstance()->getUserLayer();
        
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
    protected function view($view)
    {        
        require_once 'lib/response/ViewResponse.php'; 

        return new ViewResponse($view);
    }
    
    /**
     * Atajo para crear una JsonResponse.
     */
    protected function json(array $data = [], $success = true, $statusCode = 200)
    {
        require_once 'lib/response/JsonResponse.php';  // we load the class file

        return new JsonResponse(
        [
            'success' => $success,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Atajo para crear una Json Response de error.
     */
    protected function jsonError($message = '', $statusCode = 400)
    {
        require_once 'lib/response/JsonResponse.php';  // we load the class file

        return new JsonResponse(
        [
            'success' => false,
            'message'=> $message,
        ], $statusCode);
    }
    
    /**
     * Enriquecer una respuesta JSON antes de enviarla
     */
    function enrichJsonResponse(JsonResponse $response, array $extraFields = []): JsonResponse
    {
        // Obtener contenido de la respuesta original
        $data = $response->getContent();

        // Añadir campos extra
        $data = array_merge($data, $extraFields);

        // Devolver la respuesta JSON final
        return $this->json($data);
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
        $parentResponse = $this->callParent($this);
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