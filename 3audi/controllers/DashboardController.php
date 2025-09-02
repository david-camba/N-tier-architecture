<?php
class DashboardController_3Audi extends DashboardController_2GrupoVW 
{
    public function showDashboard()
    {
        $view = $this->parentResponse();
        $view->set('dashboard_image_url','/3audi/img/blackBackgroundAudiPro.jpg');
        $view->add('scripts','/3audi/js/dashboard_emissions_3audi.js');
        return $this->view($view);
    }

    public function showDashboard_Manager()
    {
        $view = $this->parentResponse();

        //we can remove scripts by doing this if necessary
        //$view->removeValue('scripts', '/3audi/js/dashboard_emissions_3audi.js');

        return $this->view($view);
    }

    public function showDashboard_Admin()
    {
        return $this->showDashboard();
    }
}