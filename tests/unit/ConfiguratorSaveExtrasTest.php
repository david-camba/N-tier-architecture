<?php
use PHPUnit\Framework\TestCase;
use Mockery\MockInterface;
use Mockery as m;

// Boilerplate para la configuración de rutas y dependencias
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__),2);
}
require_once BASE_PATH . '/lib/components/ORM.php';
require_once BASE_PATH . '/lib/support/Collection.php';
require_once BASE_PATH . '/1base/services/ConfiguratorService.php';
require_once BASE_PATH . '/1base/services/TranslatorService.php';
require_once BASE_PATH . '/1base/models/CarModel.php';
require_once BASE_PATH . '/1base/models/Color.php';
require_once BASE_PATH . '/1base/models/Extra.php';
require_once BASE_PATH . '/1base/models/ConfSession.php';

/**
 * Pruebas unitarias para el método saveExtras de ConfiguratorService.
 *
 * @covers ConfiguratorService_Base::saveExtras
 */
class ConfiguratorSaveExtrasTest extends TestCase
{
    private ConfiguratorService_Base $service;
    private MockInterface|TranslatorService $translatorMock;
    private MockInterface|CarModel $carModelMock;

    // Nota: ColorModel y ExtraModel no se usan directamente en `saveExtras`,
    // pero son necesarios para el constructor del servicio.
    private MockInterface|Color $colorModelMock;
    private MockInterface|Extra $extraModelMock;
    private MockInterface|ConfSession $confSessionMock;

    protected function setUp(): void
    {
        // Creamos mocks para todas las dependencias del constructor del servicio
        $this->translatorMock = m::mock(TranslatorService_Base::class);
        $this->carModelMock = m::mock(CarModel_Base::class);
        $this->colorModelMock = m::mock(Color_Base::class);
        $this->extraModelMock = m::mock(Extra_Base::class);
        $this->confSessionMock = m::mock(ConfSession_Base::class);

        // Instanciamos el servicio (SUT) inyectando los mocks
        $this->service = new ConfiguratorService_Base(
            $this->translatorMock,
            $this->confSessionMock,
            $this->carModelMock,
            $this->colorModelMock,
            $this->extraModelMock
        );
    }

    protected function tearDown(): void
    {
        m::close();
    }

    /**
     * Prueba el "camino feliz": cuando todos los IDs de extra son válidos.
     * Verifica que la sesión se actualiza y se guarda correctamente.
     */
    public function testSavesValidExtrasSuccessfully(): void
    {
        // ARRANGE
        // 1. Crear un mock para la sesión que se pasa como argumento.
        // Usamos `m::spy` porque queremos verificar que se le asigna una propiedad
        // y se le llama un método, además de usar sus propiedades.
        $sessionMock = m::spy(ConfSession_Base::class);
        $sessionMock->id_model = 1;

        // 2. Simular la cadena de llamadas para obtener los extras compatibles.
        $mockedCarModel = m::mock(CarModel_Base::class)->shouldAllowMockingProtectedMethods();
        $mockedExtrasCollection = m::mock(Collection::class);

        // Programar el mock principal del servicio para que devuelva el coche mockeado.
        $this->carModelMock->shouldReceive('find')->with(1)->andReturn($mockedCarModel);

        // Cuando se acceda a la propiedad mágica `extras`, debe devolver nuestra colección mockeada.
        $mockedCarModel->shouldReceive('extras')->andReturn($mockedExtrasCollection);

        // Cuando se llame a `pluck` en esa colección, debe devolver los IDs válidos.
        $mockedExtrasCollection->shouldReceive('pluck')->with('id_extra')->andReturn([101, 102, 103]);

        // Los IDs de extra que vamos a intentar guardar (todos válidos).
        $validExtraIds = [101, 103];

        // ACT
        $this->service->saveExtras($sessionMock, $validExtraIds);

        // ASSERT
        // Verificar los efectos secundarios en el objeto de sesión.
        // Mockery Spy nos permite hacer estas aserciones después de la acción.

        $this->assertEquals('101,103', $sessionMock->extras); //comprobamos que se setean correctamente los datos a guardar
        $sessionMock->shouldHaveReceived('save')->once(); //comprobamos que se ha llamado bien        
    }

    /**
     * Prueba la lógica de validación: cuando se envía un ID de extra no compatible.
     * Verifica que se lanza la excepción correcta y que la sesión NO se guarda.
     */
    public function testThrowsExceptionWhenInvalidExtraIsPassed(): void
    {
        // ARRANGE
        $sessionMock = m::mock(ConfSession_Base::class);
        $sessionMock->id_model = 1;

        // La configuración para obtener extras compatibles es la misma que en el test anterior.
        $mockedCarModel = m::mock(CarModel_Base::class)->shouldAllowMockingProtectedMethods();
        $mockedExtrasCollection = m::mock(Collection::class);

        $this->carModelMock->shouldReceive('find')->with(1)->andReturn($mockedCarModel);
        $mockedCarModel->shouldReceive('extras')->andReturn($mockedExtrasCollection);
        $mockedExtrasCollection->shouldReceive('pluck')->with('id_extra')->andReturn([101, 102]);        

        // IDs a guardar, uno de ellos es inválido (999).
        $extraIdsWithInvalid = [101, 999];

        // ASSERT (Expectativas)
        // 1. Esperamos que se lance una excepción de la clase `Exception`.
        $this->expectException(Exception::class);
        // 2. Esperamos que el código de la excepción sea 400.
        $this->expectExceptionCode(400);
        // 3. Esperamos que el mensaje de error contenga el ID inválido.
        $this->expectExceptionMessage("Los siguientes IDs de extra no son válidos para este modelo: 999");
        
        // 4. Expectativa crucial: el método save() NUNCA debe ser llamado si la validación falla.
        $sessionMock->shouldReceive('save')->never();

        // ACT
        // Esta llamada debería disparar la excepción esperada.
        $this->service->saveExtras($sessionMock, $extraIdsWithInvalid);
    }

    /**
     * Prueba un caso límite: cuando se pasa un array vacío de extras.
     * Debería guardar un string vacío en la sesión.
     */
    public function testSavesEmptyStringWhenExtraIdsArrayIsEmpty(): void
    {
        // ARRANGE
        $sessionMock = m::spy(ConfSession_Base::class);
        $sessionMock->id_model = 1;

        // La validación se ejecutará, así que necesitamos mockear la respuesta.
        $mockedCarModel = m::mock(CarModel_Base::class)->shouldAllowMockingProtectedMethods();
        $mockedExtrasCollection = m::mock(Collection::class);

        $this->carModelMock->shouldReceive('find')->with(1)->andReturn($mockedCarModel);
        $mockedCarModel->shouldReceive('extras')->andReturn($mockedExtrasCollection);
        $mockedExtrasCollection->shouldReceive('pluck')->with('id_extra')->andReturn([101, 102]);
        
        
        // El input es un array vacío.
        $emptyExtraIds = [];

        // ACT
        $this->service->saveExtras($sessionMock, $emptyExtraIds);

        // ASSERT
        // `implode(',', [])` devuelve un string vacío `''`.
        $this->assertEquals('', $sessionMock->extras);
        $sessionMock->shouldHaveReceived('save')->once();
    }
    
}