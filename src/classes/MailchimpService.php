<?php

/**
 * Mailchimp API Service
 * Gestisce l'integrazione con Mailchimp per double opt-in degli eventi
 */
class MailchimpService
{
    private $apiKey;
    private $listId;
    private $serverPrefix;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = $_ENV['MAILCHIMP_API_KEY'] ?? '';
        $this->listId = $_ENV['MAILCHIMP_LIST_ID'] ?? '';
        $this->serverPrefix = $_ENV['MAILCHIMP_SERVER_PREFIX'] ?? 'us1';

        $this->baseUrl = "https://{$this->serverPrefix}.api.mailchimp.com/3.0";

        if (empty($this->apiKey) || empty($this->listId)) {
            throw new Exception('Mailchimp API credentials non configurate correttamente');
        }
    }

    /**
     * Effettua chiamata API a Mailchimp
     */
    private function makeApiCall($endpoint, $method = 'GET', $data = null)
    {
        $url = $this->baseUrl . $endpoint;
        $headers = [
            'Authorization: Basic ' . base64_encode('user:' . $this->apiKey),
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Errore cURL: " . $error);
        }

        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = isset($decodedResponse['detail']) ? $decodedResponse['detail'] : 'Errore API Mailchimp';
            throw new Exception("Errore Mailchimp ({$httpCode}): " . $errorMessage);
        }

        return $decodedResponse;
    }

    /**
     * Genera hash MD5 dell'email per identificare subscriber
     */
    private function getEmailHash($email)
    {
        return md5(strtolower($email));
    }

    public function addSubscriber($email, $nome, $cognome, $azienda, $eventoNome, $eventoData)
    {
        $subscriberHash = md5(strtolower($email));
    
        // Prima verifica e crea i merge fields se necessario
        $this->checkAndCreateMergeFields();
    
        // Base data senza merge fields potenzialmente problematici
        $data = [
            'email_address' => strtolower($email),
            'status_if_new' => 'pending', // double opt-in se è un nuovo iscritto
        ];
    
        // Prova prima senza merge fields
        try {
            $response = $this->makeApiCall("/lists/{$this->listId}/members/{$subscriberHash}", 'PUT', $data);
            
            // Se ha successo, prova ad aggiornare con i merge fields
            if (!empty($nome) || !empty($cognome)) {
                $updateData = [
                    'merge_fields' => []
                ];
                
                if (!empty($nome)) {
                    $updateData['merge_fields']['FNAME'] = $nome;
                }
                
                if (!empty($cognome)) {
                    $updateData['merge_fields']['LNAME'] = $cognome;
                }
                
                // Tentativo di aggiornamento con merge fields
                try {
                    $this->makeApiCall("/lists/{$this->listId}/members/{$subscriberHash}", 'PATCH', $updateData);
                    error_log("Merge fields aggiornati con successo per: " . $email);
                } catch (Exception $e) {
                    error_log("Impossibile aggiornare merge fields per {$email}: " . $e->getMessage());
                    // Non è un errore fatale, l'email è comunque stata aggiunta
                }
            }
    
            return [
                'success' => true,
                'mailchimp_id' => $response['id'] ?? null,
                'email_hash' => $subscriberHash,
                'status' => $response['status'] ?? 'pending',
                'message' => 'Subscriber aggiunto/aggiornato con successo.'
            ];
            
        } catch (Exception $e) {
            // Se fallisce anche la versione base, c'è un problema più serio
            error_log("Errore gestione subscriber Mailchimp: " . $e->getMessage());
            error_log("Dati inviati: " . json_encode($data));
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Errore durante la gestione della mailing list'
            ];
        }
    
        // Tags per evento e data (decommentare se i campi esistono)
        /*
        $tags = [];
        if (!empty($eventoNome)) {
            $tags[] = 'evento-' . $this->sanitizeTagName($eventoNome);
        }
        if (!empty($eventoData)) {
            $tags[] = 'data-' . date('Y-m', strtotime($eventoData));
        }
        if (!empty($tags)) {
            $data['tags'] = $tags;
        }
        */
    
        try {
            // PUT = insert or update (upsert)
            $response = $this->makeApiCall("/lists/{$this->listId}/members/{$subscriberHash}", 'PUT', $data);
    
            return [
                'success' => true,
                'mailchimp_id' => $response['id'] ?? null,
                'email_hash' => $subscriberHash,
                'status' => $response['status'] ?? 'pending',
                'message' => isset($response['id'])
                    ? 'Subscriber aggiunto/aggiornato con successo.'
                    : 'Operazione completata.'
            ];
    
        } catch (Exception $e) {
            error_log("Errore gestione subscriber Mailchimp: " . $e->getMessage());
            
            // Log più dettagliato per debug
            error_log("Dati inviati: " . json_encode($data));
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Errore durante la gestione della mailing list'
            ];
        }
    }
    
    // Metodo helper per sanitizzare i nomi dei tag
    private function sanitizeTagName($name)
    {
        // Rimuovi caratteri speciali e sostituisci spazi con trattini
        $sanitized = preg_replace('/[^a-zA-Z0-9\s]/', '', $name);
        $sanitized = preg_replace('/\s+/', '-', trim($sanitized));
        return strtolower($sanitized);
    }

    /**
     * Aggiunge un subscriber con status pending (double opt-in)
     */
    public function addSubscriber_old($email, $nome, $cognome, $azienda, $eventoNome, $eventoData)
    {
        $data = [
            'email_address' => strtolower($email),
            'status' => 'pending', // Abilita double opt-in
            'merge_fields' => [
                'FNAME' => $nome,
                'LNAME' => $cognome,
                'COMPANY' => $azienda
            ],
            //'tags' => [
            //    'evento-' . sanitizeTagName($eventoNome),
            //    'data-' . date('Y-m', strtotime($eventoData))
            //],
            //'interests' => [],
            //'language' => 'it',
            //'vip' => false,
            //'location' => [
            //    'country_code' => 'IT',
            //    'timezone' => 'Europe/Rome'
            //]
        ];

        try {
            $response = $this->makeApiCall("/lists/{$this->listId}/members", 'POST', $data);

            return [
                'success' => true,
                'mailchimp_id' => $response['id'] ?? null,
                'email_hash' => $this->getEmailHash($email),
                'status' => $response['status'] ?? 'pending',
                'message' => 'Subscriber aggiunto con successo. Email di conferma inviata.'
            ];

        } catch (Exception $e) {
            // Se l'utente esiste già, prova ad aggiornarlo
            if (strpos($e->getMessage(), 'Member Exists') !== false) {
                return $this->updateSubscriber($email, $nome, $cognome, $azienda, $eventoNome, $eventoData);
            }

            error_log("Errore aggiunta subscriber Mailchimp: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Errore durante l\'aggiunta alla mailing list'
            ];
        }
    }

    /**
     * Aggiorna un subscriber esistente
     */
    public function updateSubscriber($email, $nome, $cognome, $azienda, $eventoNome, $eventoData)
    {
        $emailHash = $this->getEmailHash($email);

        $data = [
            'email_address' => strtolower($email),
            'status_if_new' => 'pending', // Se non esiste, crea con pending
            'merge_fields' => [
                'FNAME' => $nome,
                'LNAME' => $cognome,
                'COMPANY' => $azienda
            ]
        ];

        try {
            $response = $this->makeApiCall("/lists/{$this->listId}/members/{$emailHash}", 'PUT', $data);

            // Aggiungi i tag dell'evento
            $this->addTagsToSubscriber($emailHash, [
                'evento-' . sanitizeTagName($eventoNome),
                'data-' . date('Y-m', strtotime($eventoData))
            ]);

            return [
                'success' => true,
                'mailchimp_id' => $response['id'] ?? null,
                'email_hash' => $emailHash,
                'status' => $response['status'] ?? 'pending',
                'message' => 'Subscriber aggiornato con successo.'
            ];

        } catch (Exception $e) {
            error_log("Errore aggiornamento subscriber Mailchimp: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Errore durante l\'aggiornamento della mailing list'
            ];
        }
    }

    /**
     * Ottiene informazioni su un subscriber
     */
    public function getSubscriber($email)
    {
        $emailHash = $this->getEmailHash($email);

        try {
            $response = $this->makeApiCall("/lists/{$this->listId}/members/{$emailHash}");

            return [
                'success' => true,
                'subscriber' => $response,
                'status' => $response['status'] ?? 'unknown',
                'mailchimp_id' => $response['id'] ?? null
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 'not_found'
            ];
        }
    }

    /**
     * Aggiunge tag a un subscriber
     */
    public function addTagsToSubscriber($emailHash, $tags)
    {
        $data = [
            'tags' => array_map(function($tag) {
                return ['name' => $tag, 'status' => 'active'];
            }, $tags)
        ];

        try {
            $this->makeApiCall("/lists/{$this->listId}/members/{$emailHash}/tags", 'POST', $data);
            return true;
        } catch (Exception $e) {
            error_log("Errore aggiunta tag Mailchimp: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Configura webhook per ricevere conferme
     */
    public function setupWebhook($webhookUrl)
    {
        $data = [
            'url' => $webhookUrl,
            'events' => [
                'subscribe' => true,
                'unsubscribe' => true,
                'profile' => true,
                'cleaned' => true,
                'upemail' => true,
                'campaign' => false
            ],
            'sources' => [
                'user' => true,
                'admin' => true,
                'api' => true
            ]
        ];

        try {
            $response = $this->makeApiCall("/lists/{$this->listId}/webhooks", 'POST', $data);
            return [
                'success' => true,
                'webhook_id' => $response['id'] ?? null,
                'message' => 'Webhook configurato con successo'
            ];

        } catch (Exception $e) {
            error_log("Errore configurazione webhook Mailchimp: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verifica la firma del webhook
     */
    public function verifyWebhookSignature($data, $signature)
    {
        $webhookSecret = $_ENV['MAILCHIMP_WEBHOOK_SECRET'] ?? '';
        if (empty($webhookSecret)) {
            return false;
        }

        $expectedSignature = base64_encode(hash_hmac('sha256', $data, $webhookSecret, true));
        return hash_equals($expectedSignature, $signature);
    }


    public function getMergeFields()
    {
        try {
            $response = $this->makeApiCall("/lists/{$this->listId}/merge-fields", 'GET');
            
            // Log per debug
            error_log("Merge fields disponibili nella lista {$this->listId}:");
            foreach ($response['merge_fields'] as $field) {
                error_log("- Tag: {$field['tag']}, Name: {$field['name']}, Type: {$field['type']}, Required: " . ($field['required'] ? 'Yes' : 'No'));
            }
            
            return $response['merge_fields'];
            
        } catch (Exception $e) {
            error_log("Errore nel recupero merge fields: " . $e->getMessage());
            return false;
        }
    }

    public function checkAndCreateMergeFields()
    {
        try {
            // Verifica merge fields esistenti
            $existingFields = $this->getMergeFields();
            
            $existingTags = [];
            if ($existingFields) {
                foreach ($existingFields as $field) {
                    $existingTags[] = $field['tag'];
                }
            }
            
            // Crea FNAME se non existe
            if (!in_array('FNAME', $existingTags)) {
                $nameField = [
                    'tag' => 'FNAME',
                    'name' => 'Nome',
                    'type' => 'text',
                    'required' => false,
                    'public' => true
                ];
                
                $this->makeApiCall("/lists/{$this->listId}/merge-fields", 'POST', $nameField);
                error_log("Campo FNAME creato con successo");
            }
            
            // Crea LNAME se non existe
            if (!in_array('LNAME', $existingTags)) {
                $surnameField = [
                    'tag' => 'LNAME',
                    'name' => 'Cognome', 
                    'type' => 'text',
                    'required' => false,
                    'public' => true
                ];
                
                $this->makeApiCall("/lists/{$this->listId}/merge-fields", 'POST', $surnameField);
                error_log("Campo LNAME creato con successo");
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Errore nella gestione merge fields: " . $e->getMessage());
            return false;
        }
    }

    /**
    * Ottiene informazioni dettagliate su un membro della lista
    */
    public function getMemberInfo($email) {
        try {
            $subscriberHash = $this->getEmailHash($email);
            $endpoint = "lists/{$this->listId}/members/{$subscriberHash}";
            
            $response = $this->makeApiCall('GET', $endpoint);
            
            return $response;
            
        } catch (Exception $e) {
            error_log("Errore getMemberInfo per {$email}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Ottiene le impostazioni della lista
     */
    public function getListSettings() {
        try {
            $endpoint = "lists/{$this->listId}";
            
            $response = $this->makeApiCall('GET', $endpoint);
            
            return [
                'double_optin' => $response['double_optin'] ?? false,
                'email_type_option' => $response['email_type_option'] ?? false,
                'default_from_name' => $response['campaign_defaults']['from_name'] ?? '',
                'default_from_email' => $response['campaign_defaults']['from_email'] ?? '',
                'default_subject' => $response['campaign_defaults']['subject'] ?? '',
                'permission_reminder' => $response['permission_reminder'] ?? '',
                'use_archive_bar' => $response['use_archive_bar'] ?? false,
                'notify_on_subscribe' => $response['notify_on_subscribe'] ?? '',
                'notify_on_unsubscribe' => $response['notify_on_unsubscribe'] ?? ''
            ];
            
        } catch (Exception $e) {
            error_log("Errore getListSettings: " . $e->getMessage());
            throw $e;
        }
}

    /**
     * Controlla l'attività recente di un membro
     */
    public function getMemberActivity($email, $count = 10) {
        try {
            $subscriberHash = $this->getEmailHash($email);
            $endpoint = "lists/{$this->listId}/members/{$subscriberHash}/activity";
            
            $params = ['count' => $count];
            $response = $this->makeApiCall('GET', $endpoint, $params);
            
            return $response['activity'] ?? [];
            
        } catch (Exception $e) {
            error_log("Errore getMemberActivity per {$email}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verifica se un'email è nella lista di bounce/complaint
     */
    public function checkEmailStatus($email) {
        try {
            // Prima prova a ottenere info membro
            $memberInfo = $this->getMemberInfo($email);
            
            if ($memberInfo) {
                return [
                    'exists' => true,
                    'status' => $memberInfo['status'],
                    'email_type' => $memberInfo['email_type'],
                    'timestamp_opt' => $memberInfo['timestamp_opt'] ?? null,
                    'last_changed' => $memberInfo['last_changed'] ?? null,
                    'unsubscribe_reason' => $memberInfo['unsubscribe_reason'] ?? null
                ];
            }
            
            return ['exists' => false];
            
        } catch (Exception $e) {
            // Se otteniamo un 404, il membro non esiste
            if (strpos($e->getMessage(), '404') !== false) {
                return ['exists' => false];
            }
            
            error_log("Errore checkEmailStatus per {$email}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Re-iscrive un membro che era stato rimosso/unsubscribed
     */
    public function resubscribeMember($email, $mergeFields = []) {
        try {
            $subscriberHash = $this->getEmailHash($email);
            $endpoint = "lists/{$this->listId}/members/{$subscriberHash}";
            
            $data = [
                'email_address' => $email,
                'status' => 'subscribed', // Forza lo stato a subscribed
                'merge_fields' => $mergeFields
            ];
            
            $response = $this->makeRequest('PATCH', $endpoint, [], $data);
            
            return [
                'success' => true,
                'status' => $response['status'],
                'id' => $response['id']
            ];
            
        } catch (Exception $e) {
            error_log("Errore resubscribeMember per {$email}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

/**
 * Funzione helper per sanitizzare nomi tag
 */
function sanitizeTagName($name)
{
    // Rimuovi caratteri speciali e converti in lowercase
    $clean = preg_replace('/[^a-zA-Z0-9\s-]/', '', $name);
    $clean = preg_replace('/\s+/', '-', trim($clean));
    return strtolower($clean);
}

/**
 * Funzione helper per caricare variabili ambiente
 */
function loadEnvironmentVariables()
{
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue; // Salta commenti
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
                putenv(sprintf('%s=%s', $name, $value));
            }
        }
    }
}

// Carica variabili ambiente al caricamento della classe
loadEnvironmentVariables();

?>