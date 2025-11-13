<?php
class ConfiguratorController_Base extends Controller
{
    protected TranslatorService $translator;
    protected MenuHelper $menuHelper;
    protected ConfSession $confSessionModel;
    protected ConfiguratorService $service;

    public function __construct(TranslatorService $translator, MenuHelper $menuHelper, ConfSession $confSessionModel, ConfiguratorService $service)
    {
        $this->translator = $translator;
        $this->menuHelper = $menuHelper;
        $this->confSessionModel = $confSessionModel;
        $this->service = $service;
    }

    /**
     * Show the main page of the configurator.
     * Load the "skeleton" of the spa.
     *
     * @param int|null $sessionIdToLoad (Opcional) El ID de una sesión de configuración
     *                                  específica para cargar (ej. una plantilla).
     */
    public function showConfigurator()
    {
        $user = $this->getContext('user');

        /** @var ConfSession_Base $confSessionModel */
        $confSessionModel = $this->confSessionModel;
        
        // 1. LÓGICA DE CARGA DE LA SESIÓN DE CONFIGURACIÓN - for the future
        //$activeSession = null;
        //if ($sessionIdToLoad) {
            // Caso A: Se quiere cargar una plantilla o una sesión específica.
            // Buscamos esa sesión por su ID.
        //$activeSession = $confSessionModel->find('id_conf_session', (int)$sessionIdToLoad);
            // (Aquí podríamos añadir una comprobación de seguridad para asegurar que
            // esta sesión pertenece al usuario actual, si es una plantilla).
        //}

        // Caso B: Cargar la última sesión en progreso del usuario.
        $activeSession = $confSessionModel->findLastActiveForUser($user->id_user);

        // Caso C: Si no hay ninguna sesión que cargar, creamos una nueva.
        if (!$activeSession) {
            $activeSession = $confSessionModel->createForUser($user->id_user);
        }
        
        // 2. OBTENER LA LISTA DE PLANTILLAS DEL USUARIO - funcionalidad por hacer
        //$templates = $confSessionModel->findTemplatesForUser($user->id_user);
        //$view->addJson('templates-data', $templates->toArray());

        // 3. PREPARAR Y DEVOLVER LA VISTA
        $view = $this->getView('configurator', $this->_getConfiguratorViewTranslations());
        $view->add('page_css_class', 'page-order-configurator');
        
        // Pasamos la sesión activa y las plantillas a la vista.
        // El JS las leerá para saber el estado inicial.
        $view->addJson('active-session-data', $activeSession->toArray());        


        //$view->add('scripts', '/1base/js/configurator_spa.js'); //dejaremos de usar nuestro viejo configurador para usar el framework frontend

        $view->add('scripts', ['src' => '/1base/js/order-configurator/index.js', 'type' => 'module']);

        // 1. Definimos todas las claves que nuestra SPA va a necesitar.
        // La clave del array (izquierda) es la que usará el JS (ej: props.$translations.step1_title).
        // El valor del array (derecha) es la clave real de tu fichero de traducciones.
        $jsTranslationKeys = $this->_getConfiguratorJSTranslations();
        
        // 2. Creamos un array con las traducciones reales.
        $jsTranslations = [];
        foreach ($jsTranslationKeys as $jsKey => $translationKey) {
            $jsTranslations[$jsKey] = $this->translate($translationKey);
        }

        // 3. Inyectamos este array en la página usando nuestro método `addJson`.
        $view->addJson('configurator-translations', $jsTranslations);
        
        $this->menuHelper->prepareMenuData($view,'configurator_title');        
        return $this->view($view);
    }

    /**
     * API Endpoint para obtener la lista de modelos de vehículos disponibles.
     * Devuelve los modelos en formato JSON.
     */
    public function getModelsAPI()
    {
        try {            
            $carModels = $this->service->_getAllCarModels();
            return $this->json(['models' => $carModels]);

        } catch (Exception $e) {
            // Manejar cualquier error de base de datos o de otro tipo.
            return $this->jsonError('No se pudieron cargar los modelos.', 500);
        }
    }

/**
     * API: Guarda la selección del modelo (Paso 1) y DELEGA la
     * construcción de la respuesta para el paso 2.
     *
     * @param int $sessionId
     * @return JsonResponse_Base
     */
    public function saveModelsAPI($sessionId)
    {
        try {
            // 1. Validar la sesión y la entrada del usuario.
            $confSession = $this->_getAndValidateSession($sessionId);
            $input = json_decode(file_get_contents('php://input'));
            $carModelId = (int)($input->modelId ?? 0);

            $modelExist = $this->service->carModelExists($carModelId);
            if (!$modelExist) {
                return $this->jsonError('El modelo seleccionado no existe.', 400);
            }

            // 2. Lógica de guardado (su única responsabilidad real).
            $confSession->id_model = $carModelId;
            $confSession->id_color = null;
            $confSession->extras = null;
            $confSession->save();

            // 3. ¡LA DELEGACIÓN! Llama al otro método de API para que construya la respuesta.
            // Le pasa el ID de la sesión que acaba de actualizar.
            return $this->getColorsForSessionAPI($sessionId);

        } catch (Exception $e) {
            error_log("Error en saveStep1API: " . $e->getMessage());
            return $this->jsonError('Error al procesar la selección.', 500);
        }
    }

    /**
     * API: Resetea una sesión de configuración al estado inicial (solo modelo).
     */
    public function resetToStep1($sessionId)
    {
        try {
            $confSession = $this->_getAndValidateSession($sessionId);
            // Reseteamos toda la configuracion
            $confSession->resetConfiguration();

            // Obtenemos de nuevo la lista de modelos
            $carModels = $this->service->_getAllCarModels();

            return $this->json([
                'activeSession' => $confSession->toArray(),
                'models' => $carModels,
            ]);

        } catch (Exception $e) {
            error_log("Error en resetToStep1: " . $e->getMessage());
            return $this->jsonError('Error al resetear la sesión.', 500);
        }
    }

    /**
     * API: Obtiene y prepara todos los datos necesarios para el Paso 2 (colores).
     * Es ahora el único punto de verdad para esta respuesta.
     *
     * @param int $sessionId
     * @return JsonResponse_Base
     */
    public function getColorsForSessionAPI($sessionId)
    {
        try {
            // 1. Validar la sesión.
            $confSession = $this->_getAndValidateSession($sessionId);
            if (!$confSession->id_model) {
                 return $this->jsonError('Se debe seleccionar un modelo primero.', 400);
            }
            
            // 2. Lógica de obtención de datos.
            // $colors = $this->getModel('Color')->findAll('id_model', $confSession->id_model);

            $colors = $this->service->getColorsForCarModel($confSession->id_model);

            $priceDetails = $this->service->getPriceDetails($confSession);

            // 3. Lógica de formateo y traducción.
            $translatedColors = $this->service->prepareColors($colors);

            // 4. Devolver la respuesta JSON final.
            return $this->json([
                'activeSession' => $confSession->toArray(), // Devolvemos el estado actualizado
                'colors' => $translatedColors,
                'priceDetails' => $priceDetails,
            ]);

        } catch (Exception $e) {
            error_log("Error en getColorsForSessionAPI: " . $e->getMessage());
            return $this->jsonError('No se pudieron cargar los datos de los colores.', 500);
        }
    }

    /**
     * API: Guarda la selección del color (Paso 2) y devuelve los extras disponibles.
     *
     * @param int $sessionId El ID de la sesión de configuración a actualizar.
     */
    public function saveColorsAPI($sessionId)
    {
        try {
            $confSession = $this->_getAndValidateSession($sessionId);

            $input = json_decode(file_get_contents('php://input'));
            $colorId = (int)($input->colorId ?? 0);

            // ANTES de guardar, debemos comprobar si el color es válido PARA ESTE MODELO.
            $color = $this->service->getColor($colorId);
            
            if (!$color || $color->id_model !== $confSession->id_model) {
                // ¡ATAQUE DETECTADO! O un simple error.
                // El color no existe, o no pertenece al modelo que se está configurando (A5).
                return $this->jsonError('El color seleccionado no es válido para este modelo.', 400); // Bad Request
            }

            // Actualizamos la sesión con el color
            $confSession->id_color = $colorId;
            $confSession->save();

            $include = $_GET['include'] ?? null;
            if ($include === 'next-step') {
                return $this->getExtrasForSessionAPI($sessionId, $confSession);
            }
            return $this->json();         

        } catch (Exception $e) {
            error_log("Error en saveStep2: " . $e->getMessage());
            return $this->jsonError('Error al procesar la selección.', 500);
        }
    }
    
    /**
     * API: Obtiene la lista de extras compatibles con un modelo específico.
     */
    public function getExtrasForSessionAPI($sessionId, $confSession=null)
    {
        try {
            if($confSession === null) $confSession = $this->_getAndValidateSession($sessionId);
            
            // Obtenemos los extras para el modelo de esta sesión.
            $carModelId = $confSession->id_model;
             
            $extras = $this->service->getExtrasForCarModel($carModelId);

            $translatedExtras = $this->service->prepareExtras($extras);

            $priceDetails = $this->service->getPriceDetails($confSession);

            return $this->json([
                'activeSession' => $confSession->toArray(),
                'extras' => $translatedExtras->toArray(),
                'priceDetails' => $priceDetails,                
            ]);

        } catch (Exception $e) {
            error_log("Error en getExtrasForModelAPI: " . $e->getMessage());
            return $this->jsonError('No se pudieron cargar los extras.', 500);
        }
    }

    /**
     * API: Guarda la selección de extras (Paso 3) y devuelve
     * todos los datos necesarios para la página de resumen.
     *
     * @param int $sessionId El ID de la sesión de configuración a actualizar.
     */
    public function saveExtrasAPI($sessionId)
    {
        try {
            $confSession = $this->_getAndValidateSession($sessionId);

            $input = json_decode(file_get_contents('php://input'));
            
            $extraIds = (array)($input->extraIds ?? []);

            //Si nos han pedido que limpiemos los extras, dejamos el campo a null
            if(count($extraIds) > 0 && $extraIds[0] === 'cleanExtras'){
                $confSession->extras = null;
                $confSession->save();
            }
            else{
                $this->service->saveExtras($confSession, $extraIds); 
            }

            $include = $_GET['include'] ?? null;
            if ($include === 'next-step') {
                return $this->getSummaryForSessionAPI($sessionId, $confSession, $extraIds);
            }
            return $this->json();      

        } catch (Exception $e) {
            return $this->jsonError('Error al procesar la selección de extras.', 500);
        }
    }

    /**
     * API: Obtiene el resumen de la configuración de un modelo.
     */
    public function getSummaryForSessionAPI($sessionId, $confSession = null, $extraIds = null)
    {
        try{
            if($confSession === null) $confSession = $this->_getAndValidateSession($sessionId);

            // --- 2. RECOPILAR TODOS LOS DATOS PARA EL RESUMEN ---
            $configuratorService = $this->service;            
            $priceDetails = $configuratorService->getPriceDetails($confSession); 
            $summaryData = $configuratorService->getSummaryData($confSession);
            
            return $this->json([
                'activeSession' => $confSession->toArray(),
                'summary' => $summaryData,
                'priceDetails' => $priceDetails,
            ]);

        } catch (Exception $e) {
            return $this->jsonError('Error al procesar la selección.', 500);
        }
    }
    /**
     * Helper de seguridad: Obtiene y valida una sesión de configuración.
     *
     * Comprueba que la sesión exista, pertenezca al usuario actual y no
     * haya sido asignada. Si alguna comprobación falla, lanza una excepción.
     *
     * @param int $sessionId El ID de la sesión a validar.
     * @return ConfSession_Base El objeto de sesión validado.
     * @throws Exception Si la validación falla.
     */
    protected function _getAndValidateSession($sessionId)
    {
        $user = $this->getContext('user');
        if (!$user) {
            // Esto no debería pasar si handleSecurity funciona, pero es una buena defensa.
            throw new Exception("Acceso no autorizado.", 401);
        }

        $confSession = $this->confSessionModel->find((int)$sessionId);

        // Comprobación 1: ¿Existe la sesión?
        if (!$confSession) {
            throw new Exception("La sesión de configuración no existe.", 404); // Not Found
        }

        // Comprobación 2: ¿Pertenece al usuario actual?
        if ($confSession->id_user !== $user->id_user) {
            throw new Exception("No tienes permiso para acceder a esta sesión.", 403); // Forbidden
        }

        // Comprobación 3: ¿Ya ha sido asignada a un cliente?
        if ($confSession->assigned == 1) {
            throw new Exception("Esta configuración ya ha sido finalizada y no puede modificarse.", 409); // Conflict
        }
        
        // Si todas las comprobaciones pasan, devolvemos la sesión.
        return $confSession;
    }


    protected function _getConfiguratorViewTranslations(): array
    {
    return [
            // Traducciones
            'configurator_title'                    => $this->translate('configurator_title'),
            'configurator_templates_label'          => $this->translate('configurator_templates_label'),
            'configurator_templates_default_option' => $this->translate('configurator_templates_default_option'),
            'configurator_no_templates_message'     => $this->translate('configurator_no_templates_message'),
            'configurator_step1_title'              => $this->translate('configurator_step1_title'),
            'configurator_step2_title'              => $this->translate('configurator_step2_title'),
            'configurator_step3_title'              => $this->translate('configurator_step3_title'),
            'configurator_step4_title'              => $this->translate('configurator_step4_title'),
            'configurator_loading_message'          => $this->translate('configurator_loading_message'),
            'configurator_price_label'              => $this->translate('configurator_price_label'),
            'configurator_save_template_button'     => $this->translate('configurator_save_template_button'),
            'configurator_assign_client_button'     => $this->translate('configurator_assign_client_button'),
        ];
    }

    protected function _getConfiguratorJSTranslations() : array
    {
    return [
            // --- Títulos y mensajes generales ---
            'title'            => 'configurator_title',
            'loading_message'  => 'configurator_loading_message',
            'price_label'      => 'configurator_price_label', // Para 'Precio Total' o 'Estimated Total Price'

            // --- Pasos del configurador (Steps) ---
            'step1_title'      => 'configurator_step1_title',    // 'Choose a model'
            'step2_title'      => 'configurator_step2_title',    // 'Choose a color'
            'step3_title'      => 'configurator_step3_title',    // 'Extras'
            'step4_title'      => 'configurator_step4_title',    // 'Assign Client' (o 'Resumen' si cambias la traducción)

            // --- Mensajes de carga específicos ---
            'loading_models'   => 'configurator_loading_models', // 'Loading models...'
            'loading_colors'   => 'configurator_loading_colors', // 'Loading colors...'

            // --- Botones ---
            'next_button'          => 'configurator_next_button',          // 'Next' / 'SIGUIENTE'
            'back_button'          => 'configurator_back_button',          // 'Back' / 'ANTERIOR'
            'save_template_button' => 'configurator_save_template_button', // 'Save as Template'
            'assign_client_button' => 'configurator_assign_client_button', // 'Assign to Client' / 'ASIGNAR A CLIENTE'

            // --- Plantillas (Templates) ---
            'templates_label'         => 'configurator_templates_label',
            'templates_default_option'=> 'configurator_templates_default_option',
            'no_templates_message'    => 'configurator_no_templates_message',

            // --- Textos para componentes específicos (identificados en tu DOM) ---
            // NOTA: Estas claves no existen en tu fichero de traducciones.
            // Deberías añadirlas para que tu app sea 100% traducible.
            // Te sugiero las claves de la derecha.
            'extras_picker_title' => 'configurator_extras_picker_title', // Para: 'Escoge tantos extras como quieras'
            'model_card_price_prefix' => 'configurator_model_card_price_prefix', // Para: 'Desde '
            'model_picker_title' => 'configurator_model_picker_title', // Para: 'Elige tu modelo favorito'
            'summary_title' => 'configurator_summary_title', // Para: 'Resumen de la compra'
            'summary_final_price' => 'configurator_summary_final_price', // Para: 'Precio Final'
            'summary_no_extras' => 'configurator_summary_no_extras', // Para: 'Sin extras'
        ];
    }
}