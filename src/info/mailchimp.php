<?php

require_once __DIR__ . '/../classes/MailchimpService.php';
require_once __DIR__ . '/../classes/Database.php';

try {
    $mailchimp = new MailchimpService();
    $pdo = getDB();

    // Ottieni il parametro 'call' dalla URL
    $functionCall = $_GET['call'] ?? 'getAllMembers';

    $result = null;

    switch ($functionCall) {
        case 'getAllMembers':
            $count = $_GET['count'] ?? 1000;
            $offset = $_GET['offset'] ?? 0;
            $status = $_GET['status'] ?? null;
            $result = $mailchimp->getAllMembers($count, $offset, $status);
            break;

        case 'getSubscriber':
            $email = $_GET['email'] ?? '';
            if (empty($email)) {
                throw new Exception('Email richiesta per getSubscriber');
            }
            $result = $mailchimp->getSubscriber($email);
            break;

        case 'getMemberInfo':
            $email = $_GET['email'] ?? '';
            if (empty($email)) {
                throw new Exception('Email richiesta per getMemberInfo');
            }
            $result = $mailchimp->getMemberInfo($email);
            break;

        case 'checkEmailStatus':
            $email = $_GET['email'] ?? '';
            if (empty($email)) {
                throw new Exception('Email richiesta per checkEmailStatus');
            }
            $result = $mailchimp->checkEmailStatus($email);
            break;

        case 'getMemberActivity':
            $email = $_GET['email'] ?? '';
            $count = $_GET['count'] ?? 10;
            if (empty($email)) {
                throw new Exception('Email richiesta per getMemberActivity');
            }
            $result = $mailchimp->getMemberActivity($email, $count);
            break;

        case 'getListSettings':
            $result = $mailchimp->getListSettings();
            break;

        case 'getMergeFields':
            $result = $mailchimp->getMergeFields();
            break;

        case 'getAllLists':
            $count = $_GET['count'] ?? 10;
            $offset = $_GET['offset'] ?? 0;
            $result = $mailchimp->getAllLists($count, $offset);
            break;

        case 'ping':
            $result = $mailchimp->ping();
            break;

        default:
            throw new Exception('Funzione non riconosciuta: ' . $functionCall);
    }

    // Output del risultato
    echo "<h2>Risultato per: {$functionCall}</h2>";
    if (!empty($_GET)) {
        echo "<h3>Parametri utilizzati:</h3>";
        echo "<pre>";
        print_r($_GET);
        echo "</pre>";
    }

    echo "<h3>Risposta:</h3>";
    echo "<pre>";
    if (is_array($result) || is_object($result)) {
        print_r($result);
    } else {
        var_dump($result);
    }
    echo "</pre>";

} catch (Exception $e) {

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

}
?>