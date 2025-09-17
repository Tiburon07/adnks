<?php

/**
 * event_checkin_update.php
 *
 * POST JSON o x-www-form-urlencoded:
 * {
 *   "eventId": 123,         // ATTENZIONE: qui è Iscrizione_Eventi.ID (registrationId), NON Eventi.ID
 *   "userId": 456,          // Utenti.ID
 *   "checkin": "presenza",  // valori ammessi: 'presenza' | 'virtuale' | 'NA'
 *   "ruolo": "Responsabile"
 * }
 *
 * Risposte:
 *  - 200 OK     -> {"success":true,"updated":{...}}
 *  - 400 BadReq -> parametri mancanti/non validi o JSON malformato
 *  - 404 NotFnd -> utente o iscrizione non trovati / non associati
 *  - 405 Method -> metodo non ammesso
 *  - 500 IntErr -> errore interno
 */

session_start();

require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/MailchimpService.php'; // non usato qui, incluso per coerenza

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(204);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['success' => false, 'error' => 'Metodo non ammesso. Usa POST.']);
	exit;
}

// --- Leggi input: JSON preferito; fallback a form-encoded ---
$raw = file_get_contents('php://input');
$ct  = $_SERVER['CONTENT_TYPE'] ?? '';

$payload = null;
if (stripos($ct, 'application/json') !== false) {
	$payload = json_decode($raw, true);
	if (!is_array($payload)) {
		http_response_code(400);
		echo json_encode(['success' => false, 'error' => 'JSON non valido o malformato.']);
		exit;
	}
} else {
	// fallback: usa $_POST
	$payload = $_POST;
}

// --- Estrai e sanifica parametri ---
$registrationIdStr = isset($payload['eventId']) ? trim((string)$payload['eventId']) : null; // Iscrizione_Eventi.ID
$userIdStr         = isset($payload['userId'])  ? trim((string)$payload['userId'])  : null;
$checkinRaw        = isset($payload['checkin']) ? trim((string)$payload['checkin']) : null;
$ruoloRaw          = isset($payload['ruolo'])   ? (string)$payload['ruolo']         : null;

// Validazioni numeriche basilari
if ($registrationIdStr === null || !ctype_digit($registrationIdStr) || (int)$registrationIdStr <= 0) {
	http_response_code(400);
	echo json_encode(['success' => false, 'error' => 'Parametro eventId (Iscrizione_Eventi.ID) mancante o non valido.']);
	exit;
}
if ($userIdStr === null || !ctype_digit($userIdStr) || (int)$userIdStr <= 0) {
	http_response_code(400);
	echo json_encode(['success' => false, 'error' => 'Parametro userId mancante o non valido.']);
	exit;
}

$registrationId = (int)$registrationIdStr;
$userId         = (int)$userIdStr;

// Validazione checkin (case-insensitive, normalizzato)
$allowedCheckin = ['presenza', 'virtuale', 'NA'];
if ($checkinRaw === null || $checkinRaw === '') {
	http_response_code(400);
	echo json_encode(['success' => false, 'error' => 'Parametro checkin mancante.']);
	exit;
}
$checkinNorm = strtoupper($checkinRaw) === 'NA'
	? 'NA'
	: strtolower($checkinRaw);

if (!in_array($checkinNorm, $allowedCheckin, true)) {
	http_response_code(400);
	echo json_encode(['success' => false, 'error' => 'Parametro checkin non valido. Valori ammessi: presenza|virtuale|NA']);
	exit;
}

// Sanifica ruolo (stringa)
if ($ruoloRaw === null) {
	http_response_code(400);
	echo json_encode(['success' => false, 'error' => 'Parametro ruolo mancante.']);
	exit;
}
$ruolo = strip_tags(trim($ruoloRaw));
if ($ruolo === '') {
	http_response_code(400);
	echo json_encode(['success' => false, 'error' => 'Parametro ruolo vuoto.']);
	exit;
}
// Limita lunghezza (coerente con schema tipico VARCHAR)
if (mb_strlen($ruolo) > 100) {
	$ruolo = mb_substr($ruolo, 0, 100);
}

// --- Ottieni connessione PDO dal wrapper Database ---
try {
	if (class_exists('Database')) {
		if (function_exists('getDB')) {
			$pdo = getDB();
		} else {
			$db  = new Database();
			$pdo = method_exists($db, 'getConnection') ? $db->getConnection() : (property_exists($db, 'pdo') ? $pdo : null);
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

// --- Verifiche esistenza & relazione (utente ↔ iscrizione) ---
try {
	// Utente esiste?
	$sUser = $pdo->prepare("SELECT 1 FROM Utenti WHERE ID = :uid LIMIT 1");
	$sUser->execute([':uid' => $userId]);
	if (!$sUser->fetchColumn()) {
		http_response_code(404);
		echo json_encode(['success' => false, 'error' => 'Utente non trovato.']);
		exit;
	}

	// Iscrizione esiste ed è associata all'utente?
	$sReg = $pdo->prepare("SELECT 1 FROM Iscrizione_Eventi WHERE ID = :rid AND idUtente = :uid LIMIT 1");
	$sReg->execute([':rid' => $registrationId, ':uid' => $userId]);
	if (!$sReg->fetchColumn()) {
		http_response_code(404);
		echo json_encode(['success' => false, 'error' => 'Iscrizione non trovata o non associata all\'utente indicato.']);
		exit;
	}
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['success' => false, 'error' => 'Errore durante le verifiche preliminari.']);
	exit;
}

// --- Transazione: aggiorna Utenti.ruolo e Iscrizione_Eventi.checkin ---
try {
	$pdo->beginTransaction();

	// Update Utenti.ruolo
	$u1 = $pdo->prepare("UPDATE Utenti SET ruolo = :ruolo, updatedAt = NOW() WHERE ID = :uid");
	$u1->bindValue(':ruolo', $ruolo, PDO::PARAM_STR);
	$u1->bindValue(':uid',   $userId, PDO::PARAM_INT);
	$u1->execute();
	// Nota: rowCount può essere 0 anche se il valore era già identico → non è errore.

	// Update Iscrizione_Eventi.checkin
	$u2 = $pdo->prepare("
        UPDATE Iscrizione_Eventi
        SET checkin = :checkin, updatedAt = NOW()
        WHERE ID = :rid AND idUtente = :uid
    ");
	$u2->bindValue(':checkin', $checkinNorm, PDO::PARAM_STR);
	$u2->bindValue(':rid',     $registrationId, PDO::PARAM_INT);
	$u2->bindValue(':uid',     $userId, PDO::PARAM_INT);
	$u2->execute();

	$pdo->commit();

	http_response_code(200);
	echo json_encode([
		'success' => true,
		'updated' => [
			'registrationId' => $registrationId,
			'userId'         => $userId,
			'checkin'        => $checkinNorm,
			'ruolo'          => $ruolo
		]
	], JSON_UNESCAPED_UNICODE);
	exit;
} catch (Throwable $e) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	http_response_code(500);
	echo json_encode(['success' => false, 'error' => 'Errore durante l\'aggiornamento dei dati.']);
	exit;
}
