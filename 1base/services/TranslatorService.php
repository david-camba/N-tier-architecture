<?php
require_once '1base/services/Service.php';

/**
 * Translator_Base
 *
 * Servicio encargado de la internacionalización (i18n).
 * Carga un archivo de idioma y proporciona un método para obtener
 * las cadenas de texto traducidas.
 */
interface TranslatorService{}
class TranslatorService_Base extends Service implements TranslatorService
{

    protected $app;

    /**
     * @var string El código del idioma actual (ej: 'es', 'en').
     */
    protected $languageCode;

    /**
     * @var array Almacena todas las cadenas de traducción para el idioma cargado.
     */
    protected $translations = [];

    /**
     * El constructor inicializa el servicio para un idioma específico.
     *
     * @param string $languageCode El código del idioma a cargar.
     */
    public function __construct($app, $languageCode)
    {
        $this->app = $app;
        $this->languageCode = $languageCode;        
        $this->loadTranslations();
    }

    /**
     * Carga y fusiona en cascada los archivos de traducción a través de la jerarquía.
     */
    private function loadTranslations()
    {
        // 1. Intentamos cargar las traducciones para el idioma solicitado.
        $this->translations = $this->mergeTranslationsForLanguage($this->languageCode);

        // 2. Si no se encontró ninguna traducción y el idioma no era el de por defecto,
        // intentamos cargar el idioma de fallback (inglés).
        if (empty($this->translations) && $this->languageCode !== 'en') {
            $this->translations = $this->mergeTranslationsForLanguage('en');
        }
    }

    /**
     * Busca todos los archivos de un idioma a través de la jerarquía y los fusiona.
     *
     * @param string $langCode El código de idioma a buscar (ej: 'es', 'en').
     * @return array El array de traducciones fusionado.
     */
    private function mergeTranslationsForLanguage($langCode)
    {
        $mergedTranslations = [];
        
        // 1. Obtenemos las capas a las que accede el usuario actual
        $totalLayers = $this->app->getUserLayer();        

        // 2. Iteramos desde el nivel más bajo (1, base) hasta el más alto.
        // Este orden es crucial para que `array_merge` sobreescriba correctamente
        // las claves genéricas con las más específicas.
        for ($layer = 1; $layer <= $totalLayers; $layer++) {
            
            // 3. Usamos findFile con 'exactLevelOnly' a 'true' para buscar el archivo
            // de traducción SOLO en este nivel exacto.

            $fileInfo = $this->app->findFiles('translation', $langCode, $layer, true, false);

            // 4. Si se encuentra un archivo de traducción para este nivel...
            if ($fileInfo) {
                // ...cargamos su contenido...
                $translations = require_once $fileInfo['path'];
                
                // ...y lo fusionamos con lo que ya teníamos.
                // Las claves en $translations sobreescribirán a las de $mergedTranslations.
                $mergedTranslations = array_merge($mergedTranslations, $translations);
            }
        }
        return $mergedTranslations;
    }

    /**
     * Obtiene una cadena de texto traducida a partir de su clave.
     *
     * @param string $key La clave de la traducción (ej: 'login.title').
     * @param array $replacements Un array de valores para sustituir en la cadena (para %s, %d, etc.).
     * @return string La cadena traducida o la propia clave si no se encuentra la traducción.
     */
    public function get($key, array $replacements = [])
    {
        // Buscamos la clave en las traducciones cargadas.
        // Si no existe, devolvemos la clave misma como fallback, lo cual es útil para depurar.
        $string = $this->translations[$key] ?? $key;

        // Si se han proporcionado reemplazos, los aplicamos usando vsprintf.
        if (!empty($replacements)) {
            // vsprintf es como sprintf pero acepta los argumentos en un array.
            return vsprintf($string, $replacements);
        }

        return $string;
    }
}