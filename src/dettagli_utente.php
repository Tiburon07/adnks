<?php
session_start();

require_once __DIR__ . '/classes/Database.php';

// Verifica che sia stata fornita un ID utente
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">ID utente non valido.</div>';
    exit;
}

$userId = (int)$_GET['id'];

try {
    $pdo = getDB();

    // Query per recuperare i dettagli dell'utente
    $userSql = "
        SELECT
            u.ID,
            u.email,
            u.nome,
            u.cognome,
            u.ruolo,
            u.telefono,
            u.note,
            u.status,
            u.Azienda,
            u.anonymizedAt,
            u.createdAt,
            u.updatedAt,
            COUNT(DISTINCT ie.ID) as total_iscrizioni,
            COUNT(DISTINCT CASE WHEN ie.status = 'confirmed' THEN ie.ID END) as iscrizioni_confermate,
            COUNT(DISTINCT CASE WHEN ie.status = 'pending' THEN ie.ID END) as iscrizioni_pending,
            COUNT(DISTINCT CASE WHEN ie.status = 'cancelled' THEN ie.ID END) as iscrizioni_annullate,
            MAX(ie.dataIscrizione) as ultima_iscrizione
        FROM Utenti u
        LEFT JOIN Iscrizione_Eventi ie ON u.ID = ie.idUtente
        WHERE u.ID = :user_id AND u.deletedAt IS NULL
        GROUP BY u.ID
    ";

    $userStmt = $pdo->prepare($userSql);
    $userStmt->execute([':user_id' => $userId]);
    $utente = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$utente) {
        echo '<div class="alert alert-warning">Utente non trovato.</div>';
        exit;
    }

    // Query per recuperare le iscrizioni dell'utente
    $iscrizioniSql = "
        SELECT
            ie.ID,
            ie.dataIscrizione,
            ie.checkin,
            ie.status,
            ie.cancelledAt,
            e.nome as evento_nome,
            e.dataEvento,
            e.categoria as evento_categoria,
            e.tipo as evento_tipo
        FROM Iscrizione_Eventi ie
        INNER JOIN Eventi e ON ie.idEvento = e.ID
        WHERE ie.idUtente = :user_id
        ORDER BY ie.dataIscrizione DESC
        LIMIT 10
    ";

    $iscrizioniStmt = $pdo->prepare($iscrizioniSql);
    $iscrizioniStmt->execute([':user_id' => $userId]);
    $iscrizioni = $iscrizioniStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Errore recupero dettagli utente: " . $e->getMessage());
    echo '<div class="alert alert-danger">Errore nel caricamento dei dettagli utente.</div>';
    exit;
}

/**
 * Funzione helper per il badge dello status
 */
function getStatusBadge($status) {
    $badges = [
        'active' => '<span class="badge bg-success">Attivo</span>',
        'unactive' => '<span class="badge bg-warning">Inattivo</span>',
        'disabled' => '<span class="badge bg-secondary">Disabilitato</span>'
    ];

    return $badges[$status] ?? '<span class="badge bg-light text-dark">Sconosciuto</span>';
}

/**
 * Funzione helper per il badge dello status iscrizione
 */
function getIscrizioneStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge bg-warning text-dark">In Attesa</span>',
        'confirmed' => '<span class="badge bg-success">Confermata</span>',
        'cancelled' => '<span class="badge bg-danger">Annullata</span>',
        'bounced' => '<span class="badge bg-secondary">Respinta</span>'
    ];

    return $badges[$status] ?? '<span class="badge bg-light text-dark">Sconosciuto</span>';
}
?>

<div class="row">
    <!-- Informazioni generali utente -->
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-person-circle me-2"></i>
                    Informazioni Personali
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-4"><strong>Nome completo:</strong></div>
                    <div class="col-sm-8"><?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?></div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-sm-4"><strong>Email:</strong></div>
                    <div class="col-sm-8">
                        <i class="bi bi-envelope me-1"></i>
                        <?= htmlspecialchars($utente['email']) ?>
                    </div>
                </div>
                <?php if (!empty($utente['telefono'])): ?>
                    <hr>
                    <div class="row">
                        <div class="col-sm-4"><strong>Telefono:</strong></div>
                        <div class="col-sm-8">
                            <i class="bi bi-telephone me-1"></i>
                            <?= htmlspecialchars($utente['telefono']) ?>
                        </div>
                    </div>
                <?php endif; ?>
                <hr>
                <div class="row">
                    <div class="col-sm-4"><strong>Azienda:</strong></div>
                    <div class="col-sm-8">
                        <i class="bi bi-building me-1"></i>
                        <?= htmlspecialchars($utente['Azienda']) ?>
                    </div>
                </div>
                <?php if (!empty($utente['ruolo'])): ?>
                    <hr>
                    <div class="row">
                        <div class="col-sm-4"><strong>Ruolo:</strong></div>
                        <div class="col-sm-8">
                            <i class="bi bi-briefcase me-1"></i>
                            <?= htmlspecialchars($utente['ruolo']) ?>
                        </div>
                    </div>
                <?php endif; ?>
                <hr>
                <div class="row">
                    <div class="col-sm-4"><strong>Status:</strong></div>
                    <div class="col-sm-8"><?= getStatusBadge($utente['status']) ?></div>
                </div>
                <?php if (!empty($utente['note'])): ?>
                    <hr>
                    <div class="row">
                        <div class="col-sm-4"><strong>Note:</strong></div>
                        <div class="col-sm-8">
                            <div class="bg-light p-2 rounded">
                                <?= nl2br(htmlspecialchars($utente['note'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Date importanti -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-calendar3 me-2"></i>
                    Date Importanti
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-4"><strong>Registrato il:</strong></div>
                    <div class="col-sm-8">
                        <?= date('d/m/Y H:i', strtotime($utente['createdAt'])) ?>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-sm-4"><strong>Ultimo aggiornamento:</strong></div>
                    <div class="col-sm-8">
                        <?= date('d/m/Y H:i', strtotime($utente['updatedAt'])) ?>
                    </div>
                </div>
                <?php if ($utente['ultima_iscrizione']): ?>
                    <hr>
                    <div class="row">
                        <div class="col-sm-4"><strong>Ultima iscrizione:</strong></div>
                        <div class="col-sm-8">
                            <?= date('d/m/Y H:i', strtotime($utente['ultima_iscrizione'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($utente['anonymizedAt']): ?>
                    <hr>
                    <div class="row">
                        <div class="col-sm-4"><strong>Anonimizzato il:</strong></div>
                        <div class="col-sm-8 text-warning">
                            <i class="bi bi-shield-exclamation me-1"></i>
                            <?= date('d/m/Y H:i', strtotime($utente['anonymizedAt'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Statistiche iscrizioni -->
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-bar-chart me-2"></i>
                    Statistiche Iscrizioni
                </h6>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <h4 class="text-primary"><?= $utente['total_iscrizioni'] ?></h4>
                    <small class="text-muted">Totale Iscrizioni</small>
                </div>

                <div class="mb-2">
                    <div class="d-flex justify-content-between">
                        <small>Confermate</small>
                        <small><span class="badge bg-success"><?= $utente['iscrizioni_confermate'] ?></span></small>
                    </div>
                </div>

                <div class="mb-2">
                    <div class="d-flex justify-content-between">
                        <small>In Attesa</small>
                        <small><span class="badge bg-warning"><?= $utente['iscrizioni_pending'] ?></span></small>
                    </div>
                </div>

                <div class="mb-2">
                    <div class="d-flex justify-content-between">
                        <small>Annullate</small>
                        <small><span class="badge bg-danger"><?= $utente['iscrizioni_annullate'] ?></span></small>
                    </div>
                </div>

                <?php if ($utente['total_iscrizioni'] > 0): ?>
                    <hr>
                    <div class="d-grid">
                        <a href="visualizza_iscrizioni.php?utente=<?= $utente['ID'] ?>"
                           class="btn btn-outline-primary btn-sm"
                           target="_blank">
                            <i class="bi bi-calendar-check me-1"></i>
                            Visualizza tutte le iscrizioni
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Ultime iscrizioni -->
<?php if (!empty($iscrizioni)): ?>
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="bi bi-clock-history me-2"></i>
                Ultime Iscrizioni
                <small class="text-muted">(<?= count($iscrizioni) ?> più recenti)</small>
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Evento</th>
                            <th>Data Evento</th>
                            <th>Data Iscrizione</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($iscrizioni as $iscrizione): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($iscrizione['evento_nome']) ?></strong>
                                    </div>
                                    <small class="text-muted">
                                        <i class="bi bi-tag me-1"></i>
                                        <?= htmlspecialchars($iscrizione['evento_categoria']) ?>
                                        •
                                        <?php if ($iscrizione['evento_tipo'] === 'presenza'): ?>
                                            <i class="bi bi-people me-1"></i>Presenza
                                        <?php else: ?>
                                            <i class="bi bi-camera-video me-1"></i>Virtuale
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <div><?= date('d/m/Y', strtotime($iscrizione['dataEvento'])) ?></div>
                                    <small class="text-muted"><?= date('H:i', strtotime($iscrizione['dataEvento'])) ?></small>
                                </td>
                                <td>
                                    <div><?= date('d/m/Y', strtotime($iscrizione['dataIscrizione'])) ?></div>
                                    <small class="text-muted"><?= date('H:i', strtotime($iscrizione['dataIscrizione'])) ?></small>
                                </td>
                                <td>
                                    <?= getIscrizioneStatusBadge($iscrizione['status']) ?>
                                    <?php if ($iscrizione['status'] === 'cancelled' && $iscrizione['cancelledAt']): ?>
                                        <br>
                                        <small class="text-muted">
                                            <?= date('d/m/Y', strtotime($iscrizione['cancelledAt'])) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($utente['total_iscrizioni'] > count($iscrizioni)): ?>
                <div class="text-center mt-3">
                    <a href="visualizza_iscrizioni.php?utente=<?= $utente['ID'] ?>"
                       class="btn btn-outline-secondary btn-sm"
                       target="_blank">
                        <i class="bi bi-arrow-right me-1"></i>
                        Visualizza tutte le <?= $utente['total_iscrizioni'] ?> iscrizioni
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body text-center py-4">
            <i class="bi bi-calendar-x display-4 text-muted"></i>
            <h5 class="text-muted mt-3">Nessuna iscrizione</h5>
            <p class="text-muted">Questo utente non ha ancora effettuato nessuna iscrizione agli eventi.</p>
        </div>
    </div>
<?php endif; ?>