<?php


/**
 * @method mixed prepareMenuData()
 */
interface MenuHelper{} 

class MenuHelper_Base extends Helper implements MenuHelper
{

    public function __construct(TranslatorService $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Prepara una instancia de View con todos los datos comunes
     * necesarios para renderizar el layout principal de la aplicación.
     *
     * @param View $view El objeto View a preparar.
     * @param string $pageTitleKey La clave de traducción para el título de la página actual.
     */
    public function prepareMenuData(View $view, $pageTitleKey)
    {
        $user = $this->getContext('user');
        
        // --- ESTABLECE TODOS LOS DATOS DEL LAYOUT ---
        $view->set('pageTitle', $this->translate($pageTitleKey));
        $view->set($pageTitleKey, $this->translate($pageTitleKey));
        $view->set('brandName', $this->getConfig('general.brandName'));
        $view->set('page_lang', $this->getContext('language_code', 'en'));
        $view->set('menu_dashboard', $this->translate('menu_dashboard'));
        $view->set('menu_configurator', $this->translate('menu_configurator'));
        $view->set('menu_clients_legacy', $this->translate('menu_clients_legacy'));
        $view->set('login_translation_message', $this->translate('login_translation_message'));
        $view->set('menu_logout', $this->translate('menu_logout'));
        $view->set('user_greeting', $this->translate('user_greeting'));
        $view->set('login_translation_message', $this->translate('login_translation_message'));
        $view->set('dashboard_image_url', '/1base/img/backgroundBase.jpeg');        
        
        if ($user) {
            $view->set('user', $user->toArray());
        }
        
        // Llama al método que construye el menú y lo establece.
        $view->set('menuItems', $this->getMenuItems());
    }

    /**
     * Construye y devuelve el array de ítems del menú principal
     * basándose en el rol del usuario actual.
     *
     * @return array
     */
    protected function getMenuItems()
    {
        $userLevel = $this->getUserLevel();

        // --- Menú Base (para todos los usuarios logueados) ---
        $menuItems = [
            ['url' => '/app/config', 'text' => $this->translate('menu_configurator')],
            ['url' => '/app/clients.php', 'text' => $this->translate('menu_clients_legacy')],
        ];        
        
        // --- Lógica de Permisos Horizontales (Roles) ---
        // Añadimos ítems adicionales si el usuario es un Manager o superior.
        if ($userLevel >= 2) { // Nivel de Manager
            $menuItems[] = ['url' => '/api/employees-report', 'text' => $this->translate('menu_report')];
        }

        // Podríamos añadir más 'if' para otros niveles
        // if ($userLevel >= 3) { ... }

        return $menuItems;
    }

    protected function getUserLevel()
    {
        return App::getInstance()->getUserLevel();
    }
}