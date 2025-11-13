<?php
// EN: 1base/legacy/includes/database.php

/**
 * Archivo de utilidad para la conexión a la base de datos en la sección legacy.
 */

// Usamos una variable estática para cachear la conexión y evitar
// múltiples conexiones en la misma petición (patrón Singleton simple).
$legacy_db_connection_dealer = null;
$legacy_db_connection_master = null;

/**
 * Obtiene la conexión PDO a la base de datos del concesionario.
 *
 * Esta función es el punto de entrada centralizado para todas las
 * operaciones de base de datos en los scripts legacy.
 *
 * Lee el 'brand' y el 'id_dealer' de la sesión de PHP, que son establecidos
 * por la parte moderna de la aplicación durante el login.
 *
 * @return PDO|null La instancia de PDO o null si falla la conexión.
 */
function get_db_connection_dealer()
{
    // Hacemos referencia a la variable global/estática para el caché.
    global $legacy_db_connection_dealer;

    // Si ya tenemos una conexión, la devolvemos inmediatamente.
    if ($legacy_db_connection_dealer !== null) {
        return $legacy_db_connection_dealer;
    }

    // --- Lógica para determinar a qué BBDD conectar ---
    // Los scripts legacy dependen de que el framework moderno haya establecido
    // estas variables de sesión durante el login.
    $brand =  strtolower( App::getInstance()->getConfig('general.brandName') );
    $dealerId = App::getInstance()->getContext('user')->id_dealer; // Usamos 11 como fallback

    if (!$brand || !$dealerId) {
        // En un caso real, manejaríamos este error (ej. mostrando un mensaje).
        error_log("Faltan datos de sesión 'brand' o 'user_dealer_id' para la conexión legacy.");
        return null;
    }

    $dbName = "{$brand}_{$dealerId}.sqlite";
    // La ruta se construye relativa a la raíz del proyecto.
    $dbPath = dirname(__DIR__, 2) . "/databases/{$dbName}";

    try {
        // Creamos la conexión PDO.
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Guardamos la conexión en nuestro caché.
        $legacy_db_connection_dealer = $pdo;
        
        return $legacy_db_connection_dealer;

    } catch (PDOException $e) {
        // En un sistema legacy, era común morir con un error o registrarlo.
        error_log("Error de conexión en BBDD legacy: " . $e->getMessage());
        // Podríamos hacer un: die("Error de base de datos. Por favor, contacte al administrador.");
        return null;
    }
}



/**
 * Obtiene la conexión PDO a la base de datos del concesionario.
 *
 * Esta función es el punto de entrada centralizado para todas las
 * operaciones de base de datos en los scripts legacy.
 *
 * Lee el 'brand' de la sesión de PHP, que son establecidos
 * por la parte moderna de la aplicación durante el login.
 *
 * @return PDO|null La instancia de PDO o null si falla la conexión.
 */
function get_db_connection_master()
{
    // Hacemos referencia a la variable global/estática para el caché.
    global $legacy_db_connection_master;

    // Si ya tenemos una conexión, la devolvemos inmediatamente.
    if ($legacy_db_connection_master !== null) {
        return $legacy_db_connection_master;
    }

    // --- Lógica para determinar a qué BBDD conectar ---
    // Los scripts legacy dependen de que el framework moderno haya establecido
    // estas variables de sesión durante el login.
    $brand =  strtolower( App::getInstance()->getConfig('general.brandName') );

    if (!$brand) {
        // En un caso real, manejaríamos este error (ej. mostrando un mensaje).
        error_log("Faltan datos de sesión 'brand' o 'user_dealer_id' para la conexión legacy.");
        return null;
    }

    $dbName = "{$brand}_master.sqlite";
    // La ruta se construye relativa a la raíz del proyecto.
    $dbPath = dirname(__DIR__, 3) . "/databases/{$dbName}";

    try {
        // Creamos la conexión PDO.
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Guardamos la conexión en nuestro caché.
        $legacy_db_connection_master = $pdo;
        
        return $legacy_db_connection_master;

    } catch (PDOException $e) {
        // En un sistema legacy, era común morir con un error o registrarlo.
        error_log("Error de conexión en BBDD legacy: " . $e->getMessage());
        // Podríamos hacer un: die("Error de base de datos. Por favor, contacte al administrador.");
        return null;
    }
}