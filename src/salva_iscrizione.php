<?php

session_start();

require_once __DIR__ . '/classes/Database.php';

/**
 * Funzione per validare i dati del form di iscrizione
 */
function validateIscrizioneData($data) {
    $errors = [];
    
    // Validazione evento_id
    if (empty($data['evento_id']) || !is_numeric($data['evento_id'])) {
        $errors[] = "Devi selezionare un evento valido.";
    }
    
    // Validazione nome
    if (empty(trim($data['nome']))) {
        $errors[] = "Il nome è obbligatorio.";
    } elseif (strlen(trim($data['nome'])) > 100) {
        $errors[] = "Il nome non può superare i 100 caratteri.";
    }
    
    // Validazione cognome
    if (empty(trim($data['cognome']))) {
        $errors[] = "Il cognome è obbligatorio.";
    } elseif (strlen(trim($data['cognome'])) > 100) {
        $errors[] = "Il cognome non può superare i 100 caratteri.";
    }
    
    // Validazione email
    if (empty(trim($data['email']))) {
        $errors[] = "L'email è obbligatoria.";
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Inserisci un indirizzo email valido.";
    } elseif (strlen($data['email']) > 255) {
        $errors[] = "L'email non può superare i 255 caratteri.";
    }
    
    // Validazione telefono (opzionale)
    if (!empty($data['telefono'])) {
        if (strlen($data['telefono']) > 30) {
            $errors[] = "Il telefono non può superare i 30 caratteri.";
        } elseif (!preg_match('/^[0-9\s\+\-\(\)]+$/', $data['telefono'])) {
            $errors[] = "Il telefono può contenere solo numeri, spazi, +, -, (, ).";
        }
    }

    // Validazione ruolo (opzionale)
    if (!empty($data['ruolo']) && strlen($data['ruolo']) > 100) {
        $errors[] = "Il ruolo non può superare i 100 caratteri.";
    }

    // Validazione azienda
    if (empty(trim($data['azienda']))) {
        $errors[] = "L'azienda è obbligatoria.";
    } elseif (strlen(trim($data['azienda'])) > 255) {
        $errors[] = "L'azienda non può superare i 255 caratteri.";
    }
    
    // Validazione note (opzionale)
    if (!empty($data['note']) && strlen($data['note']) > 500) {
        $errors[] = "Le note non possono superare i 500 caratteri.";
    }
    
    // Validazione privacy
    if (empty($data['privacy']) || $data['privacy'] !== 'on') {
        $errors[] = "Devi accettare il trattamento dei dati personali per procedere.";
    }
    
    return $errors;
}

/**
 * Funzione per verificare se l'evento esiste ed è futuro
 */
function verificaEvento($pdo, $evento_id) {
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
function createOrGetUser($pdo, $data) {
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
function verificaIscrizioneEsistente($pdo, $evento_id, $utente_id) {
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
 * Funzione per salvare l'iscrizione nel database
 */
function saveIscrizione($pdo, $evento_id, $utente_id, $evento_tipo) {
    // Determina il tipo di checkin in base al tipo di evento
    $checkin = ($evento_tipo === 'virtuale') ? 'virtuale' : 'NA';

    $sql = "INSERT INTO Iscrizione_Eventi (idUtente, idEvento, dataIscrizione, checkin, status, createdAt, updatedAt)
            VALUES (:id_utente, :evento_id, CURRENT_TIMESTAMP, :checkin, 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";

    try {
        $stmt = $pdo->prepare($sql);
        $params = [
            ':evento_id' => (int)$evento_id,
            ':id_utente' => (int)$utente_id,
            ':checkin' => $checkin
        ];

        $stmt->execute($params);

        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Errore inserimento iscrizione: " . $e->getMessage());
        throw new Exception("Errore durante il salvataggio dell'iscrizione. Riprova più tardi.");
    }
}

// ELABORAZIONE RICHIESTA
try {
    // Verifica che sia una richiesta POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Metodo di richiesta non valido.");
    }
    
    // Recupera e sanitizza i dati dal form
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
    
    // Salva i dati del form in sessione per ripopolare il form in caso di errore
    $_SESSION['form_data'] = $formData;
    
    // Validazione dati
    $errors = validateIscrizioneData($formData);

    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        header('Location: iscrizione.php');
        exit;
    }
    
    // Connessione al database
    $pdo = getDB();
    
    // Verifica che l'evento esista ed è futuro
    $evento = verificaEvento($pdo, $formData['evento_id']);
    if (!$evento) {
        $_SESSION['error'] = "L'evento selezionato non esiste o è già passato.";
        header('Location: iscrizione.php');
        exit;
    }

    // Inizia transazione per garantire consistenza dei dati
    $pdo->beginTransaction();

    try {
        // Crea o aggiorna l'utente
        $utenteId = createOrGetUser($pdo, $formData);

        // Verifica se l'utente è già iscritto a questo evento
        if (verificaIscrizioneEsistente($pdo, $formData['evento_id'], $utenteId)) {
            $_SESSION['error'] = "Sei già iscritto a questo evento con questa email.";
            $pdo->rollBack();
            header('Location: iscrizione.php');
            exit;
        }

        // Salvataggio iscrizione
        $iscrizioneId = saveIscrizione($pdo, $formData['evento_id'], $utenteId, $evento['tipo']);

        // Commit della transazione
        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
    // Successo - pulisci i dati del form dalla sessione
    unset($_SESSION['form_data']);
    $_SESSION['success'] = "Iscrizione completata con successo all'evento '{$evento['nome']}'! " .
                          "Numero iscrizione: {$iscrizioneId}";

    header('Location: iscrizione.php');
    exit;
    
} catch (Exception $e) {
    // Gestione errori
    error_log("Errore in salva_iscrizione.php: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    header('Location: iscrizione.php');
    exit;
}

?>