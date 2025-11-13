<?php
class ModelFactory_Base
{
    protected LayerResolver $layerResolver;
    protected array $connections = []; // Caché de conexiones, ahora vive aquí.

    public $rootFinder = '/../..';

    public function __construct(LayerResolver $app)
    {
        $this->layerResolver = $app;        
        require_once 'lib/components/ORM.php'; //define the models father
        require_once 'lib/support/Collection.php'; //define collection objects that will be return by the models
    }

    public function create($modelName, $connectionType, array $constructorArgs=[], $userLevel = null) : ORM
    {
        $pdo = match ($connectionType) {
            'master' => $this->getMasterConnection(),
            'dealer' => $this->getDealerConnection(),
            'productAudi' => $this->getProductAudiDBConnection(), //DEMO: pasamos esta DB aquí para que siempre les funcione el Configurador de pedidos a los usuarios
            default => throw new Exception("Tipo de conexión desconocido: {$connectionType}"),
        };
        
        $model = $this->layerResolver->getComponent('model', $modelName, [$this->layerResolver, $pdo, $constructorArgs], $userLevel);
        
        return $model; //we pass the ready PDO to the model constructor
    }
    
    // --- MÉTODOS DE CONEXIÓN, AHORA DENTRO DE LA FÁBRICA ---

    /**
     * Obtiene y cachea la conexión a la BBDD master.
     * Es 'protected' para que las fábricas hijas puedan usarla.
     */
    protected function getMasterConnection()
    {
        $brand = strtolower(App::getInstance()->getConfig('general.brandName'));
        $dbName = "{$brand}_master";

        if (!isset($this->connections[$dbName])) {
            $this->_setSQLitePDO($dbName);
        }
        return $this->connections[$dbName];
    }
    
    /**
     * Obtiene y cachea la conexión a la BBDD del concesionario.
     */
    protected function getDealerConnection()
    {
        $brand = strtolower(App::getInstance()->getConfig('general.brandName'));
        $concessionaireId = App::getInstance()->getContext('user')->id_dealer;
        
        $dbName = "{$brand}_{$concessionaireId}";

        if (!isset($this->connections[$dbName])) {
            $this->_setSQLitePDO($dbName);
        }
        return $this->connections[$dbName];
    }

    protected function _setSQLitePDO($dbName){
        $path = __DIR__ . $this->rootFinder . "/databases/{$dbName}.sqlite";
        $this->connections[$dbName] = new PDO('sqlite:' . $path);
        $this->connections[$dbName]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connections[$dbName]->exec('PRAGMA foreign_keys = ON;');
    }
    
    // NOTA DEMO: este método de conexión debería estar solo en Audi, pero lo dejamos aquí para que la demo no de errores nunca si se intenta debugear el configurador de pedidos
    protected function getProductAudiDBConnection()
    {
        $brand = strtolower( App::getInstance()->getConfig('general.brandName') );
        $dbName = "{$brand}_prod";

        if (!isset($this->connections[$dbName])) {
            $this->_setSQLitePDO($dbName);  
        }
        return $this->connections[$dbName];
    }
}