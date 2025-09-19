<?php
/**
 * Gestione connessione database con configurazione da file .env
 */

require_once __DIR__ . '/EnvLoader.php';

class Database {
    private static $instance = null;
    private $pdo = null;
    
    /**
     * Costruttore privato per implementare il pattern Singleton
     */
    private function __construct() {
        try {
            // Carica le variabili d'ambiente
            EnvLoader::load(__DIR__ . '/../');

            // Recupera i parametri di connessione dal .env
            $host = EnvLoader::get('DB_HOST', '127.0.0.1');
            $port = EnvLoader::get('DB_PORT', 3306);
            $dbname = EnvLoader::get('DB_NAME');
            $username = EnvLoader::get('DB_USER', 'root');
            $password = EnvLoader::get('DB_PASSWORD', '');
            $charset = EnvLoader::get('DB_CHARSET', 'utf8mb4');
            
            // Verifica che i parametri obbligatori siano presenti
            if (empty($dbname)) {
                throw new Exception("DB_NAME è obbligatorio nel file .env");
            }
            
            // Costruzione DSN
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            //var_dump($dsn); die();
            
            // Opzioni PDO
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 30,
            ];
            
            // Creazione connessione PDO
            $this->pdo = new PDO($dsn, $username, $password, $options);
            
            // Log connessione riuscita (solo in development)
            if (EnvLoader::get('APP_DEBUG', false)) {
                error_log("Connessione database riuscita: {$host}:{$port}/{$dbname}");
            }
            
        } catch (PDOException $e) {
            error_log("Errore PDO: " . $e->getMessage());
            echo $e->getMessage(); // Per debug, rimuovere in produzione
            die();
            throw new Exception("Impossibile connettersi al database. Verifica la configurazione nel file .env");
        } catch (Exception $e) {
            error_log("Errore configurazione DB: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Impedisce la clonazione dell'oggetto
     */
    private function __clone() {}
    
    /**
     * Impedisce l'unserializzazione dell'oggetto
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Ottiene l'istanza singleton della classe Database
     * 
     * @return Database Istanza singleton
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Ottiene la connessione PDO
     * 
     * @return PDO Oggetto PDO per le query
     */
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * Testa la connessione al database
     * 
     * @return bool True se la connessione è attiva
     */
    public function testConnection() {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            error_log("Test connessione fallito: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottiene informazioni sulla connessione
     * 
     * @return array Informazioni sulla connessione
     */
    public function getConnectionInfo() {
        try {
            $info = [
                'server_info' => $this->pdo->getAttribute(PDO::ATTR_SERVER_INFO),
                'server_version' => $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
                'connection_status' => $this->pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS),
                'driver_name' => $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
            ];
            return $info;
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Chiude esplicitamente la connessione
     */
    public function closeConnection() {
        $this->pdo = null;
        self::$instance = null;
    }
}

/**
 * Funzione helper per ottenere rapidamente la connessione PDO
 * 
 * @return PDO Connessione PDO
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Funzione helper per testare la connessione
 * 
 * @return bool True se connesso
 */
function testDBConnection() {
    try {
        return Database::getInstance()->testConnection();
    } catch (Exception $e) {
        return false;
    }
}