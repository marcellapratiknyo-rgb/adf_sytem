<?php
$pdo = new PDO('mysql:host=localhost;dbname=adf_narayana_hotel', 'root', '');
$cols = $pdo->query('DESCRIBE room_types')->fetchAll();
foreach($cols as $c) echo $c['Field'] . "\n";
