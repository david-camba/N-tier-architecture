<?php
class DashboardController_2GrupoVW extends DashboardController_Base
{  

    public function showDashboard()
    {
        $view = $this->parentResponse();
        $view->set('dashboard_vw_message', $this->translate('dashboard_vw_message'));
        $view->set('dashboard_image_url','/2vwgroup/img/BackgroundVW.jpg');
        return $this->view($view);
    }
}