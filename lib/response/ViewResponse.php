<?php

require_once 'lib/response/Response.php';

/**
 * Representa una respuesta cuyo contenido es una vista que debe ser renderizada.
 */
class ViewResponse extends Response
{
    /**
     * @param View $view El objeto View que se va a renderizar.
     * @param int $statusCode El código de estado (normalmente 200).
     */
    public function __construct(View $view, $statusCode = 200, $userLayer=null)
    {
        // El contenido es el propio objeto View.
        $defaultHeaders = ['Content-Type' => 'text/html; charset=utf-8'];
        parent::__construct($view, $statusCode, $defaultHeaders, $userLayer);
    }
}