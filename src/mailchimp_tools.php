<?php

/**
 * mailchimp_tools.php
 * Endpoint di utilità per interrogare MailchimpService passando il metodo via querystring.
 * Esempi:
 *  - GET /mailchimp/tools?call=ping
 *  - GET /mailchimp/tools?call=getSubscriber&email=mario.rossi@example.com
 *  - GET /mailchimp/tools?call=getAllMembers&count=100&offset=0&status=pending
 *
 * Sicurezza: per abilitare in ambienti non-prod, aggiungi nel .env:
 *   MAILCHIMP_TOOLS_ENABLED=true
 */

declare(strict_types=1);

require_once __DIR__ . '/classes/EnvLoader.php';
require_once __DIR__ . '/classes/MailchimpService.php';
require_once __DIR__ . '/classes/Database.php'; // non usato direttamente, ma lascia la dipendenza per coerenza

// Header JSON (index.php imposta già CORS/Content-Type, ma non fa male essere idempotenti)
header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): void
{
	http_response_code($status);
	echo json_encode($payload, JSON_UNESCAPED_UNICODE);
	exit;
}

try {
	// Abilitazione esplicita da .env (default: disabilitato in produzione)
	EnvLoader::load(__DIR__ . '/');
	$enabled = EnvLoader::get('MAILCHIMP_TOOLS_ENABLED', 'false');
	if (!in_array(strtolower((string)$enabled), ['1', 'true', 'yes'], true)) {
		respond(403, ['success' => false, 'error' => 'Endpoint disabilitato. Impostare MAILCHIMP_TOOLS_ENABLED=true nel .env']);
	}

	if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
		respond(405, ['success' => false, 'error' => 'Metodo non ammesso. Usa GET.']);
	}

	$mailchimp = new MailchimpService();

	// Whitelist dei metodi invocabili
	$allowedCalls = [
		'getAllMembers',
		'getSubscriber',
		'getMemberInfo',
		'checkEmailStatus',
		'getMemberActivity',
		'getListSettings',
		'getMergeFields',
		'getAllLists',
		'ping',
		'debugConfig',
		'getAllLists'
	];

	$call = $_GET['call'] ?? 'getAllMembers';
	if (!in_array($call, $allowedCalls, true)) {
		respond(400, ['success' => false, 'error' => "Funzione non riconosciuta: {$call}", 'allowed' => $allowedCalls]);
	}

	// Parametri comuni
	$params = [];
	$result = null;

	switch ($call) {
		case 'debugConfig': {
				$result = $mailchimp->debugConfig();
				break;
			}
		case 'getAllMembers': {
				$count  = isset($_GET['count'])  ? (int)$_GET['count']  : 1000;
				$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
				$status = $_GET['status'] ?? null; // subscribed|unsubscribed|cleaned|pending|transactional

				// clamp e validazioni soft
				$count  = max(1, min($count, 2000));
				$offset = max(0, $offset);
				if ($status !== null) {
					$allowedStatus = ['subscribed', 'unsubscribed', 'cleaned', 'pending', 'transactional'];
					if (!in_array($status, $allowedStatus, true)) {
						respond(400, ['success' => false, 'error' => 'status non valido', 'allowed' => $allowedStatus]);
					}
				}

				$params = compact('count', 'offset', 'status');
				$result = $mailchimp->getAllMembers($count, $offset, $status);
				break;
			}

		case 'getAllLists': {
				$count  = isset($_GET['count'])  ? (int)$_GET['count']  : 10;
				$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
				$count  = max(1, min($count, 100));
				$offset = max(0, $offset);
				$params = compact('count', 'offset');
				$result = $mailchimp->getAllLists($count, $offset);
				break;
			}

		case 'getSubscriber':
		case 'getMemberInfo':
		case 'checkEmailStatus': {
				$email = trim((string)($_GET['email'] ?? ''));
				if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
					respond(400, ['success' => false, 'error' => 'Email richiesta/valida per ' . $call]);
				}
				$params = compact('email');
				$result = $mailchimp->$call($email);
				break;
			}

		case 'getMemberActivity': {
				$email = trim((string)($_GET['email'] ?? ''));
				if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
					respond(400, ['success' => false, 'error' => 'Email richiesta/valida per getMemberActivity']);
				}
				$count = isset($_GET['count']) ? (int)$_GET['count'] : 10;
				$count = max(1, min($count, 100));

				$params = compact('email', 'count');
				$result = $mailchimp->getMemberActivity($email, $count);
				break;
			}

		case 'getListSettings': {
				try {
					$result = $mailchimp->getListSettings();
				} catch (\Throwable $e) {
					$code = (int)$e->getCode();
					if ($code === 404) {
						respond(404, [
							'success' => false,
							'error'   => 'list_not_found',
							'message' => 'La LIST_ID nel .env non esiste o non appartiene a questo data center/account.'
						]);
					}
					throw $e;
				}
				break;
			}

		case 'getMergeFields': {
				$result = $mailchimp->getMergeFields();
				break;
			}

		case 'ping': {
				$result = $mailchimp->ping();
				break;
			}
	}

	respond(200, [
		'success' => true,
		'call'    => $call,
		'params'  => $params,
		'result'  => $result
	]);
} catch (Throwable $e) {
	$code = (int)$e->getCode();
	$msg  = $e->getMessage();

	if ($code === 404 || strpos($msg, '(404)') !== false) {
		respond(404, [
			'success' => false,
			'error'   => 'not_found',
			'message' => $msg
		]);
	}

	respond(500, [
		'success' => false,
		'error'   => 'mailchimp_api_error',
		'message' => $msg,
		'code'    => $code ?: 500
	]);
}
