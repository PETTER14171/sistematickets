<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}

// Filtros recibidos por GET
$filtros = [
    'nombre' => $_GET['nombre'] ?? '',
    'correo' => $_GET['correo'] ?? '',
    'rol' => $_GET['rol'] ?? '',
    'campana' => $_GET['campana'] ?? '',
    'puesto' => $_GET['puesto'] ?? '',
    'estacion' => $_GET['estacion'] ?? '',
    'estado' => $_GET['estado'] ?? '',
    'creado_en' => $_GET['creado_en'] ?? ''
];

// Consulta con filtros
$where = [];
$params = [];
$types = '';

if ($filtros['nombre']) {
    $where[] = "nombre LIKE ?";
    $params[] = "%" . $filtros['nombre'] . "%";
    $types .= 's';
}
if ($filtros['correo']) {
    $where[] = "correo LIKE ?";
    $params[] = "%" . $filtros['correo'] . "%";
    $types .= 's';
}
if ($filtros['rol']) {
    $where[] = "rol = ?";
    $params[] = $filtros['rol'];
    $types .= 's';
}
if ($filtros['campana']) {
    $where[] = "campana LIKE ?";
        $params[] = "%" . $filtros['campana'] . "%";
    $types .= 's';
}
if ($filtros['puesto']) {
    $where[] = "puesto LIKE ?";
        $params[] = "%" . $filtros['puesto'] . "%";
    $types .= 's';
}
if ($filtros['estacion']) {
    $where[] = "estacion LIKE ?";
        $params[] = "%" . $filtros['estacion'] . "%";
    $types .= 's';
}

if ($filtros['estado'] !== '') {
    $where[] = "activo = ?";
    $params[] = $filtros['estado'] === '1' ? 1 : 0;
    $types .= 'i';
}

if ($filtros['creado_en']) {
    $where[] = "creado_en LIKE ?";
    $params[] = "%" . $filtros['creado_en'] . "%";
    $types .= 's';
}

$query = "SELECT * FROM usuarios";
if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}
$query .= " ORDER BY creado_en DESC";

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$resultado = $stmt->get_result();
$usuarios = $resultado->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 25px;
        }

        h2 {
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th, table td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }

        table th {
            background-color: #f2f2f2;
        }

        .acciones a {
            display: flex;
            flex-direction: row;
            margin-right: 8px;
            margin-bottom: 4px;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 4px;
            color: white;
        }

        .editar {
            background-color: #007bff;
        }

        .eliminar {
            background-color: #dc3545;
        }

        .toggle {
            background-color: #17a2b8;
        }

        .activo {
            color: green;
            font-weight: bold;
        }

        .inactivo {
            color: red;
            font-weight: bold;
        }

        .volver {
            background: #343a40;
            color: #fff;
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 4px;
        }

        .volver:hover {
            background-color: #000;
        }
    </style>
</head>
<body>

<h2>👥 Gestión de Usuarios <a href="/panel_tecnico.php" class="volver">Volver al Panel</a></h2>

<form method="GET">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th><input type="text" name="nombre" placeholder="Nombre" value="<?= htmlspecialchars($filtros['nombre']) ?>"></th>
                <th><input type="text" name="correo" placeholder="Correo" value="<?= htmlspecialchars($filtros['correo']) ?>"></th>
                <th>
                    <select name="rol">
                        <option value="">Rol</option>
                        <option value="agente" <?= $filtros['rol'] === 'agente' ? 'selected' : '' ?>>Agente</option>
                        <option value="tecnico" <?= $filtros['rol'] === 'tecnico' ? 'selected' : '' ?>>Técnico</option>
                        <option value="admin" <?= $filtros['rol'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </th>
                <th><input type="text" name="campana" placeholder="Campaña" value="<?= htmlspecialchars($filtros['campana']) ?>"></th>
                <th><input type="text" name="puesto" placeholder="Puesto" value="<?= htmlspecialchars($filtros['puesto']) ?>"></th>
                <th><input type="text" name="estacion" placeholder="Estación" value="<?= htmlspecialchars($filtros['estacion']) ?>"></th>
                <th>
                    <select name="estado">
                        <option value="">Estado</option>
                        <option value="1" <?= $filtros['estado'] === '1' ? 'selected' : '' ?>>Activo</option>
                        <option value="0" <?= $filtros['estado'] === '0' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </th>
                <th><input type="text" name="creado_en" placeholder="Fecha de creación" value="<?= htmlspecialchars($filtros['creado_en']) ?>"></th>
                <th>
                    <button type="submit">🔍 Filtrar</button>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php if ($usuarios): ?>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['nombre']) ?></td>
                        <td><?= htmlspecialchars($u['correo']) ?></td>
                        <td><?= ucfirst($u['rol']) ?></td>
                        <td><?= htmlspecialchars($u['campana']) ?></td>
                        <td><?= htmlspecialchars($u['puesto']) ?></td>
                        <td><?= htmlspecialchars($u['estacion']) ?></td>
                        <td class="<?= $u['activo'] ? 'activo' : 'inactivo' ?>">
                            <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                        </td>
                        <td><?= $u['creado_en'] ?></td>
                        <td class="acciones">
                            <a href="editar_usuario.php?id=<?= $u['id'] ?>" class="editar">Editar</a>
                            <a href="eliminar_usuario.php?id=<?= $u['id'] ?>" class="eliminar" onclick="return confirm('¿Eliminar este usuario?')">Eliminar</a>
                            <a href="toggle_usuario.php?id=<?= $u['id'] ?>" class="toggle">
                                <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="10">No se encontraron usuarios.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</form>


</body>
</html>
