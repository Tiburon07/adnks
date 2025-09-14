<?php
require_once __DIR__ . '/classes/Database.php';

// Verifica che sia stato passato l'ID
if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">ID iscrizione non valido.</div>';
    exit;
}

$iscrizioneId = (int)$_GET['id'];

try {
    $pdo = getDB();
    
    // Query per recuperare i dettagli completi dell'iscrizione
    $sql = "
        SELECT 
            ie.ID,
            ie.dataIscrizione,
            ie.checkin,
            ie.status,
            ie.cancelledAt,
            ie.createdAt,
            ie.updatedAt,
            u.ID as utente_id,
            u.nome AS utente_nome,
            u.cognome AS utente_cognome,
            u.email AS utente_email,
            u.telefono AS utente_telefono,
            u.createdAt AS utente_createdAt,
            e.ID as evento_id,
            e.nome AS evento_nome,
            e.dataEvento,
            e.categoria AS evento_categoria,
            e.tipo AS evento_tipo,
            e.createdAt AS evento_createdAt
        FROM Iscrizione_Eventi ie
        INNER JOIN Utenti u ON ie.idUtente = u.ID
        INNER JOIN Eventi e ON ie.idEvento = e.ID
        WHERE ie.ID = :id
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $iscrizioneId]);
    $iscrizione = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$iscrizione) {
        echo '<div class="alert alert-danger">Iscrizione non trovata.</div>';
        exit;
    }
    
    // Recupera le note se la tabella esiste
    $note = null;
    try {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'Note_Iscrizioni'");
        if ($checkTable->rowCount() > 0) {
            $noteSql = "SELECT nota, createdAt FROM Note_Iscrizioni WHERE idIscrizione = :id ORDER BY createdAt DESC LIMIT 1";
            $noteStmt = $pdo->prepare($noteSql);
            $noteStmt->execute([':id' => $iscrizioneId]);
            $note = $noteStmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // Ignora errori delle note
        error_log("Errore recupero note: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    error_log("Errore recupero dettagli iscrizione: " . $e->getMessage());
    echo '<div class="alert alert-danger">Errore nel caricamento dei dettagli.</div>';
    exit;
}

/**
 * Helper functions per i badge
 */
function getStatusBadgeDettagli($status) {
    $badges = [
        'pending' => '<span class="badge bg-warning fs-6">In Attesa</span>',
        'confirmed' => '<span class="badge bg-success fs-6">Confermata</span>',
        'cancelled' => '<span class="badge bg-danger fs-6">Annullata</span>',
        'bounced' => '<span class="badge bg-secondary fs-6">Respinta</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-light text-dark fs-6">Sconosciuto</span>';
}

function getCheckinBadgeDettagli($checkin) {
    $badges = [
        'presenza' => '<span class="badge bg-info fs-6">Presenza</span>',
        'virtuale' => '<span class="badge bg-primary fs-6">Virtuale</span>',
        'NA' => '<span class="badge bg-light text-dark fs-6">N/A</span>'
    ];
    
    return $badges[$checkin] ?? '<span class="badge bg-light text-dark fs-6">N/A</span>';
}

$evento_passato = strtotime($iscrizione['dataEvento']) < time();
?>

<div class="row">
    <!-- Informazioni Iscrizione -->
    <div class="col-md-6">
        <h6 class="text-primary mb-3">
            <i class="bi bi-card-list me-2"></i>
            Informazioni Iscrizione
        </h6>
        
        <table class="table table-sm">
            <tr>
                <td class="fw-bold">ID Iscrizione:</td>
                <td>#<?= $iscrizione['ID'] ?></td>
            </tr>
            <tr>
                <td class="fw-bold">Status:</td>
                <td><?= getStatusBadgeDettagli($iscrizione['status']) ?></td>
            </tr>
            <tr>
                <td class="fw-bold">Check-in:</td>
                <td><?= getCheckinBadgeDettagli($iscrizione['checkin']) ?></td>
            </tr>
            <tr>
                <td class="fw-bold">Data Iscrizione:</td>
                <td>
                    <?= date('d/m/Y H:i:s', strtotime($iscrizione['dataIscrizione'])) ?>
                </td>
            </tr>
            <?php if ($iscrizione['cancelledAt']): ?>
            <tr>
                <td class="fw-bold">Annullata il:</td>
                <td class="text-danger">
                    <?= date('d/m/Y H:i:s', strtotime($iscrizione['cancelledAt'])) ?>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="fw-bold">Ultimo Aggiornamento:</td>
                <td>
                    <?= date('d/m/Y H:i:s', strtotime($iscrizione['updatedAt'])) ?>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- Informazioni Partecipante -->
    <div class="col-md-6">
        <h6 class="text-success mb-3">
            <i class="bi bi-person me-2"></i>
            Informazioni Partecipante
        </h6>
        
        <table class="table table-sm">
            <tr>
                <td class="fw-bold">ID Utente:</td>
                <td>#<?= $iscrizione['utente_id'] ?></td>
            </tr>
            <tr>
                <td class="fw-bold">Nome Completo:</td>
                <td><?= htmlspecialchars($iscrizione['utente_nome'] . ' ' . $iscrizione['utente_cognome']) ?></td>
            </tr>
            <tr>
                <td class="fw-bold">Email:</td>
                <td>
                    <a href="mailto:<?= htmlspecialchars($iscrizione['utente_email']) ?>" class="text-decoration-none">
                        <i class="bi bi-envelope me-1"></i>
                        <?= htmlspecialchars($iscrizione['utente_email']) ?>
                    </a>
                </td>
            </tr>
            <?php if ($iscrizione['utente_telefono']): ?>
            <tr>
                <td class="fw-bold">Telefono:</td>
                <td>
                    <a href="tel:<?= htmlspecialchars($iscrizione['utente_telefono']) ?>" class="text-decoration-none">
                        <i class="bi bi-telephone me-1"></i>
                        <?= htmlspecialchars($iscrizione['utente_telefono']) ?>
                    </a>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="fw-bold">Registrato il:</td>
                <td>
                    <?= date('d/m/Y H:i', strtotime($iscrizione['utente_createdAt'])) ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<hr>

<!-- Informazioni Evento -->
<div class="row">
    <div class="col-12">
        <h6 class="text-info mb-3">
            <i class="bi bi-calendar-event me-2"></i>
            Informazioni Evento
        </h6>
        
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <td class="fw-bold">ID Evento:</td>
                        <td>#<?= $iscrizione['evento_id'] ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Nome Evento:</td>
                        <td><?= htmlspecialchars($iscrizione['evento_nome']) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Categoria:</td>
                        <td>
                            <span class="badge bg-secondary">
                                <?= htmlspecialchars($iscrizione['evento_categoria']) ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <td class="fw-bold">Tipo:</td>
                        <td>
                            <?php if ($iscrizione['evento_tipo'] === 'presenza'): ?>
                                <span class="badge bg-info">
                                    <i class="bi bi-people me-1"></i>In Presenza
                                </span>
                            <?php else: ?>
                                <span class="badge bg-primary">
                                    <i class="bi bi-camera-video me-1"></i>Virtuale
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Data e Ora:</td>
                        <td>
                            <?= date('d/m/Y H:i', strtotime($iscrizione['dataEvento'])) ?>
                            <?php if ($evento_passato): ?>
                                <br><small class="text-danger">
                                    <i class="bi bi-exclamation-triangle me-1"></i>Evento passato
                                </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Creato il:</td>
                        <td>
                            <?= date('d/m/Y H:i', strtotime($iscrizione['evento_createdAt'])) ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>