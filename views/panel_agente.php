<?php
    include __DIR__ . '/../includes/config/verificar_sesion.php';

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
    <h2>👋 Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?> (Agente)</h2>

    <article class="tickets-body">

        <div class="tickets-shell">
            <div class="ticket-app" role="main">

                <!-- Sidebar: My Tickets -->
                <aside class="ticket-sidebar" aria-label="Lista de tickets">
                    <div class="ticket-sidebar__header">
                        <h2 class="ticket-sidebar__title">Mis Tickets</h2>
                    </div>

                    <ul class="ticket-list">
                        <?php foreach ($tickets as $ticket): ?>
                            <li class="ticket-item" tabindex="0" aria-label="Ticket <?= htmlspecialchars($ticket['status']) ?>">
                                <div class="ticket-item__status">
                                    <span class="status-dot status-dot--<?= htmlspecialchars($ticket['status_key']) ?>"></span>
                                    <span class="ticket-item__status-text">
                                        <?= htmlspecialchars($ticket['status']) ?>
                                    </span>
                                </div>
                                <div class="ticket-item__body">
                                    <p class="ticket-item__title">
                                        <?= htmlspecialchars($ticket['title']) ?>
                                    </p>
                                    <p class="ticket-item__preview">
                                        <?= htmlspecialchars($ticket['preview']) ?>
                                    </p>
                                </div>
                                <div class="ticket-item__meta">
                                    <span class="ticket-item__date">
                                        <?= htmlspecialchars($ticket['date']) ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </aside>

                <!-- Main: Create New Ticket -->
                <section class="ticket-main">
                    <header class="ticket-main__header">
                        <div class="ticket-main__title-group">
                            <h1 class="ticket-main__title">Crea Nuevo Ticket </h1>
                            <p class="ticket-main__subtitle">
                                Describe tu problema de la forma más clara posible para ayudarte más rápido.
                            </p>
                        </div>

                        <nav class="ticket-main__nav" aria-label="Navegación de panel">
                            <button class="nav-pill nav-pill--active" type="button">
                                <span>Tickets</span>
                            </button>
                            <button class="nav-pill" type="button">
                                <span>Libreria</span>
                            </button>
                        </nav>
                    </header>

                    <section class="ticket-main__content">
                        <form class="ticket-form" action="procesar_ticket.php" method="POST" enctype="multipart/form-data" novalidate>
                            <!-- Category -->
                            <div class="form-field">
                                <label class="form-label" for="categoria">Category</label>
                                <div class="form-control-wrapper">
                                    <select class="form-control" name="categoria" id="categoria" required>
                                        <option value="" disabled selected>Select</option>
                                        <option value="hardware">Hardware</option>
                                        <option value="software">Software</option>
                                        <option value="red">Network</option>
                                        <option value="acceso">Access / Accounts</option>
                                        <option value="otro">Other</option>
                                    </select>
                                    <span class="form-control__chevron">▾</span>
                                </div>
                                <p class="form-help">Selecciona la categoría que mejor describa el problema.</p>
                            </div>

                            <!-- Subject -->
                            <div class="form-field">
                                <label class="form-label" for="asunto">Subject</label>
                                <input
                                    class="form-control"
                                    type="text"
                                    id="asunto"
                                    name="asunto"
                                    placeholder="Ej. No puedo acceder a mi correo corporativo"
                                    required
                                >
                            </div>

                            <!-- Description -->
                            <div class="form-field">
                                <label class="form-label" for="descripcion">Description</label>
                                <textarea
                                    class="form-control form-control--textarea"
                                    id="descripcion"
                                    name="descripcion"
                                    rows="5"
                                    placeholder="Describe qué ocurre, cuándo empezó, qué has intentado y mensajes de error si los hay."
                                    required
                                ></textarea>
                                <p class="form-help">
                                    Mientras más contexto proporciones, más rápido podremos ayudarte.
                                </p>
                            </div>

                            <!-- File Upload -->
                            <div class="form-field">
                                <span class="form-label">Attachment (optional)</span>

                                <label class="file-input" for="archivo">
                                    <span class="file-input__icon">📎</span>
                                    <span class="file-input__text">Choose a file</span>
                                    <input
                                        type="file"
                                        id="archivo"
                                        name="archivo"
                                        class="file-input__native"
                                    >
                                </label>

                                <p class="form-help">
                                    Puedes adjuntar capturas de pantalla, documentos o logs relevantes.
                                </p>
                            </div>

                            <!-- Submit -->
                            <div class="form-actions">
                                <button class="btn-primary" type="submit">
                                    Submit Ticket
                                </button>
                            </div>
                        </form>
                    </section>
                </section>
            </div>
        </div>
    </article>
</main>

<?php 
incluirTemplate('footer');
?>
