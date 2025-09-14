<?php
session_start();

require_once __DIR__ . '/classes/Database.php';

// Imposta il content type per JSON
header('Content-Type: application/json');

try {
    // Verifica che sia una richiesta POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Metodo di richiesta non valido.");
    }
    
    // Leggi il JSON dal body della richiesta
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception("Dati JSON non validi.");
    }
    
    // Validazione dati base
    if (empty($data['id']) || !is_numeric($data['id'])) {
        throw new Exception("ID iscrizione non valido.");
    }
    
    if (empty($data['action'])) {
        throw new Exception("Azione non specificata.");
    }
    
    $pdo = getDB();
    $iscrizioneId = (int)$data['id'];
    
    // Verifica che l'iscrizione esista
    $checkSql = "SELECT ID FROM Iscrizione_Eventi WHERE ID = :id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':id' => $iscrizioneId]);
    
    if (!$checkStmt->fetch()) {
        throw new Exception("Iscrizione non trovata.");
    }
    
    // Esegui l'azione richiesta
    switch ($data['action']) {
        case 'update_status':
            updateStatus($pdo, $iscrizioneId, $data['status']);
            break;
            
        case 'checkin':
            updateCheckin($pdo, $iscrizioneId, $data['checkin']);
            break;
            
        default:
            throw new Exception("Azione non riconosciuta.");
    }
    
    // Risposta di successo
    echo json_encode([
        'success' => true,
        'message' => 'Operazione completata con successo.'
    ]);
    
} catch (Exception $e) {
    // Risposta di errore
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Aggiorna lo status dell'iscrizione
 */
function updateStatus($pdo, $iscrizioneId, $newStatus) {
    $validStatuses = ['pending', 'confirmed', 'cancelled', 'bounced'];
    
    if (!in_array($newStatus, $validStatuses)) {
        throw new Exception("Status non valido.");
    }
    
    $sql = "UPDATE Iscrizione_Eventi SET status = :status, updatedAt = NOW()";
    $params = [':status' => $newStatus, ':id' => $iscrizioneId];
    
    // Se si sta annullando, imposta anche cancelledAt
    if ($newStatus === 'cancelled') {
        $sql .= ", cancelledAt = NOW()";
    } else {
        // Se si riattiva, rimuovi cancelledAt
        $sql .= ", cancelledAt = NULL";
    }
    
    $sql .= " WHERE ID = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Nessuna modifica effettuata.");
        }
        
    } catch (PDOException $e) {
        error_log("Errore aggiornamento status: " . $e->getMessage());
        throw new Exception("Errore durante l'aggiornamento dello status.");
    }
}

/**
 * Aggiorna il checkin dell'iscrizione
 */
function updateCheckin($pdo, $iscrizioneId, $checkinType) {
    $validCheckins = ['presenza', 'virtuale', 'NA'];
    
    if (!in_array($checkinType, $validCheckins)) {
        throw new Exception("Tipo di checkin non valido.");
    }
    
    // Verifica che l'iscrizione sia confermata
    $checkSql = "SELECT status FROM Iscrizione_Eventi WHERE ID = :id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':id' => $iscrizioneId]);
    $iscrizione = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$iscrizione || $iscrizione['status'] !== 'confirmed') {
        throw new Exception("Il checkin è possibile solo per iscrizioni confermate.");
    }
    
    $sql = "UPDATE Iscrizione_Eventi SET checkin = :checkin, updatedAt = NOW() WHERE ID = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':checkin' => $checkinType,
            ':id' => $iscrizioneId
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Nessuna modifica effettuata.");
        }
        
    } catch (PDOException $e) {
        error_log("Errore aggiornamento checkin: " . $e->getMessage());
        throw new Exception("Errore durante l'aggiornamento del checkin.");
    }
}
?>