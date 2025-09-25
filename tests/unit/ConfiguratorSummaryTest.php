<?php
use PHPUnit\Framework\TestCase;
use Mockery\MockInterface;
use Mockery as m; // Alias 'm' para Mockery para mayor brevedad

// Definir una constante para la ruta base si no existe (útil para ejecutar desde la raíz)
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__,2));
}

// Incluir las clases necesarias. En un proyecto con autoloader, esto no sería necesario.
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
 * Pruebas unitarias para el método getSummaryData de ConfiguratorService.
 *
 * @covers ConfiguratorService_Base::getSummaryData
 * @covers ConfiguratorService_Base::_translateAndFormatColor
 * @covers ConfiguratorService_Base::_translateAndFormatExtras
 */
class ConfiguratorSummaryTest extends TestCase
{
    // Propiedades para almacenar el SUT y sus dependencias mockeadas
    private ConfiguratorService_Base $service;
    private MockInterface|TranslatorService $translatorMock;
    private MockInterface|CarModel $carModelMock;
    private MockInterface|Color $colorModelMock;
    private MockInterface|Extra $extraModelMock;
    private MockInterface|ConfSession $confSessionMock;

    /**
     * Se ejecuta antes de CADA test. Prepara un entorno limpio y aislado.
     */
    protected function setUp(): void
    {
        // Creamos mocks para todas las dependencias usando Mockery
        $this->translatorMock = m::mock(TranslatorService_Base::class);
        $this->carModelMock = m::mock(CarModel_Base::class);
        $this->colorModelMock = m::mock(Color_Base::class);
        $this->extraModelMock = m::mock(Extra_Base::class);
        $this->confSessionMock = m::mock(ConfSession_Base::class); // Mock genérico para el constructor

        // Instanciamos el servicio (SUT) inyectando los mocks
        $this->service = new ConfiguratorService_Base(
            $this->translatorMock,
            $this->confSessionMock,
            $this->carModelMock,
            $this->colorModelMock,
            $this->extraModelMock
        );
    }

    /**
     * Se ejecuta después de CADA test. Es CRUCIAL para Mockery.
     * Verifica que todas las expectativas se cumplieron y limpia el contenedor de mocks.
     */
    protected function tearDown(): void
    {
        m::close();
    }

    /**
     * Prueba el "camino feliz": una configuración completa con modelo, color y varios extras.
     */
    public function testReturnsFullSummaryForCompleteConfiguration(): void
    {
        // ARRANGE (Preparar el escenario)

        // 1. Configurar la sesión de prueba. Mockery permite definir propiedades dinámicamente.
        $sessionMock = m::mock(ConfSession_Base::class);
        $sessionMock->id_model = 1;
        $sessionMock->id_color = 10;
        $sessionMock->extras = '101,102';

        // 2. Simular los objetos que devolverían los modelos
        $returnedCarMock = m::mock(CarModel_Base::class);
        $returnedCarMock->shouldReceive('toArray')->andReturn(['name' => 'Audi A8', 'price' => 90000]);

        $returnedColorMock = m::mock(Color_Base::class);
        $returnedColorMock->name = 'color.black_mythos';
        $returnedColorMock->price_increase = 1200.0;

        $extrasData = [
            ['name' => 'extra.rims_21', 'price' => 2500.0],
            ['name' => 'extra.sunroof', 'price' => 1700.0]
        ];
        $returnedExtrasCollection = m::mock(Collection::class);
        $returnedExtrasCollection->shouldReceive('toArray')->andReturn($extrasData);

        // 3. Programar las expectativas de los mocks: qué métodos se llamarán, con qué argumentos y qué devolverán.
        $this->carModelMock->shouldReceive('find')->with(1)->once()->andReturn($returnedCarMock);
        $this->colorModelMock->shouldReceive('find')->with(10)->once()->andReturn($returnedColorMock);
        $this->extraModelMock->shouldReceive('findAll')->with('id_extra', ['101', '102'])->once()->andReturn($returnedExtrasCollection);

        // 4. Programar el mock del traductor para simular la i18n
        $this->translatorMock->shouldReceive('get')->with('color.black_mythos',[])->andReturn('Mythos Black');
        $this->translatorMock->shouldReceive('get')->with('extra.rims_21',[])->andReturn('21" Rims');
        $this->translatorMock->shouldReceive('get')->with('extra.sunroof',[])->andReturn('Panoramic Sunroof');
        
        // ACT (Ejecutar el método a probar)
        $summary = $this->service->getSummaryData($sessionMock);

        // ASSERT (Verificar que el resultado es el esperado)
        $this->assertIsArray($summary);
        $this->assertEquals(['name' => 'Audi A8', 'price' => 90000], $summary['model']);
        $this->assertEquals(['name' => 'Mythos Black', 'price' => 1200.0], $summary['color']);
        $this->assertCount(2, $summary['extras']);
        $this->assertEquals(['name' => '21" Rims', 'price' => 2500.0], $summary['extras'][0]);
        $this->assertEquals(['name' => 'Panoramic Sunroof', 'price' => 1700.0], $summary['extras'][1]);
    }

    /**
     * Prueba que el método maneja correctamente una sesión sin extras seleccionados. (con null)
     */
    public function testHandlesSessionWithNoExtras(): void
    {
        // ARRANGE
        $sessionMock = m::mock(ConfSession_Base::class);
        $sessionMock->id_model = 1;
        $sessionMock->id_color = 10;
        $sessionMock->extras = null; // Sin extras

        $returnedCarMock = m::mock(CarModel_Base::class);
        $returnedCarMock->shouldReceive('toArray')->andReturn(['name' => 'Audi A8']);
        $returnedColorMock = m::mock(Color_Base::class);
        $returnedColorMock->name = 'color.white_glacier';
        $returnedColorMock->price_increase = 0;

        $this->carModelMock->shouldReceive('find')->with(1)->andReturn($returnedCarMock);
        $this->colorModelMock->shouldReceive('find')->with(10)->andReturn($returnedColorMock);
        $this->translatorMock->shouldReceive('get')->with('color.white_glacier',[])->andReturn('Glacier White');

        // El método findAll de extraModel NUNCA debe ser llamado.
        $this->extraModelMock->shouldReceive('findAll')->never();

        // ACT
        $summary = $this->service->getSummaryData($sessionMock);

        // ASSERT
        $this->assertIsArray($summary['extras']);
        $this->assertEmpty($summary['extras']);
        $this->assertNotNull($summary['model']);
        $this->assertNotNull($summary['color']);
    }

    /**
     * Prueba el caso donde un id de la sesión no corresponde a un registro existente. 
     */
    public function testHandlesNonExistentEntitiesGracefully(): void
    {
        // ARRANGE
        $sessionMock = m::mock(ConfSession_Base::class);
        $sessionMock->id_model = 999; // ID de modelo inexistente
        $sessionMock->id_color = 998; // ID de color inexistente
        $sessionMock->extras = '997';  // ID de extra inexistente

        // Programar los mocks para que devuelvan null o colecciones vacías
        $this->carModelMock->shouldReceive('find')->with(999)->andReturn(null);
        $this->colorModelMock->shouldReceive('find')->with(998)->andReturn(null);
        
        $emptyCollection = m::mock(Collection::class);
        $emptyCollection->shouldReceive('toArray')->andReturn([]);
        $this->extraModelMock->shouldReceive('findAll')->with('id_extra', ['997'])->andReturn($emptyCollection);

        // ACT
        $summary = $this->service->getSummaryData($sessionMock);

        // ASSERT
        $this->assertNull($summary['model']);
        $this->assertNull($summary['color']);
        $this->assertEmpty($summary['extras']);
    }

    /**
     * Prueba que el método maneja correctamente una sesión sin extras seleccionados (con string vacio)
     */
    public function testHandlesSessionWithNoExtrasEmptyString(): void
    {
        // ARRANGE
        $sessionMock = m::mock(ConfSession_Base::class);
        $sessionMock->id_model = 1;
        $sessionMock->id_color = 10;
        $sessionMock->extras = ""; // Sin extras

        $returnedCarMock = m::mock(CarModel_Base::class);
        $returnedCarMock->shouldReceive('toArray')->andReturn(['name' => 'Audi A8']);
        $returnedColorMock = m::mock(Color_Base::class);
        $returnedColorMock->name = 'color.white_glacier';
        $returnedColorMock->price_increase = 0;

        $this->carModelMock->shouldReceive('find')->with(1)->andReturn($returnedCarMock);
        $this->colorModelMock->shouldReceive('find')->with(10)->andReturn($returnedColorMock);
        $this->translatorMock->shouldReceive('get')->with('color.white_glacier',[])->andReturn('Glacier White');

        // Expectativa clave: el método findAll de extraModel NUNCA debe ser llamado.
        $this->extraModelMock->shouldReceive('findAll')->never();

        // ACT
        $summary = $this->service->getSummaryData($sessionMock);

        // ASSERT
        $this->assertIsArray($summary['extras']);
        $this->assertEmpty($summary['extras']);
        $this->assertNotNull($summary['model']);
        $this->assertNotNull($summary['color']);
    }
}