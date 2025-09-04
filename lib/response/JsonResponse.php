<?php

require_once 'lib/response/Response.php';

/**
 * Representa una respuesta en formato JSON.
 */
class JsonResponse extends Response
{
    /**
     * @param mixed $data Los datos a codificar en JSON (normalmente un array).
     * @param int $statusCode El código de estado.
     */
    public function __construct($data, $statusCode = 200)
    {
        // Las cabeceras por defecto para una respuesta JSON.
        $defaultHeaders = ['Content-Type' => 'application/json; charset=utf-8'];
        
        // El contenido son los datos, la clase App se encargará de codificarlos.
        parent::__construct($data, $statusCode, $defaultHeaders);
    }
}