<?php


interface Request extends InterfaceORM {}; 

class Request_Base extends ORM implements Request
{
    protected string $tableName = 'requests';
    protected string $primaryKey = 'id_request';

/**
     * Incrementa el contador de intentos de login fallidos para este usuario.
     *
     * Este método se asegura de que la operación se realice de forma atómica
     * en la base de datos.
     */
     public function log($code, $userSession = null)
    {
        // 1. Asignar los datos al objeto actual usando el método mágico __set.
        $this->id_user = $userSession->id_user ?? null;
        $this->id_dealer = $userSession->id_dealer ?? null;
        $this->token = $userSession->token ?? null;
        $this->ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $this->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        $this->request_method = $_SERVER['REQUEST_METHOD'];
        $this->request_uri = $_SERVER['REQUEST_URI'];
        $this->code = $code;
        // La columna 'request_time' se llenará automáticamente por el DEFAULT en la BBDD.

        // 2. Usar el método save() que heredaremos de ModelORM_Base para
        // guardar este nuevo registro en la base de datos.
        $this->save();
    }
}