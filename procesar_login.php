<?php
session_start();
include __DIR__ . '/includes/config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo']);
    $password = $_POST['password'];

    // Validar que no estén vacíos
    if (empty($correo) || empty($password)) {
        header("Location: login.php?error=Todos los campos son obligatorios");
        exit;
    }

    // Buscar al usuario
    $stmt = $conn->prepare("SELECT id, nombre, contraseña, rol, activo FROM usuarios WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $nombre, $hash, $rol, $activo);
        $stmt->fetch();

        if (!$activo) {
            header("Location: login.php?error=Usuario inactivo");
            exit;
        }

        if (password_verify($password, $hash)) {
            // Éxito: guardar sesión
            $_SESSION['usuario_id'] = $id;
            $_SESSION['nombre'] = $nombre;
            $_SESSION['rol'] = $rol;

            // Redirigir al panel correspondiente
            switch ($rol) {
                case 'admin':
                    header("Location: panel_admin.php");
                    break;
                case 'tecnico':
                    header("Location: panel_tecnico.php");
                    break;
                case 'agente':
                    header("Location: panel_agente.php");
                    break;
                default:
                    header("Location: login.php?error=Rol no reconocido");
            }
            exit;
        } else {
            header("Location: login.php?error=Contraseña incorrecta");
            exit;
        }
    } else {
        header("Location: login.php?error=Usuario no encontrado");
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}
