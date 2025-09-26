<?php
// src/lib/auth.php
require_once __DIR__ . '/bootstrap.php';

/** Estrae Bearer token dall'header Authorization (varie configurazioni Apache/Nginx) */
function getBearerToken(): ?string
{
	$hdr = $_SERVER['HTTP_AUTHORIZATION']
		?? $_SERVER['Authorization']
		?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
		?? null;

	if (!$hdr && function_exists('getallheaders')) {
		$h = array_change_key_case(getallheaders(), CASE_LOWER);
		$hdr = $h['authorization'] ?? null;
	}
	if (!$hdr) return null;

	if (!preg_match('/^\s*Bearer\s+(.+)\s*$/i', $hdr, $m)) return null;
	return trim($m[1]);
}

/** Risposta d’errore standard RFC 6750 */
function bearerError(int $code, string $err, string $desc, string $realm = 'api'): void
{
	header('Content-Type: application/json');
	header(sprintf('WWW-Authenticate: Bearer realm="%s", error="%s", error_description="%s"', $realm, $err, $desc));
	http_response_code($code);
	echo json_encode(['error' => $err, 'error_description' => $desc], JSON_UNESCAPED_SLASHES);
	exit;
}

/** Recupera l’hash del token noto (SHA-256 bin → Base64 nel .env) */
function knownTokenHashBin(): string
{
	$b64 = env('API_TOKEN_SHA256_B64', '');
	if ($b64 === '') {
		bearerError(500, 'server_error', 'Token hash not configured');
	}
	$bin = base64_decode($b64, true);
	if ($bin === false || strlen($bin) !== 32) {
		bearerError(500, 'server_error', 'Token hash misconfigured');
	}
	return $bin;
}

/** Verifica che il Bearer sia valido (token opaco) */
function requireBearerSingleToken(): void
{
	$token = getBearerToken();
	if (!$token) {
		bearerError(401, 'invalid_request', 'Missing Authorization: Bearer');
	}
	$known = knownTokenHashBin();
	$hash  = hash('sha256', $token, true);
	if (!hash_equals($known, $hash)) {
		bearerError(401, 'invalid_token', 'Unknown or invalid token');
	}
}

/** (Opzionale) CORS, se servisse da browser */
function handleCorsIfNeeded(array $allowedOrigins = []): void
{
	$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
	if ($origin && ($allowedOrigins === ['*'] || in_array($origin, $allowedOrigins, true))) {
		header("Access-Control-Allow-Origin: $origin");
		header('Vary: Origin');
		header('Access-Control-Allow-Headers: Authorization, Content-Type');
		header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
		header('Access-Control-Max-Age: 600');
	}
	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
		http_response_code(204);
		exit;
	}
}
