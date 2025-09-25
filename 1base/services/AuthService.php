<?php
/**
 * @method mixed handleLogout()
 * @method mixed checkLogin()
 */
interface AuthService{}

class AuthService_Base extends Service implements AuthService
{
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app; 
        /** We'll use App:
         *  1- To set the Context, UserLayer and UserLevel 
         *  2- Handle redirections if needed
         *  3- Get the models: Request, User, UsserSession without caching the factory when it's not needed
         */        
        
        //NEVER build TranslatorService here.
        //If we build it here, it will mess with the translations because the layers are not set yet
    }

    /** 
    * The main security method. Orchestra Authentication and 
    * Access control for the current request. 
    * 
    * @param Array $routeInfo The original router action plan. 
    * @return Array The final and validated action plan. 
    */
    public function authenticateRequest(array $routeInfo): array
    {
        // 1. Intentamos obtener el usuario autenticado.
        $authenticatedUser = $this->getAuthenticatedUser();
        $authenticatedRoute = $this->setUserContextAndAuthenticateRoute($routeInfo, $authenticatedUser);

        return $authenticatedRoute;
    }

    /**
     * Set user context for the app and return the authenticated route.
     *
     * @param array $routeInfo
     * @param User|null $authenticatedUser
     * @return array
     */
    protected function setUserContextAndAuthenticateRoute(array $routeInfo, ?User $authenticatedUser): array
    {
        /** @var User_Base|null $authenticatedUser */
        
        // 1. We keep the user (or null) in the context of the app.
        $this->app->setContext('user', $authenticatedUser);

        // 2. Apply the access rules.
        if ($authenticatedUser) {
            // Logged user.
            $userLayer = $authenticatedUser->fetchUserLayer();
            $this->setUserLayer($userLayer);
        
            $userLevel = $authenticatedUser->fetchUserLevel();   
            $this->setUserLevel($userLevel);
            
            $currentPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
            if ($currentPath === '/login') {
                // If you are login and go to login, we redirect it.
                $this->redirect('/app', 301);
            }
            // If not, you have access.
            return $routeInfo;

        } else {
            // User not logged (guest).

            //$this->setUserLayer(1); // Guest level
            $this->setUserLayer(3); //for this app, we'll use the third layer for no logged users

            $this->setUserLevel(0); // Guest level

            // We check if the route is public.
            if ($this->isPublicRoute($routeInfo)) {               
                return $routeInfo;
            } else {
                // If not, we send to login
                $this->redirect('/login');
            }
        }
    }

    /** 
    * Verify the session cookie and the database to authenticate the user. 
    * 
    * @return array | null returns an array with user data and session 
    * If the authentication is successful, or null if it is not. 
    */
    protected function getAuthenticatedUser() : ?User
    {
        // 1. Obtain the token from the cookie of the browser.
        $token = $_COOKIE['session_token'] ?? null;
        if (!$token) {
            $this->app->getModel('Request',[],1,false)->log("NO SESSION", null);            
            return null; // Si no hay cookie, no hay usuario.
        }
        // 2. Obtain a "search engine" for the User Session model.
        $session = $this->app->getModel('UserSession',[],1,false)->find($token, 'token');

        // 4. Validate the session found.
        if (!$session) {
            $this->app->getModel('Request',[],1,false)->log("TOKEN INVALID", $session);
            // Token is not valid or is not in the BBDD. 
            // (Here we could erase the user's invalid cookie).
            setcookie('session_token', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true,'samesite' => 'Lax' //'domain' => 'dominio.com', //'secure' => true,
            ]);
            unset($_COOKIE['session_token']);
            return null;
        }

        // 5. Check if the session has expired.
        $now = new DateTime();
        $expiration = new DateTime($session->expiration_date);

        if ($now > $expiration) {      
            setcookie('session_token', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true,'samesite' => 'Lax' //'domain' => '.tudominio.com', //'secure' => true,
            ]); 
            unset($_COOKIE['session_token']); // We delete cookie
            $this->app->getModel('Request',[],1,false)->log("EXPIRED SESSION", $session);
            return null;
        }

        // 6. (Optional but recommended) Check IP and User Agent. 
        // This prevents the theft of the cookie by session.
        if ($session->ip !== $_SERVER['REMOTE_ADDR'] || $session->user_agent !== $_SERVER['HTTP_USER_AGENT']) {
            // Safety alert! Someone could have stolen the cookie.
            $this->app->getModel('Request',[],1,false)->log("SESSION_HIJACK_ATTEMPT", $session);
            $session->delete(); // Invalidate this session in the BBDD
            return null;
        }

        // 7. Success! The session is valid. 
        // We return the crucial information for the rest of the application.
        $this->app->getModel('Request',[],1,false)->log("SUCCESS", $session); // We keep the request

        $user = $this->app->getModel('User',[],1,false)->find($session->id_user);
        $user->token = $token; // We add the token
        // We eliminate sensitive data      
        unset($user->password);
        unset($user->tries);

        return $user; // We return the user
    }
    
    
    /**
     * Check if a route is on the "white list" of public access.
     *
     * @param array $routeInfo
     * @return boolean
     */
    protected function isPublicRoute(array $routeInfo) : bool
    {
        // TO EXTEND: move to security.php 
        if ($routeInfo['type'] === 'legacy_script') {
            $publicRoutes = ['legacy/bienvenida.php'];
            return in_array($routeInfo['script_path'], $publicRoutes);
        }
        
        if ($routeInfo['type'] === 'mvc_action') {
            $publicRoutes = ['AuthController:showLogin', 'AuthController:doLoginAPI'];
            $currentRoute = "{$routeInfo['controller']}:{$routeInfo['action']}";
            return in_array($currentRoute, $publicRoutes);
        }

        return false;
    }

    public function checkLogin(string $username, string $password, TranslatorService $translator) : array //JSON
    {
        /** @var TranslatorService_Base $translator */
        
        // Escenario de Error: Datos Faltantes
        if (empty($username) || empty($password)) {
            $errorMessage = $translator->get('login_api_error_missing_fields');
            // Devolvemos un objeto JsonResponse con código 400 Bad Request.
            return [
                "success" => false,
                "message" => $errorMessage,
                "statusCode" => 400,
            ];
        }

        // 2. Usar el modelo User para encontrar al usuario por su username.
        /** @var User_Base|null $user */
        $user =  $this->app->getModel("User")->find($username, 'username');

        // Escenario de Fracaso: Usuario no encontrado
        if (!$user) {
            $errorMessage = $translator->get('login_api_error_credentials');
            // Devolvemos un 401 Unauthorized para no dar pistas a los atacantes.
            return [
                "success" => false,
                "message" => $errorMessage,
                "statusCode" => 401,
            ];
        }

        // Escenario de Error: Cuenta bloqueada
        if ($user->tries >= 5) {
            $user->incrementLoginTries();
            $errorMessage = $translator->get('login_api_error_account_locked');
            // Devolvemos un 429 Too Many Requests.
            return [
                "success" => false,
                "message" => $errorMessage,
                "statusCode" => 429,
            ];
        }

         // 3. Verificar la contraseña.
        if (password_verify($password, $user->password)) {            
            // --- ÉXITO ---
            $user->resetLoginTries();     
            
            /** @var UserSession_Base $userSession */
            $userSession = $this->app->getModel('UserSession');
            $token = $userSession->createForUser($user->id_user, $user->id_dealer);

            // 5. Enviar el token al navegador en una cookie segura.
            $cookieOptions = [
                'expires' => time() + (86400 * 30), // 30 días
                'path' => '/',
                // 'domain' => '.yourdomain.com', // Descomentar en producción
                // 'secure' => true,   // Descomentar en producción (solo enviar por HTTPS)
                'httponly' => true, // El JS no puede acceder a la cookie, crucial para la seguridad
                'samesite' => 'Lax' // Protección contra ataques CSRF
            ];
            setcookie('session_token', $token, $cookieOptions);

            return [
                "success" => true,
                "message" => $translator->get('login_api_success_redirecting'),
                'redirectUrl' => '/app'
            ];
        }
        else{
            // --- FRACASO: Contraseña incorrecta ---
            $user->incrementLoginTries();
            // Devolvemos un 401 Unauthorized.
            return [
                "success" => false,
                "message" => $translator->get('login_api_error_credentials'),
                "statusCode" => 401,
            ];
        }        
    }

    public function handleLogout()
    {
        // 1. Obtener el token de la cookie.
        $token = $_COOKIE['session_token'] ?? null;

        if ($token) {
            // 2. Usar el modelo para encontrar la sesión por el token.
            $session = $this->app->getModel('UserSession')->find($token, 'token');

            if ($session) {
                // 3. Si la encontramos, la eliminamos de la base de datos.
                $session->delete();
            }

            // 4. "Matamos" la cookie en el navegador, diciéndole que expire en el pasado.
            setcookie('session_token', '', ['expires' => time() - 3600, 'path' => '/']);
        }
        
        // 5. Limpiamos completamente la sesión de PHP.
        session_unset();
        session_destroy();

        // 6. Devolvemos una respuesta de redirección a la página de login.
        return $this->redirect('/login');
    }

    protected function setUserLayer($userLayer): void
    { 
        $this->app->setUserLayer($userLayer);
    }

    protected function setUserLevel($userLevel) : void
    {
        $this->app->setUserLevel($userLevel);
    }

    public function setContext($key, $value)
    {
        return $this->app->setContext($key, $value);
    }

    protected function redirect($url, $statusCode=200) : never
    {
        $this->app->redirect($url, $statusCode);
    }
}