<?php
/**
 * UserSession_Base
 *
 * Modelo para interactuar con la tabla 'users_sess', que gestiona
 * las sesiones persistentes de los usuarios.
 */

interface UserSession extends InterfaceORM {}; 

class UserSession_Base extends ORM implements UserSession
{
    protected string $tableName = 'users_sess';
    protected string $primaryKey = 'id_session';
    protected array $fillable_columns = ['id_user', 'token'];

    /**
     * Crea un nuevo registro de sesión para un usuario y devuelve el token.
     *
     * Este método encapsula toda la lógica de crear un token seguro,
     * calcular la fecha de expiración e insertar la nueva sesión en la BBDD.
     *
     * @param int $userId El ID del usuario para el que se crea la sesión.
     * @return string El token de sesión único que se ha generado.
     */
    public function createForUser($userId, $dealerId)
    {
        // 1. Generar un token criptográficamente seguro.
        // random_bytes() genera bytes aleatorios seguros.
        // bin2hex() los convierte a una cadena hexadecimal legible.
        // Resultado: un token de 64 caracteres (32 bytes * 2).
        $token = bin2hex(random_bytes(32));

        // 2. Calcular la fecha de expiración.
        // Usamos objetos DateTime para un manejo de fechas más robusto.
        $nowDate = new DateTime();
        $expirationDate = new DateTime();
        $expirationDate->modify('+30 days'); // La sesión será válida por 30 días.
        
        // 3. Preparar los datos para la inserción en la base de datos.
        $sessionData = [
            'id_user'         => $userId,
            'id_dealer'       => $dealerId,
            'token'           => $token,
            'ip'              => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
            'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN',
            'expiration_date' => $expirationDate->format('Y-m-d H:i:s'),
            'created_at'      => $nowDate->format('Y-m-d H:i:s')
        ];

        // 4. Construir y ejecutar la consulta de inserción.
        // Usamos el método de array asociativo con execute() para máxima claridad y seguridad.
        $sql = "INSERT INTO " . $this->tableName . " 
                    (id_user, id_dealer, token, ip, user_agent, expiration_date, created_at) 
                VALUES 
                    (:id_user, :id_dealer, :token, :ip, :user_agent, :expiration_date, :created_at)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($sessionData);

        // 5. Devolver el token generado para que el controlador lo pueda
        // enviar al usuario en una cookie.
        return $token;
    }
}