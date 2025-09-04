<?php
class AuthController_Base extends Controller
{
    protected TranslatorService $translator;
    protected AuthService $service;
    protected UserSession $userSessionModel;

    public function __construct(AuthService $service, TranslatorService $translator, UserSession $userSessionModel)
    {
        $this->translator = $translator;
        $this->service = $service;
        $this->userSessionModel = $userSessionModel;        
    }

    public function showLogin()
    {
        //We can set values in our view when calling
        $view = $this->getView('login', [
            'login_page_title'                  => $this->translate('login_page_title'),
            'login_form_username_placeholder'   => $this->translate('login_form_username_placeholder'),
            'login_form_password_placeholder'   => $this->translate('login_form_password_placeholder'),
            'login_form_submit_button'          => $this->translate('login_form_submit_button'),
            'login_translation_message'         => $this->translate('login_translation_message'),
            'login_userlevel_test'              => $this->translate('login_userlevel_warning'), 
            'scripts'                           => ['/js/1base/login_base.js','/js/common/utils.js'], //we can set various scripts
        ]);
        
        //DEMOSTRATION: This part is "View" showroom, could have been include in
        //we can still to set other values later
        $brandName = $this->getConfig('general.brandName');

        //we can concatanate set()
        $view
        ->set('login_header_brand', $this->translate('login_header_brand', [$brandName]))
        ->set('login_background_image_url', '/1base/img/backgroundBase.jpeg');

        $view->remove('scripts'); //we can remove any list o value  

        $view->add('scripts','/1base/js/login_base.js'); //add will add a new value to the list "scripts". 
        // if it doesn't find one, it will create the list

        // 3. Devolvemos la vista en la respuesta para que App la gestione (en este caso renderizando)
        return $this->view($view);
    }

    public function showLogin_Seller()
    { 
        $view = $this->showLogin()->getContent();
        $view->set('login_userlevel_test', $this->translate('login_userlevel_joke') );
        return $this->view($view);
    }
      

    public function doLoginAPI()
    {
        // 1. Obtener y validar los datos de entrada del JSON.
        $input = json_decode(file_get_contents('php://input'));
        $username = trim($input->username ?? '');
        $password = $input->password ?? '';

        $authService = $this->service;
        $loginCheck = $authService->checkLogin($username, $password, $this->translator);

        if ($loginCheck['success']) {
            return $this->json(['redirectUrl' => $loginCheck['redirectUrl'], 'message' => $loginCheck['message']]);
        }else{
            return $this->jsonError($loginCheck['message'], $loginCheck['statusCode']);
        }
    }

    /**
     * Cierra la sesión del usuario actual.
     *
     * Invalida la sesión en la base de datos, elimina la cookie de sesión
     * y redirige al usuario a la página de login.
     *
     * @return RedirectResponse
     */
    public function doLogout()
    {
        $this->service->handleLogout();
    }
}