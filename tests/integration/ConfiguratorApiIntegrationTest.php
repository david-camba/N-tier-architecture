<?php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

// Definimos la constante de ruta base
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

// Requerimos nuestra nueva TestApp y las clases que vayamos a usar
require_once BASE_PATH . '/tests/TestApp.php'; // Incluye TestApp y FakeAuthService
require_once BASE_PATH . '/lib/Router.php';
require_once BASE_PATH . '/lib/response/Response.php';
require_once BASE_PATH . '/lib/response/JsonResponse.php';
require_once BASE_PATH . '/1base/factories/ModelFactory.php';
require_once BASE_PATH . '/3audi/factories/ModelFactory.php';

/**
 * Prueba de integración REFACTORIZADA usando TestApp.
 * @covers ConfiguratorController_Base::saveModelsAPI
 */
class ConfiguratorApiIntegrationTest extends TestCase
{
    private static $pdo = null;
    private $app;
    private static array $config;
    private static $router;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = new PDO('sqlite::memory:');
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Crear el schema de la BBDD de prueba
        self::$pdo->exec("CREATE TABLE conf_sessions (id_conf_session INTEGER PRIMARY KEY, id_user INTEGER, id_model INTEGER, id_color INTEGER, extras TEXT, assigned INTEGER, template INTEGER, last_modification TEXT)");
        self::$pdo->exec("CREATE TABLE models (id_model INTEGER PRIMARY KEY, name TEXT, price REAL, emissions TEXT)");
        self::$pdo->exec("CREATE TABLE colors (id_color INTEGER PRIMARY KEY, id_model INTEGER, name TEXT, img TEXT, price_increase REAL)");
        
        self::$config = require BASE_PATH . '/config.php';

        self::$router = new Router();
    }

    protected function setUp(): void
    {
        // Limpiar y sembrar la base de datos para cada test
        self::$pdo->beginTransaction();
        self::$pdo->exec("DELETE FROM conf_sessions");
        self::$pdo->exec("INSERT INTO conf_sessions (id_conf_session, id_user) VALUES (1, 1)");
        self::$pdo->exec("INSERT INTO models (id_model, name, price) VALUES (1, 'Audi A8', 90000.0)");
        self::$pdo->exec("INSERT INTO colors (id_color, id_model, name, price_increase) VALUES (10, 1, 'color.black_mythos', 0.0)");
        self::$pdo->exec("INSERT INTO colors (id_color, id_model, name, price_increase) VALUES (11, 1, 'color.silver_floret', 1200.0)");
        // Y un color para otro modelo, para asegurar que no se devuelve por error
        self::$pdo->exec("INSERT INTO colors (id_color, id_model, name, price_increase) VALUES (20, 2, 'color.red_matador', 1500.0)");

        $this->app = new TestApp(self::$config, self::$router);
        $this->app->fakePDO = self::$pdo;

        
        }

    protected function tearDown(): void
    {
        // IMPORTANTE: Restaurar el wrapper original de 'php' para no afectar a otros tests
        stream_wrapper_restore('php');
        self::$pdo->rollBack();
    }

    public function testSaveModelApiUpdatesSessionAndReturnsCorrectData(): void
    {
        // ARRANGE 
        // 1- Personalizar el usuario/capa para este test específico
        $this->app->fakeUserLayer = 3;
        $this->app->fakeUserLevel = 2; // Simular un Manager    
        
        // 2. "Engañar" al Router simulando las variables de servidor
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $_SERVER['REQUEST_URI'] = '/api/configurator/session/1/model';

        // 3. Preparar el payload de la llamada API para que pueda ser recuperado en "php://input"
        $payload = ['modelId' => 1];
        
        // Registramos nuestro wrapper para que intercepte 'php://'
        stream_wrapper_unregister('php'); // Buena práctica: desregistrar primero
        stream_wrapper_register('php', MockPhpInputStreamWrapper::class);

        // Asignamos el contenido que queremos que lea file_get_contents('php://input')
        MockPhpInputStreamWrapper::$data = json_encode($payload);

        // ACT
        // 3. Capturamos la salida de la respuesta.
        ob_start();
        $this->app->run();
        $output = ob_get_clean();

        // ASSERT
        // 1. Verificar la respuesta HTTP
        $responseData = json_decode($output, true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals(1, $responseData['data']['activeSession']['id_model']);
        $this->assertArrayHasKey('colors', $responseData['data']);

        // 2. Verificar el estado final de la base de datos
        $stmt = self::$pdo->query("SELECT id_model FROM conf_sessions WHERE id_conf_session = 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(1, $result['id_model']);
    }

    public function testSaveModelApiUnder3LayerCannotAccessAudiProductDatabase(): void
    {
        // ARRANGE 
        $this->app->fakeUserLayer = 2; // VW layer
        $this->app->fakeUserLevel = 2; // Simular un Manager    
        
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $_SERVER['REQUEST_URI'] = '/api/configurator/session/1/model';

        // 3. Preparar el payload de la llamada API para que pueda ser recuperado en "php://input"
        $payload = ['modelId' => 1];
        
        // Registramos nuestro wrapper para que intercepte 'php://'
        stream_wrapper_unregister('php'); // Buena práctica: desregistrar primero
        stream_wrapper_register('php', MockPhpInputStreamWrapper::class);

        // Asignamos el contenido que queremos que lea file_get_contents('php://input')
        MockPhpInputStreamWrapper::$data = json_encode($payload);

        // ASSERT EXCEPCION
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Tipo de conexión desconocido: productAudi");

        // ACT
        // 3. Capturamos la salida de la respuesta.
        //ob_start();
        $this->app->run();
        //$output = ob_get_clean();

                

    }
}

/**
 * Un stream wrapper para simular php://input en tests.
 * Nos permite inyectar el contenido del cuerpo de una petición.
 */
class MockPhpInputStreamWrapper
{
    private int $position = 0;
    public static string $data = '';
    
    // Necesario para que el wrapper funcione con file_get_contents
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $this->position = 0;
        return true;
    }

    public function stream_read(int $count): string|false
    {
        $chunk = substr(self::$data, $this->position, $count);
        $this->position += strlen($chunk);
        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen(self::$data);
    }

    // Métodos boilerplate que deben existir
    public function stream_stat(): array { return []; }
    public function url_stat(string $path, int $flags): array { return []; }
    public function stream_close(): void {}
    public function stream_flush(): bool { return true; }
    public function stream_tell(): int { return $this->position; }
    public function stream_seek(int $offset, int $whence = SEEK_SET): bool { return false; } // No necesario para este caso
}