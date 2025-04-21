<?php
require_once('vendor/autoload.php');

require_once('config.php');
require_once('functions.php');

ini_set('display_errors', 1);
$path = __DIR__ . '/eventData/';

$protectClient = new \UniFi_API\ProtectClient($userName, $password, $host);
$protectClient->setCookiePath(__DIR__ . '/cookie/cookie.txt');
$protectClient->setKeepSession(true);
$protectClient->login();

// $_POST did not work....
$data = file_get_contents('php://input');

if (empty($data)) {
    die('no parameters');
}

try {
    $cameraApiData = $protectClient->getCameras();

    $cameras = [];
    foreach ($cameraApiData as $camera) {
        $cameras[$camera->id] = $camera;
    }

    $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
    $eventPath = $data['alarm']['eventPath'];

    $eventId = str_replace('/protect/events/event/', '', $eventPath);

    $loops = 0;
    do {
        $event = $protectClient->getEvent($eventId);
        $loops++;
        sleep(10);
    } while ($event->end === null && $loops < 15);

    if ($event->end === null) {
        file_put_contents('log.txt', 'event not ended' . $eventId . "\r\n", FILE_APPEND);
        die();
    }

    $messages = [];
    $messages += handleEvent($event, $protectClient, $cameras, $path);
} catch (Throwable $e) {
    file_put_contents('log.txt', 'exception ' . $e->getFile() . ' ' .  $e->getLine() . ' ' . $e->getMessage() . "\r\n", FILE_APPEND);
}

try {
    $mqtt = new \PhpMqtt\Client\MqttClient($mqttHost);

    $mqtt->connect();

    try {
        $mqtt->publish('unifi/protect/event', json_encode($messages, JSON_THROW_ON_ERROR));
    } catch (JsonException $e) {
        echo 'json decode failed' . $e->getMessage();
    }

    $mqtt->disconnect();
} catch (\PhpMqtt\Client\Exceptions\ConfigurationInvalidException | \PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException | \PhpMqtt\Client\Exceptions\RepositoryException | \PhpMqtt\Client\Exceptions\DataTransferException | \PhpMqtt\Client\Exceptions\ProtocolNotSupportedException $e) {
    file_put_contents('log.txt', 'mqtt connection failed' . $e->getMessage() . "\r\n");
}

// cleanup
foreach (scandir($path) as $file) {
    if (in_array($file, [".", ".."])) {
        continue;
    }

    if (filemtime($path . $file) < time() - 24*3600) {
        unlink($path . $file);
    }
}