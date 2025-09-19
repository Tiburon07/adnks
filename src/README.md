# ADNKronos – Eventi API
Front‑controller PHP per gestione iscrizioni eventi + integrazione Mailchimp (double opt‑in).

> **Entry point**: `index.php` · **Runtime**: Apache + PHP‑FPM · **DB**: MariaDB (PDO)
>  
> Questa API usa `index.php` come *single entry point* (front controller) per tutte le rotte. Gli endpoint storici (`event_registration.php`, `event_list_checkin.php`, ecc.) restano operativi e vengono inclusi dal router per massima retro‑compatibilità.

---

## 1) Panoramica
- **Obiettivo**: raccogliere iscrizioni ad eventi, salvarle in MariaDB, attivare **Mailchimp double opt‑in**, gestire check‑in e ruoli in presenza.
- **Tecnologie**: PHP 8+, PDO, Apache, MariaDB, Mailchimp Marketing API.
- **Pattern**: Front Controller con **routing** a path REST‑like.
- **Formato dati**: JSON (UTF‑8). Date/ore in ISO‑8601 (`YYYY-MM-DDTHH:mm`). Timezone predefinita: **Europe/Rome**.

---

## 2) Architettura & routing
Tutte le richieste passano da `index.php`. L’**.htaccess** fa rewrite di ogni URL verso `index.php` (escluse risorse fisiche).

### Rotte disponibili
| Metodo | Path | Descrizione | Handler interno |
|---|---|---|---|
| `GET` | `/` | Healthcheck/info API | inline |
| `POST` | `/event-registration` | Iscrizione ad un evento (avvia double opt‑in) | `event_registration.php` |
| `GET` | `/events/{eventId}/checkins` | Elenco iscrizioni + stato check‑in per evento **(Eventi.ID)** | `event_list_checkin.php` |
| `POST` `PUT` `PATCH` | `/event-checkin-update` | Aggiorna **ruolo** (Utenti) e **checkin** (Iscrizione_Eventi) | `event_checkin_update.php` |
| `POST` | `/events` | Crea nuovo evento (backend) | `salva_evento.php` |
| `GET` `POST` | `/mailchimp/webhook` | Webhook di conferma/aggiornamento da Mailchimp | `mailchimp_webhook.php` |

> **Compatibilità legacy**: chiamate dirette a `/*.php` vengono ancora accettate (fallback del router).

---

## 3) Setup & configurazione

### Requisiti
- Apache con `AllowOverride All` abilitato sulla cartella dell’API
- PHP 8.1+ con estensioni `pdo_mysql`, `json`
- MariaDB 10.5+
- Accesso alle API Mailchimp

### .htaccess
È incluso e instrada tutto verso `index.php`:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^ index.php [QSA,L]
```

### Variabili ambiente (`.env`)
File `appeventi/.env` (non committare in VCS; forniamo valori di esempio):
```ini
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=appeventi
DB_USER=appeventi_user
DB_PASS=********
DB_CHARSET=utf8mb4

# Mailchimp
MAILCHIMP_API_KEY=xxxxxxxxxxxxxxxxxxxxxxxx-usX
MAILCHIMP_LIST_ID=abcdef123456
MAILCHIMP_SERVER_PREFIX=usX   # es. 'us21'

# Generali
APP_ENV=production
APP_TIMEZONE=Europe/Rome
```

> Il loader (`classes/EnvLoader.php`) popola `$_ENV` all’avvio; `classes/Database.php` crea un singleton PDO usando **prepared statements**.

---

## 4) Modello dati (estratto rilevante)
- **Eventi** `(ID, nome, dataEvento, categoria, tipo, createdAt, updatedAt)`
- **Utenti** `(ID, email, nome, cognome, ruolo, telefono, note, status, Azienda, createdAt, updatedAt)`
- **Iscrizione_Eventi** `(ID, idUtente, idEvento, dataIscrizione, checkin('virtuale','presenza','NA'), status, createdAt, updatedAt)`
- **Iscrizione_Eventi_Log**: traccia cambi stato (es. invio a Mailchimp)
- **Mailchimp_Webhook_Log**: traccia eventi del webhook

> Ogni tabella ha `createdAt`/`updatedAt` gestiti in INSERT/UPDATE. Double opt‑in su **ogni** iscrizione.

---

## 5) Endpoint – dettagli & esempi

### 5.1 Iscrizione ad un evento
**POST** `/event-registration`  
Header: `Content-Type: application/json`  
Body:
```json
{
  "evento_id": 123,
  "nome": "Mario",
  "cognome": "Rossi",
  "email": "mario.rossi@example.com",
  "azienda": "ACME S.p.A.",
  "telefono": "3331234567",
  "ruolo": "Marketing",
  "checkin": "NA"
}
```
**Risposte tipiche**
- `200 OK` → `{"success":true,"message":"Iscrizione ricevuta. Controlla l’email per confermare.","idIscrizione": 987}`
- `400 Bad Request` con `fieldErrors` per validazioni (email, azienda, ecc.)

**Note**
- Valida evento (esistenza e data futura), evita doppie iscrizioni dello stesso utente all’evento, salva su DB, **invia/aggiorna Mailchimp** in modalità **double opt‑in** e logga l’operazione.

---

### 5.2 Elenco iscritti/check‑in per evento
**GET** `/events/{eventId}/checkins`  
Parametri: `eventId` = **Eventi.ID**  
**Risposte**
- `200 OK` → elenco JSON degli iscritti e relativo `checkin`
- `404 Not Found` se nessuna iscrizione

---

### 5.3 Aggiornamento ruolo + check‑in (uso in presenza/hostess)
**POST | PUT | PATCH** `/event-checkin-update`  
Header: `Content-Type: application/json`  
Body:
```json
{
  "registrationId": `#`,        // ID della riga su Iscrizione_Eventi
  "userId": `#`,                 // Utenti.ID
  "checkin": "presenza",         // "presenza" | "virtuale" | "NA"
  "ruolo": "Responsabile Area"
}
```
**Risposte**
- `200 OK` → `{"success":true,"updated":{...}}`
- `400 Bad Request` (parametri mancanti/invalidi)
- `404 Not Found` (riga non trovata in Iscrizione_Eventi/Utenti)

> **Nota compatibilità**: dove la versione precedente usava `eventId` per riferirsi alla riga di `Iscrizione_Eventi`, ora documentiamo esplicitamente `registrationId` per chiarezza.

---

### 5.4 Creazione evento (backend)
**POST** `/events`  
Body:
```json
{
  "nome": "XXXXX",
  "dataEvento": "2025-10-15T18:30",
  "categoria": "convegno",
  "tipo": "in presenza"
}
```
**Risposte**
- `201 Created` o `200 OK` con ID creato
- `400 Bad Request` per validazioni

---

### 5.5 Webhook Mailchimp
**GET | POST** `/mailchimp/webhook`  
- **GET**: verifica raggiungibilità/echo test
- **POST**: ricezione eventi (conferme iscrizione, aggiornamenti stato). Gli eventi sono salvati in `Mailchimp_Webhook_Log`. Quando opportuno, viene aggiornato lo **status** su `Iscrizione_Eventi` e/o `Utenti`.

---

## 6) Regole CORS
Di default: `Access-Control-Allow-Origin: *`.  
In **produzione**, impostare una whitelist dei domini front‑end autorizzati (WordPress/hostess app).

---

## 7) Sicurezza
- **Input validation & sanitizzazione** lato server; tutti gli accessi DB via **PDO prepared statements**.
- **Double opt‑in** Mailchimp su ogni iscrizione.
- **Log** di esiti positivi/negativi delle integrazioni.
- Consigliati (se richiesti): **rate‑limit** per IP/endpoint, **API key** o **JWT** per rotte riservate (es. `/events` POST, `/event-checkin-update`).

---

## 8) Formati errore & status code
Formato uniforme:
```json
{ "success": false, "error": "Messaggio", "fieldErrors": { "email": "non valida" } }
```
Mappa status principali:
- `200 OK` successo generico
- `201 Created` creazione risorsa
- `204 No Content` preflight/operazioni senza body
- `400 Bad Request` validazioni/JSON malformato
- `404 Not Found` risorsa non esistente
- `405 Method Not Allowed`
- `409 Conflict` duplicati (es. stessa email già iscritta all’evento)
- `422 Unprocessable Entity` (validazioni semantiche)
- `429 Too Many Requests` (se attivato rate‑limit)
- `500 Internal Server Error`

---

## 9) Esecuzione & test

### Avvio locale (esempio Apache)
1. Copiare la cartella `appeventi/` sotto il DocumentRoot.
2. Verificare `.htaccess` attivo e `AllowOverride All`.
3. Creare `appeventi/.env` con le credenziali reali.
4. Importare schema tabelle (fornito dal progetto) in MariaDB.
5. Test:
   - `GET /` → health
   - `POST /event-registration` con JSON di esempio
   - `GET /events/{id}/checkins`
   - `POST /event-checkin-update`
   - `GET|POST /mailchimp/webhook`

### Postman
È disponibile una collection con esempi di chiamata e risposte attese (richiedere ultima versione).

---

## 10) Note su Mailchimp (double opt‑in)
- L’iscrizione invia/aggiorna il **member** nella **audience** configurata (`MAILCHIMP_LIST_ID`) usando l’**hash MD5 dell’email**.
- Si usano **tags**/merge fields per contestualizzare l’evento (nome/data) – logica modulare nel servizio `MailchimpService`.
- L’utente riceve email di conferma (**double opt‑in**). Solo dopo la conferma lo **status** diventa attivo lato Mailchimp; eventuali sincronizzazioni possono aggiornare i nostri `status`.

---

## 11) Manutenzione & log
- Errori ed eventi d’integrazione sono registrati su DB (`*_Log`) e su error_log PHP (se abilitato).
- Consigliato: dashboard operativa per contare **pending/confirmed/failed** per evento e audit degli update hostess.

---

## 12) Versionamento & breaking changes
- **v1.0**: introduzione front controller `index.php`, rotte REST‑like, compat legacy su `/*.php`.
- Uniformato il nome del campo per update check‑in a `registrationId` (evita ambiguità con `eventId`).

---

## 13) Contatti
Per supporto tecnico, richieste di estensioni (auth, rate‑limit, esport CSV/Excel, dashboard), o per ricevere la Postman collection aggiornata: contattare il referente tecnico del progetto.
