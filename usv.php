<?php

$value = [];
exec('upsc ups@192.168.0.157', $value);
$ups = implode("\n", $value);

date_default_timezone_set('Europe/Berlin');

preg_match('%battery.runtime: (.*)%', $ups, $runtime);
preg_match('%battery.charge: (.*)%', $ups, $charge);
preg_match('%output.voltage: (.*)%', $ups, $voltageOut);
preg_match('%input.voltage: (.*)%', $ups, $voltageIn);
preg_match('%ups.load: (.*)%', $ups, $load);
preg_match('%ups.status: (.*)%', $ups, $status);
preg_match('%ups.test.result: (.*)%', $ups, $testResult);

$runtime = [
'minutes' => floor(($runtime[1] / 60) % 60),
'seconds' => $runtime[1] % 60,
];


?>
<html lang="de">
<head><title>USV Status</title></head>

<body style="background-color: darkgray">
<h1>Status:
    <?php
    switch ($status[1]) {
        case 'OL':
            echo '<span style="color:green">Online</span>';
            break;
        case 'OB':
            echo '<span style="color:red">Batterie</span>';
            break;
        case 'OL CHRG':
            echo '<span style="color:yellow">Lädt auf ' . $charge[1] . '%</span>';
            break;
        case 'CHRG':
            echo 'Lädt auf';
            break;
        case 'DISCHRG':
            echo 'Entlädt sich';
            break;
        case 'LB':
            echo '<span style="color:red">Batterie fast leer</span>';
            break;
        case 'RB':
            echo '<span style="color:red">Batterie ersetzen</span>';
            break;
        case 'OVER':
            echo '<span style="color:red">Last zu groß</span>';
            break;
        default:
            echo $status[1];
   }
    ?>
</h1>
<ul>
    <li>Runtime: <?php printf('%d:%d',$runtime['minutes'], $runtime['seconds']); ?> min</li>
    <li>Battery Charge: <?= $charge[1] ?> %</li>
    <li>Output Voltage: <?= $voltageOut[1] ?> V</li>
    <li>Input Voltage: <?= $voltageIn[1] ?> V</li>
    <li>Load: <?= $load[1] ?> %</li>
    <li>Test Result: <?= $testResult[1] ?></li>
</ul>

</body>
</html>

