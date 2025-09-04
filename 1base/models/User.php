<?php
interface User extends InterfaceORM {}; 

class User_Base extends ORM implements User
{
    protected string $tableName = 'users';
    protected string $primaryKey = 'id_user';

    protected array $hidden = ['user_layer', 'user_level'];

    protected array $fillable_columns = [
        'username',
        'email',
        'id_dealer',
        'id_user'
    ];

/**
     * Incrementa el contador de intentos de login fallidos para este usuario.
     *
     * Este método se asegura de que la operación se realice de forma atómica
     * en la base de datos.
     */
    public function incrementLoginTries()
    {
        // Nos aseguramos de que el objeto actual represente a un usuario real (que tenga un ID).
        if (!isset($this->data[$this->primaryKey])) {
            // No hacemos nada si el objeto está vacío o no ha sido guardado.
            return;
        }

        // 1. Construir la consulta SQL.
        // Usamos 'tries = tries + 1' para que la operación sea atómica.
        // Esto evita problemas si dos peticiones intentan actualizarlo a la vez.
        $sql = "UPDATE " . $this->tableName . " 
                SET tries = tries + 1 
                WHERE " . $this->primaryKey . " = :id";
        
        // 2. Preparar y ejecutar la consulta.
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $this->id_user]);
        
        // (Opcional) Actualizar el objeto en memoria para que refleje el cambio.
        $this->tries++;
    }

    /**
     * Resetea el contador de intentos de login a 0.
     * Se llamaría después de un login exitoso.
     */
    public function resetLoginTries()
    {
        if (!isset($this->data[$this->primaryKey])) {
            return;
        }
        $sql = "UPDATE " . $this->tableName . " SET tries = 0 WHERE " . $this->primaryKey . " = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $this->id_user]);
        $this->tries = 0;
    }

    /**
     * Obtiene el user_layer de este usuario directamente desde la BBDD.
     * Es un método de instancia que solo funciona si el objeto User tiene un ID.
     *
     * @return int|null
     */
    public function fetchUserLayer() : ?int
    {
        if (!$this->id_user) return null;

        $stmt = $this->pdo->prepare(
            "SELECT user_layer FROM " . $this->tableName . " WHERE " . $this->primaryKey . " = ?"
        );
        $stmt->execute([$this->id_user]);
        
        // fetchColumn() es perfecto para obtener un único valor de una única columna.
        $layer = $stmt->fetchColumn();
        
        return $layer !== false ? (int)$layer : null;
    }

    /**
     * Obtiene el user_level de este usuario directamente desde la BBDD.
     *
     * @return int|null
     */
    public function fetchUserLevel() : ?int
    {
        if (!$this->id_user) return null;

        $stmt = $this->pdo->prepare(
            "SELECT user_level FROM " . $this->tableName . " WHERE " . $this->primaryKey . " = ?"
        );
        $stmt->execute([$this->id_user]);
        $level = $stmt->fetchColumn();

        return $level !== false ? (int)$level : null;
    }
}