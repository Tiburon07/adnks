<?php

/**
 * Front Controller - index.php
 * Entry point unico per l'intera API.
 *
 * Aggiunti "hints" nel payload della rotta GET "/" per aiutare il FE.
 */

declare(strict_types=1);

// --------- CORS ---------
$allowedOrigins = ["*"]; // in produzione, sostituisci con la whitelist
$origin = $_SERVER["HTTP_ORIGIN"] ?? "*";
if (!in_array($origin, $allowedOrigins, true) && $allowedOrigins !== ["*"]) {
	$origin = $allowedOrigins[0];
}
header("Access-Control-Allow-Origin: " . $origin);
header("Vary: Origin");
header("Access-Control-Allow-Credentials: true");
header(
	"Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-HTTP-Method-Override"
);
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
	http_response_code(204);
	exit();
}

// --------- Helpers ---------
function send_json(int $status, $payload): void
{
	http_response_code($status);
	echo json_encode($payload, JSON_UNESCAPED_UNICODE);
	exit();
}

function method(): string
{
	$m = $_SERVER["REQUEST_METHOD"] ?? "GET";
	if (
		isset($_SERVER["HTTP_X_HTTP_METHOD_OVERRIDE"]) &&
		in_array(
			$_SERVER["HTTP_X_HTTP_METHOD_OVERRIDE"],
			["PUT", "PATCH", "DELETE"],
			true
		)
	) {
		$m = $_SERVER["HTTP_X_HTTP_METHOD_OVERRIDE"];
	}
	return strtoupper($m);
}

function path(): string
{
	$uri = $_SERVER["REQUEST_URI"] ?? "/";
	$uri = strtok($uri, "?") ?: "/";
	$uri = preg_replace("#//+#", "/", $uri);
	if ($uri !== "/" && substr($uri, -1) === "/") {
		$uri = rtrim($uri, "/");
	}
	return $uri;
}

// Parse JSON body (retrocompatibilità: riversa su $_POST)
$rawBody = file_get_contents("php://input");
if ($rawBody !== "" && $rawBody !== false) {
	$contentType =
		$_SERVER["CONTENT_TYPE"] ?? ($_SERVER["HTTP_CONTENT_TYPE"] ?? "");
	if (stripos($contentType, "application/json") !== false) {
		$json = json_decode($rawBody, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			send_json(400, [
				"success" => false,
				"error" => "JSON non valido: " . json_last_error_msg(),
			]);
		}
		if (is_array($json)) {
			foreach ($json as $k => $v) {
				$_POST[$k] = $v;
			}
		}
	}
}

// --------- Routing ---------
$routes = [
	[
		"GET",
		'#^/$#',
		function () {
			send_json(200, [
				"name" => "ADNKronos Event API",
				"version" => "1.0",
				"time" => date("c"),
				"endpoints" => [
					["POST", "/event-registration"],
					["GET", "/events/{eventId}/checkins"],
					["POST", "/event-checkin-update"],
					["PUT", "/event-checkin-update"],
					["PATCH", "/event-checkin-update"],
					["POST", "/events"],
					["POST", "/mailchimp/webhook"],
					["GET", "/mailchimp/webhook"],
				],
				"hints" => [
					[
						"endpoint" => "POST /event-registration",
						"expects" => [
							"headers" => ["Content-Type: application/json"],
							"body" => [
								"evento_id (int, required)",
								"nome (string, required)",
								"cognome (string, required)",
								"email (string, required, valid email)",
								"azienda (string, required)",
								"telefono (string, optional, digits/+/-/() allowed)",
								"ruolo (string, optional)",
								"checkin (string, optional: NA|presenza|virtuale; default NA)",
							],
						],
						"returns" => [
							'200 OK -> {"success":true,"message":"Iscrizione ricevuta...","idIscrizione":987}',
							'400 Bad Request -> {"success":false,"error":"...", "fieldErrors":{"email":"..."}}',
						],
						"curl" =>
						'curl -X POST https://<host>/event-registration -H "Content-Type: application/json" -d \'{"evento_id":123,"nome":"Mario","cognome":"Rossi","email":"mario.rossi@example.com","azienda":"ACME S.p.A.","telefono":"3331234567","ruolo":"Marketing","checkin":"NA"}\'',
					],
					[
						"endpoint" => "GET /events/{eventId}/checkins",
						"expects" => [
							"path" => ["eventId (int, Eventi.ID)"],
						],
						"returns" => [
							'200 OK -> [{"idIscrizione":987,"utente":{"id":456,"nome":"Mario","cognome":"Rossi","email":"..."}, "checkin":"NA","ruolo":"Marketing"}]',
							'404 Not Found -> {"success":false,"error":"Nessuna iscrizione trovata..."}',
						],
						"curl" => "curl https://<host>/events/123/checkins",
					],
					[
						"endpoint" => "POST|PUT|PATCH /event-checkin-update",
						"expects" => [
							"headers" => ["Content-Type: application/json"],
							"body" => [
								"registrationId (int, required, Iscrizione_Eventi.ID)",
								"userId (int, required, Utenti.ID)",
								"checkin (string, required: presenza|virtuale|NA)",
								"ruolo (string, required)",
							],
						],
						"returns" => [
							'200 OK -> {"success":true,"updated":{"registrationId":987,"userId":456,"checkin":"presenza","ruolo":"Responsabile"}}',
							"400 Bad Request / 404 Not Found / 500 Internal Server Error",
						],
						"curl" =>
						'curl -X POST https://<host>/event-checkin-update -H "Content-Type: application/json" -d \'{"registrationId":987,"userId":456,"checkin":"presenza","ruolo":"Responsabile"}\'',
					],
					[
						"endpoint" => "POST /events",
						"expects" => [
							"headers" => ["Content-Type: application/json"],
							"body" => [
								"nome (string, required)",
								"dataEvento (string, required, ISO-8601: YYYY-MM-DDTHH:mm)",
								"categoria (string, required)",
								'tipo (string, required: "in presenza"|"virtuale")',
							],
						],
						"returns" => [
							'201 Created/200 OK -> {"success":true,"idEvento":123}',
							"400 Bad Request",
						],
						"curl" =>
						'curl -X POST https://<host>/events -H "Content-Type: application/json" -d \'{"nome":"ADNK • Talk 2025","dataEvento":"2025-10-15T18:30","categoria":"convegno","tipo":"in presenza"}\'',
					],
					[
						"endpoint" => "GET|POST /mailchimp/webhook",
						"expects" => [
							"GET" => "usato da Mailchimp per verifica/echo",
							"POST" =>
							"payload webhook Mailchimp (conferme, aggiornamenti).",
						],
						"returns" => [
							'200 OK -> {"success":true} oppure eco di diagnostica',
							"400/500 su payload non valido",
						],
						"note" =>
						"Gli eventi sono loggati in Mailchimp_Webhook_Log con timestamp.",
					],
				],
				"schemas" => [
					"EventRegistrationRequest" => [
						"type" => "object",
						"required" => [
							"evento_id",
							"nome",
							"cognome",
							"email",
							"azienda",
						],
						"properties" => [
							"evento_id" => [
								"type" => "integer",
								"description" => "ID evento (Eventi.ID)",
							],
							"nome" => ["type" => "string", "maxLength" => 100],
							"cognome" => [
								"type" => "string",
								"maxLength" => 100,
							],
							"email" => [
								"type" => "string",
								"format" => "email",
								"maxLength" => 255,
							],
							"azienda" => [
								"type" => "string",
								"maxLength" => 255,
							],
							"telefono" => [
								"type" => "string",
								"maxLength" => 30,
								"pattern" => '^[0-9\\s\\+\\-\\(\\)]+$',
							],
							"ruolo" => [
								"type" => "string",
								"maxLength" => 100,
								"nullable" => true,
							],
							"checkin" => [
								"type" => "string",
								"enum" => ["NA", "presenza", "virtuale"],
								"default" => "NA",
							],
						],
						"example" => [
							"evento_id" => 123,
							"nome" => "Mario",
							"cognome" => "Rossi",
							"email" => "mario.rossi@example.com",
							"azienda" => "ACME S.p.A.",
							"telefono" => "3331234567",
							"ruolo" => "Marketing",
							"checkin" => "NA",
						],
					],

					"EventRegistrationSuccessResponse" => [
						"type" => "object",
						"properties" => [
							"success" => ["type" => "boolean", "const" => true],
							"message" => ["type" => "string"],
							"idIscrizione" => ["type" => "integer"],
						],
						"example" => [
							"success" => true,
							"message" =>
							"Iscrizione ricevuta. Controlla l’email per confermare.",
							"idIscrizione" => 987,
						],
					],

					"ValidationErrorResponse" => [
						"type" => "object",
						"properties" => [
							"success" => [
								"type" => "boolean",
								"const" => false,
							],
							"error" => ["type" => "string"],
							"fieldErrors" => [
								"type" => "object",
								"additionalProperties" => ["type" => "string"],
							],
						],
						"example" => [
							"success" => false,
							"error" => "Validazione fallita",
							"fieldErrors" => [
								"email" =>
								"Inserisci un indirizzo email valido.",
							],
						],
					],

					"CheckinsListResponse" => [
						"type" => "array",
						"items" => ['$ref' => "#Schema:CheckinRecord"],
						"example" => [
							[
								"idIscrizione" => 987,
								"checkin" => "NA",
								"ruolo" => "Marketing",
								"utente" => [
									"id" => 456,
									"nome" => "Mario",
									"cognome" => "Rossi",
									"email" => "mario.rossi@example.com",
								],
							],
						],
					],

					"CheckinRecord" => [
						"type" => "object",
						"properties" => [
							"idIscrizione" => ["type" => "integer"],
							"checkin" => [
								"type" => "string",
								"enum" => ["NA", "presenza", "virtuale"],
							],
							"ruolo" => ["type" => "string", "nullable" => true],
							"utente" => ['$ref' => "#Schema:UserSummary"],
						],
					],

					"UserSummary" => [
						"type" => "object",
						"properties" => [
							"id" => ["type" => "integer"],
							"nome" => ["type" => "string"],
							"cognome" => ["type" => "string"],
							"email" => [
								"type" => "string",
								"format" => "email",
							],
						],
					],

					"CheckinUpdateRequest" => [
						"type" => "object",
						"required" => ["userId", "checkin", "ruolo"],
						"properties" => [
							// Preferito
							"registrationId" => [
								"type" => "integer",
								"description" => "Iscrizione_Eventi.ID",
							],
							// Legacy (accettato ma deprecato in favore di registrationId)
							"eventId" => [
								"type" => "integer",
								"deprecated" => true,
								"description" =>
								"Legacy alias di registrationId",
							],
							"userId" => [
								"type" => "integer",
								"description" => "Utenti.ID",
							],
							"checkin" => [
								"type" => "string",
								"enum" => ["presenza", "virtuale", "NA"],
							],
							"ruolo" => ["type" => "string", "maxLength" => 100],
						],
						"example" => [
							"registrationId" => 987,
							"userId" => 456,
							"checkin" => "presenza",
							"ruolo" => "Responsabile",
						],
					],

					"CheckinUpdateSuccessResponse" => [
						"type" => "object",
						"properties" => [
							"success" => ["type" => "boolean", "const" => true],
							"updated" => [
								"type" => "object",
								"properties" => [
									"registrationId" => ["type" => "integer"],
									"userId" => ["type" => "integer"],
									"checkin" => [
										"type" => "string",
										"enum" => [
											"presenza",
											"virtuale",
											"NA",
										],
									],
									"ruolo" => ["type" => "string"],
								],
							],
						],
						"example" => [
							"success" => true,
							"updated" => [
								"registrationId" => 987,
								"userId" => 456,
								"checkin" => "presenza",
								"ruolo" => "Responsabile",
							],
						],
					],

					"CreateEventRequest" => [
						"type" => "object",
						"required" => [
							"nome",
							"dataEvento",
							"categoria",
							"tipo",
						],
						"properties" => [
							"nome" => ["type" => "string", "maxLength" => 255],
							"dataEvento" => [
								"type" => "string",
								"format" => "date-time",
								"pattern" =>
								'^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}$',
							],
							"categoria" => [
								"type" => "string",
								"maxLength" => 100,
							],
							"tipo" => [
								"type" => "string",
								"enum" => ["in presenza", "virtuale"],
							],
						],
						"example" => [
							"nome" => "ADNK • Talk 2025",
							"dataEvento" => "2025-10-15T18:30",
							"categoria" => "convegno",
							"tipo" => "in presenza",
						],
					],

					"CreateEventResponse" => [
						"type" => "object",
						"properties" => [
							"success" => ["type" => "boolean"],
							"idEvento" => ["type" => "integer"],
						],
						"example" => ["success" => true, "idEvento" => 123],
					],

					"MailchimpWebhookEvent" => [
						"type" => "object",
						"description" =>
						"Payload generico webhook Mailchimp (subscribe/update/unsubscribe...)",
						"properties" => [
							"type" => ["type" => "string"],
							"email" => [
								"type" => "string",
								"format" => "email",
							],
							"mailchimp_id" => ["type" => "string"],
							"data" => [
								"type" => "object",
								"description" => "contenuto evento grezzo",
							],
						],
						"example" => [
							"type" => "subscribe",
							"email" => "mario.rossi@example.com",
							"mailchimp_id" => "a1b2c3d4",
							"data" => ["status" => "subscribed"],
						],
					],

					"StandardErrorResponse" => [
						"type" => "object",
						"properties" => [
							"success" => [
								"type" => "boolean",
								"const" => false,
							],
							"error" => ["type" => "string"],
						],
						"example" => [
							"success" => false,
							"error" => "Endpoint non trovato",
						],
					],
				],
			]);
		},
	],
	[
		"POST",
		'#^/event-registration$#',
		function () {
			require __DIR__ . "/event_registration.php";
		},
	],
	[
		"GET",
		'#^/events/(\d+)/checkins$#',
		function ($matches) {
			$_GET["eventId"] = $matches[0][0];
			require __DIR__ . "/event_list_checkin.php";
		},
	],
	[
		"POST",
		'#^/event-checkin-update$#',
		function () {
			require __DIR__ . "/event_checkin_update.php";
		},
	],
	[
		"PUT",
		'#^/event-checkin-update$#',
		function () {
			require __DIR__ . "/event_checkin_update.php";
		},
	],
	[
		"PATCH",
		'#^/event-checkin-update$#',
		function () {
			require __DIR__ . "/event_checkin_update.php";
		},
	],
	[
		"POST",
		'#^/events$#',
		function () {
			require __DIR__ . "/salva_evento.php";
		},
	],
	[
		"GET",
		'#^/mailchimp/webhook$#',
		function () {
			require __DIR__ . "/mailchimp_webhook.php";
		},
	],
	[
		"POST",
		'#^/mailchimp/webhook$#',
		function () {
			require __DIR__ . "/mailchimp_webhook.php";
		},
	],
	['GET',   '#^/mailchimp/tools$#', function () {
		require __DIR__ . '/mailchimp_tools.php';
	}],

];

$reqMethod = method();
$reqPath = path();

foreach ($routes as [$m, $regex, $handler]) {
	if ($reqMethod === $m && preg_match($regex, $reqPath, $matches)) {
		array_shift($matches);
		$handler($matches);
		send_json(200, ["success" => true]);
	}
}

// Fallback legacy
if (preg_match('#^/([a-zA-Z0-9_\-]+)\.php$#', $reqPath, $m)) {
	$file = __DIR__ . "/" . $m[1] . ".php";
	if (is_file($file)) {
		require $file;
		exit();
	}
}

send_json(404, [
	"success" => false,
	"error" => "Endpoint non trovato",
	"method" => $reqMethod,
	"path" => $reqPath,
]);
