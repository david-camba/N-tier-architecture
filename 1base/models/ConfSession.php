<?php




interface ConfSession extends InterfaceORM {}; 

/**
 * ConfSession_Base
 *
 * Modelo para interactuar con la tabla 'conf_sessions', que almacena
 * las configuraciones de vehículos en progreso o guardadas como plantillas.
 */
class ConfSession_Base extends ORM implements ConfSession
{
    /** @var string El nombre de la tabla en la base de datos. */
    protected string $tableName = 'conf_sessions';
    
    /** @var string El nombre de la clave primaria. */
    protected string $primaryKey = 'id_conf_session';

    /** @var array Lista blanca de columnas para búsquedas seguras. */
    protected array $fillable_columns = [
        'id_conf_session',
        'id_user',
        'assigned',
        'template'
    ];
    
    /**
     * Busca la última configuración en progreso para un usuario específico.
     *
     * @param int $userId El ID del usuario.
     * @return self|null Un objeto ConfSession_Base o null si no hay ninguna activa.
     */
    public function findLastActiveForUser($userId)
    {
        // Esta consulta busca la sesión más reciente que no haya sido
        // asignada a un cliente.
        $sql = "SELECT * FROM " . $this->tableName . " 
                WHERE id_user = ? 
                AND assigned = 0 
                ORDER BY last_modification DESC 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si encontramos datos, creamos y devolvemos una nueva instancia "llena".
        return $data ? new static($this->app, $this->pdo, $data) : null;
    }

    /**
     * Busca todas las configuraciones guardadas como plantilla por un usuario.
     *
     * @param int $userId El ID del usuario.
     * @return Collection Una colección de objetos ConfSession_Base.
     */
    public function findTemplatesForUser($userId)
    {
        // Esta consulta busca todas las sesiones marcadas como 'template'.
        $sql = "SELECT * FROM " . $this->tableName . " 
                WHERE id_user = ? 
                AND template = 1 
                ORDER BY last_modification DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $allData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($allData as $data) {
            $items[] = new static($this->app, $this->pdo, $data);
        }
        
        return new Collection($items);
    }

    /**
     * Resetea la configuración del vehículo, manteniendo solo
     * la información del usuario.
     */
    public function resetConfiguration()
    {
        $this->assigned = 0;
        $this->template = 0;
        $this->id_model = null;
        $this->id_color = null;
        $this->extras = null;

        $this->save();
    }

    /**
     * 
    *
    * Configura y guarda una nueva sesión para un usuario.
    * Se llama desde un objeto 'ConfSession' vacío (el buscador).
    *
    * @param int $userId El ID del usuario.
    * @return self El mismo objeto, ahora lleno y guardado (con un ID).
    */
    public function createForUser($userId)
    {
        // El objeto $this ya existe y tiene $this->pdo gracias a la App.
        
        // 1. Establecemos los datos en el objeto actual.
        $this->id_user = $userId;
        $this->id_model = null;
        $this->id_color = null;
        $this->extras = null;
        $this->assigned = 0;
        $this->template = 0;
        
        // 2. Guardamos el estado actual del objeto en la base de datos.
        $this->save();

        // 3. Devolvemos el mismo objeto, que ahora ha sido "llenado"
        // y tiene un id_conf_session gracias al método save().
        return $this;
    }
}