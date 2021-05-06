<?php
require_once('vendor/autoload.php');
require_once('config.php');

ini_set('display_errors', 1);

$cameraId = $_GET['id'] ?? $defaultCameraId;

$protectClient = new \UniFi_API\ProtectClient($userName, $password, $host);
$protectClient->login();

$file = tmpfile();
$path = stream_get_meta_data($file)['uri'];
$protectClient->downloadCurrentCameraSnapshot($path, $cameraId);
header('Content-type: image/jpeg');
readfile($path);

unlink($path);