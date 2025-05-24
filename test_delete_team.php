<?php
require_once 'config.php';
$pdo = getConnection();
$id = '550e8400-e29b-41d4-a716-446655440001';
$stmt = $pdo->prepare('DELETE FROM teams WHERE id = ?');
$stmt->execute([$id]);
if ($stmt->rowCount() > 0) {
    echo "Equipo eliminado correctamente.";
} else {
    echo "No se pudo eliminar el equipo o no existe.";
}
?>
