<?php

// 1) Config cookie sesión (ANTES de session_start)
$cookieLifetime = 60 * 60 * 24 * 7; // 7 días
$timeout        = 60 * 60 * 6;       // 6 horas por inactividad (ajusta)

session_set_cookie_params([
  'lifetime' => $cookieLifetime,
  'path'     => '/',
  'domain'   => '', // vacío = host actual (tickets.talk-hub.com)
  'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
  'httponly' => true,
  'samesite' => 'Lax'
]);

session_start();

// 2) Validación por inactividad (ya con sesión iniciada)
if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > $timeout) {
  session_unset();
  session_destroy();
  header("Location: /index.php?error=Sesión expirada por inactividad");
  exit;
}

// 3) Actualizar actividad
$_SESSION['last_activity'] = time();

// 4) Si no hay sesión iniciada
if (empty($_SESSION['usuario_id']) || empty($_SESSION['rol'])) {
  header("Location: /index.php");
  exit;
}
