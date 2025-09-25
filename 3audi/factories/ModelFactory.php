<?php
class ModelFactory_3Audi extends ModelFactory_Base
{
    public function create($modelName, $connectionType, array $constructorArgs=[], $userLevel = null) : ORM
    {        
        $pdo = match ($connectionType) {
            'productAudi' => $this->getProductAudiDBConnection(), //case: Audi needs an exclusive DB for their products (other brands include it in master)
            default => null,
        };

        if ($pdo) {
            return $this->app->getComponent('model', $modelName, [$this->app, $pdo, $constructorArgs], $userLevel);
        }

        //if not this specific case, then parent factory will handle the model
        return $this->app->callParent($this);
    }
    
    // --- MÉTODOS DE CONEXIÓN ---
    protected function getProductAudiDBConnection()
    {
        $brand = $this->app->getConfig('general.brandName');
        $dbName = "{$brand}_prod";

        if (!isset($this->connections[$dbName])) {
            $this->_setSQLitePDO($dbName);  
        }
        return $this->connections[$dbName];
    }
}