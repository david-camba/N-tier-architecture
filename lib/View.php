<?php
class View
{
    private App $app;
    private string $viewName;     // La ruta a renderizar
    private $data = [];    // Los datos que le pasará el controlador
    private $compiledCache = []; // Save compiled views
    

    /**
     * El constructor recibe la ruta a la plantilla que debe usar.
     */
    public function __construct(string $viewName)
    {
        $this->viewName = $viewName;
        $this->app = App::getInstance();
    }

    /**
     * Asigna datos a la vista.
     *
     * Acepta dos formas de uso:
     * 1. set('clave', 'valor'): Asigna un único par clave-valor.
     * 2. set(['clave1' => 'valor1', 'clave2' => 'valor2']): Asigna un array de datos.
     *
     * @param string|array $key La clave del dato o un array de datos.
     * @param mixed $value El valor del dato (ignorado si el primer argumento es un array).
     * @return self Para permitir el encadenamiento de métodos ($view->set(...)->set(...)).
     */
    public function set($key, $value = null)
    {
        // Comprobamos si el primer argumento es un array.
        if (is_array($key)) {
            // Si es un array, lo fusionamos con los datos existentes.
            // array_merge se asegura de que las nuevas claves sobrescriban a las antiguas.
            $this->data = array_merge($this->data, $key);
        } else {
            // Si no es un array, es el caso normal de clave-valor.
            $this->data[$key] = $value;
        }

        // Devolvemos $this para permitir el encadenamiento de métodos.
        return $this;
    }

    /**
     * Obtiene un dato que ya ha sido asignado a la vista.
     */
    public function get($key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Añade un valor a una lista en los datos de la vista.
     */
    public function add($listKey, $value)
    {
        if (!isset($this->data[$listKey]) || !is_array($this->data[$listKey])) {
            $this->data[$listKey] = [];
        }
        $this->data[$listKey][] = $value;
    }

    /**
     * Elimina un dato de la vista por su clave.
     *
     * @param string $key La clave del dato a eliminar.
     */
    public function remove($key)
    {
        unset($this->data[$key]);
    }

    /**
     * Elimina un valor específico de una lista en los datos de la vista.
     *
     * @param string $listKey La clave de la lista.
     * @param mixed $value El valor a eliminar.
     */
    public function removeValue($listKey, $value)
    {
        if (isset($this->data[$listKey]) && is_array($this->data[$listKey])) {
            $this->data[$listKey] = array_filter(
                $this->data[$listKey],
                function ($item) use ($value) {
                    return $item !== $value;
                }
            );

            // Reindexar el array para evitar huecos en los índices
            $this->data[$listKey] = array_values($this->data[$listKey]);
        }
    }

    /**
     * El método render() ahora hace todo el trabajo pesado.
     */
    public function render($userLayer=null) //añadir aquí el user level override para buscar a partir de X vista sino se queire usar lo de user
    {
        $user = $this->app->getContext("user");

        $userToken = $user ? $user->token : "noLog";

        $basePath = realpath(__DIR__ . '/../cache'); 
        $userFolder = $basePath . '/' . $userToken; 
        $viewFolder = $userFolder . '/views' . '/' . $this->viewName; 
        $finalXslFile = $viewFolder . '/' . $this->viewName . '.xsl';

        $cacheOn = defined('DEBUG_VIEWS_CACHE') ? DEBUG_VIEWS_CACHE : true;

        // ✅ 0. Si ya existe la vista compilada, usarla directamente
        if (file_exists($finalXslFile) && $cacheOn) {
            $xml = $this->createDataXml();

            $xsl = new DOMDocument();
            $xsl->load($finalXslFile);

            $proc = new XSLTProcessor();
            $proc->importStyleSheet($xsl);
            echo $proc->transformToXML($xml);
            return;
        }

        // 1. Buscar la vista más específica que exista y no cargarla
        $viewFileInfo = $this->app->findFiles('view', $this->viewName, $userLayer, false, false);
        if (!$viewFileInfo) {
            throw new Exception("Plantilla de vista no encontrada: {$this->viewName}");
        }

        // 2. Compilar vistas heredadas (las deja en compiledCache)
        $this->compileView($viewFileInfo);

        // 3. Extraer array de vistas temporales compiladas (nueva ruta => contenido)
        $tempXsl = $this->compileAndStoreXslFiles($basePath, $userToken, $this->viewName);

        // Ahora guardar archivos dentro de $viewFolder
        foreach ($tempXsl as $filename => $content) {
            $filePath = $viewFolder . '/' . $filename;
            file_put_contents($filePath, $content);
        }

        // 6. Crear el XML de datos para transformar
        $xml = $this->createDataXml();

        // 7. Procesar transformación
        $xsl = new DOMDocument();
        $xsl->load($finalXslFile);

        $proc = new XSLTProcessor();
        $proc->importStyleSheet($xsl);
        echo $proc->transformToXML($xml);
        return;
    }


        /**
     * El método "compilador" que el controlador llama.
     * @param string $viewName El nombre de la vista a compilar.
     * @return string El contenido XSLT ya procesado.
     */
    private function compileView($viewFileInfo)
    {        
        // 2. Llama al worker recursivo.
        return $this->processTemplate($viewFileInfo);
    }

    /**
     * El "worker" recursivo. Procesa un archivo, y antes de devolverlo,
     * se asegura de que todas sus dependencias ya han sido procesadas.
     * @param array $templateInfo La info del archivo a procesar.
     * @return string El contenido ya procesado.
     */
    private function processTemplate(array $templateInfo)
    {
        $path = $templateInfo['path'];

        // 1. Si ya está en nuestro caché ("substitutionDone"), lo devolvemos directamente.
        if (isset($this->compiledCache[$path])) {
            return $this->compiledCache[$path];
        }

        // 2. Leemos el contenido del archivo.
        $content = file_get_contents($path);

        // 3. SUSTITUIMOS EL PADRE:
        // Buscamos el marcador [PARENT_TEMPLATE_PATH].
        if (strpos($content, '[PARENT_TEMPLATE_PATH]') !== false) {
            $parentInfo = $this->findParent($templateInfo); // Busca el padre
            if ($parentInfo) {
                // ANTES de reemplazar, nos aseguramos de que el padre esté compilado.
                $this->processTemplate($parentInfo);
                // Ahora reemplazamos el marcador con la ruta real del padre.
                $content = str_replace('[PARENT_TEMPLATE_PATH]', $parentInfo['path'], $content);
            }
        }
        
        // 4. SUSTITUIMOS LOS [VIEW_PATH:...]:
        // Usamos una función que encuentra todos los VIEW_PATH.
        $importedTemplates = $this->findImported($content);

        foreach ($importedTemplates as $marker => $viewNameToFind) {
            $importedInfo = $this->app->findFiles('view', $viewNameToFind, null, false, false);
            if ($importedInfo) {
                // ANTES de reemplazar, compilamos la dependencia.
                $this->processTemplate($importedInfo);
                // Reemplazamos el marcador con la ruta real.
                $content = str_replace($marker, $importedInfo['path'], $content);
            }
        }

        // 5. Guardamos el contenido ya "parcheado" en el caché y lo devolvemos.
        $this->compiledCache[$path] = $content;
        return $content;
    }
    /**
     * Encuentra la plantilla padre de una plantilla dada.
     * @param array $childInfo La info de la plantilla actual. Debe contener 'level' y 'name'.
     * @return array|null La info de la plantilla padre, o null si no tiene.
     */
    private function findParent(array $childInfo)
    {
        $currentLevel = $childInfo['level'];
        $viewName = $childInfo['name'];

        // Si el nivel actual es 1 (la base) o inferior, no puede tener un padre.
        if ($currentLevel <= 1) {
            return null;
        }

        // El nivel máximo que queremos buscar es uno menos que el actual.
        $maxParentLevel = $currentLevel - 1;

        // Le pedimos a la App que busque la MISMA vista, pero limitando la
        // búsqueda a un nivel máximo igual al del padre. findFile se encargará
        // de ignorar la capa actual y las superiores, devolviendo la primera que encuentre.
        return $this->app->findFiles('view', $viewName, $maxParentLevel, false, false);
    }
        /**
     * Busca y extrae todos los marcadores de importación de tipo [VIEW_PATH:...]
     * de una cadena de texto.
     *
     * @param string $content El contenido del archivo XSLT.
     * @return array Un array asociativo [marcadorCompleto => vistaAEncontrar].
     *               Ej: ['[VIEW_PATH:layouts/app]' => 'layouts/app']
     */


    private function findImported($content)
    {
        $imports = [];
        
        // La expresión regular para encontrar nuestros marcadores.
        $pattern = '/\[VIEW_PATH:([\w\/\.-]+)\]/';
        
        // preg_match_all encuentra todas las coincidencias, no solo la primera.
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            // $matches será un array de arrays.
            // Cada subarray contiene la coincidencia completa y los grupos capturados.
            foreach ($matches as $match) {
                // $match[0] es el marcador completo, ej: "[VIEW_PATH:layouts/app]"
                $fullMarker = $match[0];
                // $match[1] es lo que capturó el paréntesis, ej: "layouts/app"
                $viewNameToFind = $match[1];
                
                $imports[$fullMarker] = $viewNameToFind;
            }
        }
        
        return $imports;
    }

    private function compileAndStoreXslFiles(string $basePath, string $userToken, string $viewName): array
    {
        $compiledCache = $this->compiledCache;
        $keyToVar = [];
        $index = 0;

        // Asociar claves a nombres temporales, dejando el último como nombre de vista
        foreach ($compiledCache as $key => $_) {
            $keyToVar[$key] = ($index === count($compiledCache) - 1)
                ? "{$viewName}.xsl"
                : "v{$index}.xsl";
            $index++;
        }

        // Reemplazar referencias a claves por los nuevos nombres en los valores
        $tempXsl = [];
        foreach ($compiledCache as $key => $value) {
            foreach ($keyToVar as $searchKey => $replaceKey) {
                $value = str_replace($searchKey, $replaceKey, $value);
            }
            $newKey = $keyToVar[$key];
            $tempXsl[$newKey] = $value;
        }

        // Crear carpetas necesarias
        $userFolder = $basePath . '/' . $userToken;
        $viewFolder = $userFolder . '/views/' . $viewName;
        foreach ([$basePath, $userFolder, $viewFolder] as $folder) {
            if (!is_dir($folder)) {
                mkdir($folder, 0777, true);
            }
        }

        // Guardar los archivos XSL generados
        foreach ($tempXsl as $filename => $content) {
            file_put_contents($viewFolder . '/' . $filename, $content);
        }

        return $tempXsl;
    }

    /*
     * Punto de entrada para crear el documento XML a partir de los datos de la vista.
     */
    private function createDataXml()
    {
        $xml = new DOMDocument('1.0', 'UTF-8');

        $root = $xml->createElement('data');
        $xml->appendChild($root);

        // Llamamos a la función ayudante recursiva para construir el árbol XML.
        $this->buildXmlNodes($xml, $root, $this->data);
        
        $xml->formatOutput = true; //formatea el output en distintas lineas
        $xml->formatOutput = false;

        return $xml;
    }

    /**
     * Construye nodos XML de forma recursiva a partir de un array de datos.
     *
     * @param DOMDocument $xml El objeto DOMDocument principal.
     * @param DOMElement $parent El nodo padre al que se añadirán los nuevos elementos.
     * @param array $data Los datos a convertir en nodos.
     */
    private function buildXmlNodes(DOMDocument $xml, DOMElement $parent, array $data)
    {

        foreach ($data as $key => $value) {
            // Caso 1: El valor es un array.
            if (is_array($value)) {
                // Comprobamos si es un array asociativo o una lista (array numérico).
                if (array_keys($value) !== range(0, count($value) - 1)) {
                    // Es un array ASOCIATIVO (un "objeto"). Creamos un nodo con el nombre de la clave.
                    $element = $xml->createElement($key);
                    $parent->appendChild($element);
                    // Y llamamos recursivamente para construir sus hijos.
                    $this->buildXmlNodes($xml, $element, $value);
                } else {
                    // Es una LISTA (array numérico).
                    
                    // 1. Creamos el nodo CONTENEDOR (plural, ej: <scripts>).
                    $listContainer = $xml->createElement($key);
                    $parent->appendChild($listContainer);
                    
                    // 2. Iteramos sobre los ítems de la lista.
                    foreach ($value as $item) {
                        // 3. Creamos el nodo para cada ítem (singular, ej: <script>).
                        $nodeName = rtrim($key, 's');
                        $element = $xml->createElement($nodeName);

                        // Si el ítem es a su vez un array (lista de objetos), llamamos recursivamente.
                        if (is_array($item)) {
                            $this->buildXmlNodes($xml, $element, $item);
                        } else {
                            $element->appendChild($xml->createTextNode($item));
                        }
                        
                        // 4. Añadimos el ítem al CONTENEDOR.
                        $listContainer->appendChild($element);
                    }
                }
            } 
            // Caso 2: El valor es un escalar (string, int, bool).
            else {
                $element = $xml->createElement($key);
                $element->appendChild($xml->createTextNode($value));
                $parent->appendChild($element);
            }
        }
    }

    /**
     * Añade un bloque de datos JSON a la vista para que sea leído por JavaScript.
     * Genera una etiqueta <script type="application/json">.
     *
     * @param string $id El ID que tendrá la etiqueta script en el HTML.
     * @param array $data El array de datos a codificar en JSON.
     */
    public function addJson($id, array $data)
    {
        // Obtenemos los bloques JSON que ya pudieran existir.
        $currentJsonBlocks = $this->get('json_data_blocks', ''); // Usamos una clave diferente

        // Codificamos el array a JSON de forma segura.
        // Las flags que has puesto son excelentes para inyectar en HTML de forma segura.
        $json = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        // Creamos la nueva etiqueta y la añadimos a las existentes.
        $newBlock = "<script type=\"application/json\" id=\"{$id}\">{$json}</script>\n";
        
        $this->set('json_data_blocks', $currentJsonBlocks . $newBlock);
    }
}