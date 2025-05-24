<?php
require_once 'config.php';
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT * FROM teams');
$stmt->execute();
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
foreach ($teams as $team) {
    echo "ID: {$team['id']} | Nombre: {$team['name']}\n";
}
echo "</pre>";
?>
