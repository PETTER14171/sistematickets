<?php  
    if(!isset($_SESSION)){
        session_start();    
    }

    $auth = $_SESSION['login'] ?? false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soporte TI</title>
    <link rel="stylesheet" href="/build/css/app.css">
</head>
<body>

    <header class="header <?php echo $inicio ? 'inicio' : '' ?>">
        <div class="contenedor contenido-header">
            <div class="barra">
                <a href="/">
                    <img src="/build/img/logo.svg" alt="Logotipo Bienes Raices">
                </a>
                <div class="mobile-menu">
                    <img src="/build/img/barras.svg" alt="navegacion">
                </div>
    
                <div class="derecha">
                    <img class="dark-mode-boton" src="/build/img/dark-mode.svg" alt="boton modo obscuro">
                        <nav class="navegacion">
                            <a href="nosotros.php">Nosotros</a>
                            <a href="anuncios.php">Anuncios</a>
                            <a href="blog.php">Blog</a>
                            <a href="contacto.php">Contacto</a>
                            <?php if($auth): ?>
                                <a href="../../cerrar-sesion.php">Cerrar Sesión</a>
                            <?php endif;?>
                        </nav>
                </div>
             </div> <!-- cierre de la clase barra -->
             <?php echo $inicio ? "<h1>Venta de Casa y Departamentos Exclusivos de Lujo</h1>" : '';?>
        </div>
    </header>