<?php

session_start();

require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/MailchimpService.php';

/**
 * Rileva se la richiesta è asincrona (AJAX)
 */
function isAjaxRequest()
{
	return (
		!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
		strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
	) || (
		isset($_SERVER['HTTP_CONTENT_TYPE']) &&
		strpos($_SERVER['HTTP_CONTENT_TYPE'], 'application/json') !== false
	) || (
		isset($_SERVER['CONTENT_TYPE']) &&
		strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
	);
}

/**
 * Invia risposta JSON per richieste asincrone
 */
function sendJsonResponse($success, $message, $data = null, $errors = null)
{
	header('Content-Type: application/json; charset=utf-8');

	$response = [
		'success' => $success,
		'message' => $message
	];

	if ($data !== null) {
		$response['data'] = $data;
	}

	if ($errors !== null) {
		$response['errors'] = $errors;
	}

	echo json_encode($response, JSON_UNESCAPED_UNICODE);
	exit;
}

/**
 * Invia risposta tradizionale con redirect
 */
function sendTraditionalResponse($success, $message, $formData = null)
{
	if ($success) {
		unset($_SESSION['form_data']);
		$_SESSION['success'] = $message;
	} else {
		if ($formData) {
			$_SESSION['form_data'] = $formData;
		}
		$_SESSION['error'] = $message;
	}

	header('Location: iscrizione.php');
	exit;
}

/**
 * Funzione per mappare i dati JSON in arrivo al formato interno
 */
function mapJsonToFormData($jsonData)
{
	return [
		'evento_id' => $jsonData['eventId'] ?? '',
		'nome' => $jsonData['firstName'] ?? '',
		'cognome' => $jsonData['lastName'] ?? '',
		'email' => $jsonData['email'] ?? '',
		'telefono' => '', // Non presente nel JSON
		'ruolo' => '', // Non presente nel JSON
		'azienda' => $jsonData['company'] ?? '',
		'note' => '', // Non presente nel JSON
		'privacy' => 'on' // Assunto accettato per richieste JSON
	];
}

/**
 * Funzione per validare i dati del form di iscrizione
 */
function validateIscrizioneData($data)
{
	$errors = [];
	$fieldErrors = [];

	// Validazione evento_id
	if (empty($data['evento_id']) || !is_numeric($data['evento_id'])) {
		$errors[] = "Devi selezionare un evento valido.";
		$fieldErrors['evento_id'] = "Seleziona un evento valido.";
	}

	// Validazione nome
	if (empty(trim($data['nome']))) {
		$errors[] = "Il nome è obbligatorio.";
		$fieldErrors['nome'] = "Il nome è obbligatorio.";
	} elseif (strlen(trim($data['nome'])) > 100) {
		$errors[] = "Il nome non può superare i 100 caratteri.";
		$fieldErrors['nome'] = "Il nome non può superare i 100 caratteri.";
	}

	// Validazione cognome
	if (empty(trim($data['cognome']))) {
		$errors[] = "Il cognome è obbligatorio.";
		$fieldErrors['cognome'] = "Il cognome è obbligatorio.";
	} elseif (strlen(trim($data['cognome'])) > 100) {
		$errors[] = "Il cognome non può superare i 100 caratteri.";
		$fieldErrors['cognome'] = "Il cognome non può superare i 100 caratteri.";
	}

	// Validazione email
	if (empty(trim($data['email']))) {
		$errors[] = "L'email è obbligatoria.";
		$fieldErrors['email'] = "L'email è obbligatoria.";
	} elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
		$errors[] = "Inserisci un indirizzo email valido.";
		$fieldErrors['email'] = "Inserisci un indirizzo email valido.";
	} elseif (strlen($data['email']) > 255) {
		$errors[] = "L'email non può superare i 255 caratteri.";
		$fieldErrors['email'] = "L'email non può superare i 255 caratteri.";
	}

	// Validazione telefono (opzionale)
	if (!empty($data['telefono'])) {
		if (strlen($data['telefono']) > 30) {
			$errors[] = "Il telefono non può superare i 30 caratteri.";
			$fieldErrors['telefono'] = "Il telefono non può superare i 30 caratteri.";
		} elseif (!preg_match('/^[0-9\s\+\-\(\)]+$/', $data['telefono'])) {
			$errors[] = "Il telefono può contenere solo numeri, spazi, +, -, (, ).";
			$fieldErrors['telefono'] = "Il telefono può contenere solo numeri, spazi, +, -, (, ).";
		}
	}

	// Validazione ruolo (opzionale)
	if (!empty($data['ruolo']) && strlen($data['ruolo']) > 100) {
		$errors[] = "Il ruolo non può superare i 100 caratteri.";
		$fieldErrors['ruolo'] = "Il ruolo non può superare i 100 caratteri.";
	}

	// Validazione azienda
	if (empty(trim($data['azienda']))) {
		$errors[] = "L'azienda è obbligatoria.";
		$fieldErrors['azienda'] = "L'azienda è obbligatoria.";
	} elseif (strlen(trim($data['azienda'])) > 255) {
		$errors[] = "L'azienda non può superare i 255 caratteri.";
		$fieldErrors['azienda'] = "L'azienda non può superare i 255 caratteri.";
	}

	return [
		'errors' => $errors,
		'fieldErrors' => $fieldErrors,
		'hasErrors' => !empty($errors)
	];
}

/**
 * Funzione per verificare se l'evento esiste ed è futuro
 */
function verificaEvento($pdo, $evento_id)
{
	$sql = "SELECT ID, nome, dataEvento, tipo FROM Eventi WHERE ID = :evento_id AND dataEvento > NOW() AND archivedAt IS NULL AND deletedAt IS NULL";

	try {
		$stmt = $pdo->prepare($sql);
		$stmt->execute([':evento_id' => $evento_id]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
	} catch (PDOException $e) {
		error_log("Errore verifica evento: " . $e->getMessage());
		throw new Exception("Errore durante la verifica dell'evento.");
	}
}

/**
 * Funzione per creare o ottenere un utente
 */
function createOrGetUser($pdo, $data)
{
	// Prima verifica se l'utente esiste già
	$sql = "SELECT ID FROM Utenti WHERE email = :email AND deletedAt IS NULL";

	try {
		$stmt = $pdo->prepare($sql);
		$stmt->execute([':email' => $data['email']]);
		$user = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($user) {
			// Aggiorna i dati dell'utente esistente
			$updateSql = "UPDATE Utenti SET
                            nome = :nome,
                            cognome = :cognome,
                            ruolo = :ruolo,
                            telefono = :telefono,
                            note = :note,
                            Azienda = :azienda,
                            status = 'active',
                            updatedAt = CURRENT_TIMESTAMP
                          WHERE ID = :id";

			$updateStmt = $pdo->prepare($updateSql);
			$updateStmt->execute([
				':nome' => trim($data['nome']),
				':cognome' => trim($data['cognome']),
				':ruolo' => !empty($data['ruolo']) ? trim($data['ruolo']) : null,
				':telefono' => !empty($data['telefono']) ? trim($data['telefono']) : null,
				':note' => !empty($data['note']) ? trim($data['note']) : null,
				':azienda' => trim($data['azienda']),
				':id' => $user['ID']
			]);

			return $user['ID'];
		} else {
			// Crea nuovo utente
			$insertSql = "INSERT INTO Utenti (email, nome, cognome, ruolo, telefono, note, status, Azienda, createdAt, updatedAt)
                          VALUES (:email, :nome, :cognome, :ruolo, :telefono, :note, 'active', :azienda, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

			$insertStmt = $pdo->prepare($insertSql);
			$insertStmt->execute([
				':email' => $data['email'],
				':nome' => trim($data['nome']),
				':cognome' => trim($data['cognome']),
				':ruolo' => !empty($data['ruolo']) ? trim($data['ruolo']) : null,
				':telefono' => !empty($data['telefono']) ? trim($data['telefono']) : null,
				':note' => !empty($data['note']) ? trim($data['note']) : null,
				':azienda' => trim($data['azienda'])
			]);

			return $pdo->lastInsertId();
		}
	} catch (PDOException $e) {
		error_log("Errore creazione/aggiornamento utente: " . $e->getMessage());
		throw new Exception("Errore durante la gestione dell'utente.");
	}
}

/**
 * Funzione per verificare se l'utente è già iscritto all'evento
 */
function verificaIscrizioneEsistente($pdo, $evento_id, $utente_id)
{
	$sql = "SELECT ID FROM Iscrizione_Eventi WHERE idEvento = :evento_id AND idUtente = :utente_id AND status != 'cancelled'";

	try {
		$stmt = $pdo->prepare($sql);
		$stmt->execute([
			':evento_id' => $evento_id,
			':utente_id' => $utente_id
		]);
		return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
	} catch (PDOException $e) {
		error_log("Errore verifica iscrizione esistente: " . $e->getMessage());
		throw new Exception("Errore durante la verifica dell'iscrizione.");
	}
}

/**
 * Funzione per salvare l'iscrizione nel database con integrazione Mailchimp
 */
function saveIscrizione($pdo, $evento_id, $utente_id, $evento_tipo, $evento, $userData)
{
	// Determina il tipo di checkin in base al tipo di evento
	$checkin = ($evento_tipo === 'virtuale') ? 'virtuale' : 'NA';

	$sql = "INSERT INTO Iscrizione_Eventi (idUtente, idEvento, dataIscrizione, checkin, status, mailchimp_status, createdAt, updatedAt)
            VALUES (:id_utente, :evento_id, CURRENT_TIMESTAMP, :checkin, 'pending', 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

	try {
		$stmt = $pdo->prepare($sql);
		$params = [
			':evento_id' => (int)$evento_id,
			':id_utente' => (int)$utente_id,
			':checkin' => $checkin
		];

		$stmt->execute($params);
		$iscrizioneId = $pdo->lastInsertId();

		// Integrazione Mailchimp - Invio con double opt-in
		$mailchimpSuccess = false;
		$mailchimpMessage = '';

		try {
			$mailchimp = new MailchimpService();

			$exists = $mailchimp->getSubscriber($userData['email']);

			if (!$exists['success']) {
				throw new Exception("Errore verifica esistenza utente Mailchimp: " . $exists['error']);
			} else {
				if ($exists && $exists['success'] && $exists['status'] === 'subscribed') {
					// Utente già iscritto, bisogna riabilitarlo
					$reactivateResult = $mailchimp->resubscribeMember($userData['email']);
					if (!$reactivateResult['success']) {
						throw new Exception("Errore riabilitazione utente Mailchimp: " . $reactivateResult['error']);
					}
				} elseif ($exists && $exists['success'] && in_array($exists['status'], ['pending', 'unsubscribed', 'cleaned'])) {
					// Utente esistente ma non confermato o disiscritto, reinvia l'email di conferma
					$resendResult = $mailchimp->resendConfirmationEmail($userData['email']);

					if ($resendResult['success']) {
						$mailchimpSuccess = true;
						$mailchimpMessage = "Email di conferma reinviata con successo.";
					} else {
						throw new Exception("Errore reinvio email di conferma: " . $resendResult['error']);
					}
				} else {
					$mailchimpResult = $mailchimp->addSubscriber(
						$userData['email'],
						$userData['nome'],
						$userData['cognome'],
						$userData['azienda'],
						$evento['nome'],
						$evento['dataEvento']
					);

					if ($mailchimpResult['success']) {
						$mailchimpSuccess = true;
						$mailchimpMessage = $mailchimpResult['message'];

						// Aggiorna record con dati Mailchimp
						$updateSql = "UPDATE Iscrizione_Eventi
                             SET mailchimp_id = :mailchimp_id,
                                 mailchimp_email_hash = :email_hash,
                                 mailchimp_synced_at = CURRENT_TIMESTAMP
                             WHERE ID = :iscrizione_id";

						$updateStmt = $pdo->prepare($updateSql);
						$updateStmt->execute([
							':mailchimp_id' => $mailchimpResult['mailchimp_id'],
							':email_hash' => $mailchimpResult['email_hash'],
							':iscrizione_id' => $iscrizioneId
						]);

						// Log dell'operazione Mailchimp
						$logSql = "INSERT INTO Iscrizione_Eventi_Log
                          (idIscrizione, oldStatus, newStatus, source, note, changedAt)
                          VALUES (:id, NULL, 'pending', 'mailchimp', :note, CURRENT_TIMESTAMP)";

						$logStmt = $pdo->prepare($logSql);
						$logStmt->execute([
							':id' => $iscrizioneId,
							':note' => 'Iscrizione inviata a Mailchimp per double opt-in: ' . $mailchimpResult['message']
						]);

						error_log("Mailchimp integration success per iscrizione {$iscrizioneId}: " . $mailchimpResult['message']);
					} else {
						// Log errore Mailchimp ma non bloccare l'iscrizione
						$mailchimpMessage = $mailchimpResult['error'];
						error_log("Mailchimp integration failed per iscrizione {$iscrizioneId}: " . $mailchimpResult['error']);

						$logSql = "INSERT INTO Iscrizione_Eventi_Log
                          (idIscrizione, oldStatus, newStatus, source, note, changedAt)
                          VALUES (:id, NULL, 'pending', 'mailchimp_error', :note, CURRENT_TIMESTAMP)";

						$logStmt = $pdo->prepare($logSql);
						$logStmt->execute([
							':id' => $iscrizioneId,
							':note' => 'Errore integrazione Mailchimp: ' . $mailchimpResult['error']
						]);
					}
				}
			}
		} catch (Exception $mailchimpException) {
			// Log errore ma non bloccare l'iscrizione
			$mailchimpMessage = $mailchimpException->getMessage();
			error_log("Errore Mailchimp integration: " . $mailchimpException->getMessage());

			$logSql = "INSERT INTO Iscrizione_Eventi_Log
                      (idIscrizione, oldStatus, newStatus, source, note, changedAt)
                      VALUES (:id, NULL, 'pending', 'mailchimp_exception', :note, CURRENT_TIMESTAMP)";

			$logStmt = $pdo->prepare($logSql);
			$logStmt->execute([
				':id' => $iscrizioneId,
				':note' => 'Eccezione Mailchimp: ' . $mailchimpException->getMessage()
			]);
		}

		return [
			'iscrizioneId' => $iscrizioneId,
			'mailchimpSuccess' => $mailchimpSuccess,
			'mailchimpMessage' => $mailchimpMessage
		];
	} catch (PDOException $e) {
		error_log("Errore inserimento iscrizione: " . $e->getMessage());
		throw new Exception("Errore durante il salvataggio dell'iscrizione. Riprova più tardi.");
	}
}

// ELABORAZIONE RICHIESTA
try {
	// Verifica che sia una richiesta POST
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		$message = "Metodo di richiesta non valido.";
		if (isAjaxRequest()) {
			sendJsonResponse(false, $message);
		} else {
			sendTraditionalResponse(false, $message);
		}
	}

	// Recupera e sanitizza i dati
	$formData = [];

	// Verifica se la richiesta è JSON
	$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
	if (strpos($contentType, 'application/json') !== false) {
		// Leggi il body JSON
		$jsonInput = file_get_contents('php://input');
		$jsonData = json_decode($jsonInput, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			$message = "Formato JSON non valido.";
			sendJsonResponse(false, $message);
		}

		// Mappa i dati JSON al formato interno
		$formData = mapJsonToFormData($jsonData);
	} else {
		// Dati dal form tradizionale
		$formData = [
			'evento_id' => $_POST['evento_id'] ?? '',
			'nome' => $_POST['nome'] ?? '',
			'cognome' => $_POST['cognome'] ?? '',
			'email' => $_POST['email'] ?? '',
			'telefono' => $_POST['telefono'] ?? '',
			'ruolo' => $_POST['ruolo'] ?? '',
			'azienda' => $_POST['azienda'] ?? '',
			'note' => $_POST['note'] ?? '',
			'privacy' => $_POST['privacy'] ?? ''
		];
	}

	// Validazione dati
	$validation = validateIscrizioneData($formData);

	if ($validation['hasErrors']) {
		$message = implode('<br>', $validation['errors']);

		if (isAjaxRequest()) {
			sendJsonResponse(false, $message, null, $validation['fieldErrors']);
		} else {
			sendTraditionalResponse(false, $message, $formData);
		}
	}

	// Connessione al database
	$pdo = getDB();

	// Verifica che l'evento esista ed è futuro
	$evento = verificaEvento($pdo, $formData['evento_id']);
	if (!$evento) {
		$message = "L'evento selezionato non esiste o è già passato.";

		if (isAjaxRequest()) {
			sendJsonResponse(false, $message, null, ['evento_id' => 'Evento non valido o scaduto']);
		} else {
			sendTraditionalResponse(false, $message, $formData);
		}
	}

	// Inizia transazione per garantire consistenza dei dati
	$pdo->beginTransaction();

	try {
		// Crea o aggiorna l'utente
		$utenteId = createOrGetUser($pdo, $formData);

		// Verifica se l'utente è già iscritto a questo evento
		if (verificaIscrizioneEsistente($pdo, $formData['evento_id'], $utenteId)) {
			$message = "Sei già iscritto a questo evento con questa email.";
			$pdo->rollBack();

			if (isAjaxRequest()) {
				sendJsonResponse(false, $message, null, ['email' => 'Email già registrata per questo evento']);
			} else {
				sendTraditionalResponse(false, $message, $formData);
			}
		}

		// Salvataggio iscrizione con integrazione Mailchimp
		$risultato = saveIscrizione($pdo, $formData['evento_id'], $utenteId, $evento['tipo'], $evento, $formData);

		// Commit della transazione
		$pdo->commit();

		// Preparazione messaggio di successo
		$baseMessage = "Iscrizione completata con successo all'evento '{$evento['nome']}'!";
		$numeroIscrizione = "Numero iscrizione: {$risultato['iscrizioneId']}";

		if ($risultato['mailchimpSuccess']) {
			$emailMessage = "<strong>Importante:</strong> Controlla la tua email per confermare la partecipazione. La tua iscrizione sarà attiva solo dopo la conferma via email.";
		} else {
			$emailMessage = "<strong>Nota:</strong> L'iscrizione è stata salvata ma potrebbero esserci stati problemi con l'invio dell'email di conferma. Contattaci se non ricevi comunicazioni.";
		}

		if (isAjaxRequest()) {
			$successMessage = $baseMessage;
			$additionalInfo = $numeroIscrizione . ". " . strip_tags($emailMessage);

			sendJsonResponse(true, $successMessage, [
				'iscrizioneId' => $risultato['iscrizioneId'],
				'eventoNome' => $evento['nome'],
				'numeroIscrizione' => $risultato['iscrizioneId'],
				'mailchimpSuccess' => $risultato['mailchimpSuccess'],
				'additionalInfo' => $additionalInfo
			]);
		} else {
			$fullMessage = $baseMessage . "<br>" . $numeroIscrizione . "<br>" . $emailMessage;
			sendTraditionalResponse(true, $fullMessage);
		}
	} catch (Exception $e) {
		$pdo->rollBack();
		throw $e;
	}
} catch (Exception $e) {
	// Gestione errori
	$errorMessage = $e->getMessage();
	error_log("Errore in event_registration.php: " . $errorMessage);

	if (isAjaxRequest()) {
		sendJsonResponse(false, $errorMessage);
	} else {
		sendTraditionalResponse(false, $errorMessage, $formData ?? null);
	}
}
