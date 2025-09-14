<?php
session_start();

require_once __DIR__ . '/classes/Database.php';

// Recupero delle iscrizioni con informazioni utente e evento
$iscrizioni = [];
$totalIscrizioni = 0;
$error_message = '';

try {
    $pdo = getDB();
    
    // Query per recuperare tutte le iscrizioni con dati utente e evento
    $sql = "
        SELECT 
            ie.ID,
            ie.dataIscrizione,
            ie.checkin,
            ie.status,
            ie.cancelledAt,
            u.nome AS utente_nome,
            u.cognome AS utente_cognome,
            u.email AS utente_email,
            u.telefono AS utente_telefono,
            e.nome AS evento_nome,
            e.dataEvento,
            e.categoria AS evento_categoria,
            e.tipo AS evento_tipo
        FROM Iscrizione_Eventi ie
        INNER JOIN Utenti u ON ie.idUtente = u.ID
        INNER JOIN Eventi e ON ie.idEvento = e.ID
        ORDER BY ie.dataIscrizione DESC
    ";
    
    $stmt = $pdo->query($sql);
    $iscrizioni = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalIscrizioni = count($iscrizioni);
    
    // Statistiche per status
    $stats = [
        'pending' => 0,
        'confirmed' => 0,
        'cancelled' => 0,
        'bounced' => 0
    ];
    
    foreach ($iscrizioni as $iscrizione) {
        if (isset($stats[$iscrizione['status']])) {
            $stats[$iscrizione['status']]++;
        }
    }
    
} catch (Exception $e) {
    error_log("Errore recupero iscrizioni: " . $e->getMessage());
    $error_message = "Errore nel caricamento delle iscrizioni: " . $e->getMessage();
}

/**
 * Funzione helper per il badge dello status
 */
function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge bg-warning">In Attesa</span>',
        'confirmed' => '<span class="badge bg-success">Confermata</span>',
        'cancelled' => '<span class="badge bg-danger">Annullata</span>',
        'bounced' => '<span class="badge bg-secondary">Respinta</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-light text-dark">Sconosciuto</span>';
}

/**
 * Funzione helper per il badge del checkin
 */
function getCheckinBadge($checkin) {
    $badges = [
        'presenza' => '<span class="badge bg-info">Presenza</span>',
        'virtuale' => '<span class="badge bg-primary">Virtuale</span>',
        'NA' => '<span class="badge bg-light text-dark">N/A</span>'
    ];
    
    return $badges[$checkin] ?? '<span class="badge bg-light text-dark">N/A</span>';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Iscrizioni Eventi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 mb-0">
                    <i class="bi bi-people-fill me-2"></i>
                    Gestione Iscrizioni Eventi
                </h1>
                <p class="text-muted mb-0">Visualizza e gestisci tutte le iscrizioni agli eventi</p>
            </div>
            <div class="col-auto">
                <div class="btn-group" role="group">
                    <a href="index.php" class="btn btn-outline-primary">
                        <i class="bi bi-calendar-event me-1"></i>
                        Gestione Eventi
                    </a>
                    <a href="iscrizione.php" class="btn btn-success">
                        <i class="bi bi-person-plus me-1"></i>
                        Nuova Iscrizione
                    </a>
                </div>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Statistiche -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-primary"><?= $totalIscrizioni ?></h5>
                        <p class="card-text">Totale Iscrizioni</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-warning"><?= $stats['pending'] ?></h5>
                        <p class="card-text">In Attesa</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-success"><?= $stats['confirmed'] ?></h5>
                        <p class="card-text">Confermate</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-danger"><?= $stats['cancelled'] ?></h5>
                        <p class="card-text">Annullate</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabella Iscrizioni -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-list-ul me-2"></i>
                    Elenco Iscrizioni
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($iscrizioni)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                        <h4 class="text-muted mt-3">Nessuna iscrizione trovata</h4>
                        <p class="text-muted">Non ci sono ancora iscrizioni agli eventi.</p>
                        <a href="iscrizione.php" class="btn btn-success">
                            <i class="bi bi-person-plus me-1"></i>
                            Crea Prima Iscrizione
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Partecipante</th>
                                    <th>Evento</th>
                                    <th>Data Evento</th>
                                    <th>Data Iscrizione</th>
                                    <th>Status</th>
                                    <th>Check-in</th>
                                    <th class="text-center">Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($iscrizioni as $iscrizione): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?= $iscrizione['ID'] ?></strong>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($iscrizione['utente_nome'] . ' ' . $iscrizione['utente_cognome']) ?></strong>
                                            </div>
                                            <small class="text-muted">
                                                <i class="bi bi-envelope me-1"></i>
                                                <?= htmlspecialchars($iscrizione['utente_email']) ?>
                                            </small>
                                            <?php if (!empty($iscrizione['utente_telefono'])): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="bi bi-telephone me-1"></i>
                                                    <?= htmlspecialchars($iscrizione['utente_telefono']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($iscrizione['evento_nome']) ?></strong>
                                            </div>
                                            <small class="text-muted">
                                                <i class="bi bi-tag me-1"></i>
                                                <?= htmlspecialchars($iscrizione['evento_categoria']) ?>
                                            </small>
                                            <br>
                                            <small class="text-muted">
                                                <?php if ($iscrizione['evento_tipo'] === 'presenza'): ?>
                                                    <i class="bi bi-people me-1"></i>In Presenza
                                                <?php else: ?>
                                                    <i class="bi bi-camera-video me-1"></i>Virtuale
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div>
                                                <?= date('d/m/Y', strtotime($iscrizione['dataEvento'])) ?>
                                            </div>
                                            <small class="text-muted">
                                                <?= date('H:i', strtotime($iscrizione['dataEvento'])) ?>
                                            </small>
                                            <?php 
                                            $evento_passato = strtotime($iscrizione['dataEvento']) < time();
                                            if ($evento_passato): 
                                            ?>
                                                <br><small class="text-danger">
                                                    <i class="bi bi-exclamation-triangle me-1"></i>Evento passato
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <?= date('d/m/Y', strtotime($iscrizione['dataIscrizione'])) ?>
                                            </div>
                                            <small class="text-muted">
                                                <?= date('H:i', strtotime($iscrizione['dataIscrizione'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?= getStatusBadge($iscrizione['status']) ?>
                                            <?php if ($iscrizione['status'] === 'cancelled' && $iscrizione['cancelledAt']): ?>
                                                <br>
                                                <small class="text-muted">
                                                    Annullata il <?= date('d/m/Y H:i', strtotime($iscrizione['cancelledAt'])) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= getCheckinBadge($iscrizione['checkin']) ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <?php if ($iscrizione['status'] === 'pending'): ?>
                                                    <button type="button" class="btn btn-outline-success" 
                                                            onclick="updateStatus(<?= $iscrizione['ID'] ?>, 'confirmed')"
                                                            title="Conferma iscrizione">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="updateStatus(<?= $iscrizione['ID'] ?>, 'cancelled')"
                                                            title="Annulla iscrizione">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                <?php elseif ($iscrizione['status'] === 'confirmed'): ?>
                                                    <?php if (!$evento_passato && $iscrizione['checkin'] === 'NA'): ?>
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                onclick="updateCheckin(<?= $iscrizione['ID'] ?>, '<?= $iscrizione['evento_tipo'] ?>')"
                                                                title="Effettua check-in">
                                                            <i class="bi bi-box-arrow-in-right"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-outline-warning" 
                                                            onclick="updateStatus(<?= $iscrizione['ID'] ?>, 'cancelled')"
                                                            title="Annulla iscrizione">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                <?php elseif ($iscrizione['status'] === 'cancelled'): ?>
                                                    <button type="button" class="btn btn-outline-success" 
                                                            onclick="updateStatus(<?= $iscrizione['ID'] ?>, 'confirmed')"
                                                            title="Riattiva iscrizione">
                                                        <i class="bi bi-arrow-clockwise"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="btn btn-outline-info" 
                                                        onclick="viewDetails(<?= $iscrizione['ID'] ?>)"
                                                        title="Visualizza dettagli">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal per dettagli iscrizione -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-info-circle me-2"></i>
                        Dettagli Iscrizione
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="modalContent">
                        <div class="text-center py-3">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Caricamento...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal per conferma azioni -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Conferma Azione</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmMessage">Sei sicuro di voler procedere?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" id="confirmAction">Conferma</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentAction = null;
        let currentIscrizioneId = null;

        // Funzione per aggiornare lo status
        function updateStatus(iscrizioneId, newStatus) {
            currentIscrizioneId = iscrizioneId;
            
            let message = '';
            let buttonClass = 'btn-primary';
            
            switch(newStatus) {
                case 'confirmed':
                    message = 'Confermare questa iscrizione?';
                    buttonClass = 'btn-success';
                    break;
                case 'cancelled':
                    message = 'Annullare questa iscrizione?';
                    buttonClass = 'btn-danger';
                    break;
                default:
                    message = 'Procedere con questa azione?';
            }
            
            document.getElementById('confirmMessage').textContent = message;
            document.getElementById('confirmAction').className = 'btn ' + buttonClass;
            
            currentAction = () => {
                fetch('update_iscrizione.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: iscrizioneId,
                        action: 'update_status',
                        status: newStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Errore: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Si è verificato un errore durante l\'aggiornamento.');
                });
            };
            
            new bootstrap.Modal(document.getElementById('confirmModal')).show();
        }

        // Funzione per aggiornare il checkin
        function updateCheckin(iscrizioneId, eventoTipo) {
            currentIscrizioneId = iscrizioneId;
            
            document.getElementById('confirmMessage').textContent = 
                'Effettuare il check-in per questa iscrizione come "' + 
                (eventoTipo === 'presenza' ? 'Presenza' : 'Virtuale') + '"?';
            document.getElementById('confirmAction').className = 'btn btn-primary';
            
            currentAction = () => {
                fetch('update_iscrizione.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: iscrizioneId,
                        action: 'checkin',
                        checkin: eventoTipo
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Errore: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Si è verificato un errore durante il check-in.');
                });
            };
            
            new bootstrap.Modal(document.getElementById('confirmModal')).show();
        }

        // Funzione per visualizzare i dettagli
        function viewDetails(iscrizioneId) {
            fetch('dettagli_iscrizione.php?id=' + iscrizioneId)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('modalContent').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('detailsModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('modalContent').innerHTML = 
                        '<div class="alert alert-danger">Errore nel caricamento dei dettagli.</div>';
                    new bootstrap.Modal(document.getElementById('detailsModal')).show();
                });
        }

        // Event listener per il pulsante di conferma
        document.getElementById('confirmAction').addEventListener('click', function() {
            if (currentAction) {
                currentAction();
                bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
            }
        });

        // Auto-refresh ogni 30 secondi
        setInterval(function() {
            // Solo se non ci sono modal aperti
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>