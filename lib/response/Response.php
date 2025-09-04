<?php

/**
 * Clase base para todas las respuestas HTTP.
 *
 * Encapsula el contenido, el código de estado y las cabeceras
 * de una respuesta HTTP.
 */
class Response
{
    /**
     * @var mixed El contenido de la respuesta (string, array, objeto, etc.).
     */
    protected $content;

    /**
     * @var int El código de estado HTTP (ej. 200, 404, 500).
     */
    protected $statusCode;

    /**
     * @var array Un array asociativo de cabeceras HTTP.
     */
    protected $headers;

    protected $userLayer;

    /**
     * @param mixed $content El contenido a enviar.
     * @param int $statusCode El código de estado HTTP.
     * @param array $headers Las cabeceras HTTP adicionales.
     * @param array $userLayer Para renderizar vistas de un nivel especifico desde el controlador.
     */
    public function __construct($content, $statusCode = 200, array $headers = [], $userLayer = null)
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->userLayer = $userLayer;
    }
    public function getContent() { return $this->content; }

    public function getStatusCode(): int { return $this->statusCode; }

    public function getHeaders(): array { return $this->headers; }

    public function getUserLayer(): mixed { return $this->userLayer; }
}