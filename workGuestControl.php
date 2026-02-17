<?php
require_once('vendor/autoload.php');

require_once('config.php');

$argumentMac = $_SERVER['argv'][1] ?? null;
$time = $_SERVER['argv'][2] ?? null;

$unifiClient = new \UniFi_API\Client($userName, $password, $host);
$unifiClient->setCookiePath(__DIR__ . '/cookie/cookie.txt');
$unifiClient->setKeepSession(true);
$unifiClient->login();

$authorizedMinutes = $time ?? 560;

$mac = $argumentMac ?? $guestMac;
$client = $unifiClient->list_clients($mac);

var_dump($unifiClient->authorize_guest('06:40:8c:56:65:d6', 10*60));

if ($client !== false && $client[0]->authorized === false) {
   // $unifiClient->unauthorize_guest($mac);
    $unifiClient->authorize_guest($mac, $authorizedMinutes);
}