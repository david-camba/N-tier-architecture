<?php
require_once dirname(__DIR__) . '/lib/App.php';

require_once dirname(__DIR__) . '/lib/components/Component.php';
require_once dirname(__DIR__) . '/lib/components/Service.php';

require_once dirname(__DIR__) . '/1base/services/AuthService.php';
require_once dirname(__DIR__) . '/1base/factories/ModelFactory.php';
require_once dirname(__DIR__) . '/3audi/factories/ModelFactory.php';

// Un AuthService falso que siempre "autentica" a un usuario de prueba.
class FakeAuthService extends AuthService_Base
{
    private $fakeUser;
    private $fakeUserLayer;
    private $fakeUserLevel;

    public function __construct(App $app, $fakeUser = ['id_user' => 1, 'id_dealer' => 1, 'name' => 'Test User'], int $fakeUserLayer=3, int $fakeUserLevel=3)
    {
        parent::__construct($app); 
        $this->fakeUser = (object)$fakeUser;
        $this->fakeUserLayer = $fakeUserLayer;
        $this->fakeUserLevel = $fakeUserLevel;
    }

    // Sobrescribimos el método principal para que no haga nada y devuelva la ruta tal cual.
    public function authenticateRequest(array $routeInfo): array
    {
        // Simula que el usuario está logueado
        $this->app->setContext('user', $this->fakeUser);
        $this->app->setUserLayer($this->fakeUserLayer); // Capa máxima para el test
        $this->app->setUserLevel($this->fakeUserLevel); // Nivel del usuario
        
        // Devuelve la ruta sin validación, asumiendo que el acceso está concedido.
        return $routeInfo;
    }
}

class FakeModelFactory_3Audi extends ModelFactory_3Audi{
    private PDO $testPdo;

    public function __construct(App $app, PDO $testPdo)
    {
        parent::__construct($app);
        $this->testPdo = $testPdo;
    }
    
    // Sobrescribimos todos los métodos de conexión para que usen el PDO de prueba
    protected function getMasterConnection() { return $this->testPdo; }
    protected function getDealerConnection() { return $this->testPdo; }
    protected function getProductAudiDBConnection() { return $this->testPdo; }
}

class FakeModelFactory_Base extends ModelFactory_Base{
    private PDO $testPdo;

    public function __construct(App $app, PDO $testPdo)
    {
        parent::__construct($app);
        $this->testPdo = $testPdo;
    }
    
    // Sobrescribimos todos los métodos de conexión para que usen el PDO de prueba
    protected function getMasterConnection() { return $this->testPdo; }
    protected function getDealerConnection() { return $this->testPdo; }
}


class TestApp extends App
{
    public $fakeUser = ['id_user' => 1, 'id_dealer' => 1, 'name' => 'Test User'];
    public $fakeUserLayer = 3;
    public $fakeUserLevel = 3;
    public $fakePDO;

    // ¡La magia! Sobrescribimos el constructor de servicios.
    public function buildService(string $serviceName)
    {
        require_once 'lib/components/Service.php';
        //lo lanzamos antes para cargar correctamente el archivo 
        if ($serviceName === 'AuthService') {
            // ...le devolvemos nuestra versión falsa, inyectándole el usuario de prueba.
            $fakeAuth = new FakeAuthService($this, $this->fakeUser, $this->fakeUserLayer, $this->fakeUserLevel);
            $this->cachedComponents['AuthService'] = $fakeAuth;
            return $fakeAuth;
        }
        
        // Para cualquier otro servicio, que se comporte como la clase padre.
        $service = parent::buildService($serviceName);
        return $service;
    }

    public function getComponent(string $type, string $name, array $constructorArgs = [], ?int $userLayer = null, bool $exactLayerOnly = false): object
    {
        if ($type === 'factory' && $name === 'ModelFactory') {
            if (!$this->fakePDO) {
                throw new \RuntimeException("La propiedad fakePDO no ha sido configurada en TestApp.");
            }

            if ($this->fakeUserLayer >= 3) return new FakeModelFactory_3Audi($this, $this->fakePDO); //devolvemos máxima capa
            
            return new FakeModelFactory_Base($this, $this->fakePDO);
            
        }
        
        // Para cualquier otro componente, que se comporte como la clase padre.
        $component = parent::getComponent($type, $name, $constructorArgs, $userLayer, $exactLayerOnly);
        return $component;
    }

    protected function sendResponse(Response $response)
    {
        // --- Step 1: Send headers ---
        
        // We send the HTTP status code (ej. 200, 404, 302).
        http_response_code($response->getStatusCode());

        // We send all the headwaters defined in the response object
        foreach ($response->getHeaders() as $name => $value) {
            header("{$name}: {$value}");
        }

        // --- PASO 2: ENVIAR CONTENIDO (basado en el tipo de respuesta) ---
        
        $content = $response->getContent();

        if ($response instanceof ViewResponse) {
            // If the answer is a view, we call your render method ().
            $content->render($response->getUserLayer());

        } elseif ($response instanceof JsonResponse) {
            // If it is JSON, we encode the content (which is an array/object) and printed it.
            echo json_encode($content);

        } elseif ($response instanceof FileResponse) {
            // If it is a file, we read its content and send it directly.
            // $content It is the route to the file.
            readfile($content);

        } elseif ($response instanceof RedirectResponse) {
            // For a redirection, there is no content to send. The header
            // 'Location' That we already send is all that is needed.

        } else {
            // For a base or unknown response, we simply print the content.
            echo $content;
        }
        
    }
}