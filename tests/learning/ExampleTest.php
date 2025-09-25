<?php

use Mockery;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

require_once 'Calculator.php';

class ExampleTest extends PhpUnitTestCase
{
    /**
     * IMPORTANTE: Mockery mantiene un registro global de los mocks.
     * Este método se ejecuta después de cada test para limpiar ese registro
     * y verificar que se cumplieron todas las expectativas.
     * Si un mock no fue llamado como se esperaba, este método lanzará la excepción.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function testSum()
    {
        $calculator = new Calculator;
        $this->assertEquals(4, $calculator->add(2, 2));
    }

    // --------------------------
    // 1 - Dummy
    // --------------------------
    public function testDummy()
    {
        /* Un "dummy". Un objeto "muerto", que no hace nada.

            1- Sirve para comprobar que una clase existe  

            2- Sirve para cumplir la firma de constructores/métodos que necesitan recibir un objeto de determinada clase pero que no lo van a usar.
        */
        
        // Esto fallaría porque la clase no existe o no se ha cargado en el archivo
        // $dummy = $this->createMock(CalculatorNotFound::class);

        $dummy = $this->createMock(Calculator::class);

        // Da error, porque "get_class" detecta que es un "mock" de Calculator
        // "get_class" devuelve un string del estilo: 'MockObject_Calculator_3c716a90'
        //$this->assertEquals('Calculator', get_class($dummy));

        // Para comprobarlo correctamente...
        $this->assertInstanceOf('Calculator', $dummy);
    }

    // --------------------------
    // 2 - Stub
    // --------------------------
    public function testStub()
    {
        /* Un STUB es un objeto que simula una dependencia devolviendo valores predefinidos en sus métodos, sin comprobar cómo se usan. Se usa para: simular lecturas de BD, APIs, etc
        
            1- Solo sirve para comprobar que un método de una clase existe y controlar la salida de dependencias. 
            
            2- No verifica que se llamen los métodos, ni cuántas veces.
        */

        $stub = $this->createStub(Calculator::class);

        // Detecta si el método no existe y da error
        // $stub->method('getValueNotFound')->willReturn(99);

        $stub->method('getValue')->willReturn(99);

        // Devuelve siempre 99 aunque no haga nada real
        $this->assertEquals(99, $stub->getValue());
    }

    // --------------------------
    // 3 - Mock
    // --------------------------
    public function testMock()
    {
        /* 
            MOCK simula una dependencia, y además, verifica las interacciones de la misma.
            Sirve para comprobar si los métodos se están llamando correctamente.
        */
        $mock = $this->createMock(Calculator::class);

        //EJEMPLO RAPIDO
        $mock->expects($this->once())
        ->method('save')
        ->with( $this->equalTo(11) , $this->stringContains('amorosa'), $this->anything())
        ->willReturn(113);

        //USANDO MOCKERY  

        // "makePartial()" hace que el objeto sea real por defecto
        $mockMockery = Mockery::mock(Calculator::class)->makePartial();
        
        // Pero ahora, al definirlo, sobreescribimos el método particular. 
        // Mockery funcionará como un proxy al invocar este método
        $mockMockery->shouldReceive('save')
        ->with(11, Mockery::pattern('/amorosa/'), Mockery::any())
        ->andReturn(113)
        ->once();

        // Lo usamos una vez como se prometio 
        $mockMockery->save(11, 'MiniMunt es amorosa', "tak");

        // Pero "add()" no ha sido sobreescrito. Se ejecuta la funcion original
        $this->assertEquals(11, $mockMockery->add(5,6));

        // EXPLICACION DESGLOSADA
        // expects() -> Definimos cuántas veces esperamos que sea llamado el método
        $mock->expects($this->once())
        /*
            $this->once() -> debe llamarse UNA sola vez
            $this->never() -> nunca debe llamarse
            $this->exactly(n) -> exactamente n veces
            $this->atLeast(n) -> al menos n veces
        */

        // method() -> Definimos qué método vamos a monitorear
        ->method('save')

        // with() -> Defino los parámetros con los que esperamos que el método sea llamado
        ->with( $this->equalTo(11) , $this->stringContains('amorosa'), $this->anything())
        /*
            $this->equalTo() -> se llama exactamente con este argumento
            $this->anything() -> se llama con cualquier valor
            $this->stringContains('foo') -> contiene el string 'foo'
            $this->greaterThan(0) -> mayor que cero
        */

        // también podemos usar el will return para asegurar una respuesta
        ->willReturn(113);

        $magic = new Magic; //podemos pasarle cualquier cosa al tercer argumento

        // Llamamos al método y le hacemos assert
        $this->assertEquals(113, $mock->save(11, 'MiniMunt es amorosa', $magic) );
        
        // Podemos crear un Mock que solo sobreescriba ciertos metodos
        $mock = $this->getMockBuilder(Calculator::class)
        ->onlyMethods(['save']) // Métodos que quieres simular
        ->getMock();  
    }

    // --------------------------
    // 4 - Spy 
    // --------------------------
    public function testSpy()
    {
        // 1. Creamos el Spy envolviendo un objeto REAL de Calculator.
        // ¡No es un objeto vacío, es el objeto real "con superpoderes de espía"!
        $spy = Mockery::spy(new Calculator());

        // 2. Ejecutamos el código.
        // Esto llamará al método *real* de la clase Calculator.
        // El resultado será 5 * 2 = 10.
        $result = $spy->doSomething(5.5);

        // 3. Verificamos el resultado del método real.
        $this->assertEquals(11, $result);

        // 4. Ahora, usamos el poder del Spy para verificar *después* de la acción.
        // La sintaxis es clave: "should have received" (debería haber recibido).
        // Le preguntamos al espía: "¿Oye, te llamaron al método 'doSomething' con un 5.5?"
        $spy->shouldHaveReceived('doSomething')->once()->with(5.5);

        // Este error no es intuitivo, dice: "no se ha hecho llamadas suficientes"
        // Cuando el fallo obvio es que no se ha llamado con el argumento que hemos definido
        // ¿Por qué ocurre? Porque esto comprueba que:
        // "Se ha llamado al método "doSomething" al menos una vez con el argumento de entrada "113"? NO -> ERROR
        // $spy->shouldHaveReceived('doSomething')->once()->with(113);

        // Si lo separamos lo vemos más claro donde está el error
        $spy->shouldHaveReceived('doSomething')->once();
        // $spy->shouldHaveReceived('doSomething')->with(113); //aquí está el error
        
        // También podemos verificar otras cosas...
        // "¿Te llamaron al método 'save' alguna vez?" (La respuesta será no, entonces no dará error)
        $spy->shouldNotHaveReceived('save');
    }

    // --------------------------
    // 5 - Fake
    // --------------------------
    /*
        Inventamos una clase nueva, por ejemplo para sustituir una Base de Datos mySQL con una SQLITE3 que sea fácil de manejar.        
    */
    public function testFake()
    {
        // Implementación funcional simple: suma en memoria en vez de DB real
        $fakeCalculator = new class {
            private $storage = 0;
            public function save($value) { $this->storage = $value; return true; }
            public function getStorage() { return $this->storage; }
        };

        $fakeCalculator->save(123);
        $this->assertEquals(123, $fakeCalculator->getStorage());
    }
}