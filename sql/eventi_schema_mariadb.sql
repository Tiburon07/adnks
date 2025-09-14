
-- ============================================================================
--  Schema Dati - Iscrizioni Eventi (WordPress + API PHP + Mailchimp)
--  MariaDB DDL
--  Versione: v1.0 (storico conservato, FK ON DELETE RESTRICT)
--  Charset: utf8mb4 / Collation: utf8mb4_unicode_ci
-- ============================================================================

-- Opzionale: crea il database (cambiare nome se necessario)
-- CREATE DATABASE IF NOT EXISTS eventi_app
--   DEFAULT CHARACTER SET utf8mb4
--   DEFAULT COLLATE utf8mb4_unicode_ci;
-- USE eventi_app;

-- Modalità SQL consigliate
SET NAMES utf8mb4;
SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ---------------------------------------------------------------------------
-- DROP tabelle (ordine: prima figli poi genitori)
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS Iscrizione_Eventi_Log;
DROP TABLE IF EXISTS Iscrizione_Eventi;
DROP TABLE IF EXISTS Aziende;
DROP TABLE IF EXISTS Utenti;
DROP TABLE IF EXISTS Eventi;

-- ---------------------------------------------------------------------------
-- TABELLA: Eventi
-- Catalogo eventi. Conservazione storica tramite soft-delete/archiviazione.
-- ---------------------------------------------------------------------------
CREATE TABLE Eventi (
  ID           INT AUTO_INCREMENT PRIMARY KEY,
  nome         VARCHAR(255)      NOT NULL,
  dataEvento   DATETIME          NOT NULL,
  categoria    VARCHAR(100)      NOT NULL,
  tipo         ENUM('presenza','virtuale') NOT NULL,
  archivedAt   DATETIME          NULL,   -- archiviazione operativa
  deletedAt    DATETIME          NULL,   -- soft-delete (no rimozione fisica)
  createdAt    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_Eventi_dataEvento (dataEvento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- TABELLA: Utenti
-- Anagrafica iscritti. Email univoca. Previsione di anonimizzazione/soft-delete.
-- ---------------------------------------------------------------------------
CREATE TABLE Utenti (
  ID            INT AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(320)     NOT NULL,
  nome          VARCHAR(100)     NOT NULL,
  cognome       VARCHAR(100)     NOT NULL,
  ruolo         VARCHAR(100)     NULL,
  telefono      VARCHAR(30)      NULL,
  note          TEXT             NULL,
  status        ENUM('active','unactive','disabled') NOT NULL DEFAULT 'disabled',
  Azienda       VARCHAR(255)     NOT NULL,
  anonymizedAt  DATETIME         NULL,   -- GDPR: anonimizzazione dati personali
  deletedAt     DATETIME         NULL,   -- soft-delete (no rimozione fisica)
  createdAt     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_Utenti_email (email),
  KEY idx_Utenti_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- TABELLA: Aziende (opzionale)
-- Collegamento 1→N dall'utente alle sue aziende (modello MVP semplice).
-- ---------------------------------------------------------------------------
CREATE TABLE Aziende (
  ID         INT AUTO_INCREMENT PRIMARY KEY,
  idUtente   INT              NOT NULL,
  contatti   TEXT             NULL,
  referente  VARCHAR(255)     NULL,
  createdAt  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_Aziende_idUtente (idUtente),
  CONSTRAINT fk_Aziende_Utenti
    FOREIGN KEY (idUtente) REFERENCES Utenti(ID)
    ON UPDATE RESTRICT
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- TABELLA: Iscrizione_Eventi
-- Ponte Utente↔Evento. Le iscrizioni non vengono cancellate (storico).
-- ---------------------------------------------------------------------------
CREATE TABLE Iscrizione_Eventi (
  ID              INT AUTO_INCREMENT PRIMARY KEY,
  idUtente        INT            NOT NULL,
  idEvento        INT            NOT NULL,
  dataIscrizione  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  checkin         ENUM('virtuale','presenza','NA') NOT NULL DEFAULT 'NA',
  status          ENUM('pending','confirmed','cancelled','bounced') NOT NULL DEFAULT 'pending',
  cancelledAt     DATETIME       NULL,
  createdAt       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_Iscrizione (idUtente, idEvento),
  KEY idx_Iscrizioni_idUtente (idUtente),
  KEY idx_Iscrizioni_idEvento (idEvento),
  KEY idx_Iscrizioni_status (status),
  CONSTRAINT fk_Iscrizioni_Utenti
    FOREIGN KEY (idUtente) REFERENCES Utenti(ID)
    ON UPDATE RESTRICT
    ON DELETE RESTRICT,
  CONSTRAINT fk_Iscrizioni_Eventi
    FOREIGN KEY (idEvento) REFERENCES Eventi(ID)
    ON UPDATE RESTRICT
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- TABELLA: Iscrizione_Eventi_Log (opzionale ma raccomandata per audit)
-- Traccia variazioni di stato/sorgente con approccio append-only.
-- ---------------------------------------------------------------------------
CREATE TABLE Iscrizione_Eventi_Log (
  ID            INT AUTO_INCREMENT PRIMARY KEY,
  idIscrizione  INT            NOT NULL,
  oldStatus     ENUM('pending','confirmed','cancelled','bounced') NULL,
  newStatus     ENUM('pending','confirmed','cancelled','bounced') NOT NULL,
  source        VARCHAR(50)    NULL,   -- es. origine operazione: 'mailchimp', 'hostess', 'api', ...
  note          TEXT           NULL,
  changedAt     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_Log_idIscrizione (idIscrizione),
  KEY idx_Log_changedAt (changedAt),
  CONSTRAINT fk_Log_Iscrizioni
    FOREIGN KEY (idIscrizione) REFERENCES Iscrizione_Eventi(ID)
    ON UPDATE RESTRICT
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
--  NOTE
--  - Le FK sono ON DELETE RESTRICT per preservare lo storico delle iscrizioni.
--  - Le colonne createdAt/updatedAt usano CURRENT_TIMESTAMP (MariaDB >= 10.2).
--    In caso di versioni più vecchie, valutare TRIGGER per aggiornare updatedAt.
--  - Valori ENUM allineati ai flussi applicativi (presenza/virtuale; pending/confirmed/etc.).
--  - L'indice UNIQUE su (idUtente, idEvento) impedisce iscrizioni duplicate
--    dello stesso utente allo stesso evento.
-- ============================================================================

