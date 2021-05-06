<?php
// ONLY FOR INTERNAL USE
require_once('vendor/autoload.php');
require_once('config.php');

ini_set('display_errors', 1);

$itemCount = count($_POST);
if ($itemCount === 0) {
    return;
}

$data = [];
for ($i = 0; $i < $itemCount; $i++) {
    $data[] = json_decode($_POST[$i], true);
}

$mail = new Nette\Mail\Message;
$mail->setFrom($mailFrom)
    ->addTo($mailTo)
    ->setSubject('Protect Alert')
;

$body = $itemCount . ' Events detected<br/>';

$separator = '';
foreach ($data as $event) {
    $body .= $separator;
    $body .= $event['type'] . ' detected on Camera ' . $event['camera'] . '<br/>';
    $body .= 'Video: <a href="http://' . $_SERVER['HTTP_HOST'] . '/' . $event['videoUrl'] . '">Video</a>';
    $mail->addAttachment($event['thumbnailUrl']);
    $mail->addAttachment($event['heatmapImage']);

    $separator = '<br/>';
}

$mail->setHtmlBody($body);

$mailer = new Nette\Mail\SmtpMailer([
    'host' => $mailHost,
    'username' => $mailUsername,
    'password' => $mailPassword,
    'secure' => 'tls',
    'port' => 587
]);

$mailer->send($mail);