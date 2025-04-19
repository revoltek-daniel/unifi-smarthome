<?php
require_once('vendor/autoload.php');

require_once('config.php');
require_once('functions.php');

ini_set('display_errors', 1);

$protectClient = new \UniFi_API\ProtectClient($userName, $password, $host);
$protectClient->setCookiePath(__DIR__ . '/cookie/cookie.txt');
$protectClient->setKeepSession(true);
$protectClient->login();
//$startOffset = 2*3600;

$startOffset = 5*60;

//$data = $protectClient->getEvents(time() - $startOffset, time(), [\UniFi_API\ProtectClient::EVENT_TYPE_MOTION]);
$data = $protectClient->getDetections(time() - $startOffset, time());

$path = __DIR__ . '/eventData/';

if ((is_dir($path) === false) && !mkdir($path) && !is_dir($path)) {
    throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
}

if ($data) {
    $data = $data->events;

    /**
     * Get all cameras
     */
    $cameraApiData = $protectClient->getCameras();

    $cameras = [];
    foreach ($cameraApiData as $camera) {
        $cameras[$camera->id] = $camera;
    }
    rsort($data);

    $messages = [];
    foreach ($data as $event) {
        try {
            $messages += handleEvent($event, $protectClient, $cameras, $path);
        } catch (Exception $e) {
            file_put_contents('log.txt', $e->getMessage(), FILE_APPEND);
        }
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
        echo 'mqtt connection failed' . $e->getMessage();
    }
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

