<?php
session_start();

// OJO: procesar_login.php está en /views, includes en /includes
// Por eso usamos ../
require_once __DIR__ . '/../includes/config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo   = isset($_POST['correo']) ? trim($_POST['correo']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Validar que no estén vacíos
    if ($correo === '' || $password === '') {
        header("Location: ../index.php?error=Todos+los+campos+son+obligatorios");
        exit;
    }

    // Buscar al usuario
    $stmt = $conn->prepare("SELECT id, nombre, contraseña, rol, activo FROM usuarios WHERE correo = ?");
    if (!$stmt) {
        // Error al preparar la consulta
        header("Location: ../index.php?error=Error+en+el+servidor");
        exit;
    }

    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $nombre, $hash, $rol, $activo);
        $stmt->fetch();

        // Validar si el usuario está activo
        if (!$activo) {
            header("Location: ../index.php?error=Usuario+inactivo");
            exit;
        }

        // Verificar contraseña
        if (password_verify($password, $hash)) {
            // Éxito: guardar sesión
            $_SESSION['usuario_id'] = $id;
            $_SESSION['nombre']     = $nombre;
            $_SESSION['rol']        = $rol;

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
                    header("Location: ../index.php?error=Rol+no+reconocido");
                    break;
            }
            exit;
        } else {
            header("Location: ../index.php?error=Contraseña+incorrecta");
            exit;
        }
    } else {
        header("Location: ../index.php?error=Usuario+no+encontrado");
        exit;
    }
} else {
    header("Location: ../index.php");
    exit;
}
