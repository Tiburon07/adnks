<?php

session_start();

require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/EnvLoader.php';

/**
 * Funzione per validare i dati del form
 */
function validateFormData($data) {
    $errors = [];
    
    // Validazione nome
    if (empty(trim($data['nome']))) {
        $errors[] = "Il nome dell'evento è obbligatorio.";
    } elseif (strlen($data['nome']) > 255) {
        $errors[] = "Il nome dell'evento non può superare i 255 caratteri.";
    }
    
    // Validazione data evento
    if (empty($data['dataEvento'])) {
        $errors[] = "La data e ora dell'evento sono obbligatorie.";
    } else {
        $dataEvento = DateTime::createFromFormat('Y-m-d\TH:i', $data['dataEvento']);
        if (!$dataEvento) {
            $errors[] = "Formato data non valido.";
        } elseif ($dataEvento <= new DateTime()) {
            $errors[] = "La data dell'evento deve essere futura.";
        }
    }
    
    // Validazione categoria
    if (empty(trim($data['categoria']))) {
        $errors[] = "La categoria è obbligatoria.";
    } elseif (strlen($data['categoria']) > 100) {
        $errors[] = "La categoria non può superare i 100 caratteri.";
    }
    
    // Validazione tipo
    $tipi_validi = ['presenza', 'virtuale'];
    if (empty($data['tipo']) || !in_array($data['tipo'], $tipi_validi)) {
        $errors[] = "Il tipo di evento deve essere 'presenza' o 'virtuale'.";
    }
    
    return $errors;
}

/**
 * Funzione per salvare l'evento nel database
 */
function saveEvento($pdo, $data) {
    $sql = "INSERT INTO Eventi (nome, dataEvento, categoria, tipo, createdAt, updatedAt) 
            VALUES (:nome, :dataEvento, :categoria, :tipo, NOW(), NOW())";
    
    try {
        $stmt = $pdo->prepare($sql);
        
        // Conversione della data dal formato HTML al formato MySQL
        $dataEvento = DateTime::createFromFormat('Y-m-d\TH:i', $data['dataEvento']);
        $dataEventoMySQL = $dataEvento->format('Y-m-d H:i:s');
        
        $params = [
            ':nome' => trim($data['nome']),
            ':dataEvento' => $dataEventoMySQL,
            ':categoria' => trim($data['categoria']),
            ':tipo' => $data['tipo']
        ];
        
        $stmt->execute($params);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Errore inserimento evento: " . $e->getMessage());
        throw new Exception("Errore durante il salvataggio dell'evento. Riprova più tardi.");
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
        'nome' => $_POST['nome'] ?? '',
        'dataEvento' => $_POST['dataEvento'] ?? '',
        'categoria' => $_POST['categoria'] ?? '',
        'tipo' => $_POST['tipo'] ?? ''
    ];
    
    // Salva i dati del form in sessione per ripopolare il form in caso di errore
    $_SESSION['form_data'] = $formData;
    
    // Validazione dati
    $errors = validateFormData($formData);
    
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        header('Location: index.php');
        exit;
    }
    
    // Connessione al database usando il file .env
    $pdo = getDB();
    
    // Salvataggio evento
    $eventoId = saveEvento($pdo, $formData);
    
    // Successo - pulisci i dati del form dalla sessione
    unset($_SESSION['form_data']);
    $_SESSION['success'] = "Evento salvato con successo! ID: {$eventoId}";
    
    header('Location: index.php');
    exit;
    
} catch (Exception $e) {
    // Gestione errori
    error_log("Errore in salva_evento.php: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    header('Location: index.php');
    exit;
}
?>