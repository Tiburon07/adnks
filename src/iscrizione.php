<?php
// Avvio la sessione per gestire eventuali messaggi di feedback
session_start();

require_once __DIR__ . '/classes/Database.php';

// Recupero eventuali messaggi dalla sessione
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Pulisco i messaggi dalla sessione dopo averli recuperati
unset($_SESSION['success'], $_SESSION['error']);

// Valori predefiniti per il form (in caso di errori di validazione)
$nome = isset($_SESSION['form_data']['nome']) ? $_SESSION['form_data']['nome'] : '';
$cognome = isset($_SESSION['form_data']['cognome']) ? $_SESSION['form_data']['cognome'] : '';
$email = isset($_SESSION['form_data']['email']) ? $_SESSION['form_data']['email'] : '';
$telefono = isset($_SESSION['form_data']['telefono']) ? $_SESSION['form_data']['telefono'] : '';
$ruolo = isset($_SESSION['form_data']['ruolo']) ? $_SESSION['form_data']['ruolo'] : '';
$azienda = isset($_SESSION['form_data']['azienda']) ? $_SESSION['form_data']['azienda'] : '';
$evento_id = isset($_SESSION['form_data']['evento_id']) ? $_SESSION['form_data']['evento_id'] : '';
$note = isset($_SESSION['form_data']['note']) ? $_SESSION['form_data']['note'] : '';

// Pulisco i dati del form dalla sessione
unset($_SESSION['form_data']);

// Recupero gli eventi disponibili per il dropdown
$eventi = [];
try {
    $pdo = getDB();
    $sql = "SELECT ID, nome, dataEvento FROM Eventi WHERE dataEvento > NOW() AND archivedAt IS NULL AND deletedAt IS NULL ORDER BY dataEvento ASC";
    $stmt = $pdo->query($sql);
    $eventi = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Errore recupero eventi: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iscrizione Evento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }
        
        .card {
            position: relative;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <!-- Loading Overlay -->
                    <div class="loading-overlay" id="loadingOverlay">
                        <div class="text-center">
                            <div class="spinner-border text-success" role="status">
                                <span class="visually-hidden">Caricamento...</span>
                            </div>
                            <div class="mt-2 text-muted">Invio in corso...</div>
                        </div>
                    </div>
                    
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-person-plus me-2"></i>
                            Iscrizione Evento
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Area per i messaggi dinamici -->
                        <div id="messageArea">
                            <?php if ($success_message): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="bi bi-check-circle me-2"></i>
                                    <?= htmlspecialchars($success_message) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php if ($error_message): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <?= htmlspecialchars($error_message) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <form id="iscrizioneForm" action="salva_iscrizione.php" method="POST" novalidate>
                            <!-- Selezione Evento -->
                            <div class="mb-3">
                                <label for="evento_id" class="form-label">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    Evento *
                                </label>
                                <select class="form-select" id="evento_id" name="evento_id" required>
                                    <option value="">Seleziona un evento</option>
                                    <?php foreach ($eventi as $evento): ?>
                                        <option value="<?= $evento['ID'] ?>" <?= $evento_id == $evento['ID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($evento['nome']) ?> -
                                            <?= date('d/m/Y H:i', strtotime($evento['dataEvento'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Seleziona un evento per l'iscrizione.
                                </div>
                                <?php if (empty($eventi)): ?>
                                    <div class="form-text text-warning">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        Nessun evento disponibile al momento.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Nome -->
                            <div class="mb-3">
                                <label for="nome" class="form-label">
                                    <i class="bi bi-person me-1"></i>
                                    Nome *
                                </label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    id="nome" 
                                    name="nome" 
                                    value="<?= htmlspecialchars($nome) ?>"
                                    required
                                    maxlength="100"
                                    placeholder="Inserisci il tuo nome"
                                >
                                <div class="invalid-feedback">
                                    Il nome è obbligatorio (max 100 caratteri).
                                </div>
                            </div>

                            <!-- Cognome -->
                            <div class="mb-3">
                                <label for="cognome" class="form-label">
                                    <i class="bi bi-person me-1"></i>
                                    Cognome *
                                </label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    id="cognome" 
                                    name="cognome" 
                                    value="<?= htmlspecialchars($cognome) ?>"
                                    required
                                    maxlength="100"
                                    placeholder="Inserisci il tuo cognome"
                                >
                                <div class="invalid-feedback">
                                    Il cognome è obbligatorio (max 100 caratteri).
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="bi bi-envelope me-1"></i>
                                    Email *
                                </label>
                                <input 
                                    type="email" 
                                    class="form-control" 
                                    id="email" 
                                    name="email" 
                                    value="<?= htmlspecialchars($email) ?>"
                                    required
                                    maxlength="255"
                                    placeholder="esempio@email.com"
                                >
                                <div class="invalid-feedback">
                                    Inserisci un indirizzo email valido.
                                </div>
                            </div>

                            <!-- Telefono -->
                            <div class="mb-3">
                                <label for="telefono" class="form-label">
                                    <i class="bi bi-phone me-1"></i>
                                    Telefono
                                </label>
                                <input
                                    type="tel"
                                    class="form-control"
                                    id="telefono"
                                    name="telefono"
                                    value="<?= htmlspecialchars($telefono) ?>"
                                    maxlength="30"
                                    placeholder="Es: 123 456 7890"
                                    pattern="[0-9\s\+\-\(\)]+"
                                >
                                <div class="invalid-feedback">
                                    Inserisci un numero di telefono valido.
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Campo opzionale - solo numeri, spazi, +, -, (, )
                                </div>
                            </div>

                            <!-- Ruolo -->
                            <div class="mb-3">
                                <label for="ruolo" class="form-label">
                                    <i class="bi bi-briefcase me-1"></i>
                                    Ruolo/Posizione
                                </label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="ruolo"
                                    name="ruolo"
                                    value="<?= htmlspecialchars($ruolo) ?>"
                                    maxlength="100"
                                    placeholder="Es: Manager, Sviluppatore, Consulente"
                                >
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Campo opzionale
                                </div>
                            </div>

                            <!-- Azienda -->
                            <div class="mb-3">
                                <label for="azienda" class="form-label">
                                    <i class="bi bi-building me-1"></i>
                                    Azienda *
                                </label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="azienda"
                                    name="azienda"
                                    value="<?= htmlspecialchars($azienda) ?>"
                                    required
                                    maxlength="255"
                                    placeholder="Nome dell'azienda di appartenenza"
                                >
                                <div class="invalid-feedback">
                                    L'azienda è obbligatoria (max 255 caratteri).
                                </div>
                            </div>

                            <!-- Note -->
                            <div class="mb-4">
                                <label for="note" class="form-label">
                                    <i class="bi bi-chat-text me-1"></i>
                                    Note aggiuntive
                                </label>
                                <textarea 
                                    class="form-control" 
                                    id="note" 
                                    name="note" 
                                    rows="3"
                                    maxlength="500"
                                    placeholder="Eventuali note o richieste speciali (opzionale)"
                                ><?= htmlspecialchars($note) ?></textarea>
                                <div class="form-text">
                                    <span id="noteCount">0</span>/500 caratteri<br>
                                    <i class="bi bi-info-circle me-1"></i>
                                    <small class="text-muted">Le note verranno salvate separatamente per riferimento interno</small>
                                </div>
                            </div>

                            <!-- Privacy e Consenso -->
                            <div class="mb-4">
                                <div class="form-check">
                                    <input 
                                        class="form-check-input" 
                                        type="checkbox" 
                                        id="privacy" 
                                        name="privacy" 
                                        required
                                    >
                                    <label class="form-check-label" for="privacy">
                                        <i class="bi bi-shield-check me-1"></i>
                                        Accetto il trattamento dei dati personali secondo la privacy policy *
                                    </label>
                                    <div class="invalid-feedback">
                                        Devi accettare il trattamento dei dati personali per procedere.
                                    </div>
                                </div>
                            </div>

                            <!-- Pulsanti -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="reset" class="btn btn-outline-secondary me-md-2">
                                    <i class="bi bi-arrow-clockwise me-1"></i>
                                    Reset
                                </button>
                                <button type="submit" id="submitBtn" class="btn btn-success" <?= empty($eventi) ? 'disabled' : '' ?>>
                                    <i class="bi bi-check-lg me-1"></i>
                                    Iscriviti
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Link per tornare alla gestione eventi -->
                <div class="text-center mt-3">
                    <a href="index.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-1"></i>
                        Torna alla Gestione Eventi
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variabili globali
        let isSubmitting = false;

        // Inizializzazione
        document.addEventListener('DOMContentLoaded', function() {
            initializeFormValidation();
            initializeNoteCounter();
            initializeAsyncSubmit();
            initializeResetButton();
        });

        // Validazione personalizzata Bootstrap
        function initializeFormValidation() {
            const form = document.getElementById('iscrizioneForm');
            
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                } else if (!isSubmitting) {
                    event.preventDefault();
                    handleAsyncSubmit();
                }
                form.classList.add('was-validated');
            }, false);
        }

        // Contatore caratteri per le note
        function initializeNoteCounter() {
            const noteTextarea = document.getElementById('note');
            const noteCount = document.getElementById('noteCount');
            
            function updateCount() {
                const count = noteTextarea.value.length;
                noteCount.textContent = count;
                
                // Cambia colore quando si avvicina al limite
                if (count > 450) {
                    noteCount.className = 'text-danger';
                } else if (count > 400) {
                    noteCount.className = 'text-warning';
                } else {
                    noteCount.className = 'text-muted';
                }
            }
            
            noteTextarea.addEventListener('input', updateCount);
            updateCount(); // Inizializzazione
        }

        // Submit asincrono
        function initializeAsyncSubmit() {
            // Già gestito nella validazione del form
        }

        // Reset personalizzato
        function initializeResetButton() {
            document.querySelector('button[type="reset"]').addEventListener('click', function() {
                setTimeout(function() {
                    document.getElementById('iscrizioneForm').classList.remove('was-validated');
                    document.getElementById('noteCount').textContent = '0';
                    document.getElementById('noteCount').className = 'text-muted';
                    clearMessages();
                }, 10);
            });
        }

        // Gestione submit asincrono
        async function handleAsyncSubmit() {
            if (isSubmitting) return;
            
            const form = document.getElementById('iscrizioneForm');
            const submitBtn = document.getElementById('submitBtn');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            try {
                isSubmitting = true;
                
                // Mostra loading
                showLoading(true);
                submitBtn.disabled = true;
                
                // Prepara FormData
                const formData = new FormData(form);
                // Invia richiesta con header per identificarla come asincrona
                const response = await fetch('salva_iscrizione.php', {
                    method: 'POST',
                    body: JSON.stringify(Object.fromEntries(formData.entries())),
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Fetch-Request': 'true'
                    }
                });
                
                // Controlla se la risposta è ok
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                // Analizza la risposta - dovrebbe sempre essere JSON ora
                const result = await response.json();
                handleJsonResponse(result);
                
            } catch (error) {
                console.error('Errore durante l\'invio:', error);
                
                // Verifica se è un errore di parsing JSON
                if (error.name === 'SyntaxError') {
                    showMessage(
                        'Errore del server. La risposta non è nel formato previsto.', 
                        'danger'
                    );
                } else {
                    showMessage(
                        'Errore di connessione. Controlla la tua connessione internet e riprova.', 
                        'danger'
                    );
                }
            } finally {
                // Nasconde loading
                showLoading(false);
                submitBtn.disabled = false;
                isSubmitting = false;
            }
        }

        // Gestisce risposta JSON
        function handleJsonResponse(result) {
            if (result.success) {
                showMessage(result.message || 'Iscrizione completata con successo!', 'success');
                // Reset del form
                const form = document.getElementById('iscrizioneForm');
                form.reset();
                form.classList.remove('was-validated');
                document.getElementById('noteCount').textContent = '0';
                document.getElementById('noteCount').className = 'text-muted';
            } else {
                showMessage(result.message || 'Si è verificato un errore durante l\'invio.', 'danger');
                
                // Se ci sono errori specifici per campo, potresti gestirli qui
                if (result.errors) {
                    handleFieldErrors(result.errors);
                }
            }
        }

        // Gestisce errori specifici per campo
        function handleFieldErrors(errors) {
            // Rimuovi eventuali messaggi di errore personalizzati precedenti
            document.querySelectorAll('.custom-error-feedback').forEach(el => el.remove());
            
            // Aggiungi nuovi messaggi di errore
            Object.keys(errors).forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    field.classList.add('is-invalid');
                    
                    // Crea elemento per messaggio di errore personalizzato
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback custom-error-feedback';
                    errorDiv.textContent = errors[fieldName];
                    
                    // Inserisci dopo il campo
                    field.parentNode.insertBefore(errorDiv, field.nextSibling);
                }
            });
        }

        // Mostra/nasconde loading
        function showLoading(show) {
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.style.display = show ? 'flex' : 'none';
        }

        // Mostra messaggi
        function showMessage(message, type) {
            const messageArea = document.getElementById('messageArea');
            
            // Rimuovi messaggi precedenti
            clearMessages();
            
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.setAttribute('role', 'alert');
            
            const icon = type === 'success' ? 'check-circle' : 'exclamation-triangle';
            
            alertDiv.innerHTML = `
                <i class="bi bi-${icon} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            messageArea.appendChild(alertDiv);
            
            // Scroll verso l'alto per mostrare il messaggio
            document.querySelector('.card').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        }

        // Pulisce i messaggi
        function clearMessages() {
            const messageArea = document.getElementById('messageArea');
            messageArea.innerHTML = '';
            
            // Rimuovi anche errori personalizzati
            document.querySelectorAll('.custom-error-feedback').forEach(el => el.remove());
            document.querySelectorAll('.is-invalid').forEach(el => {
                if (!el.matches(':invalid')) {
                    el.classList.remove('is-invalid');
                }
            });
        }
    </script>
</body>
</html>