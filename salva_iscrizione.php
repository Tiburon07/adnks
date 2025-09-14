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
        if (strlen($data['telefono']) > 20) {
            $errors[] = "Il telefono non può superare i 20 caratteri.";
        } elseif (!preg_match('/^[0-9\s\+\-\(\)]+$/', $data['telefono'])) {
            $errors[] = "Il telefono può contenere solo numeri, spazi, +, -, (, ).";
        }
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
    $sql = "SELECT id, nome, dataEvento FROM Eventi WHERE id = :evento_id AND dataEvento > NOW()";
    
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
 * Funzione per verificare se l'utente è già iscritto all'evento
 */
function verificaIscrizioneEsistente($pdo, $evento_id, $email) {
    $sql = "SELECT id FROM Iscrizione_Eventi WHERE idEvento = :evento_id AND idUtente = :email";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':evento_id' => $evento_id,
            ':email' => $email
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
function saveIscrizione($pdo, $data) {

    $sql = "INSERT INTO Iscrizione_Eventi (idEvento, IDUtente, checkin, dataIscrizione, createdAt, updatedAt) 
            VALUES (:evento_id, :id_utente, :checkin, NOW(), NOW(), NOW())";
    
    try {
        $stmt = $pdo->prepare($sql);
        $idUtente = 1;
        $checkin = 'virtuale';
        $params = [
            ':evento_id' => (int)$data['evento_id'],
            ':id_utente' => $idUtente,
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

    // TODO: Abilitare questa verifica una volta che la tabella utenti è implementata
    // Verifica se l'utente è già iscritto a questo evento
    //if (verificaIscrizioneEsistente($pdo, $formData['evento_id'], $formData['email'])) {
    //    $_SESSION['error'] = "Sei già iscritto a questo evento con questa email.";
    //    header('Location: iscrizione.php');
    //    exit;
    //}
    //var_dump('verifica iscrizione evento'); die();
    
    // Salvataggio iscrizione
    $iscrizioneId = saveIscrizione($pdo, $formData);
    
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