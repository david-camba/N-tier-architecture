<?php
/**
 * @method mixed findAll()
 * @method mixed find()
 * @method mixed save()
 * @method mixed all()
 * @method mixed delete()
 */
interface InterfaceORM{}

abstract class ORM implements InterfaceORM
{
    /**
 * ORM
 *
 * La clase fundamental de nuestro ORM (Active Record).
 * Todos los modelos de la aplicación heredarán de esta clase.
 * Proporciona métodos CRUD para interactuar con la base de datos.
 */
    protected App $app;

    protected $relations = [];
    
    
    /** @var PDO La conexión a la base de datos, inyectada por la ModelFactory. */
    protected PDO $pdo;

    /** @var string El nombre de la tabla en la base de datos. Los hijos deben definirlo. */
    protected string $tableName;
    
    /** @var string El nombre de la clave primaria. Los hijos pueden sobreescribirlo. */
    protected string $primaryKey = 'id';

    /** @var array Los datos del registro actual (la fila de la tabla). */
    protected array $data = [];

    // OPTIONAL - we can force the developers to define the table and key for each model
    //abstract protected function getTableName(): string;
    //abstract protected function getPrimaryKey(): string;

    /**
     * Define las columnas por las que se permite buscar con find() y findAll().
     * Los modelos hijos DEBERÍAN sobreescribir esta lista para añadir sus columnas.
     * @var array
     */
    protected array $fillable_columns = [];

    protected array $hidden = [];

    /**
     * El constructor recibe la conexión PDO y la almacena.
     * Opcionalmente puede recibir un array de atributos para llenar el modelo.
     */
    public function __construct(App $app, PDO $pdo, array $data = [])
    {
        $this->app = $app;
        $this->pdo = $pdo;
        $this->data = $data;
    }

    //magic PHP methods. They execute when you try to "get" or "set" an innacesible variable
    public function __set($name, $value) { $this->data[$name] = $value; }
    
    public function __get($name) 
    {
        // 1. Primero, busca en los datos del modelo (comportamiento actual).
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        // 2. Si no está en los datos, comprueba si ya hemos cargado y cacheado esta relación.
        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name];
        }

        // 3. ¡LA MAGIA! Si no, comprueba si existe un método con el mismo nombre.
        // Este método debería ser una de nuestras definiciones de relación (author, posts, etc.).
        if (method_exists($this, $name)) {
            // Llama al método de relación (ej: $this->author()).
            $relationResult = $this->{$name}();
            
            // Cachea el resultado para no tener que volver a consultar la base de datos
            // si se accede de nuevo a la misma propiedad.
            $this->relations[$name] = $relationResult;
            
            return $relationResult;
        }
        
        // 4. Si no se encuentra nada, devuelve null.
        return null;
    }



    /**
     * Método Mágico UNSET.
     * Se ejecuta automáticamente cuando intentas "desestablecer" una propiedad inaccesible.
     * Ejemplo: unset($user->password);
     *
     * @param string $name El nombre de la propiedad a eliminar.
     */
    public function __unset($name)
    {
        unset($this->data[$name]);
    }
    /**
     * Comprueba si una propiedad está establecida en los datos.
     * Se llama cuando usas isset($objeto->propiedad).
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * Busca el PRIMER registro que coincida con una condición.
     * Es un método de instancia que actúa como un "buscador".
     *
     * @param string $column El nombre de la columna por la que buscar.
     * @param mixed $value El valor a buscar en esa columna.
     * @return static|null Devuelve una NUEVA instancia del modelo si lo encuentra, o null.
     */
    public function find($value, $column=null)
    {
        if($column===null) $column = $this->primaryKey;
        // --- 1. CAPA DE SEGURIDAD: VALIDACIÓN DE LA COLUMNA ---
        // Comprobamos si el nombre de la columna está en nuestra "lista blanca".
        // Esto previene que se inyecte SQL en el nombre de la columna.
        if (!in_array($column, $this->fillable_columns)) {
            // Si la columna no está permitida, lanzamos una excepción para
            // notificar al programador de que está haciendo algo mal.
            throw new InvalidArgumentException(
                "Búsqueda no permitida por la columna '{$column}' en el modelo " . get_class($this)
            );
        }

        // --- 2. CONSTRUCCIÓN Y EJECUCIÓN DE LA CONSULTA ---
        // Es seguro interpolar la variable $column en el SQL gracias a la validación anterior.
        $sql = "SELECT * FROM " . $this->tableName . " WHERE {$column} = ? LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        
        // El valor ($value) se pasa como un parámetro seguro a través de execute().
        $stmt->execute([$value]);
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        // --- 3. CREACIÓN DE LA INSTANCIA ---
        if ($data) {
            // Eliminamos las claves definidas en la propiedad estática $hidden
            $filteredData = array_diff_key($data, array_flip($this->hidden));
            // Creamos la instancia con los datos ya filtrados.

            // Si encontramos datos, creamos una nueva instancia "llena".
            // Le pasamos la App y el PDO para que el nuevo objeto también sea funcional.
            return new static($this->app, $this->pdo, $filteredData);
        }
        
        return null;
    }

    public function findAll($column, $value)
    {
        // --- 1. CAPA DE SEGURIDAD: VALIDACIÓN DE LA COLUMNA ---
        // Reutilizamos la misma "lista blanca" para asegurar que la columna es válida.
        if (!in_array($column, $this->fillable_columns)) {
            throw new InvalidArgumentException(
                "Búsqueda no permitida por la columna '{$column}' en el modelo " . get_class($this)
            );
        }

        // --- 2. CONSTRUCCIÓN Y EJECUCIÓN DE LA CONSULTA ---
        // La consulta es la misma que en find, pero sin el 'LIMIT 1'.
        $params = [];
        $sql = "SELECT * FROM " . $this->tableName . " WHERE ";

        if (is_array($value)) {
            if (empty($value)) {
                return new Collection([]);
            }
            
            // --- ¡LA CORRECCIÓN CLAVE! ---
            // Construimos marcadores de posición con nombre (:param_0, :param_1, ...)
            $placeholders = [];
            foreach ($value as $key => $val) {
                $placeholder = ":param_{$key}";
                $placeholders[] = $placeholder;
                $params[$placeholder] = $val; // Construimos el array de parámetros para execute()
            }
            
            $sql .= "{$column} IN (" . implode(',', $placeholders) . ")";
            
        } else {
            // El caso simple no cambia, pero usamos un marcador con nombre por consistencia.
            $sql .= "{$column} = :value";
            $params = [':value' => $value];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params); // Usamos el array de parámetros que hemos construido.
        
        // fetchAll() nos devuelve un array con TODAS las filas encontradas.
        $allData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- 3. CREACIÓN DE LA COLECCIÓN DE INSTANCIAS ---
        $items = [];
        foreach ($allData as $data) {
            $filteredData = array_diff_key($data, array_flip($this->hidden));
            // Por cada fila de datos, creamos una nueva instancia "llena" del modelo.
            $items[] = new static($this->app, $this->pdo, $filteredData);
        }
        
        // 4. Devolvemos los resultados envueltos en nuestro objeto Collection.
        return new Collection($items);
    }

        /**
     * ¡NUEVO MÉTODO!
     * Obtiene TODOS los registros de la tabla del modelo.
     *
     * @return Collection Devuelve una Colección con todos los objetos del modelo.
     */
    public function all()
    {
        // 1. Construimos una consulta simple sin cláusula WHERE.
        $sql = "SELECT * FROM " . $this->tableName;
        
        // 2. Ejecutamos la consulta.
        // query() es seguro aquí porque la consulta no tiene datos del usuario.
        $stmt = $this->pdo->query($sql);
        
        $allData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Creamos la colección de instancias (misma lógica que en findAll).
        $items = [];
        foreach ($allData as $data) {
            // Filtramos los datos ocultos antes de crear el objeto
            $filteredData = array_diff_key($data, array_flip($this->hidden));
            $items[] = new static($this->app, $this->pdo, $filteredData);
        }
        
        return new Collection($items);
    }


    /**
     * Guarda el estado actual del modelo en la base de datos.
     * Realiza una operación INSERT si el modelo es nuevo (no tiene ID),
     * o una operación UPDATE si el modelo ya existe.
     *
     * @return bool True si la operación fue exitosa.
     */
    public function save()
    {
        // 1. Determinar si es un INSERT o un UPDATE.
        // La lógica es simple: si el atributo de la clave primaria está establecido,
        // asumimos que el registro ya existe.
        $isUpdate = isset($this->data[$this->primaryKey]);

        if ($isUpdate) {
            // --- LÓGICA DE UPDATE ---
            $sql = $this->buildUpdateQuery();
        } else {
            // --- LÓGICA DE INSERT ---
            $sql = $this->buildInsertQuery();
        }

        // 2. Preparar y ejecutar la consulta.
        $stmt = $this->pdo->prepare($sql);
        
        // Pasamos todos los atributos del objeto a execute().
        // PDO es lo suficientemente inteligente como para solo usar los
        // marcadores de posición que existen en la consulta SQL.
        $result = $stmt->execute($this->data);

        // 3. (Opcional pero recomendado) Si fue un INSERT, actualizar el objeto
        // con el ID que la base de datos acaba de generar.
        if (!$isUpdate && $result) {
            $this->data[$this->primaryKey] = $this->pdo->lastInsertId();
        }

        return $result;
    }

    // --- MÉTODOS PRIVADOS DE AYUDA PARA CONSTRUIR LAS CONSULTAS ---

    /**
     * Construye la cadena SQL para una consulta INSERT.
     * @return string
     */
    private function buildInsertQuery()
    {
        // Obtenemos los nombres de las columnas a partir de las claves del array de atributos.
        $columns = array_keys($this->data);
        
        // Creamos los marcadores de posición (:column_name).
        $placeholders = array_map(fn($col) => ":{$col}", $columns);

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->tableName,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
    }

    /**
     * Construye la cadena SQL para una consulta UPDATE.
     * @return string
     */
    private function buildUpdateQuery()
    {
        $fields = [];
        // Iteramos sobre las columnas para construir la parte SET de la consulta.
        foreach ($this->data as $column => $value) {
            // No queremos incluir la clave primaria en la lista de campos a actualizar.
            if ($column !== $this->primaryKey) {
                $fields[] = "{$column} = :{$column}";
            }
        }

        return sprintf(
            'UPDATE %s SET %s WHERE %s = :%s',
            $this->tableName,
            implode(', ', $fields),
            $this->primaryKey,
            $this->primaryKey
        );
    }

    /**
     * Elimina el registro actual de la base de datos.
     *
     * Solo funciona si el objeto modelo representa un registro existente
     * (es decir, tiene una clave primaria).
     *
     * @return bool True si la eliminación fue exitosa, false en caso contrario.
     */
    public function delete()
    {
        // 1. Comprobación de seguridad: nos aseguramos de que el objeto
        // tiene una clave primaria antes de intentar borrar.
        // Esto previene errores y borrados accidentales.
        if (!isset($this->data[$this->primaryKey])) {
            // No se puede borrar un objeto que no ha sido guardado o cargado.
            return false;
        }

        // 2. Construir la consulta SQL.
        // La cláusula WHERE usa la clave primaria para asegurar que solo
        // borramos el registro correcto.
        $sql = "DELETE FROM " . $this->tableName . " WHERE " . $this->primaryKey . " = ?";
        
        // 3. Preparar y ejecutar la consulta.
        $stmt = $this->pdo->prepare($sql);
        
        // Pasamos solo el valor de la clave primaria para la cláusula WHERE.
        return $stmt->execute([ $this->data[$this->primaryKey] ]);
    }

    /**
     * Devuelve los datos del modelo como un array asociativo.
     */
    public function toArray()
    {
        return $this->data;
    }

    // En la clase ORM

    /**
     * Define una relación "pertenece a" (inversa de uno a muchos).
     * @param string $relatedModel La clase del modelo relacionado.
     * @param string $foreignKey La clave foránea en la tabla *actual*.
     */
    protected function belongsTo($relatedModel, $foreignKey=null)
    {
        if ($foreignKey === null) $foreignKey = $this->primaryKey;
        // Obtenemos la instancia "vacía" del modelo relacionado desde la fábrica
        // para poder usar sus métodos de búsqueda.
        $relatedInstance = $this->app->getModel($relatedModel);
        
        // El valor de la clave foránea en este objeto actual.
        $relatedId = $this->{$foreignKey};

        // Usamos el método find del modelo relacionado para obtener el objeto.
        return $relatedInstance->find($relatedInstance->primaryKey, $relatedId);
    }

    /**
     * Define una relación "tiene muchos".
     * @param string $relatedModel La clase del modelo relacionado.
     * @param string $foreignKey La clave foránea en la tabla *relacionada*.
     */
    protected function hasMany($relatedModel, $foreignKey=null)
    {
        if ($foreignKey === null) $foreignKey = $this->primaryKey;

        $relatedInstance = $this->app->getModel($relatedModel);
        
        // El valor de nuestra clave primaria.
        $localId = $this->{$this->primaryKey};

        // Usamos findAll para obtener todos los objetos relacionados.
        return $relatedInstance->findAll($foreignKey, $localId);
    }

    /**
     * Define una relación "pertenece a muchos" (muchos a muchos).
     * @param string $relatedModel La clase del modelo relacionado.
     * @param string $pivotTable La tabla intermedia (pivote).
     * @param string $foreignPivotKey La clave foránea en la tabla pivote que apunta a *este* modelo.
     * @param string $relatedPivotKey La clave foránea en la tabla pivote que apunta al modelo *relacionado*.
     */
    protected function belongsToMany($relatedModel, $pivotTable, $foreignPivotKey=null, $relatedPivotKey=null)
    {
        if($foreignPivotKey === null) $foreignPivotKey = $this->primaryKey;

        $relatedInstance = $this->app->getModel($relatedModel);
        $relatedTable = $relatedInstance->tableName;
        $relatedPk = $relatedInstance->primaryKey;

        if($relatedPivotKey === null) $relatedPivotKey = $relatedPk;

        $sql = "SELECT {$relatedTable}.* FROM {$relatedTable}
                INNER JOIN {$pivotTable} ON {$pivotTable}.{$relatedPivotKey} = {$relatedTable}.{$relatedPk}
                WHERE {$pivotTable}.{$foreignPivotKey} = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->{$this->primaryKey}]);

        $allData = $stmt->fetchAll(PDO::FETCH_ASSOC);       

        $items = [];
        foreach ($allData as $data) {            
            $items[] = $this->app->getModel($relatedModel, $data);
        }

        return new Collection($items);
    }
}