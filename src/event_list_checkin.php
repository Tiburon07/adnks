<?php
session_start();

require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/MailchimpService.php'; // non usato qui, ma incluso come richiesto

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(204);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	http_response_code(405);
	echo json_encode(['success' => false, 'error' => 'Metodo non ammesso. Usa GET.']);
	exit;
}

// --- Lettura e validazione param ---
$eventIdStr = $_GET['eventId'] ?? null;
if ($eventIdStr === null || !ctype_digit($eventIdStr) || (int)$eventIdStr <= 0) {
	http_response_code(400);
	echo json_encode(['success' => false, 'error' => 'Parametro eventId mancante o non valido.']);
	exit;
}
$eventId = (int)$eventIdStr;

// --- Ottieni connessione PDO dal tuo wrapper Database ---
// Adatta la riga seguente al tuo costruttore/metodo reale:
try {
	if (class_exists('Database')) {
		// Esempi comuni: scegli quello che hai nella tua classe
		if (method_exists('Database', 'getConnection')) {
			$pdo = getDB();
		} else {
			$db = new Database();
			$pdo = property_exists($db, 'pdo') ? $pdo : (method_exists($db, 'getConnection') ? $db->getConnection() : null);
		}
	}
	if (empty($pdo) || !($pdo instanceof PDO)) {
		throw new RuntimeException('Connessione PDO non disponibile dal wrapper Database.');
	}
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['success' => false, 'error' => 'Errore connessione DB.']);
	exit;
}

// --- Query: elenco utenti iscritti a eventId (join Utenti ↔ Iscrizione_Eventi) ---
$sql = "
    SELECT
        u.ID                AS userId,
        u.nome              AS nome,
        u.cognome           AS cognome,
        u.Azienda           AS azienda,
        u.email             AS email,
        u.telefono          AS telefono,
        u.ruolo             AS ruolo,
        u.status            AS user_status,
        ie.ID               AS registrationId,
        ie.status           AS registration_status,
        ie.checkin          AS checkin,
        ie.dataiscrizione   AS dataiscrizione
    FROM Iscrizione_Eventi ie
    INNER JOIN Utenti u ON u.ID = ie.idUtente
    WHERE ie.idEvento = :eventId
    ORDER BY u.cognome ASC, u.nome ASC
";

try {
	$stmt = $pdo->prepare($sql);
	$stmt->bindValue(':eventId', $eventId, PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll();

	if (empty($rows)) {
		http_response_code(404);
		echo json_encode([
			'success' => false,
			'error'   => 'Nessuna iscrizione trovata per l\'evento specificato.',
			'eventId' => $eventId
		], JSON_UNESCAPED_UNICODE);
		exit;
	}
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['success' => false, 'error' => 'Errore durante la lettura delle iscrizioni.']);
	exit;
}


echo json_encode([
	'success' => true,
	'eventId' => $eventId,
	'total'   => count($rows),
	'data'    => $rows,
], JSON_UNESCAPED_UNICODE);
