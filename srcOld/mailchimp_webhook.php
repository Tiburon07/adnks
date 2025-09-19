<?php

/**
 * Webhook Endpoint per Mailchimp
 * Gestisce le conferme di iscrizione e altri eventi Mailchimp
 * Include supporto per richieste GET per la verifica dell'endpoint
 */

// Carica dipendenze
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/MailchimpService.php';

// Imposta header di risposta
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

/**
 * Gestione richiesta GET per verifica endpoint Mailchimp
 */
function handleGetRequest() {
    // Mailchimp invia parametri specifici nella richiesta GET
    $challenge = $_GET['challenge'] ?? '';
    $verify_token = $_GET['verify_token'] ?? '';
    
    // Log della richiesta GET
    error_log("Richiesta GET ricevuta da Mailchimp - Challenge: {$challenge}, Token: {$verify_token}");
    
    // Se è presente il parametro challenge, rispondi con esso
    if (!empty($challenge)) {
        http_response_code(200);
        echo $challenge;
        exit;
    }
    
    // Altrimenti restituisci una risposta di conferma
    echo json_encode([
        'status' => 'webhook_endpoint_active',
        'message' => 'Mailchimp webhook endpoint is ready',
        'timestamp' => date('Y-m-d H:i:s'),
        'methods_supported' => ['GET', 'POST']
    ]);
    exit;
}

/**
 * Funzione per loggare eventi webhook
 */
function logWebhookEvent($pdo, $type, $email, $mailchimpId, $eventData, $processed = false, $errorMessage = null) {
    try {
        $sql = "INSERT INTO Mailchimp_Webhook_Log
                (webhook_type, email, mailchimp_id, event_data, processed, error_message, received_at)
                VALUES (:type, :email, :mailchimp_id, :event_data, :processed, :error_message, CURRENT_TIMESTAMP)";

        $processed = !$processed ? 0 : (int)$processed;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':type' => $type,
            ':email' => $email,
            ':mailchimp_id' => $mailchimpId,
            ':event_data' => json_encode($eventData),
            ':processed' => $processed,
            ':error_message' => $errorMessage
        ]);

        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Errore log webhook: " . $e->getMessage());
        return false;
    }
}

/**
 * Aggiorna stato iscrizione da evento Mailchimp
 */
function updateIscrizioneFromWebhook($pdo, $email, $mailchimpStatus, $webhookType, $eventData) {
    try {
        // Trova l'iscrizione più recente per questa email
        $findSql = "SELECT ie.ID, ie.status, ie.mailchimp_status, u.email, e.nome as evento_nome
                    FROM Iscrizione_Eventi ie
                    JOIN Utenti u ON ie.idUtente = u.ID
                    JOIN Eventi e ON ie.idEvento = e.ID
                    WHERE u.email = :email
                    AND ie.status = 'pending'
                    AND ie.mailchimp_status = 'pending'
                    ORDER BY ie.createdAt DESC
                    LIMIT 1";

        $findStmt = $pdo->prepare($findSql);
        $findStmt->execute([':email' => $email]);
        $iscrizione = $findStmt->fetch(PDO::FETCH_ASSOC);

        if (!$iscrizione) {
            return [
                'success' => false,
                'message' => 'Nessuna iscrizione pending trovata per questa email'
            ];
        }

        // Determina il nuovo stato in base al webhook
        $newStatus = 'pending';
        switch ($webhookType) {
            case 'subscribe':
                $newStatus = 'confirmed';
                $newMailchimpStatus = 'subscribed'; // Valore fisso e sicuro
                break;
            case 'unsubscribe':
                $newStatus = 'cancelled';
                $newMailchimpStatus = 'unsubscribed'; // Valore fisso e sicuro
                break;
            case 'cleaned':
                $newStatus = 'bounced';
                $newMailchimpStatus = 'cleaned'; // Valore fisso e sicuro
                break;
            case 'profile':
                // Aggiornamento profilo, mantieni stato attuale
                $newStatus = $iscrizione['status'];
                // Per i profile updates, usa un valore standard o mantieni quello esistente
                $newMailchimpStatus = $iscrizione['mailchimp_status']; // Mantieni quello esistente
                break;
            default:
                return [
                    'success' => false,
                    'message' => "Tipo webhook non gestito: {$webhookType}"
                ];
        }

        // Log del valore originale vs troncato per debug
        if (strlen($mailchimpStatus) > 20) {
            error_log("Mailchimp status troncato: '{$mailchimpStatus}' -> '{$newMailchimpStatus}' per email {$email}");
        }

        // Aggiorna l'iscrizione
        $updateSql = "UPDATE Iscrizione_Eventi
                      SET status = :new_status,
                          mailchimp_status = :mailchimp_status,
                          mailchimp_synced_at = CURRENT_TIMESTAMP,
                          updatedAt = CURRENT_TIMESTAMP
                      WHERE ID = :iscrizione_id";

        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            ':new_status' => $newStatus,
            ':mailchimp_status' => $newMailchimpStatus,
            ':iscrizione_id' => $iscrizione['ID']
        ]);

        // Log dell'operazione - salva il valore completo nei dati JSON
        $logSql = "INSERT INTO Iscrizione_Eventi_Log
                   (idIscrizione, oldStatus, newStatus, source, note, mailchimp_event, mailchimp_data, changedAt)
                   VALUES (:id, :old_status, :new_status, 'mailchimp_webhook', :note, :webhook_type, :event_data, CURRENT_TIMESTAMP)";

        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([
            ':id' => $iscrizione['ID'],
            ':old_status' => $iscrizione['status'],
            ':new_status' => $newStatus,
            ':note' => "Webhook {$webhookType} ricevuto per {$email} - Status originale: {$mailchimpStatus}",
            ':webhook_type' => $webhookType,
            ':event_data' => json_encode($eventData)
        ]);

        return [
            'success' => true,
            'iscrizione_id' => $iscrizione['ID'],
            'old_status' => $iscrizione['status'],
            'new_status' => $newStatus,
            'evento' => $iscrizione['evento_nome'],
            'message' => "Iscrizione aggiornata da {$iscrizione['status']} a {$newStatus}",
            'mailchimp_status_original' => $mailchimpStatus,
            'mailchimp_status_saved' => $newMailchimpStatus
        ];

    } catch (PDOException $e) {
        error_log("Errore aggiornamento iscrizione da webhook: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
/**
 * Verifica la firma del webhook (se configurata)
 */
function verifyWebhookSignature($data, $signature) {
    $mailchimp = new MailchimpService();
    return $mailchimp->verifyWebhookSignature($data, $signature);
}

// GESTIONE RICHIESTA WEBHOOK
error_log("Ricevuta richiesta webhook Mailchimp - Metodo: " . $_SERVER['REQUEST_METHOD']);

try {
    // Gestisci richiesta GET (verifica endpoint)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequest();
    }

    // Verifica metodo POST per eventi webhook
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'error' => 'Method not allowed',
            'allowed_methods' => ['GET', 'POST'],
            'current_method' => $_SERVER['REQUEST_METHOD']
        ]);
        exit;
    }

    // Log headers e dati per debug
    error_log("Headers: " . json_encode(getallheaders()));
    error_log("Raw POST data: " . file_get_contents('php://input'));

    // Leggi dati raw del webhook
    $rawData = file_get_contents('php://input');

    // Log raw data per debug
    error_log("Mailchimp webhook raw data: " . $rawData);

    // Verifica firma (se configurata)
    $signature = $_SERVER['HTTP_X_MC_SIGNATURE'] ?? '';
    if (!empty($_ENV['MAILCHIMP_WEBHOOK_SECRET']) && !empty($signature)) {
        if (!verifyWebhookSignature($rawData, $signature)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        }
    }

    // Parse dati webhook
    $webhookData = [];
    parse_str($rawData, $webhookData);

    if (empty($webhookData)) {
        // Prova anche JSON se non è form-encoded
        $webhookData = json_decode($rawData, true);
    }

    if (empty($webhookData)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid webhook data']);
        exit;
    }

    // Estrai informazioni chiave
    $type = $webhookData['type'] ?? '';
    $email = $webhookData['data']['email'] ?? '';
    $mailchimpId = $webhookData['data']['id'] ?? '';
    $status = $webhookData['data']['status'] ?? '';

    if (empty($type) || empty($email)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required webhook data']);
        exit;
    }

    // Connessione database
    $pdo = getDB();

    // Log evento webhook
    $logId = logWebhookEvent($pdo, $type, $email, $mailchimpId, $webhookData);

    // Processa solo eventi rilevanti
    $supportedEvents = ['subscribe', 'unsubscribe', 'profile', 'cleaned'];

    if (!in_array($type, $supportedEvents)) {
        echo json_encode([
            'status' => 'ignored',
            'message' => "Evento {$type} non gestito",
            'log_id' => $logId
        ]);
        exit;
    }

    // Aggiorna iscrizione
    $result = updateIscrizioneFromWebhook($pdo, $email, $status, $type, $webhookData);

    if ($result['success']) {
        // Aggiorna log come processato
        if ($logId) {
            $updateLogSql = "UPDATE Mailchimp_Webhook_Log
                            SET processed = true, processed_at = CURRENT_TIMESTAMP
                            WHERE ID = :log_id";
            $updateLogStmt = $pdo->prepare($updateLogSql);
            $updateLogStmt->execute([':log_id' => $logId]);
        }

        // Log successo
        error_log("Webhook processato con successo: {$type} per {$email} - " . $result['message']);

        echo json_encode([
            'status' => 'success',
            'type' => $type,
            'email' => $email,
            'result' => $result,
            'log_id' => $logId,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } else {
        // Aggiorna log con errore
        if ($logId) {
            $updateLogSql = "UPDATE Mailchimp_Webhook_Log
                            SET error_message = :error, retry_count = retry_count + 1
                            WHERE ID = :log_id";
            $updateLogStmt = $pdo->prepare($updateLogSql);
            $updateLogStmt->execute([
                ':error' => $result['message'] ?? $result['error'] ?? 'Errore sconosciuto',
                ':log_id' => $logId
            ]);
        }

        error_log("Errore processing webhook: {$type} per {$email} - " . ($result['message'] ?? $result['error'] ?? 'Unknown'));

        echo json_encode([
            'status' => 'error',
            'type' => $type,
            'email' => $email,
            'error' => $result['message'] ?? $result['error'] ?? 'Errore sconosciuto',
            'log_id' => $logId
        ]);
    }

} catch (Exception $e) {
    error_log("Errore critico webhook Mailchimp: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

?>