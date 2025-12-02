<?php
    include __DIR__ . '/../includes/config/verificar_sesion.php';
    include __DIR__ . '/../includes/config/conexion.php';
    if ($_SESSION['rol'] !== 'agente') {
        header("Location: ../index.php?error=Acceso denegado");
        exit;
    }
?>

<?php 
    require_once __DIR__ . '/../includes/funciones.php';
    incluirTemplate('head', [
        'page_title' => 'Panel Agente',
        'page_desc'  => 'Panel para que el agente levante su ticket'
    ]);
    incluirTemplate('header');
?>

<main>
    <article class="tickets-body">

        <div class="tickets-shell">
            <div class="ticket-app" role="main">
                <!-- Sidebar: My Tickets -->
                    <?php include __DIR__ . '/mis_tickets.php';?>
                <!-- Main: Create New Ticket -->
                <section class="ticket-main">
                    <?php include __DIR__ . '/crear_ticket.php';?>
                </section>
            </div>
        </div>
    </article>
</main>

<?php 
    incluirTemplate('footer');
?>
