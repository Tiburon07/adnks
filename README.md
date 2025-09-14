# ADNKS - Sistema di Gestione Eventi e Iscrizioni

Applicazione web PHP per la gestione completa di eventi e iscrizioni utenti, con architettura containerizzata Docker e supporto SSL per il dominio `adnks.site`.

## 🛠 Stack Tecnologico

- **Backend**: PHP 8.2-FPM
- **Database**: MariaDB 10.11
- **Web Server**: Nginx (con supporto SSL/HTTPS)
- **Containerizzazione**: Docker & Docker Compose
- **SSL**: Let's Encrypt (Certbot con DNS DigitalOcean)

## 📋 Prerequisiti

- Docker e Docker Compose installati
- Accesso al DNS DigitalOcean (per certificati SSL)
- Porta 80, 443 e 3306 disponibili

## 🚀 Installazione e Avvio

### 1. Clona il Repository
```bash
git clone https://github.com/Tiburon07/adnks.git
cd adnks
```

### 2. Configurazione Ambiente
Modifica il file `src/.env` con le tue credenziali:
```env
DB_HOST=mariadb
DB_PORT=3306
DB_NAME=app_database
DB_USER=app_user
DB_PASSWORD=app_password
APP_ENV=production
APP_DEBUG=false
```

### 3. Avvio con Docker Compose

#### Sviluppo
```bash
make up
```

#### Build e Deploy Completo
```bash
make build
```

#### Altri Comandi Utili
```bash
make down          # Ferma i container
make down-v         # Ferma i container e rimuove volumi
make show-logs      # Mostra i log
```

### 4. Accesso all'Applicazione

- **Sviluppo**: http://localhost
- **Produzione**: https://adnks.site

## 🗄 Struttura del Progetto

```
adnks/
├── docker-compose.yml          # Orchestrazione servizi Docker
├── makefile                    # Comandi per gestione container
├── README.md
│
├── nginx/
│   └── default.conf           # Configurazione Nginx + SSL
│
├── php/
│   ├── Dockerfile             # Container PHP 8.2-FPM
│   └── php.ini               # Configurazione PHP
│
├── mysql/
│   └── init/                 # Script inizializzazione DB
│
├── sql/
│   └── eventi_schema_mariadb.sql  # Schema database
│
└── src/                      # Codice applicazione PHP
    ├── .env                  # Configurazione ambiente
    ├── index.php            # Homepage gestione eventi
    ├── iscrizione.php       # Form iscrizione eventi
    ├── visualizza_iscrizioni.php
    ├── visualizza_utenti.php
    ├── dettagli_utente.php
    ├── salva_evento.php
    ├── salva_iscrizione.php
    ├── update_iscrizione.php
    ├── update_utente.php
    └── classes/             # Classi PHP (DB, utility)
```

## 🌟 Funzionalità Principali

### Gestione Eventi
- ✅ Creazione, modifica ed eliminazione eventi
- ✅ Visualizzazione calendario eventi
- ✅ Gestione dettagli eventi (data, luogo, descrizione)

### Gestione Iscrizioni
- ✅ Form iscrizione utenti agli eventi
- ✅ Conferma/Annullamento iscrizioni
- ✅ Check-in partecipanti
- ✅ Gestione stati iscrizione

### Gestione Utenti
- ✅ Registrazione e gestione profili utenti
- ✅ Visualizzazione dettagli utente
- ✅ Aggiornamento informazioni personali

### Statistiche e Report
- ✅ Dashboard statistiche eventi
- ✅ Report iscrizioni per evento
- ✅ Elenco partecipanti

## 🔒 Configurazione SSL

### Generazione Certificato Let's Encrypt
```bash
sudo docker run -it --rm --name certbot \
  --env-file ~/.config/certbot/certbot.env \
  --volume "/etc/letsencrypt:/etc/letsencrypt" \
  --volume "/var/lib/letsencrypt:/var/lib/letsencrypt" \
  certbot/dns-digitalocean certonly \
  --dns-digitalocean \
  --dns-digitalocean-credentials /etc/letsencrypt/digitalocean.ini \
  -d adnks.site -d "*.adnks.site" \
  --agree-tos \
  --email t.iordache@outlook.it
```

### Rinnovo Certificato
```bash
sudo docker run -it --rm --name certbot \
  --env-file ~/.config/certbot/certbot.env \
  --volume "/etc/letsencrypt:/etc/letsencrypt" \
  --volume "/var/lib/letsencrypt:/var/lib/letsencrypt" \
  certbot/dns-digitalocean certonly \
  --dns-digitalocean \
  --dns-digitalocean-credentials /etc/letsencrypt/digitalocean.ini \
  -d adnks.site -d "*.adnks.site" \
  --agree-tos \
  --email t.iordache@outlook.it \
  --force-renewal
```

## 🧪 Testing e Debug

### Verifica Configurazione PHP
Visita: http://localhost/phpinfo.php (solo in sviluppo)

### Log dei Container
```bash
make show-logs
```

### Accesso al Database
```bash
docker exec -it mariadb_container mysql -u root -p
```

## 📦 Servizi Docker

| Servizio | Porta | Descrizione |
|----------|-------|-------------|
| nginx | 80, 443 | Web server con SSL |
| php | 9000 | PHP-FPM |
| mariadb | 3306 | Database MariaDB |

## ⚙️ Configurazioni di Produzione

### Variabili Ambiente (.env)
```env
APP_ENV=production
APP_DEBUG=false
DB_HOST=mariadb
DB_PORT=3306
```

### Nginx
- Configurazione SSL automatica per `adnks.site`
- Cache statica per asset (1 anno)
- Ottimizzazioni FastCGI per PHP

### PHP
- PHP 8.2 con estensioni: PDO, MySQL, GD, Zip, BCMath
- Memory limit e timeout ottimizzati
- OPcache abilitato in produzione

## 🔧 Troubleshooting

### Container non si avvia
```bash
docker compose logs [nome-servizio]
```

### Database non accessibile
Verifica che la porta 3306 non sia occupata:
```bash
sudo netstat -tulpn | grep 3306
```

### Problemi SSL
Verifica i certificati Let's Encrypt:
```bash
sudo certbot certificates
```

## 👤 Autore

**Tiburon07**
📧 t.iordache@outlook.it
🌐 https://adnks.site

## 📄 Licenza

MIT License - vedi file LICENSE per dettagli.

---

*Ultimo aggiornamento: Settembre 2025*