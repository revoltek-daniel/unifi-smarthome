<?php

function handleEvent($event, \UniFi_API\ProtectClient $protectClient, $cameras, $path): array
{
    $thumbnailName = $event->id . '.jpg';
    $thumbnailDlPath = $path . $thumbnailName;
    if (\file_exists($thumbnailDlPath) === false || \filesize($thumbnailDlPath) === 0) {
        $hasImage = $protectClient->downloadEventThumbnail($thumbnailDlPath, $event->id);
    } else {
        $hasImage = true;
    }

    $videoName = $event->id . '.mp4';
    $dlPath = $path . $videoName;
    if (\file_exists($dlPath) === false || \filesize($dlPath) === 0) {
        $hasVideo = $protectClient->downloadVideo($dlPath, $event->camera, $event->start, $event->end);

        if (\filesize($dlPath) === 0) {
            file_put_contents('log.txt', $event->id . ' video empty, try again start:' . $event->start . ' - end:' . $event->end. "\r\n", FILE_APPEND);
            $hasVideo = $protectClient->downloadVideo($dlPath, $event->camera, $event->start, $event->end);

            if (\filesize($dlPath) === 0) {
                file_put_contents('log.txt', $event->id . " video empty again\r\n", FILE_APPEND);
                return [];
            }
        }
    } else {
        $hasVideo = true;
    }

    $heatmapName = $event->id . '-heat.png';
    $dlPath = $path . $heatmapName;
    if (\file_exists($dlPath) === false || \filesize($dlPath) === 0) {
        $hasHeatmapImage = $protectClient->downloadHeatmapImage($dlPath, $event->id);
    } else {
        $hasHeatmapImage = true;
    }

    if ($hasImage && $hasHeatmapImage) {
        if (mergeHeatmapImage($thumbnailDlPath, $dlPath) === false) {
            $heatmapName = '../no-image.jpg';
        }
    } else {
        $heatmapName = '../no-image.jpg';
        // file_put_contents('log.txt', $event->id . ' no image loaded', FILE_APPEND);
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

    return $messages;
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