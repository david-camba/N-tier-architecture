<?php
class ModelFactory_Base
{
    protected App $app;
    protected array $connections = []; // Caché de conexiones, ahora vive aquí.

    public function __construct(App $app)
    {
        $this->app = $app;        
        require_once 'lib/components/ORM.php'; //define the models father
        require_once 'lib/support/Collection.php'; //define collection objects that will be return by the models
    }

    public function create($modelName, $connectionType, array $constructorArgs=[], $userLevel = null)
    {
        $pdo = match ($connectionType) {
            'master' => $this->getMasterConnection(),
            'dealer' => $this->getDealerConnection(),
            default => throw new Exception("Tipo de conexión desconocido: {$connectionType}"),
        };
        
        return $this->app->getComponent('model', $modelName, [$this->app, $pdo, $constructorArgs], $userLevel); //we pass the ready PDO to the model constructor
    }
    
    // --- MÉTODOS DE CONEXIÓN, AHORA DENTRO DE LA FÁBRICA ---

    /**
     * Obtiene y cachea la conexión a la BBDD master.
     * Es 'protected' para que las fábricas hijas puedan usarla.
     */
    protected function getMasterConnection()
    {
        $brand = $this->app->getConfig('general.brandName');
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
        $brand = $this->app->getConfig('general.brandName');
        $concessionaireId = $this->app->getContext('user')->id_dealer;
        
        $dbName = "{$brand}_{$concessionaireId}";

        if (!isset($this->connections[$dbName])) {
            $this->_setSQLitePDO($dbName);
        }
        return $this->connections[$dbName];
    }

    protected function _setSQLitePDO($dbName){
        $path = dirname(__DIR__, 2) . "/databases/{$dbName}.sqlite";
        $this->connections[$dbName] = new PDO('sqlite:' . $path);
        $this->connections[$dbName]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connections[$dbName]->exec('PRAGMA foreign_keys = ON;');
    }
}