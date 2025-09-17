<?php

session_start();

require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/MailchimpService.php';

/**
 * event_checkin_list.php
 *
 * Endpoint READ-ONLY per ottenere la lista iscritti di un evento
 * (pensato per hostess/operativi in sede).
 *
 * Metodo: GET
 * Query params:
 *  - eventId (int, obbligatorio)
 *  - status (opzionale, CSV o singolo)       -> pending|confirmed|cancelled|bounced
 *  - checkin (opzionale)                     -> NA|presenza|virtuale
 *  - search (opzionale, testo libero)        -> nome|cognome|azienda|email
 *  - sort (opzionale)                        -> cognome_asc (default)|cognome_desc|dataiscrizione_asc|dataiscrizione_desc
 *  - page (opzionale, int>=1)                -> default 1
 *  - per_page (opzionale, 1–200)             -> default 50
 *  - include_mailchimp (opzionale, bool)     -> default false
 *  - include_email_full (opzionale, bool)    -> default false (se false, email mascherata)
 *
 * Risposte:
 *  - 200: payload con lista, paginazione e aggregati
 *  - 400: eventId mancante/non valido
 *  - 404: evento inesistente
 *  - 422: parametri non validi
 *  - 500: errore interno
 */

// ===== Bootstrap / DB =====
// Sostituisci con il TUO bootstrap che fornisce $pdo (PDO).
// require __DIR__ . '/../inc/bootstrap.php'; // <-- ADATTARE AL PROGETTO

// Fallback minimale SE vuoi testarlo standalone (da rimuovere in integrazione):
// try {
//   $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_NAME') ?: 'adnks');
//   $pdo = new PDO($dsn, getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '', [
//     PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
//     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
//     PDO::ATTR_EMULATE_PREPARES => false,
//   ]);
// } catch (Throwable $e) {
//   http_response_code(500);
//   header('Content-Type: application/json; charset=utf-8');
//   echo json_encode(['success' => false, 'error' => 'DB connection failed']);
//   exit;
// }

// ===== Headers =====
header('Content-Type: application/json; charset=utf-8');
// In prod limita l’origin al dominio WP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// ===== Helpers =====
function boolParam(?string $v, bool $default = false): bool {
  if ($v === null) return $default;
  $v = strtolower(trim($v));
  return in_array($v, ['1','true','yes','on'], true);
}

function maskEmail(string $email): string {
  // maschera parte locale tranne le prime 3
  if (strpos($email, '@') === false) return $email;
  [$local, $domain] = explode('@', $email, 2);
  if (mb_strlen($local) <= 3) return str_repeat('*', mb_strlen($local)) . '@' . $domain;
  return mb_substr($local, 0, 3) . str_repeat('*', max(0, mb_strlen($local)-3)) . '@' . $domain;
}

function respond(int $code, array $payload) {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

// ===== Validazione parametri =====
$eventIdStr = $_GET['eventId'] ?? null;
if ($eventIdStr === null || !ctype_digit($eventIdStr) || (int)$eventIdStr <= 0) {
  respond(400, ['success' => false, 'error' => 'eventId mancante o non valido']);
}
$eventId = (int)$eventIdStr;

$statusParam   = $_GET['status']  ?? null; // csv o singolo
$checkinParam  = $_GET['checkin'] ?? null;
$search        = trim((string)($_GET['search'] ?? ''));
$sort          = $_GET['sort']    ?? 'cognome_asc';
$page          = (int)($_GET['page'] ?? 1);
$perPage       = (int)($_GET['per_page'] ?? 50);
$includeMC     = boolParam($_GET['include_mailchimp'] ?? null, false);
$includeFullEM = boolParam($_GET['include_email_full'] ?? null, false);

if ($page < 1) $page = 1;
if ($perPage < 1) $perPage = 50;
if ($perPage > 200) $perPage = 200;

// status
$allowedStatus = ['pending','confirmed','cancelled','bounced'];
$statusList = null;
if ($statusParam !== null && $statusParam !== '') {
  $parts = array_filter(array_map('trim', explode(',', $statusParam)));
  $parts = array_values(array_unique($parts));
  foreach ($parts as $p) {
    if (!in_array($p, $allowedStatus, true)) {
      respond(422, ['success' => false, 'error' => "Parametro status non valido: {$p}"]);
    }
  }
  $statusList = $parts;
}

// checkin
$allowedCheckin = ['NA','presenza','virtuale'];
if ($checkinParam !== null && $checkinParam !== '') {
  if (!in_array($checkinParam, $allowedCheckin, true)) {
    respond(422, ['success' => false, 'error' => "Parametro checkin non valido"]);
  }
}

// ordinamento
$sortMap = [
  'cognome_asc'            => 'u.cognome ASC, u.nome ASC',
  'cognome_desc'           => 'u.cognome DESC, u.nome DESC',
  'dataiscrizione_asc'     => 'ie.dataiscrizione ASC, u.cognome ASC',
  'dataiscrizione_desc'    => 'ie.dataiscrizione DESC, u.cognome ASC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['cognome_asc'];

// ===== Verifica evento esistente + meta =====
try {
  $stmtEvt = $pdo->prepare("SELECT ID, nome, dataEvento, categoria, tipo FROM Eventi WHERE ID = :id LIMIT 1");
  $stmtEvt->execute([':id' => $eventId]);
  $event = $stmtEvt->fetch(PDO::FETCH_ASSOC);
  if (!$event) {
    respond(404, ['success' => false, 'error' => 'Evento non trovato']);
  }
} catch (Throwable $e) {
  respond(500, ['success' => false, 'error' => 'Errore lettura evento']);
}

// ===== Costruzione filtri =====
$where = ["ie.idEvento = :eventId", "u.deletedAt IS NULL", "u.anonymizedAt IS NULL"];
$params = [':eventId' => $eventId];

if ($statusList) {
  $in = implode(',', array_fill(0, count($statusList), '?'));
  $where[] = "ie.status IN ($in)";
  // li aggiungeremo in coda ai params come posizionali
}
if ($checkinParam) {
  $where[] = "ie.checkin = :checkin";
  $params[':checkin'] = $checkinParam;
}
if ($search !== '') {
  $where[] = "(u.nome LIKE :q OR u.cognome LIKE :q OR u.Azienda LIKE :q OR u.email LIKE :q)";
  $params[':q'] = '%' . $search . '%';
}
$whereSql = implode(' AND ', $where);

// ===== Query conteggi aggregati =====
try {
  $countSql = "
    SELECT
      COUNT(*) AS total,
      SUM(ie.status = 'pending')   AS pending,
      SUM(ie.status = 'confirmed') AS confirmed,
      SUM(ie.status = 'cancelled') AS cancelled,
      SUM(ie.status = 'bounced')   AS bounced,
      SUM(ie.checkin = 'NA')       AS checkin_NA,
      SUM(ie.checkin = 'presenza') AS checkin_presenza,
      SUM(ie.checkin = 'virtuale') AS checkin_virtuale
    FROM Iscrizione_Eventi ie
    INNER JOIN Utenti u ON u.ID = ie.idUtente
    WHERE $whereSql
  ";
  $stmtCnt = $pdo->prepare($countSql);
  $bind = [];
  // bind named
  foreach ($params as $k => $v) $bind[$k] = $v;
  // bind posizionali per status
  if ($statusList) {
    foreach ($statusList as $s) $bind[] = $s;
  }
  $stmtCnt->execute($bind);
  $counts = $stmtCnt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  respond(500, ['success' => false, 'error' => 'Errore conteggi']);
}

// ===== Query dati paginati =====
$offset = ($page - 1) * $perPage;

$selectMailchimp = $includeMC
  ? ", ie.mailchimp_id, ie.mailchimp_email_hash, ie.mailchimp_status, ie.mailchimp_synced_at"
  : "";

$sql = "
  SELECT
    u.ID AS userId,
    u.nome,
    u.cognome,
    u.Azienda AS azienda,
    u.ruolo,
    u.telefono,
    u.email,
    u.note,
    u.status AS user_status,
    ie.ID AS registrationId,
    ie.dataiscrizione,
    ie.status AS reg_status,
    ie.checkin,
    ie.createdAt AS reg_createdAt,
    ie.updatedAt AS reg_updatedAt
    $selectMailchimp
  FROM Iscrizione_Eventi ie
  INNER JOIN Utenti u ON u.ID = ie.idUtente
  WHERE $whereSql
  ORDER BY $orderBy
  LIMIT :limit OFFSET :offset
";

try {
  $stmt = $pdo->prepare($sql);

  // bind named
  foreach ($params as $k => $v) {
    if ($k === ':eventId') {
      $stmt->bindValue($k, (int)$v, PDO::PARAM_INT);
    } else {
      $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
  }
  // bind posizionali per status (in ordine)
  $pos = 1;
  if ($statusList) {
    foreach ($statusList as $s) {
      $stmt->bindValue($pos, $s, PDO::PARAM_STR);
      $pos++;
    }
  }
  $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);

  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  respond(500, ['success' => false, 'error' => 'Errore lettura iscritti']);
}

// ===== Post-processing: maschera email se richiesto =====
$data = [];
foreach ($rows as $r) {
  $email = $r['email'];
  if (!$includeFullEM) {
    $email = maskEmail($email);
  }

  $item = [
    'userId'      => (int)$r['userId'],
    'nome'        => $r['nome'],
    'cognome'     => $r['cognome'],
    'azienda'     => $r['azienda'],
    'ruolo'       => $r['ruolo'],
    'telefono'    => $r['telefono'],
    'email'       => $email,
    'note'        => $r['note'],
    'user_status' => $r['user_status'],
    'registration' => [
      'registrationId' => (int)$r['registrationId'],
      'dataiscrizione' => $r['dataiscrizione'],
      'status'         => $r['reg_status'],
      'checkin'        => $r['checkin'],
      'createdAt'      => $r['reg_createdAt'],
      'updatedAt'      => $r['reg_updatedAt'],
    ],
  ];

  if ($includeMC) {
    $item['mailchimp'] = [
      'id'          => $r['mailchimp_id'] ?? null,
      'email_hash'  => $r['mailchimp_email_hash'] ?? null,
      'status'      => $r['mailchimp_status'] ?? null,
      'synced_at'   => $r['mailchimp_synced_at'] ?? null,
    ];
  }

  $data[] = $item;
}

// ===== Calcolo pagine =====
$total = (int)($counts['total'] ?? 0);
$totalPages = (int)ceil($total / max(1, $perPage));

// ===== Risposta =====
respond(200, [
  'success' => true,
  'event' => [
    'id'         => (int)$event['ID'],
    'nome'       => $event['nome'],
    'dataEvento' => $event['dataEvento'],
    'categoria'  => $event['categoria'],
    'tipo'       => $event['tipo'],
  ],
  'counts' => [
    'total'             => $total,
    'pending'           => (int)($counts['pending'] ?? 0),
    'confirmed'         => (int)($counts['confirmed'] ?? 0),
    'cancelled'         => (int)($counts['cancelled'] ?? 0),
    'bounced'           => (int)($counts['bounced'] ?? 0),
    'checkin_NA'        => (int)($counts['checkin_NA'] ?? 0),
    'checkin_presenza'  => (int)($counts['checkin_presenza'] ?? 0),
    'checkin_virtuale'  => (int)($counts['checkin_virtuale'] ?? 0),
  ],
  'page'       => $page,
  'per_page'   => $perPage,
  'total_pages'=> $totalPages,
  'data'       => $data,
]);
