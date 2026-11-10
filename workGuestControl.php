<?php
require_once('vendor/autoload.php');

require_once('config.php');

$argumentMac = $_SERVER['argv'][1] ?? null;
$time = $_SERVER['argv'][2] ?? null;
$force = (int)($_SERVER['argv'][3] ?? 0);

$unifiClient = new \UniFi_API\Client($userName, $password, $host);
$unifiClient->setCookiePath(__DIR__ . '/cookie/cookie.txt');
$unifiClient->setKeepSession(true);
$unifiClient->login();

$authorizedMinutes = $time ?? 560;

$mac = $argumentMac ?? $guestMac;
$client = $unifiClient->list_clients($mac);

if ($force === 1  || ($client !== false && $client[0]->authorized === false)) {
    $unifiClient->authorize_guest($mac, $authorizedMinutes);
}