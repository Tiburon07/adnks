<?php
session_start();

require_once __DIR__ . '/classes/Database.php';

// Parametri di paginazione
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Parametri di ricerca e filtri
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$order_by = isset($_GET['order']) ? $_GET['order'] : 'createdAt';
$order_dir = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'ASC' : 'DESC';

// Recupero degli utenti
$utenti = [];
$totalUtenti = 0;
$totalPages = 0;
$error_message = '';

try {
    $pdo = getDB();

    // Costruzione della query base
    $whereConditions = ['u.deletedAt IS NULL'];
    $params = [];

    // Filtro per ricerca
    if (!empty($search)) {
        $whereConditions[] = "(u.nome LIKE :search OR u.cognome LIKE :search OR u.email LIKE :search OR u.Azienda LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    // Filtro per status
    if (!empty($status_filter)) {
        $whereConditions[] = "u.status = :status";
        $params[':status'] = $status_filter;
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Validazione campo ordinamento
    $validOrderFields = ['nome', 'cognome', 'email', 'Azienda', 'status', 'createdAt'];
    if (!in_array($order_by, $validOrderFields)) {
        $order_by = 'createdAt';
    }

    // Query per contare il totale
    $countSql = "SELECT COUNT(*) FROM Utenti u WHERE " . $whereClause;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalUtenti = $countStmt->fetchColumn();
    $totalPages = ceil($totalUtenti / $perPage);

    // Query per recuperare gli utenti con il conteggio delle iscrizioni
    $sql = "
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
            u.createdAt,
            u.updatedAt,
            COUNT(ie.ID) as iscrizioni_count,
            MAX(ie.dataIscrizione) as ultima_iscrizione
        FROM Utenti u
        LEFT JOIN Iscrizione_Eventi ie ON u.ID = ie.idUtente
        WHERE " . $whereClause . "
        GROUP BY u.ID
        ORDER BY u." . $order_by . " " . $order_dir . "
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $utenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistiche per status
    $statsSql = "
        SELECT
            status,
            COUNT(*) as count
        FROM Utenti
        WHERE deletedAt IS NULL
        GROUP BY status
    ";
    $statsStmt = $pdo->query($statsSql);
    $statsData = $statsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $stats = [
        'active' => $statsData['active'] ?? 0,
        'unactive' => $statsData['unactive'] ?? 0,
        'disabled' => $statsData['disabled'] ?? 0
    ];

} catch (Exception $e) {
    error_log("Errore recupero utenti: " . $e->getMessage());
    $error_message = "Errore nel caricamento degli utenti: " . $e->getMessage();
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
 * Genera URL per ordinamento
 */
function getSortUrl($field, $current_field, $current_dir) {
    $params = $_GET;
    $params['order'] = $field;
    $params['dir'] = ($field === $current_field && $current_dir === 'ASC') ? 'desc' : 'asc';
    return '?' . http_build_query($params);
}

/**
 * Genera icona di ordinamento
 */
function getSortIcon($field, $current_field, $current_dir) {
    if ($field !== $current_field) {
        return '<i class="bi bi-arrow-down-up text-muted"></i>';
    }
    return $current_dir === 'ASC' ? '<i class="bi bi-arrow-up"></i>' : '<i class="bi bi-arrow-down"></i>';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Utenti</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 mb-0">
                    <i class="bi bi-person-lines-fill me-2"></i>
                    Gestione Utenti
                </h1>
                <p class="text-muted mb-0">Visualizza e gestisci tutti gli utenti registrati</p>
            </div>
            <div class="col-auto">
                <div class="btn-group" role="group">
                    <a href="index.php" class="btn btn-outline-primary">
                        <i class="bi bi-calendar-event me-1"></i>
                        Gestione Eventi
                    </a>
                    <a href="visualizza_iscrizioni.php" class="btn btn-outline-info">
                        <i class="bi bi-people-fill me-1"></i>
                        Visualizza Iscrizioni
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
                        <h5 class="card-title text-primary"><?= $totalUtenti ?></h5>
                        <p class="card-text">Totale Utenti</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-success"><?= $stats['active'] ?></h5>
                        <p class="card-text">Attivi</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-warning"><?= $stats['unactive'] ?></h5>
                        <p class="card-text">Inattivi</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-secondary"><?= $stats['disabled'] ?></h5>
                        <p class="card-text">Disabilitati</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtri e ricerca -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Ricerca</label>
                        <input type="text" class="form-control" id="search" name="search"
                               value="<?= htmlspecialchars($search) ?>"
                               placeholder="Nome, cognome, email o azienda...">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Tutti</option>
                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Attivi</option>
                            <option value="unactive" <?= $status_filter === 'unactive' ? 'selected' : '' ?>>Inattivi</option>
                            <option value="disabled" <?= $status_filter === 'disabled' ? 'selected' : '' ?>>Disabilitati</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="order" class="form-label">Ordina per</label>
                        <select class="form-select" id="order" name="order">
                            <option value="createdAt" <?= $order_by === 'createdAt' ? 'selected' : '' ?>>Data registrazione</option>
                            <option value="nome" <?= $order_by === 'nome' ? 'selected' : '' ?>>Nome</option>
                            <option value="cognome" <?= $order_by === 'cognome' ? 'selected' : '' ?>>Cognome</option>
                            <option value="email" <?= $order_by === 'email' ? 'selected' : '' ?>>Email</option>
                            <option value="Azienda" <?= $order_by === 'Azienda' ? 'selected' : '' ?>>Azienda</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i>
                                Cerca
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabella Utenti -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-list-ul me-2"></i>
                    Elenco Utenti
                    <?php if (!empty($search) || !empty($status_filter)): ?>
                        <small class="text-muted">(filtrato)</small>
                    <?php endif; ?>
                </h5>
                <small class="text-muted">
                    Pagina <?= $page ?> di <?= $totalPages ?> (<?= $totalUtenti ?> utenti)
                </small>
            </div>
            <div class="card-body">
                <?php if (empty($utenti)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-person-x display-1 text-muted"></i>
                        <h4 class="text-muted mt-3">Nessun utente trovato</h4>
                        <?php if (!empty($search) || !empty($status_filter)): ?>
                            <p class="text-muted">Prova a modificare i filtri di ricerca.</p>
                            <a href="visualizza_utenti.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise me-1"></i>
                                Reset filtri
                            </a>
                        <?php else: ?>
                            <p class="text-muted">Non ci sono ancora utenti registrati.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>
                                        <a href="<?= getSortUrl('nome', $order_by, $order_dir) ?>" class="text-white text-decoration-none">
                                            Nome <?= getSortIcon('nome', $order_by, $order_dir) ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="<?= getSortUrl('cognome', $order_by, $order_dir) ?>" class="text-white text-decoration-none">
                                            Cognome <?= getSortIcon('cognome', $order_by, $order_dir) ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="<?= getSortUrl('email', $order_by, $order_dir) ?>" class="text-white text-decoration-none">
                                            Email <?= getSortIcon('email', $order_by, $order_dir) ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="<?= getSortUrl('Azienda', $order_by, $order_dir) ?>" class="text-white text-decoration-none">
                                            Azienda <?= getSortIcon('Azienda', $order_by, $order_dir) ?>
                                        </a>
                                    </th>
                                    <th>Status</th>
                                    <th>Iscrizioni</th>
                                    <th>
                                        <a href="<?= getSortUrl('createdAt', $order_by, $order_dir) ?>" class="text-white text-decoration-none">
                                            Registrato <?= getSortIcon('createdAt', $order_by, $order_dir) ?>
                                        </a>
                                    </th>
                                    <th class="text-center">Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($utenti as $utente): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?= $utente['ID'] ?></strong>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($utente['nome']) ?></strong>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($utente['cognome']) ?></strong>
                                        </td>
                                        <td>
                                            <div>
                                                <i class="bi bi-envelope me-1"></i>
                                                <?= htmlspecialchars($utente['email']) ?>
                                            </div>
                                            <?php if (!empty($utente['telefono'])): ?>
                                                <small class="text-muted">
                                                    <i class="bi bi-telephone me-1"></i>
                                                    <?= htmlspecialchars($utente['telefono']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <i class="bi bi-building me-1"></i>
                                                <?= htmlspecialchars($utente['Azienda']) ?>
                                            </div>
                                            <?php if (!empty($utente['ruolo'])): ?>
                                                <small class="text-muted">
                                                    <i class="bi bi-briefcase me-1"></i>
                                                    <?= htmlspecialchars($utente['ruolo']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= getStatusBadge($utente['status']) ?>
                                        </td>
                                        <td>
                                            <?php if ($utente['iscrizioni_count'] > 0): ?>
                                                <div>
                                                    <span class="badge bg-primary"><?= $utente['iscrizioni_count'] ?></span>
                                                    <small class="text-muted">iscrizioni</small>
                                                </div>
                                                <?php if ($utente['ultima_iscrizione']): ?>
                                                    <small class="text-muted">
                                                        Ultima: <?= date('d/m/Y', strtotime($utente['ultima_iscrizione'])) ?>
                                                    </small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <small class="text-muted">Nessuna iscrizione</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <?= date('d/m/Y', strtotime($utente['createdAt'])) ?>
                                            </div>
                                            <small class="text-muted">
                                                <?= date('H:i', strtotime($utente['createdAt'])) ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-info"
                                                        onclick="viewUserDetails(<?= $utente['ID'] ?>)"
                                                        title="Visualizza dettagli utente">
                                                    <i class="bi bi-eye"></i>
                                                </button>

                                                <?php if ($utente['iscrizioni_count'] > 0): ?>
                                                    <a href="visualizza_iscrizioni.php?utente=<?= $utente['ID'] ?>"
                                                       class="btn btn-outline-primary"
                                                       title="Visualizza iscrizioni utente">
                                                        <i class="bi bi-calendar-check"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($utente['status'] === 'active'): ?>
                                                    <button type="button" class="btn btn-outline-warning"
                                                            onclick="updateUserStatus(<?= $utente['ID'] ?>, 'unactive')"
                                                            title="Disattiva utente">
                                                        <i class="bi bi-person-dash"></i>
                                                    </button>
                                                <?php elseif ($utente['status'] === 'unactive'): ?>
                                                    <button type="button" class="btn btn-outline-success"
                                                            onclick="updateUserStatus(<?= $utente['ID'] ?>, 'active')"
                                                            title="Attiva utente">
                                                        <i class="bi bi-person-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginazione -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start = max(1, $page - 2);
                                $end = min($totalPages, $page + 2);

                                for ($i = $start; $i <= $end; $i++):
                                ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal per dettagli utente -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-circle me-2"></i>
                        Dettagli Utente
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="userModalContent">
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

        // Funzione per visualizzare i dettagli utente
        function viewUserDetails(userId) {
            fetch('dettagli_utente.php?id=' + userId)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('userModalContent').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('userDetailsModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('userModalContent').innerHTML =
                        '<div class="alert alert-danger">Errore nel caricamento dei dettagli utente.</div>';
                    new bootstrap.Modal(document.getElementById('userDetailsModal')).show();
                });
        }

        // Funzione per aggiornare lo status dell'utente
        function updateUserStatus(userId, newStatus) {
            let message = '';
            let buttonClass = 'btn-primary';

            switch(newStatus) {
                case 'active':
                    message = 'Attivare questo utente?';
                    buttonClass = 'btn-success';
                    break;
                case 'unactive':
                    message = 'Disattivare questo utente?';
                    buttonClass = 'btn-warning';
                    break;
                case 'disabled':
                    message = 'Disabilitare questo utente? Questa azione impedirà completamente l\'accesso.';
                    buttonClass = 'btn-danger';
                    break;
                default:
                    message = 'Procedere con questa azione?';
            }

            document.getElementById('confirmMessage').textContent = message;
            document.getElementById('confirmAction').className = 'btn ' + buttonClass;

            currentAction = () => {
                fetch('update_utente.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: userId,
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

        // Event listener per il pulsante di conferma
        document.getElementById('confirmAction').addEventListener('click', function() {
            if (currentAction) {
                currentAction();
                bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
            }
        });

        // Gestione form di ricerca con invio automatico dopo typing
        let searchTimeout;
        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });

        // Invio automatico del form quando cambia il filtro status
        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>