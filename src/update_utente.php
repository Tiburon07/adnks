<?php
session_start();

require_once __DIR__ . '/classes/Database.php';

// Imposta header per risposta JSON
header('Content-Type: application/json');

try {
    // Verifica che sia una richiesta POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Metodo di richiesta non valido.");
    }

    // Recupera i dati JSON dalla richiesta
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Dati JSON non validi.");
    }

    // Validazione parametri obbligatori
    if (empty($data['id']) || !is_numeric($data['id'])) {
        throw new Exception("ID utente non valido.");
    }

    if (empty($data['action'])) {
        throw new Exception("Azione non specificata.");
    }

    $userId = (int)$data['id'];
    $action = $data['action'];

    $pdo = getDB();

    switch ($action) {
        case 'update_status':
            if (empty($data['status'])) {
                throw new Exception("Nuovo status non specificato.");
            }

            $newStatus = $data['status'];
            $validStatuses = ['active', 'unactive', 'disabled'];

            if (!in_array($newStatus, $validStatuses)) {
                throw new Exception("Status non valido.");
            }

            // Verifica che l'utente esista
            $checkSql = "SELECT ID, status FROM Utenti WHERE ID = :user_id AND deletedAt IS NULL";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([':user_id' => $userId]);
            $user = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception("Utente non trovato.");
            }

            if ($user['status'] === $newStatus) {
                throw new Exception("L'utente ha già questo status.");
            }

            // Aggiorna lo status
            $updateSql = "UPDATE Utenti SET status = :status, updatedAt = CURRENT_TIMESTAMP WHERE ID = :user_id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':status' => $newStatus,
                ':user_id' => $userId
            ]);

            $message = "Status utente aggiornato con successo.";
            break;

        case 'anonymize':
            // Verifica che l'utente esista e non sia già anonimizzato
            $checkSql = "SELECT ID, anonymizedAt FROM Utenti WHERE ID = :user_id AND deletedAt IS NULL";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([':user_id' => $userId]);
            $user = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception("Utente non trovato.");
            }

            if ($user['anonymizedAt']) {
                throw new Exception("L'utente è già stato anonimizzato.");
            }

            // Anonimizza i dati personali mantenendo l'ID per le relazioni
            $anonymizeSql = "
                UPDATE Utenti SET
                    nome = 'Anonimo',
                    cognome = 'Utente',
                    email = CONCAT('anon_', ID, '@example.com'),
                    telefono = NULL,
                    ruolo = NULL,
                    note = NULL,
                    Azienda = 'Anonimizzata',
                    status = 'disabled',
                    anonymizedAt = CURRENT_TIMESTAMP,
                    updatedAt = CURRENT_TIMESTAMP
                WHERE ID = :user_id
            ";
            $anonymizeStmt = $pdo->prepare($anonymizeSql);
            $anonymizeStmt->execute([':user_id' => $userId]);

            $message = "Utente anonimizzato con successo.";
            break;

        case 'soft_delete':
            // Verifica che l'utente esista
            $checkSql = "SELECT ID, deletedAt FROM Utenti WHERE ID = :user_id";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([':user_id' => $userId]);
            $user = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception("Utente non trovato.");
            }

            if ($user['deletedAt']) {
                throw new Exception("L'utente è già stato eliminato.");
            }

            // Soft delete dell'utente
            $deleteSql = "
                UPDATE Utenti SET
                    status = 'disabled',
                    deletedAt = CURRENT_TIMESTAMP,
                    updatedAt = CURRENT_TIMESTAMP
                WHERE ID = :user_id
            ";
            $deleteStmt = $pdo->prepare($deleteSql);
            $deleteStmt->execute([':user_id' => $userId]);

            $message = "Utente eliminato con successo.";
            break;

        default:
            throw new Exception("Azione non riconosciuta.");
    }

    // Risposta di successo
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (Exception $e) {
    // Log dell'errore
    error_log("Errore in update_utente.php: " . $e->getMessage());

    // Risposta di errore
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>