<?php
// phpinfo();
// die();
// Avvio la sessione per gestire eventuali messaggi di feedback
session_start();

// Recupero eventuali messaggi dalla sessione
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Pulisco i messaggi dalla sessione dopo averli recuperati
unset($_SESSION['success'], $_SESSION['error']);

// Valori predefiniti per il form (in caso di errori di validazione)
$nome = isset($_SESSION['form_data']['nome']) ? $_SESSION['form_data']['nome'] : '';
$dataEvento = isset($_SESSION['form_data']['dataEvento']) ? $_SESSION['form_data']['dataEvento'] : '';
$categoria = isset($_SESSION['form_data']['categoria']) ? $_SESSION['form_data']['categoria'] : '';
$tipo = isset($_SESSION['form_data']['tipo']) ? $_SESSION['form_data']['tipo'] : '';

// Pulisco i dati del form dalla sessione
unset($_SESSION['form_data']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Eventi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <!-- Header con navigazione -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="h2 mb-0">
                    <i class="bi bi-calendar-event me-2"></i>
                    Gestione Eventi
                </h1>
                <p class="text-muted">Crea e gestisci i tuoi eventi</p>
            </div>
            <div class="col-auto">
                <div class="btn-group" role="group">
                    <a href="iscrizione.php" class="btn btn-success">
                        <i class="bi bi-person-plus me-1"></i>
                        Iscriviti ad un Evento
                    </a>
                    <a href="visualizza_iscrizioni.php" class="btn btn-outline-info">
                        <i class="bi bi-people me-1"></i>
                        Visualizza Iscrizioni
                    </a>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-calendar-plus me-2"></i>
                            Nuovo Evento
                        </h4>
                    </div>
                    <div class="card-body">
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

                        <form action="salva_evento.php" method="POST" novalidate>
                            <!-- Nome Evento -->
                            <div class="mb-3">
                                <label for="nome" class="form-label">
                                    <i class="bi bi-card-text me-1"></i>
                                    Nome Evento *
                                </label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    id="nome" 
                                    name="nome" 
                                    value="<?= htmlspecialchars($nome) ?>"
                                    required
                                    maxlength="255"
                                    placeholder="Inserisci il nome dell'evento"
                                >
                                <div class="invalid-feedback">
                                    Il nome dell'evento è obbligatorio (max 255 caratteri).
                                </div>
                            </div>

                            <!-- Data e Ora Evento -->
                            <div class="mb-3">
                                <label for="dataEvento" class="form-label">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    Data e Ora Evento *
                                </label>
                                <input 
                                    type="datetime-local" 
                                    class="form-control" 
                                    id="dataEvento" 
                                    name="dataEvento" 
                                    value="<?= htmlspecialchars($dataEvento) ?>"
                                    required
                                    min="<?= date('Y-m-d\TH:i') ?>"
                                >
                                <div class="invalid-feedback">
                                    La data e ora dell'evento sono obbligatorie.
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Seleziona una data futura
                                </div>
                            </div>

                            <!-- Categoria -->
                            <div class="mb-3">
                                <label for="categoria" class="form-label">
                                    <i class="bi bi-tags me-1"></i>
                                    Categoria *
                                </label>
                                <select class="form-select" id="categoria" name="categoria" required>
                                    <option value="">Seleziona una categoria</option>
                                    <option value="Conferenza" <?= $categoria === 'Conferenza' ? 'selected' : '' ?>>Conferenza</option>
                                    <option value="Workshop" <?= $categoria === 'Workshop' ? 'selected' : '' ?>>Workshop</option>
                                    <option value="Seminario" <?= $categoria === 'Seminario' ? 'selected' : '' ?>>Seminario</option>
                                    <option value="Meeting" <?= $categoria === 'Meeting' ? 'selected' : '' ?>>Meeting</option>
                                    <option value="Formazione" <?= $categoria === 'Formazione' ? 'selected' : '' ?>>Formazione</option>
                                    <option value="Evento Sociale" <?= $categoria === 'Evento Sociale' ? 'selected' : '' ?>>Evento Sociale</option>
                                    <option value="Presentazione" <?= $categoria === 'Presentazione' ? 'selected' : '' ?>>Presentazione</option>
                                    <option value="Altro" <?= $categoria === 'Altro' ? 'selected' : '' ?>>Altro</option>
                                </select>
                                <div class="invalid-feedback">
                                    Seleziona una categoria per l'evento.
                                </div>
                            </div>

                            <!-- Tipo Evento -->
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="bi bi-geo-alt me-1"></i>
                                    Tipo Evento *
                                </label>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-check">
                                            <input 
                                                class="form-check-input" 
                                                type="radio" 
                                                name="tipo" 
                                                id="presenza" 
                                                value="presenza"
                                                <?= $tipo === 'presenza' ? 'checked' : '' ?>
                                                required
                                            >
                                            <label class="form-check-label" for="presenza">
                                                <i class="bi bi-people me-1"></i>
                                                In Presenza
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-check">
                                            <input 
                                                class="form-check-input" 
                                                type="radio" 
                                                name="tipo" 
                                                id="virtuale" 
                                                value="virtuale"
                                                <?= $tipo === 'virtuale' ? 'checked' : '' ?>
                                                required
                                            >
                                            <label class="form-check-label" for="virtuale">
                                                <i class="bi bi-camera-video me-1"></i>
                                                Virtuale
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="invalid-feedback d-block" id="tipo-error" style="display: none !important;">
                                    Seleziona il tipo di evento.
                                </div>
                            </div>

                            <!-- Pulsanti -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="reset" class="btn btn-outline-secondary me-md-2">
                                    <i class="bi bi-arrow-clockwise me-1"></i>
                                    Reset
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>
                                    Salva Evento
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validazione personalizzata Bootstrap
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByTagName('form');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        // Validazione custom per i radio button
                        var tipoRadios = document.getElementsByName('tipo');
                        var tipoSelected = false;
                        for (var i = 0; i < tipoRadios.length; i++) {
                            if (tipoRadios[i].checked) {
                                tipoSelected = true;
                                break;
                            }
                        }
                        
                        if (!tipoSelected) {
                            document.getElementById('tipo-error').style.display = 'block';
                            event.preventDefault();
                            event.stopPropagation();
                        } else {
                            document.getElementById('tipo-error').style.display = 'none';
                        }
                        
                        if (form.checkValidity() === false || !tipoSelected) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Aggiorna il valore min del datetime-local ogni volta che la pagina si carica
        document.addEventListener('DOMContentLoaded', function() {
            var now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('dataEvento').min = now.toISOString().slice(0, 16);
        });
    </script>
</body>
</html>