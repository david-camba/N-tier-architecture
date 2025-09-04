<?php

require_once 'lib/response/Response.php';

/**
 * Representa una respuesta que envía un archivo para su descarga.
 */
class FileResponse extends Response
{
    /**
     * @param string $filePath La ruta al archivo en el servidor.
     * @param string|null $downloadName El nombre que tendrá el archivo al descargarse.
     * @param string $contentType El tipo MIME del archivo (ej. 'application/pdf').
     */
    public function __construct($filePath, $downloadName = null, $contentType = 'application/octet-stream')
    {
        if (!file_exists($filePath)) {
            // En un caso real, esto debería lanzar una excepción que resulte en un 404.
            parent::__construct('Archivo no encontrado.', 404);
            return;
        }

        // Si no se especifica un nombre de descarga, usamos el nombre del archivo.
        $downloadName = $downloadName ?? basename($filePath);

        $headers = [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"{$downloadName}\"",
            'Content-Length' => filesize($filePath)
        ];
        
        // El contenido será la ruta al archivo, la App sabrá leerlo y enviarlo.
        parent::__construct($filePath, 200, $headers);
    }
}