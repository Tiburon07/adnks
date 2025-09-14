<?php
/**
 * Caricatore per file .env
 * Legge le variabili di ambiente dal file .env e le rende disponibili
 */

class EnvLoader {
    /**
     * Carica il file .env dalla directory specificata
     * 
     * @param string $path Percorso della directory contenente il file .env
     * @return bool True se il caricamento è riuscito, False altrimenti
     */
    public static function load($path = __DIR__ . '/../') {

        error_log("Caricamento file .env da: " . $path);
        $envFile = rtrim($path, '/') . '/.env';
        
        if (!file_exists($envFile)) {
            throw new Exception("File .env non trovato in: " . $envFile);
        }
        
        if (!is_readable($envFile)) {
            throw new Exception("File .env non leggibile: " . $envFile);
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignora i commenti
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Cerca il pattern KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                
                $name = trim($name);
                $value = trim($value);
                
                // Rimuovi le virgolette se presenti
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                // Imposta la variabile d'ambiente se non è già presente
                if (!array_key_exists($name, $_ENV)) {
                    $_ENV[$name] = $value;
                    putenv("$name=$value");
                }
            }
        }
        
        return true;
    }
    
    /**
     * Ottiene una variabile d'ambiente con valore di default opzionale
     * 
     * @param string $key Nome della variabile
     * @param mixed $default Valore di default se la variabile non esiste
     * @return mixed Valore della variabile o default
     */
    public static function get($key, $default = null) {
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Conversione automatica per valori booleani
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
        }
        
        // Se è un numero, convertilo
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }
        
        return $value;
    }
    
    /**
     * Verifica se una variabile d'ambiente esiste
     * 
     * @param string $key Nome della variabile
     * @return bool True se esiste, False altrimenti
     */
    public static function has($key) {
        return array_key_exists($key, $_ENV) || getenv($key) !== false;
    }
    
    /**
     * Ottiene tutte le variabili d'ambiente caricate
     * 
     * @return array Array associativo delle variabili
     */
    public static function all() {
        return $_ENV;
    }
}