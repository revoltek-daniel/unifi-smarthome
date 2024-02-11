<?php
require_once('vendor/autoload.php');

require_once('config.php');

$unifiClient = new \UniFi_API\Client($userName, $password, $host);
$unifiClient->setCookiePath(__DIR__ . '/cookie/cookie.txt');
$unifiClient->setKeepSession(true);
$unifiClient->login();

$authorizedMinutes = 560;

$client = $unifiClient->list_clients($guestMac);

if ($client !== false && $client[0]->authorized === false) {
    $unifiClient->authorize_guest($guestMac, $authorizedMinutes);
}