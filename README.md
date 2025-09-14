# adnks - Gestione Eventi e Iscrizioni

Applicazione PHP per la gestione di eventi e iscrizioni, con interfaccia web responsive e database MariaDB/MySQL.

## Requisiti

- **PHP** (versione 7.4 o superiore) installato in locale
- **Estensione PDO** abilitata in `php.ini` (`extension=pdo_mysql`)
- **MariaDB** o **MySQL** (puoi usare Docker, vedi sotto)
- **Composer** (opzionale, se vuoi gestire dipendenze aggiuntive)

## Installazione

1. **Clona il repository**  
   ```bash
   git clone https://github.com/Tiburon07/adnks.git
   cd adnks
   ```

2. **Configura il database**  
   - Puoi usare il file `mariadb.yml` per avviare un database MariaDB con Docker Compose:
     ```bash
     docker compose -f mariadb.yml up -d
     ```
   - Oppure crea manualmente un database e importa lo schema da `sql/eventi_schema_mariadb.sql`.

3. **Configura le variabili d'ambiente**  
   - Copia il file `.env` e personalizza le credenziali di accesso al database se necessario.

4. **Abilita l'estensione PDO in PHP**  
   - Modifica il file `php.ini` e assicurati che la riga seguente sia decommentata:
     ```
     extension=pdo_mysql
     ```

## Avvio del server

Avvia il server di sviluppo PHP dalla root del progetto:

```bash
php -S localhost:8000
```

L'applicazione sarà accessibile su [http://localhost:8000](http://localhost:8000).

## Funzionalità principali

- Creazione, modifica e visualizzazione eventi
- Iscrizione utenti agli eventi
- Gestione stato iscrizione (conferma, annulla, check-in)
- Statistiche e dettagli iscrizioni
- Interfaccia responsive con Bootstrap

## Struttura del progetto

- `index.php` — Gestione eventi
- `iscrizione.php` — Form iscrizione evento
- `visualizza_iscrizioni.php` — Elenco iscrizioni
- `classes/` — Classi PHP per database e ambiente
- `sql/` — Script SQL per il database
- `.env` — Configurazione ambiente (database, debug, ecc.)

## Note

- Assicurati che la porta 3306 non sia occupata se usi Docker.
- Per ambiente di produzione, imposta `APP_DEBUG=false` nel file `.env`.

---

**Autore:** Tiburon07  
**Licenza:** MIT