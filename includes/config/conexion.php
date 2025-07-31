<?php
$host = "127.0.0.1";
$usuario = "root";
$clave = "admin";
$bd = "sistema_tickets";
$puerto = "3307";

$conn = new mysqli($host, $usuario, $clave, $bd, $puerto);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
?>
