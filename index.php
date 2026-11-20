<?php
include("conexion-waffle.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waffle - Información del Proyecto</title>
    <link rel="icon" href="images/Icon.png">
    <link rel="stylesheet" href="style.css">
    <script src="script-waffle.js"></script>
</head>
<body>

    <header>
    <div class="navbar">
        <a href="index.php">
            <img src="images/logo-original.png" alt="Waffle Logo" class="logo-img" id="logo-header">
        </a>
        <nav class="nav-center">
            <a href="reproductor.html" class="btn-nav">Reproductor de juegos</a>
            <a href="repositorio.html" class="btn-nav">Repositorio de juegos</a>
            <a href="comunidad.html" class="btn-nav">Comunidad y Publicaciones</a>
            <a href="index.php" class="btn-nav">Información sobre el proyecto</a>
            <a href="tienda.html" class="btn-nav">Tienda</a>
        </nav>

        <div class="nav-right">
            <button class="btn-inter" title="Personalización">
                <a href="aspecto.html">
                    <img src="images/aspect-icon.png" alt="Personalización" class="icon-nav">
                </a>
            </button>
    
            <button class="btn-inter" title="Ajustes">
                <a href="ajustes.html">
                    <img src="images/ajustes-icon.png" alt="Ajustes" class="icon-nav">
                </a>
            </button>

            <div class="puntos-usuario" title="Tus puntos disponibles">
                <span>🧇 <strong id="puntos-header">500</strong> pts</span>
            </div>
    
            <div class="perfil-usuario">
                <img src="images/guest.png" alt="Avatar" class="avatar-img">
                <span class="nombre-usuario" id="nombre-header">guest666</span>
            </div>
        </div>
    </div>
</header>

    <main class="main-container">
        
        <h1 class="main-title">WAFFLE</h1>

        <section class="content-card">
            <h2 class="card-title">Sobre Waffle</h2>
            <p>Waffle se creo para preservar la era de los juegos interactivos desarrollados en Adobe Flash Player (formatos .SWF). Nuestro objetivo es permitir a los usuarios un espacio moderno, seguro y organizado donde revivir la nostalgia y compartir opiniones.</p>
        </section>

        <section class="content-card">
            <h2 class="card-title">Misión y Objetivos</h2>

            <div class="feature-item">
                <h3 class="feature-subtitle">Preservación Web</h3>
                <p>Mantener viva la colección de juegos clásicos integrando emuladores en línea para reproducirlos sin necesidad de complementos inseguros.</p>
            </div>

            <div class="feature-item">
                <h3 class="feature-subtitle">Comunidad Global</h3>
                <p>Permitir a los jugadores poder comunicarse mediante publicaciones, sistemas de calificación y personalización de perfiles.</p>
            </div>
        </section>

        <div class="alert-card">
            <strong>ESTADO DEL PROYECTO:</strong> Plataforma en Desarrollo. Algunas funciones pueden no estar disponibles o presentar errores.
        </div>

    </main>
</body>
</html>