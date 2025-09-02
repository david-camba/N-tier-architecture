<?php
class AuthController_3Audi extends AuthController_2GrupoVW
{
    public function showLogin() {
        $view = $this->parentResponse();        
        $view->set([
            'login_3_header_subtitle' => $this->translate('login_3_header_subtitle'),
            'login_background_image_url' => '3audi/img/backgroundAudi.jpeg',
        ]);
        
        return $this->view($view);           
    }
}