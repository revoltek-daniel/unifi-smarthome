<?php
require_once('vendor/autoload.php');

require_once('config.php');

ini_set('display_errors', 1);

$protectClient = new \UniFi_API\ProtectClient($userName, $password, $host);
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
            $thumbnailName = $event->id . '.jpg';
            $thumbnailDlPath = $path . $thumbnailName;
            $hasImage = $protectClient->downloadEventThumbnail($thumbnailDlPath, $event->id);

            $videoName = $event->id . '.mp4';
            $dlPath = $path . $videoName;
            $hasVideo = $protectClient->downloadVideo($dlPath, $event->camera, $event->start, $event->end);

            $heatmapName = $event->id . '-heat.png';
            $dlPath = $path . $heatmapName;
            $hasHeatmapImage = $protectClient->downloadHeatmapImage($dlPath, $event->id);

            if ($hasImage && $hasHeatmapImage) {
                if (mergeHeatmapImage($thumbnailDlPath, $dlPath) === false) {
                    $heatmapName = '../no-image.jpg';
                }
            } else {
                $heatmapName = '../no-image.jpg';
                file_put_contents('log.txt', $event->id . ' no image loaded', FILE_APPEND);
            }

            // remove microseconds from timestamp
            $start = substr($event->start, 0, 10);
            $end = substr($event->end, 0, 10);

            $messages[$start] = [
                'type' => $event->type,
                'detectTypes' => $event->smartDetectTypes,
                'start' => date('c', $start),
                'end' => date('c', $end),
                'length' => $end - $start,
                'camera' => $cameras[$event->camera]->name,
                'cameraId' => $event->camera,
                'thumbnailUrl' => 'eventData/' . $thumbnailName,
                'heatmapImage' => 'eventData/'. $heatmapName,
                'videoUrl' => 'eventData/' . $videoName,
            ];
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

function mergeHeatmapImage(string $thumbnail, string $heatmap): bool
{
    $heatmapPng = imagecreatefrompng($heatmap);
    if ($heatmapPng === false) {
        return false;
    }

    $dest1 = imagecreatefromjpeg($thumbnail);

    [$newWidth, $newHeight] = getimagesize($thumbnail);
    [$width, $height] = getimagesize($heatmap);
    $heatmapResize = imagecreatetruecolor($newWidth, $newHeight);

    imagecolortransparent($heatmapResize, imagecolorallocatealpha($heatmapResize, 0, 0, 0, 127));
    imagealphablending($heatmapResize, false);
    imagesavealpha($heatmapResize, true);

    imagecopyresampled($heatmapResize, $heatmapPng, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    [$src_w, $src_h] = getimagesize($thumbnail);

    imagecopymergeAlpha($dest1, $heatmapResize, 0, 0, 0, 0, $src_w, $src_h, 100);

    imagepng($dest1, $heatmap);

    return true;
}

function imagecopymergeAlpha($dst_im, $src_im, int $dst_x, int $dst_y, $src_x, $src_y, int $src_w, int $src_h, int $pct): void
{
    $cut = imagecreatetruecolor($src_w, $src_h);
    imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
    imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
    imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct);
}