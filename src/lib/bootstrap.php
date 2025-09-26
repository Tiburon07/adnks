<?php
// src/lib/bootstrap.php
require_once __DIR__ . '/../classes/EnvLoader.php';

// Carica .env dalla root di src (dove vive il tuo .env)
EnvLoader::load(__DIR__ . '/..');

function env(string $key, ?string $default = null): ?string
{
	$v = EnvLoader::get($key, $default);
	return $v === '' ? $default : $v;
}
