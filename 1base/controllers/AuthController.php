<?php
class AuthController_Base extends Controller
{
    protected TranslatorService $translator;
    protected AuthService $service;

    public function __construct(AuthService $service, TranslatorService $translator)
    {
        $this->translator = $translator;
        $this->service = $service;     
    }

    public function showLogin()
    {
        //We can set values in our view when calling
        $view = $this->getView('login', [
            'login_page_title'                  => $this->translate('login_page_title'),
            'login_form_username_placeholder'   => $this->translate('login_form_username_placeholder'),
            'login_form_password_placeholder'   => $this->translate('login_form_password_placeholder'),
            'login_form_submit_button'          => $this->translate('login_form_submit_button'),
            'login_form_guest_access'          => $this->translate('login_form_guest_access'),            
            'login_translation_message'         => $this->translate('login_translation_message'),
            'login_userlevel_test'              => $this->translate('login_userlevel_warning'), 
            'login_explanation'              => $this->translate('login_explanation'), 
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

    public function createAuthUser() : never
    {
        $authService = $this->service;
        $userData = $authService->createGuestUser(); //creamos el usuario, esto nos devolverá su username y contraseña

        if (!$userData) throw new Exception('Could not create guest user.');

        //Logueamos al usuario
        $username = $userData['username'];
        $password = $userData['password']; 
        $loginCheck = $authService->checkLogin($username, $password, $this->translator); //como los datos serán correctos, el loginCheck debería ser true 

        //Si fallo mandamos error
        if(!$loginCheck['success']) throw new Exception('Guest user could not be logged.');

        //Redirige al usuario a la demo de LifeTree o a la homepage, según los parámetros de la URL
        $this->redirectToDemo();
    }

    protected function redirectToDemo() : never
    {
        /*Ejemplos URLs: 
            • Homepage en EN: guest-access?lang=en
            • Frontend en ES: guest-access?redirect=LifeTree&lang=es            
        */

        //Obtenemos el idioma pedido o seteamos ingles por defecto
        $langCode = $_GET['lang'] ?? 'en';
        $langCode = preg_replace('/[^a-z]/i', '', $langCode); //evitamos inyecciones
        $setLanguage = '?lang='.$langCode;

        //comprobamos a ver si se nos ha pedido redirección
        $redirect = $_GET['redirect'] ?? '';
        $redirect = preg_replace('/[^a-z]/i', '', $redirect); //evitamos inyecciones

        //Si se ha solicitado, redirigimos a la demo del LifeTree framework directamente
        if ($redirect === 'LifeTree') 
            $this->redirect('/app/config'.$setLanguage, 301);
        
        //Sino, le redirigimos a la homepage
        $this->redirect('/app'.$setLanguage, 301); 
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