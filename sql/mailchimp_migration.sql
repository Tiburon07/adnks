-- ============================================================================
-- MAILCHIMP INTEGRATION MIGRATION
-- Aggiunge campi necessari per l'integrazione con Mailchimp
-- ============================================================================

-- Aggiunge colonna mailchimp_id alla tabella Iscrizione_Eventi
ALTER TABLE Iscrizione_Eventi
ADD COLUMN mailchimp_id VARCHAR(100) NULL AFTER status,
ADD COLUMN mailchimp_email_hash VARCHAR(32) NULL AFTER mailchimp_id,
ADD COLUMN mailchimp_status ENUM('pending','subscribed','unsubscribed','cleaned') NULL DEFAULT 'pending' AFTER mailchimp_email_hash,
ADD COLUMN mailchimp_synced_at DATETIME NULL AFTER mailchimp_status;

-- Aggiunge indici per le nuove colonne
CREATE INDEX idx_Iscrizioni_mailchimp_id ON Iscrizione_Eventi(mailchimp_id);
CREATE INDEX idx_Iscrizioni_mailchimp_status ON Iscrizione_Eventi(mailchimp_status);

-- Aggiorna la tabella di log per includere informazioni Mailchimp
ALTER TABLE Iscrizione_Eventi_Log
ADD COLUMN mailchimp_event VARCHAR(50) NULL AFTER source,
ADD COLUMN mailchimp_data JSON NULL AFTER mailchimp_event;

-- Aggiunge colonna per tracciare la sincronizzazione Mailchimp negli utenti
ALTER TABLE Utenti
ADD COLUMN mailchimp_subscriber_id VARCHAR(100) NULL AFTER status,
ADD COLUMN mailchimp_email_hash VARCHAR(32) NULL AFTER mailchimp_subscriber_id,
ADD COLUMN mailchimp_status ENUM('pending','subscribed','unsubscribed','cleaned','not_synced') NULL DEFAULT 'not_synced' AFTER mailchimp_email_hash,
ADD COLUMN mailchimp_last_sync DATETIME NULL AFTER mailchimp_status;

-- Indici per la tabella utenti
CREATE INDEX idx_Utenti_mailchimp_id ON Utenti(mailchimp_subscriber_id);
CREATE INDEX idx_Utenti_mailchimp_status ON Utenti(mailchimp_status);

-- Aggiorna possibili valori dello status per includere 'confirmed'
ALTER TABLE Iscrizione_Eventi
MODIFY COLUMN status ENUM('pending','confirmed','cancelled','bounced') NOT NULL DEFAULT 'pending';

-- Aggiorna anche la tabella di log
ALTER TABLE Iscrizione_Eventi_Log
MODIFY COLUMN oldStatus ENUM('pending','confirmed','cancelled','bounced') NULL,
MODIFY COLUMN newStatus ENUM('pending','confirmed','cancelled','bounced') NOT NULL;

-- Crea tabella per configurazione Mailchimp (opzionale)
CREATE TABLE IF NOT EXISTS Mailchimp_Config (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    list_id VARCHAR(50) NOT NULL,
    list_name VARCHAR(255) NOT NULL,
    webhook_id VARCHAR(50) NULL,
    webhook_url VARCHAR(500) NULL,
    is_active BOOLEAN NOT NULL DEFAULT true,
    last_sync DATETIME NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_list_id (list_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crea tabella per log eventi webhook Mailchimp
CREATE TABLE IF NOT EXISTS Mailchimp_Webhook_Log (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    webhook_type VARCHAR(50) NOT NULL,
    email VARCHAR(320) NOT NULL,
    mailchimp_id VARCHAR(100) NULL,
    event_data JSON NULL,
    processed BOOLEAN NOT NULL DEFAULT false,
    processed_at DATETIME NULL,
    error_message TEXT NULL,
    retry_count INT NOT NULL DEFAULT 0,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_webhook_email (email),
    KEY idx_webhook_type (webhook_type),
    KEY idx_webhook_processed (processed),
    KEY idx_webhook_received (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DATI DI ESEMPIO / CONFIGURAZIONE INIZIALE
-- ============================================================================

-- Inserisci configurazione Mailchimp di default (da personalizzare)
INSERT INTO Mailchimp_Config (list_id, list_name, is_active)
VALUES ('your_list_id_here', 'Eventi ADNKS', true)
ON DUPLICATE KEY UPDATE
    list_name = VALUES(list_name),
    updatedAt = CURRENT_TIMESTAMP;

-- ============================================================================
-- FUNZIONI HELPER (opzionali - per MySQL/MariaDB con supporto stored procedures)
-- ============================================================================

DELIMITER //

-- Funzione per aggiornare lo status di un'iscrizione e loggarla
CREATE OR REPLACE PROCEDURE UpdateIscrizioneStatus(
    IN p_iscrizione_id INT,
    IN p_new_status ENUM('pending','confirmed','cancelled','bounced'),
    IN p_source VARCHAR(50),
    IN p_mailchimp_status ENUM('pending','subscribed','unsubscribed','cleaned'),
    IN p_note TEXT
)
BEGIN
    DECLARE v_old_status ENUM('pending','confirmed','cancelled','bounced');

    -- Ottieni il vecchio status
    SELECT status INTO v_old_status
    FROM Iscrizione_Eventi
    WHERE ID = p_iscrizione_id;

    -- Aggiorna l'iscrizione
    UPDATE Iscrizione_Eventi
    SET
        status = p_new_status,
        mailchimp_status = COALESCE(p_mailchimp_status, mailchimp_status),
        mailchimp_synced_at = CURRENT_TIMESTAMP,
        updatedAt = CURRENT_TIMESTAMP
    WHERE ID = p_iscrizione_id;

    -- Inserisci nel log
    INSERT INTO Iscrizione_Eventi_Log (
        idIscrizione, oldStatus, newStatus, source, note, changedAt
    ) VALUES (
        p_iscrizione_id, v_old_status, p_new_status, p_source, p_note, CURRENT_TIMESTAMP
    );
END //

-- Funzione per ottenere statistiche Mailchimp
CREATE OR REPLACE FUNCTION GetMailchimpStats()
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE result JSON DEFAULT '{}';

    SELECT JSON_OBJECT(
        'total_subscriptions', COUNT(*),
        'pending', SUM(CASE WHEN mailchimp_status = 'pending' THEN 1 ELSE 0 END),
        'subscribed', SUM(CASE WHEN mailchimp_status = 'subscribed' THEN 1 ELSE 0 END),
        'unsubscribed', SUM(CASE WHEN mailchimp_status = 'unsubscribed' THEN 1 ELSE 0 END),
        'cleaned', SUM(CASE WHEN mailchimp_status = 'cleaned' THEN 1 ELSE 0 END),
        'last_updated', MAX(mailchimp_synced_at)
    ) INTO result
    FROM Iscrizione_Eventi
    WHERE mailchimp_id IS NOT NULL;

    RETURN result;
END //

DELIMITER ;

-- ============================================================================
-- NOTE PER L'IMPLEMENTAZIONE
-- ============================================================================

/*
ISTRUZIONI POST-MIGRAZIONE:

1. Aggiorna il file .env con le credenziali Mailchimp reali:
   - MAILCHIMP_API_KEY=your_actual_api_key
   - MAILCHIMP_LIST_ID=your_actual_list_id
   - MAILCHIMP_WEBHOOK_SECRET=your_webhook_secret
   - MAILCHIMP_SERVER_PREFIX=us1 (o il tuo datacenter)

2. Configura il webhook in Mailchimp puntando a:
   https://yourdomain.com/mailchimp_webhook.php

3. Testa l'integrazione con un'iscrizione di prova

4. Monitora i log in Mailchimp_Webhook_Log per eventuali errori

CAMPI AGGIUNTI:
- mailchimp_id: ID univoco del subscriber in Mailchimp
- mailchimp_email_hash: Hash MD5 dell'email per API calls
- mailchimp_status: Status sincronizzato da Mailchimp
- mailchimp_synced_at: Timestamp ultima sincronizzazione

WORKFLOW:
1. Iscrizione → status='pending', mailchimp_status='pending'
2. Mailchimp invia email di conferma
3. Utente conferma → webhook ricevuto
4. Sistema aggiorna → status='confirmed', mailchimp_status='subscribed'
*/