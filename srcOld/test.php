<?php
/**
 * Script di debug per verificare stato iscrizione Mailchimp
 * Versione con indentazione ottimizzata per massima leggibilità
 */

require_once __DIR__ . '/classes/MailchimpService.php';
require_once __DIR__ . '/classes/Database.php';

// Sostituisci con l'email che stai testando
$emailToCheck = 'iordachetiberiu4@gmail.com';

// Funzioni helper per output super leggibile
function printMainHeader($title) {
    echo "\n\n";
    echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
    echo "║                          " . strtoupper(str_pad($title, 40, ' ', STR_PAD_BOTH)) . "║\n";
    echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";
}

function printSectionHeader($number, $title) {
    echo "\n";
    echo "┌──────────────────────────────────────────────────────────────────────────────┐\n";
    echo "│  {$number}. " . strtoupper($title) . str_repeat(' ', 70 - strlen($title)) . "│\n";
    echo "└──────────────────────────────────────────────────────────────────────────────┘\n\n";
}

function printSubHeader($icon, $title) {
    echo "    ┌─ {$icon} {$title}\n";
    echo "    │\n";
}

function printSuccess($message) {
    echo "    │  ✅ {$message}\n";
}

function printError($message) {
    echo "    │  ❌ {$message}\n";
}

function printWarning($message) {
    echo "    │  ⚠️  {$message}\n";
}

function printInfo($message) {
    echo "    │  ℹ️  {$message}\n";
}

function printDetail($label, $value) {
    $paddedLabel = str_pad($label, 20, '·', STR_PAD_RIGHT);
    echo "    │      {$paddedLabel} {$value}\n";
}

function printNote($message) {
    echo "    │      💡 {$message}\n";
}

function printSeparator() {
    echo "    │\n";
}

function printEndSection() {
    echo "    └─\n";
}

function formatStatus($status) {
    $statusMap = [
        'subscribed'   => '🟢 ISCRITTO',
        'unsubscribed' => '🔴 DISISCRITTO', 
        'pending'      => '🟡 IN ATTESA',
        'cleaned'      => '⚫ RIMOSSO',
        'confirmed'    => '✅ CONFERMATO',
        'cancelled'    => '❌ ANNULLATO'
    ];
    return $statusMap[$status] ?? "❓ " . strtoupper($status);
}

function formatDate($dateString) {
    if (!$dateString) return 'N/A';
    return date('d/m/Y H:i:s', strtotime($dateString));
}

function formatYesNo($value) {
    return $value ? '✅ SÌ' : '❌ NO';
}

try {
    $mailchimp = new MailchimpService();
    $pdo = getDB();
    
    printMainHeader("Debug Mailchimp Iscrizione");
    
    echo "📧 Email analizzata: {$emailToCheck}\n";
    echo "🕒 Timestamp: " . date('d/m/Y H:i:s') . "\n";
    echo "🖥️  Server: " . php_uname('n') . "\n";

    // ════════════════════════════════════════════════════════════════════════════════════
    // SEZIONE 1: DATABASE LOCALE
    // ════════════════════════════════════════════════════════════════════════════════════
    printSectionHeader("1", "Analisi Database Locale");
    
    $localSql = "SELECT ie.*, u.email, e.nome as evento_nome
                 FROM Iscrizione_Eventi ie
                 JOIN Utenti u ON ie.idUtente = u.ID
                 JOIN Eventi e ON ie.idEvento = e.ID
                 WHERE u.email = :email
                 ORDER BY ie.createdAt DESC
                 LIMIT 3";
    
    $localStmt = $pdo->prepare($localSql);
    $localStmt->execute([':email' => $emailToCheck]);
    $iscrizioni = $localStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($iscrizioni)) {
        printSubHeader("🔍", "Risultato Ricerca");
        printWarning("Nessuna iscrizione trovata nel database locale");
        printSeparator();
        printNote("L'utente potrebbe non esistere nella tabella Utenti");
        printNote("Oppure non ha mai completato un'iscrizione agli eventi");
        printEndSection();
        
    } else {
        printSubHeader("📊", "Iscrizioni Trovate: " . count($iscrizioni));
        printSuccess("Dati recuperati correttamente dal database");
        printSeparator();
        
        foreach ($iscrizioni as $index => $isc) {
            echo "    ┌─ 📝 ISCRIZIONE #" . ($index + 1) . "\n";
            echo "    │\n";
            printDetail("Evento", $isc['evento_nome']);
            printDetail("Status Database", formatStatus($isc['status']));
            printDetail("Status Mailchimp", formatStatus($isc['mailchimp_status']));
            printDetail("Data Creazione", formatDate($isc['createdAt']));
            printDetail("Ultimo Aggiornamento", formatDate($isc['updatedAt']));
            
            if ($isc['status'] !== $isc['mailchimp_status']) {
                printSeparator();
                printWarning("Stati non sincronizzati tra database e Mailchimp!");
            }
            echo "    └─\n";
            
            if ($index < count($iscrizioni) - 1) {
                echo "\n";
            }
        }
    }

    // ════════════════════════════════════════════════════════════════════════════════════
    // SEZIONE 2: STATO MAILCHIMP
    // ════════════════════════════════════════════════════════════════════════════════════
    printSectionHeader("2", "Verifica Stato Mailchimp");
    
    try {
        $memberInfo = $mailchimp->getMemberInfo($emailToCheck);
        
        if ($memberInfo) {
            printSubHeader("👤", "Membro Trovato in Mailchimp");
            printSuccess("Connessione API Mailchimp riuscita");
            printSeparator();
            
            printDetail("Status Corrente", formatStatus($memberInfo['status']));
            printDetail("Tipo Email", $memberInfo['email_type'] ?? 'Non specificato');
            
            if (isset($memberInfo['timestamp_opt'])) {
                printDetail("Data Opt-in", formatDate($memberInfo['timestamp_opt']));
            }
            
            if (isset($memberInfo['last_changed'])) {
                printDetail("Ultima Modifica", formatDate($memberInfo['last_changed']));
            }
            
            if (isset($memberInfo['email_client'])) {
                printDetail("Client Email", $memberInfo['email_client']);
            }
            
            printSeparator();
            
            // Tag
            if (isset($memberInfo['tags']) && !empty($memberInfo['tags'])) {
                echo "    │  🏷️  TAG ASSEGNATI:\n";
                foreach ($memberInfo['tags'] as $tag) {
                    echo "    │         • " . $tag['name'] . "\n";
                }
            } else {
                printInfo("Nessun tag assegnato al membro");
            }
            
            printSeparator();
            
            // Campi personalizzati
            if (isset($memberInfo['merge_fields']) && !empty($memberInfo['merge_fields'])) {
                echo "    │  📝 CAMPI PERSONALIZZATI:\n";
                foreach ($memberInfo['merge_fields'] as $key => $value) {
                    $paddedKey = str_pad($key, 15, '·', STR_PAD_RIGHT);
                    echo "    │         {$paddedKey} {$value}\n";
                }
            } else {
                printInfo("Nessun campo personalizzato configurato");
            }
            
            printEndSection();
            
        } else {
            printSubHeader("❌", "Membro NON Trovato");
            printError("L'email non è presente nella lista Mailchimp");
            printSeparator();
            printNote("L'email potrebbe non essere mai stata iscritta");
            printNote("Oppure potrebbe essere stata rimossa completamente");
            printNote("Verificare se l'email è in blacklist o bounce");
            printEndSection();
        }
        
    } catch (Exception $e) {
        printSubHeader("💥", "Errore API Mailchimp");
        printError("Impossibile recuperare dati da Mailchimp");
        printSeparator();
        printDetail("Messaggio Errore", $e->getMessage());
        printDetail("Codice Errore", $e->getCode());
        printNote("Verificare configurazione API key");
        printNote("Controllare connettività internet");
        printEndSection();
    }

    // ════════════════════════════════════════════════════════════════════════════════════
    // SEZIONE 3: LOG WEBHOOK
    // ════════════════════════════════════════════════════════════════════════════════════
    printSectionHeader("3", "Analisi Log Webhook");
    
    $webhookSql = "SELECT * FROM Mailchimp_Webhook_Log 
                   WHERE email = :email 
                   ORDER BY received_at DESC 
                   LIMIT 5";
    
    try {
        $webhookStmt = $pdo->prepare($webhookSql);
        $webhookStmt->execute([':email' => $emailToCheck]);
        $webhooks = $webhookStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($webhooks)) {
            printSubHeader("🔇", "Nessun Webhook Registrato");
            printWarning("Non sono stati ricevuti webhook per questa email");
            printSeparator();
            printNote("Potrebbe indicare che non ci sono stati cambi di stato");
            printNote("Verificare configurazione webhook su Mailchimp");
            printNote("Controllare che l'URL endpoint sia raggiungibile");
            printEndSection();
            
        } else {
            printSubHeader("📨", "Webhook Trovati: " . count($webhooks));
            printSuccess("Eventi webhook registrati nel database");
            printSeparator();
            
            foreach ($webhooks as $index => $webhook) {
                echo "    ┌─ 🔔 WEBHOOK #" . ($index + 1) . "\n";
                echo "    │\n";
                
                printDetail("Tipo Evento", strtoupper($webhook['webhook_type']));
                printDetail("Stato Processo", formatYesNo($webhook['processed']));
                printDetail("Data Ricezione", formatDate($webhook['received_at']));
                
                if ($webhook['error_message']) {
                    printDetail("Errore", $webhook['error_message']);
                } else {
                    printDetail("Errore", "Nessuno - Elaborato correttamente");
                }
                
                if (isset($webhook['response_time'])) {
                    printDetail("Tempo Risposta", $webhook['response_time'] . "ms");
                }
                
                // Payload se presente
                if (isset($webhook['payload_data']) && !empty($webhook['payload_data'])) {
                    printSeparator();
                    echo "    │  📋 PAYLOAD DATI:\n";
                    
                    $payload = json_decode($webhook['payload_data'], true);
                    if ($payload && is_array($payload)) {
                        foreach ($payload as $key => $value) {
                            $displayValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : $value;
                            $paddedKey = str_pad($key, 15, '·', STR_PAD_RIGHT);
                            echo "    │         {$paddedKey} " . substr($displayValue, 0, 50) . "\n";
                        }
                    }
                }
                
                echo "    └─\n";
                
                if ($index < count($webhooks) - 1) {
                    echo "\n";
                }
            }
        }
        
    } catch (Exception $e) {
        printSubHeader("💥", "Errore Database Webhook");
        printError("Impossibile recuperare log webhook");
        printSeparator();
        printDetail("Errore", $e->getMessage());
        printNote("La tabella Mailchimp_Webhook_Log potrebbe non esistere");
        printEndSection();
    }

    // ════════════════════════════════════════════════════════════════════════════════════
    // SEZIONE 4: CONFIGURAZIONE LISTA
    // ════════════════════════════════════════════════════════════════════════════════════
    printSectionHeader("4", "Configurazione Lista Mailchimp");
    
    try {
        $listInfo = $mailchimp->getListSettings();
        
        printSubHeader("⚙️", "Impostazioni Lista");
        printSuccess("Configurazione recuperata correttamente");
        printSeparator();
        
        // Impostazioni principali
        echo "    │  🔧 CONFIGURAZIONE ISCRIZIONI:\n";
        printDetail("Double Opt-in", formatYesNo($listInfo['double_optin'] ?? false));
        printDetail("Email Type Option", formatYesNo($listInfo['email_type_option'] ?? false));
        printDetail("Marketing Permissions", formatYesNo($listInfo['marketing_permissions'] ?? false));
        
        printSeparator();
        
        // Configurazioni mittente
        echo "    │  📧 CONFIGURAZIONE MITTENTE:\n";
        printDetail("Nome Mittente", $listInfo['default_from_name'] ?? 'Non configurato');
        printDetail("Email Mittente", $listInfo['default_from_email'] ?? 'Non configurata');
        printDetail("Oggetto Default", $listInfo['default_subject'] ?? 'Non configurato');
        
        printSeparator();
        
        // Statistiche
        if (isset($listInfo['member_count'])) {
            echo "    │  📊 STATISTICHE LISTA:\n";
            printDetail("Totale Membri", number_format($listInfo['member_count']));
        }
        
        if (isset($listInfo['unsubscribe_count'])) {
            printDetail("Disiscritti", number_format($listInfo['unsubscribe_count']));
        }
        
        if (isset($listInfo['cleaned_count'])) {
            printDetail("Email Pulite", number_format($listInfo['cleaned_count']));
        }
        
        printEndSection();
        
    } catch (Exception $e) {
        printSubHeader("💥", "Errore Configurazione Lista");
        printError("Impossibile recuperare configurazione lista");
        printSeparator();
        printDetail("Errore", $e->getMessage());
        printNote("Verificare permessi API per accesso alle liste");
        printEndSection();
    }

    // ════════════════════════════════════════════════════════════════════════════════════
    // SEZIONE 5: ANALISI DIAGNOSTICA
    // ════════════════════════════════════════════════════════════════════════════════════
    printSectionHeader("5", "Analisi Diagnostica e Raccomandazioni");
    
    // Raccolta dati per analisi
    $hasLocalData = !empty($iscrizioni);
    $hasMailchimpData = isset($memberInfo) && $memberInfo;
    $hasWebhooks = !empty($webhooks);
    
    printSubHeader("📈", "Riepilogo Stato Complessivo");
    echo "    │  🎯 STATO GENERALE:\n";
    printDetail("Database Locale", $hasLocalData ? '✅ Dati trovati' : '❌ Nessun dato');
    printDetail("Mailchimp API", $hasMailchimpData ? '✅ Membro trovato' : '❌ Membro assente');
    printDetail("Webhook Events", $hasWebhooks ? '✅ Eventi registrati' : '❌ Nessun evento');
    printSeparator();
    
    // Calcolo livello di salute
    $healthScore = 0;
    if ($hasLocalData) $healthScore += 33;
    if ($hasMailchimpData) $healthScore += 33;
    if ($hasWebhooks) $healthScore += 34;
    
    $healthStatus = $healthScore >= 67 ? '🟢 BUONO' : ($healthScore >= 34 ? '🟡 PARZIALE' : '🔴 CRITICO');
    printDetail("Livello Salute", $healthStatus . " ({$healthScore}%)");
    
    printEndSection();
    
    // Raccomandazioni specifiche
    printSubHeader("💡", "Raccomandazioni Specifiche");
    
    if (!$hasLocalData && !$hasMailchimpData) {
        printError("SCENARIO: Nessun dato trovato");
        printSeparator();
        echo "    │  📋 AZIONI IMMEDIATE:\n";
        echo "    │      1. Verificare esistenza utente nella tabella Utenti\n";
        echo "    │      2. Testare processo di iscrizione end-to-end\n";
        echo "    │      3. Controllare log applicazione per errori\n";
        echo "    │      4. Verificare configurazione database\n";
        
    } elseif ($hasLocalData && !$hasMailchimpData) {
        printWarning("SCENARIO: Dati locali presenti ma assenti in Mailchimp");
        printSeparator();
        echo "    │  📋 AZIONI IMMEDIATE:\n";
        echo "    │      1. Verificare API key e configurazione Mailchimp\n";
        echo "    │      2. Controllare se email è in blacklist\n";
        echo "    │      3. Tentare re-iscrizione manuale\n";
        echo "    │      4. Verificare formato email e validità\n";
        
    } elseif (!$hasLocalData && $hasMailchimpData) {
        printWarning("SCENARIO: Membro in Mailchimp ma non nel database locale");
        printSeparator();
        echo "    │  📋 AZIONI IMMEDIATE:\n";
        echo "    │      1. Indagare come il membro è finito in Mailchimp\n";
        echo "    │      2. Sincronizzare dati nel database locale\n";
        echo "    │      3. Verificare integrità del processo di iscrizione\n";
        
    } else {
        printSuccess("SCENARIO: Dati presenti in entrambi i sistemi");
        printSeparator();
        
        // Verifica sincronizzazione stati
        if ($hasLocalData && $hasMailchimpData) {
            $ultimaIscrizione = $iscrizioni[0];
            $statusLocale = $ultimaIscrizione['mailchimp_status'];
            $statusMailchimp = $memberInfo['status'];
            
            if ($statusLocale !== $statusMailchimp) {
                printWarning("Stati non sincronizzati!");
                echo "    │  📋 AZIONI IMMEDIATE:\n";
                echo "    │      1. Eseguire sincronizzazione manuale degli stati\n";
                echo "    │      2. Verificare funzionamento webhook\n";
                echo "    │      3. Controllare log per errori di sincronizzazione\n";
            } else {
                echo "    │  ✅ Stati perfettamente sincronizzati\n";
                echo "    │  📋 MONITORAGGIO CONTINUO:\n";
                echo "    │      1. Continuare monitoraggio normale\n";
                echo "    │      2. Verificare periodicamente la sincronizzazione\n";
            }
        }
    }
    
    printSeparator();
    
    // Note finali
    echo "    │  📌 NOTE IMPORTANTI:\n";
    echo "    │      • Conservare questo output per supporto tecnico\n";
    echo "    │      • Ripetere il debug dopo aver applicato le correzioni\n";
    echo "    │      • Monitorare i webhook per verificare la risoluzione\n";
    
    printEndSection();

    // ════════════════════════════════════════════════════════════════════════════════════
    // CONCLUSIONE
    // ════════════════════════════════════════════════════════════════════════════════════
    printMainHeader("Debug Completato");
    
    echo "🏁 Analisi terminata con successo\n";
    echo "📊 Livello di salute sistema: {$healthStatus}\n";
    echo "🕒 Durata analisi: " . (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) . " secondi\n";
    echo "📞 Per supporto tecnico, condividi questo output completo\n\n";
    
} catch (Exception $e) {
    printMainHeader("Errore Critico Sistema");
    
    printSubHeader("💥", "Errore Non Gestito");
    printError("Il sistema ha incontrato un errore critico");
    printSeparator();
    
    printDetail("Messaggio", $e->getMessage());
    printDetail("File", basename($e->getFile()));
    printDetail("Linea", $e->getLine());
    printDetail("Codice Errore", $e->getCode());
    
    printSeparator();
    echo "    │  📋 STACK TRACE:\n";
    $stackTrace = $e->getTraceAsString();
    $stackLines = explode("\n", $stackTrace);
    foreach (array_slice($stackLines, 0, 5) as $line) {
        echo "    │      " . trim($line) . "\n";
    }
    
    printEndSection();
    
    echo "\n🆘 Contattare immediatamente il supporto tecnico con questo output\n\n";
}
?>