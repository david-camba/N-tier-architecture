<?php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__),2);
}

require_once BASE_PATH . '/lib/App.php';
require_once BASE_PATH . '/lib/components/Component.php';
require_once BASE_PATH . '/lib/components/Service.php';
require_once BASE_PATH . '/lib/components/ORM.php';
require_once BASE_PATH . '/lib/support/Collection.php';
require_once BASE_PATH . '/1base/services/ConfiguratorService.php';
require_once BASE_PATH . '/1base/services/TranslatorService.php';
require_once BASE_PATH . '/1base/models/ConfSession.php';
require_once BASE_PATH . '/1base/models/CarModel.php';
require_once BASE_PATH . '/1base/models/Color.php';
require_once BASE_PATH . '/1base/models/Extra.php';


/**
 * Pruebas unitarias para la clase ConfiguratorService_Base.
 *
 * @covers ConfiguratorService_Base
 */
class ConfiguratorCalculateTest extends TestCase
{
    private ConfiguratorService_Base $service;
    private MockObject|TranslatorService $translatorMock;
    private MockObject|CarModel $carModelMock;
    private MockObject|Color $colorModelMock;
    private MockObject|Extra $extraModelMock;
    private MockObject|ConfSession $confSessionMock; // Solo para type-hinting, se recrea en cada test

    private MockObject|App $appMock; // Necesitamos mockear App para el constructor de ConfSession_Base
    private MockObject|PDO $pdoMock; // Necesitamos mockear PDO para el constructor de ConfSession_Base

    /**
     * Este método se ejecuta antes de cada test.
     * Prepara el entorno creando mocks de las dependencias e instanciando el servicio.
     */
    protected function setUp(): void
    {
        // 1. Crear mocks (dobles de prueba) para cada una de las dependencias del servicio.
        $this->translatorMock = $this->createMock(TranslatorService_Base::class);
        $this->carModelMock = $this->createMock(CarModel_Base::class);
        $this->colorModelMock = $this->createMock(Color_Base::class);
        $this->extraModelMock = $this->createMock(Extra_Base::class);
        
        // El mock de ConfSession se crea dentro de cada test porque sus propiedades cambian.
        $this->confSessionMock = $this->createMock(ConfSession_Base::class);

        $this->appMock = $this->createMock(App::class); // Mockeamos App
        $this->pdoMock = $this->createMock(PDO::class); // Mockeamos PDO        

        // 2. Instanciar la clase que vamos a probar (SUT - Subject Under Test),
        // inyectando los mocks en lugar de las dependencias reales.
        $this->service = new ConfiguratorService_Base(
            $this->translatorMock,
            $this->confSessionMock, // Un mock genérico para el constructor
            $this->carModelMock,
            $this->colorModelMock,
            $this->extraModelMock
        );
    }

    /**
     * ¡MÉTODO CRÍTICO PARA MOCKERY!
     * Se ejecuta después de cada test para verificar que todas las expectativas
     * se cumplieron (ej. que un método fue llamado 'once()') y limpiar los mocks.
     * Si olvidas esto, los tests se contaminarán entre sí.
     */
    protected function tearDown(): void
    {
        \Mockery::close();
    }

    private function initMockeryTest(){
        $this->confSessionMock = \Mockery::mock(ConfSession_Base::class);
        $this->translatorMock = \Mockery::mock(TranslatorService_Base::class);
        $this->carModelMock   = \Mockery::mock(CarModel_Base::class);
        $this->colorModelMock = \Mockery::mock(Color_Base::class);
        $this->extraModelMock = \Mockery::mock(Extra_Base::class);
        $this->appMock        = \Mockery::mock(App::class);
        $this->pdoMock        = \Mockery::mock(PDO::class);

        $this->service = new ConfiguratorService_Base(
            $this->translatorMock,
            $this->confSessionMock, // Un mock genérico para el constructor
            $this->carModelMock,
            $this->colorModelMock,
            $this->extraModelMock
        );
    }

    public function testCalculateTotalWithOnlyModel(): void
    {
        // ARRANGE (Preparar el escenario)
        // 1. Configurar la sesión de prueba: solo tiene un ID de modelo.
        $this->confSessionMock = new ConfSession_Base($this->appMock, $this->pdoMock);
        $this->confSessionMock->id_model = 1;
        $this->confSessionMock->id_color = null;
        $this->confSessionMock->extras = null;

        // 2. Simular el objeto CarModel que el servicio esperaría de la base de datos.
        $mockedCarModel = (object)['price' => 50000.00];

        // 3. Instruir a nuestro mock del modelo para que, cuando se llame a su método `find(1)`,
        // devuelva nuestro objeto simulado.
        // NOTA: Probamos el método `getCarModelPrice` indirectamente a través de `calculateTotal`.
        $this->carModelMock
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($mockedCarModel);

        // ACT (Ejecutar la acción)
        // Llamar al método que queremos probar con la sesión configurada.
        $total = $this->service->calculateTotal($this->confSessionMock);

        // ASSERT (Verificar el resultado)
        // Comprobar que el total calculado es el esperado.
        $this->assertEquals(50000.00, $total);
    }

    public function testCalculateTotalWithModelAndColor(): void
    {
        // ARRANGE
        $this->confSessionMock = new ConfSession_Base($this->appMock, $this->pdoMock);
        $this->confSessionMock->id_model = 1;
        $this->confSessionMock->id_color = 10;
        $this->confSessionMock->extras = null;

        $mockedCarModel = (object)['price' => 50000.00];
        $mockedColor = (object)['price_increase' => 1200.50];

        $this->carModelMock->method('find')->with(1)->willReturn($mockedCarModel);
        $this->colorModelMock->method('find')->with(10)->willReturn($mockedColor);

        // ACT
        $total = $this->service->calculateTotal($this->confSessionMock);

        // ASSERT
        $this->assertEquals(51200.50, $total);
    }

    public function testCalculateTotalWithModelColorAndMultipleExtras(): void
    {
        // MOCKEANDO LOS MODELS  
        // Ahora, en vez de usar un modelo real Mockeado, lo que hacemos es sobreescribir el método "__get" con PHPUnit

        // 1. Mockeamos la sesión con un mock "hueco" 
        $confSessionMock = $this->getMockBuilder(ConfSession_Base::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__get'])
            ->getMock();

        // Preparamos los datos del modelo
        $sessionData = [
            'id_model' => 1,
            'id_color' => 10,
            'extras'   => '101,102',
        ];

        // 3. Programamos el __get falso para que use nuestro array de datos.
        $confSessionMock->method('__get')
            ->willReturnCallback(function (string $propertyName) use ($sessionData) {
                return $sessionData[$propertyName] ?? null;
            });

        $mockedCarModel = (object)['price' => 50000.00];
        $mockedColor = (object)['price_increase' => 1200.00];
        
        // Simular los objetos Extra individuales
        $mockedExtra1 = (object)['price' => 500.00];
        $mockedExtra2 = (object)['price' => 250.75];

        // El método `findAll` debe devolver una `Collection` que contenga nuestros extras simulados.
        $extrasCollection = new Collection([$mockedExtra1, $mockedExtra2]);

        $this->carModelMock->method('find')->with(1)->willReturn($mockedCarModel);
        $this->colorModelMock->method('find')->with(10)->willReturn($mockedColor);
        // Instruir al mock de Extra para que devuelva la colección cuando se busquen los IDs [101, 102].
        // NOTA: El `explode(',', '101,102')` dará como resultado `['101', '102']`. PHPUnit es flexible con tipos (`int` vs `string`) en `with`.
        $this->extraModelMock->method('findAll')
             ->with('id_extra', ['101', '102'])
             ->willReturn($extrasCollection);

        // ACT
        $total = $this->service->calculateTotal($confSessionMock);

        // ASSERT (50000 + 1200 + 500 + 250.75)
        $this->assertEquals(51950.75, $total);
    }

    public function testCalculateTotalReturnsZeroWhenSessionIsEmpty(): void
    {
        // ARRANGE
        $this->confSessionMock = new ConfSession_Base($this->appMock, $this->pdoMock);
        $this->confSessionMock->id_model = null;
        $this->confSessionMock->id_color = null;
        $this->confSessionMock->extras = null;

        // ACT
        $total = $this->service->calculateTotal($this->confSessionMock);

        // ASSERT
        $this->assertEquals(0.0, $total);
    }

    public function testCalculateTotalIgnoresEmptyExtrasString(): void
    {
        // ARRANGE
        $this->confSessionMock = new ConfSession_Base($this->appMock, $this->pdoMock);
        $this->confSessionMock->id_model = 1;
        $this->confSessionMock->id_color = null;
        $this->confSessionMock->extras = ''; // Un string vacío

        $mockedCarModel = (object)['price' => 50000.00];
        $this->carModelMock->method('find')->with(1)->willReturn($mockedCarModel);

        // ASSERT: Nos aseguramos de que `findAll` nunca sea llamado si el string de extras está vacío.
        $this->extraModelMock->expects($this->never())->method('findAll');
        
        // ACT
        $total = $this->service->calculateTotal($this->confSessionMock);

        // ASSERT
        $this->assertEquals(50000.00, $total);
    }
    
    public function testCalculateTotalHandlesNonExistentColorGracefully(): void
    {
        // ARRANGE
        $this->confSessionMock = new ConfSession_Base($this->appMock, $this->pdoMock);
        $this->confSessionMock->id_model = 1;
        $this->confSessionMock->id_color = 999; // Un color que no existirá
        $this->confSessionMock->extras = null;

        $mockedCarModel = (object)['price' => 50000.00];
        $this->carModelMock->method('find')->with(1)->willReturn($mockedCarModel);

        // Instruimos al mock para que devuelva `null`, simulando que no encontró el color.
        $this->colorModelMock->method('find')->with(999)->willReturn(null);

        // ACT
        $total = $this->service->calculateTotal($this->confSessionMock);

        // ASSERT: El total debe ser solo el del modelo, ya que el color no añadió precio.
        $this->assertEquals(50000.00, $total);
    }

    public function testCalculateTotalWithModelColorAndMultipleExtras_UsingMockery(): void
    {
        $this->initMockeryTest();        
        
        // ARRANGE
        // MOCKEAMOS NUESTRO MODELO CON MOCKERY, MUCHO MÁS SENCILLO
        // Esto permite los métodos set() y get() originales, que son muy poco agresivos, pero nos permite interceptar cualquier otro método que necesitamos. Es la mejor solución para la mayoría de los casos (PHPUnit con inyección de mocks funciona igual pero necesitamos mockear dependencias, PHPUnit con sobreescritura del método __get es demasiado verboso para este caso)
        $confSessionMock = \Mockery::mock(ConfSession_Base::class);
        $confSessionMock->id_model = 1;
        $confSessionMock->id_color = 10;
        $confSessionMock->extras = '101,102'; // Los extras se guardan como string

        // 2. La configuración de los datos devueltos es la misma.
        $mockedCarModel = (object)['price' => 50000.00];
        $mockedColor = (object)['price_increase' => 1200.00];
        $mockedExtra1 = (object)['price' => 500.00];
        $mockedExtra2 = (object)['price' => 250.75];
        $extrasCollection = new Collection([$mockedExtra1, $mockedExtra2]);

        // 3. La sintaxis de "programar" los mocks es más fluida.
        // `shouldReceive` implica una EXPECTATIVA: el test fallará si el método no se llama.
        $this->carModelMock->shouldReceive('find')->with(1)->once()->andReturn($mockedCarModel);
        $this->colorModelMock->shouldReceive('find')->with(10)->once()->andReturn($mockedColor);
        $this->extraModelMock->shouldReceive('findAll')->with('id_extra', ['101', '102'])->once()->andReturn($extrasCollection);

        // ACT
        $total = $this->service->calculateTotal($confSessionMock);

        // ASSERT
        $this->assertEquals(51950.75, $total);
    }    
}