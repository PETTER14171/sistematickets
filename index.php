<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>TalkHub - Sistema de Tickets</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primario: #115bf0;
            --secundario: #eaf3ff;
            --texto: #1f1f1f;
            --boton: #4cd964;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: var(--secundario);
            color: var(--texto);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        header {
            background-color: var(--primario);
            color: white;
            padding: 20px;
            text-align: center;
        }

        main {
            flex: 1;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        p {
            font-size: 1.1rem;
            max-width: 600px;
            margin-bottom: 30px;
        }

        .btn-login {
            background-color: var(--boton);
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        .btn-login:hover {
            background-color: #267d46;
        }

        footer {
            text-align: center;
            padding: 15px;
            background-color: #ddd;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<header>
    <h1>TalkHub</h1>
    <p>Soluciones inteligentes para soporte técnico</p>
</header>

<main>
    <h2>Bienvenido al Sistema de Gestión de Tickets</h2>
    <p>Accede al portal de atención para agentes, técnicos y administradores. Gestiona reportes, consulta guías de solución o da seguimiento a tus incidencias con eficiencia y rapidez.</p>
    <a href="login.php" class="btn-login">Iniciar sesión</a>
</main>

<footer>
    &copy; <?= date('Y') ?> TalkHub. Todos los derechos reservados.
</footer>

</body>
</html>
