<?php
// 0. Seteamos el contexto del usuario
$user = $this->getContext('user');
$levelUser = $this->getUserLevel();
$layerUser = $this->getUserLayer();
$userId = $user->id_user;

// 1. Incluir la utilidad de base de datos.
require_once __DIR__ . '/utils/database.php';


// --- SI NO ES UNA PETICIÓN AJAX, EL SCRIPT CONTINÚA NORMALMENTE ---

// Lógica de traducción, etc. (como la teníamos antes)
$lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'en';
// ...here we can use the new "TranslatorService" because "App" is accesible in legacy script as "$this"
$translator = $this->buildComponent('service', "TranslatorService");

// 2. Obtener la conexión.
$pdo = get_db_connection_dealer();

// Si la conexión falló, detenemos el script.
if (!$pdo) {
    die("No se pudo conectar a la base de datos.");
}

// --- ¡NUEVO! LÓGICA PARA GESTIONAR LA ASIGNACIÓN DE CONFIGURACIÓN ---
$confSessionId = null;
$confSession = null;
$errorMessageAssign = '';
$successMessage = '';
$isAssignmentMode = false;

$introMessage = 'clients_intro_message';

// 1. Comprobar si se ha pasado el parámetro 'conf_session' en la URL.
if (isset($_GET['conf_session'])) {
    $confSessionId = (int)$_GET['conf_session'];
    $isAssignmentMode = true;
    $introMessage = 'clients_intro_message_assigning';    

    // 2. Buscar la sesión de configuración en la BBDD.
    $stmt = $pdo->prepare("SELECT * FROM conf_sessions WHERE id_conf_session = ?");
    $stmt->execute([$confSessionId]);
    $confSession = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Realizar las validaciones.

    $confService = $this->buildComponent('service', "ConfiguratorService");

    if (!$confSession) {        
        $errorMessageAssign = $translator->get('clients_error_no_session');
    } elseif ($confSession['id_user'] != $userId) {
        $errorMessageAssign = $translator->get('clients_error_not_owner');
    } elseif (empty($confSession['id_model']) || empty($confSession['id_color'])) {
        $errorMessageAssign = $translator->get('clients_error_invalid_configuration');
    } elseif ($confSession['assigned'] == 1) {
        $errorMessageAssign = $translator->get('clients_warning_already_assigned');
        $summaryData = $confService->getSummaryDataLegacy($confSession);
        $totalPrice = $confService->calculateTotalLegacy($confSession);
    }
    else{
        $summaryData = $confService->getSummaryDataLegacy($confSession);
        $totalPrice = $confService->calculateTotalLegacy($confSession);
    }

    // --- ¡NUEVO! GESTIONAR EL FORMULARIO DE ASIGNACIÓN (cuando se envía por POST) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_proposal']) && empty($errorMessageAssign)) {
        $clientIdToAssign = (int)$_POST['client_id'];
        
        // Usamos una transacción para asegurar la integridad de los datos
        try {
            $pdo->beginTransaction();

            // 1. Marcar la sesión como asignada
            $stmtUpdate = $pdo->prepare("UPDATE conf_sessions SET assigned = 1 WHERE id_conf_session = ?");
            $stmtUpdate->execute([$confSessionId]);

            // 2. Crear la nueva propuesta en la tabla 'proposals'
            // NOTA: El precio total debería venir de la sesión de configuración.
            // Para esta demo, usaremos un valor fijo.
            $stmtInsert = $pdo->prepare(
                "INSERT INTO proposals (client_id, id_conf_session, total_price) VALUES (?, ?, ?)"
            );
            $stmtInsert->execute([$clientIdToAssign, $confSessionId, $totalPrice]);
            
            $pdo->commit();
            $successMessage = $translator->get('clients_success_assigned');
            
            // Actualizamos la variable local para que la vista refleje el cambio inmediatamente
            $confSession['assigned'] = 1;

        } catch (Exception $e) {
            $pdo->rollBack();
            // En un caso real, loggearíamos el error: error_log($e->getMessage());
            $errorMessageAssign = $translator->get('clients_error_assign_failed');
        }
    }
}

// --- ¡NUEVA LÓGICA DE DETECCIÓN DE AJAX! ---
// Si la petición viene con el parámetro 'ajax=true', actuamos como una API.
if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    
    // 1. Establecer la cabecera JSON.
    header('Content-Type: application/json');
    
    // 2. Conectar a la BBDD.
    $pdo = get_db_connection_dealer();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed.']);
        exit();
    }

    // 3. Hacer la búsqueda.
    $searchTerm = $_GET['search-name'] ?? '';
    $stmt = $pdo->prepare("SELECT client_id, name FROM clients WHERE name LIKE ? LIMIT 10");
    $stmt->execute(["%{$searchTerm}%"]);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Devolver los resultados en JSON y terminar.
    echo json_encode([
        "clients" => $clients,
        "confSessionId" => $confSessionId,
    ]);
    exit();
}

// 3. Ya podemos usar $pdo para hacer nuestras consultas.
$stmt = $pdo->prepare("SELECT * FROM clients");
$stmt->execute();
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- ¡NUEVA LÓGICA PARA MOSTRAR DETALLES DEL CLIENTE! ---

$selectedClient = null; // Inicializamos la variable
$clientProposals = []; // ¡NUEVA! Inicializamos un array para las propuestas

// 1. Comprobamos si nos han pasado un client_id en la URL.
if (isset($_GET['client_id'])) {
    $clientId = (int)$_GET['client_id']; // Convertimos a entero por seguridad
    
    // 2. Hacemos la consulta para obtener los detalles de ESE cliente.
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = ?");
    $stmt->execute([$clientId]);
    $selectedClient = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- ¡NUEVA LÓGICA PARA BUSCAR PROPUESTAS! ---
    // 2. Si encontramos un cliente, buscamos sus propuestas.
    if ($selectedClient && $levelUser>1) { //only work if the user is manager or above
        $stmtProposals = $pdo->prepare("SELECT * FROM proposals WHERE client_id = ? ORDER BY created_at DESC");
        $stmtProposals->execute([$clientId]);
        // fetchAll() para obtener todas las filas.
        $clientProposals = $stmtProposals->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>
<!-- En la parte HTML de 1base/legacy/clients.php -->
<style>
.autocomplete-container {
    position: relative; /* Clave para el posicionamiento de los resultados */
    width: 300px; /* O el ancho que prefieras */
}

#search-box {
    width: 100%;
    padding: 8px;
    font-size: 16px;
}

.autocomplete-results {
    position: absolute;
    top: 100%; /* Se posiciona justo debajo del input */
    left: 0;
    right: 0;
    border: 1px solid #ddd;
    border-top: none;
    max-height: 200px;
    overflow-y: auto;
    background-color: white;
    z-index: 1000;
    display: none; /* Empezará oculto por defecto */
}

/* Estilos para los ítems de la lista de resultados */
.autocomplete-results ul {
    list-style: none;
    margin: 0;
    padding: 0;
}

.autocomplete-results li a {
    display: block;
    padding: 10px;
    text-decoration: none;
    color: #333;
    border-bottom: 1px solid #eee;
    cursor: pointer;
}

.autocomplete-results li a:hover {
    background-color: #f2f2f2;
}

.back-to-app-button {
    display: inline-block;
    padding: 10px 20px;
    margin-bottom: 20px;
    background-color: #0078d4;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    font-weight: bold;
    transition: background-color 0.3s;
}
.back-to-app-button:hover {
    background-color: #005a9e;
}
.assign-form {
    display: inline-block;
}

.assign-button {
    background-color: #4CAF50; /* Verde profesional */
    color: white;
    font-size: 16px;
    font-weight: 600;
    padding: 10px 18px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.assign-button:hover {
    background-color: #45a049; /* Un verde más oscuro */
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.assign-button:active {
    transform: scale(0.98);
}

</style>

<h1><?= htmlspecialchars($translator->get('clients_title')) ?></h1>

<a href="/app" class="back-to-app-button">
    &larr; <?= htmlspecialchars($translator->get('backmenu_tag')) ?>
</a>

<!-- ¡NUEVO! Envolvemos todo en un div para el posicionamiento -->
<div class="autocomplete-container">
    <form id="search-form" autocomplete="off"> <!-- autocomplete="off" evita el autocompletado del navegador -->
        <input type="text" id="search-box" placeholder="<?= htmlspecialchars($translator->get('clients_search')) ?>">
    </form>

    <!-- La lista de resultados irá aquí. Empezará oculta. -->
    <div id="results-container" class="autocomplete-results"></div>
</div>

<?php if (!$selectedClient): ?>
    <p style="font-weight:bold;font-size:20px"><?= htmlspecialchars($translator->get($introMessage)) ?></p>
<?php endif; ?>

<?php if ($errorMessageAssign): ?>
    <p style="font-weight:bold;font-size:20px;color:red"><?= htmlspecialchars($errorMessageAssign) ?></p>
<?php endif; ?>
<?php if ($successMessage): ?>
    <p style="font-weight:bold;font-size:20px;color:green"><?= htmlspecialchars($successMessage) ?></p>
<?php endif; ?>
<?php if (isset($summaryData)): ?>
     <div style="display:flex;justify-content:center;gap:20px;margin:10px auto;font-family:sans-serif;font-size:14px;">
        <!-- Columna izquierda: Modelo y Color -->
        <div style="width:40%;">
            <table border="1" cellspacing="0" cellpadding="6" style="border-collapse:collapse;width:100%;">
                <tr>
                    <th colspan="2" style="text-align:left;background:#f4f4f4;">Modelo</th>
                </tr>
                <tr><td>Nombre</td><td><?= htmlspecialchars($summaryData['model']['name']) ?></td></tr>
                <tr><td>Precio</td><td><?= number_format($summaryData['model']['price'], 2, ',', '.') ?> €</td></tr>
                <tr><td>Emisiones</td><td><?= htmlspecialchars($summaryData['model']['emissions']) ?> g/km</td></tr>

                <tr>
                    <th colspan="2" style="text-align:left;background:#f4f4f4;">Color</th>
                </tr>
                <tr><td>Nombre</td><td><?= htmlspecialchars($summaryData['color']['name']) ?></td></tr>
                <tr><td>Precio</td><td><?= number_format($summaryData['color']['price'], 2, ',', '.') ?> €</td></tr>
            </table>
        </div>

        <!-- Columna derecha: Extras -->
        <div style="width:40%;">
            <table border="1" cellspacing="0" cellpadding="6" style="border-collapse:collapse;width:100%;">
                <tr>
                    <th colspan="2" style="text-align:left;background:#f4f4f4;">Extras</th>
                </tr>
                <?php foreach ($summaryData['extras'] as $extra): ?>
                    <tr>
                        <td><?= htmlspecialchars($extra['name']) ?></td>
                        <td><?= number_format($extra['price'], 2, ',', '.') ?> €</td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <!-- Total -->
    <p style="text-align:center;font-weight:bold;font-size:16px;margin-top:15px;">
        Total: <?= number_format($totalPrice, 2, ',', '.') ?> €
    </p>
<?php endif; ?>
 <!-- --- ¡NUEVA SECCIÓN DE DETALLES DEL CLIENTE! --- -->    
    <!-- 3. Comprobamos si la variable $selectedClient tiene datos. -->
    <?php if ($selectedClient): ?>
        <h2><?= htmlspecialchars($translator->get('clients_details')) ?></h2>
        <!-- --- ¡NUEVO! LÓGICA PARA MOSTRAR EL BOTÓN DE ASIGNAR PROPUESTA --- -->
        <?php if ($isAssignmentMode && empty($errorMessageAssign) && empty($confSession['assigned'])): ?>
            <form method="POST" action="?conf_session=<?= htmlspecialchars($confSessionId) ?>&client_id=<?= htmlspecialchars($selectedClient['client_id']) ?>" class="assign-form">
                <input type="hidden" name="client_id" value="<?= htmlspecialchars($selectedClient['client_id']) ?>">
                <button type="submit" name="assign_proposal" class="assign-button">
                    Asignar Propuesta
                </button>
            </form>            
        <?php endif; ?>
    

        <table border="1" cellpadding="10" cellspacing="0" style="width: 100%;">
            <tbody>
                <tr>
                    <th align="left"><?= htmlspecialchars($translator->get('name_tag')) ?></th>
                    <td><?= htmlspecialchars($selectedClient['name']) ?></td>
                </tr>
                <tr>
                    <th align="left"><?= htmlspecialchars($translator->get('adress_tag')) ?></th>
                    <td><?= htmlspecialchars($selectedClient['address']) ?></td>
                </tr>
                <tr>
                    <th align="left"><?= htmlspecialchars($translator->get('age_tag')) ?></th>
                    <td><?= htmlspecialchars($selectedClient['age']) ?></td>
                </tr>

                <?php if ($layerUser>=3): ?> <!-- ONLY FOR AUDI -->
                <tr>
                    <th align="left"><?= htmlspecialchars($translator->get('salary_tag')) ?></th>
                    <td><?= number_format($selectedClient['estimated_salary'], 2, ',', '.') ?> €</td>
                </tr>
                <?php endif; ?>

                <tr>
                    <th align="left"><?= htmlspecialchars($translator->get('financing_tag')) ?></th>
                    <td><?= $selectedClient['has_financing_access'] ? 'Sí' : 'No' ?></td>
                </tr>
                <tr>
                    <th align="left"><?= htmlspecialchars($translator->get('registrationdate_tag')) ?></th>
                    <td><?= htmlspecialchars($selectedClient['created_at']) ?></td>
                </tr>
            </tbody>
        </table>

    <!-- --- ¡NUEVA SECCIÓN DE PROPUESTAS! --- -->
        <h2><?= htmlspecialchars($translator->get('clients_proposals_title')) ?></h2>
        
        <!-- 3. Comprobamos si el array $clientProposals tiene datos -->
        <?php if ($levelUser>1): ?> <!-- only for managers and above section -->
            <?php if (!empty($clientProposals)): ?>
                <table border="1" cellpadding="10" cellspacing="0" style="width: 100%;">
                    <thead>
                        <tr>
                            <th><?= htmlspecialchars($translator->get('totalprice_tag')) ?></th>
                            <th><?= htmlspecialchars($translator->get('proposaldate_tag')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Iteramos sobre cada propuesta y creamos una fila -->
                        <?php foreach ($clientProposals as $proposal): ?>
                            <tr>
                                <td><?= number_format($proposal['total_price'], 2, ',', '.') ?> €</td>
                                <td><?= htmlspecialchars($proposal['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <!-- Si el array está vacío, mostramos el mensaje -->
                <p><i><?= htmlspecialchars($translator->get('clients_no_proposals')) ?></i></p>
            <?php endif; ?>
        <?php else: ?>
            <p><i><?= htmlspecialchars($translator->get('clients_only_managers')) ?></i></p>
        <?php endif; ?>
    <?php endif; ?>
<script src="/legacy/js/clients.js"></script>