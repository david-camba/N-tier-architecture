<?php

require_once 'lib/response/Response.php';

/**
 * It represents an HTTP redirection response.
 */
class RedirectResponse extends Response
{
    /**
     * @param string $url The URL to which it must be redirected.
     * @param int $statusCode The State Code (302 default for temporary redirection).
     */
    public function __construct(string $url, int $statusCode = 302)
    {
        // El contenido de una redirección está vacío.
        // La magia está en la cabecera 'Location'.
        $headers = ['Location' => $url];
        
        parent::__construct('', $statusCode, $headers);
    }
}