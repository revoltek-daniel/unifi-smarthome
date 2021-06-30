<html>
<head><title>Stock</title></head>
<body>
    <form method="post">
        <label for="value">Kurs:</label> <input id="value" type="number" name="value" step="0.01"/><br/>
        <label for="count">Anzahl:</label> <input id="count" name="count" type="number"/><br>
        <input type="submit">
    </form>

<?php

if ($_POST['value']) {
    $fixCosts = 490;
    $value = $_POST['value'] * 100;
    $count = (int)$_POST['count'];
    $rawCosts = ($value * $count);
    $costs = $fixCosts + $rawCosts * 1.0025;

    echo 'Anzahl Aktien: ' . $count . '<br>';
    echo 'Preis Aktie: ' . number_format($value / 100, 2) . ' € <br>';
    echo 'Preis Aktien ohne Kosten: ' . number_format($rawCosts / 100, 2) . ' € <br>';
    echo 'Preis Einzelaktie mit Kosten: ' . number_format($costs / $count / 100, 2 ) . ' € <br/>';
    echo 'Kosten: ' . number_format(($costs - $rawCosts) / 100, 2) . ' € <br>';
    echo 'Gesamtpreis: ' . number_format($costs / 100, 2) . ' €';
}
?>
</body>
</html>