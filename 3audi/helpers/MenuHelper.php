<?php
class MenuHelper_3Audi extends MenuHelper_Base
{
    public function prepareMenuData(View $view, $pageTitleKey)
    {
        $this->callParent();
        $view->set('dashboard_image_url', '/3audi/img/blackBackgroundAudi.jpg');
    }

    /**
     * Construye y devuelve el array de ítems del menú principal
     * basándose en el rol del usuario actual.
     *
     * @return array
     */
    protected function getMenuItems()
    {
        $menuItems = $this->callParent();
        $translator = $this->app->getTranslator();
        $userLevel = $this->app->getUserLevel();     
        
        $menuItems[] = ['url' => '/api/report-emissions', 'text' => $translator->get('menu_report_emissions')];
        // --- Lógica de Permisos Horizontales (Roles) ---
        // Añadimos ítems adicionales si el usuario es un Manager o superior.
        if ($userLevel >= 2) { // Nivel de Admin,Manager
            $menuItems[] = ['url' => '/app/spa-emissions', 'text' => $translator->get('menu_report_emissions_manager')];
        }       

        return $menuItems;
    }
}