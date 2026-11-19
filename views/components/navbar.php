<style>
    /* Estilos exclusivos de la barra de navegación pública */
    .cabecera {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: #FFFFFF;
        padding: 10px 20px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        flex-wrap: wrap;
        gap: 10px;
    }

    .cabecera .titulo {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: bold;
        font-size: 24px;
        color: #3E5C76;
    }

    .cabecera .menu {
        display: flex;
        gap: 20px;
        align-items: center;
    }

    .cabecera .menu a {
        color: #666;
        text-decoration: none;
        font-size: 16px;
    }

    .cabecera .menu a:hover {
        color: #3E5C76;
    }

    .cabecera .menu a.registrarse,
    .cabecera .menu a.btn-cliente {
        background-color: #3E5C76;
        color: #FFFFFF;
        padding: 8px 16px;
        border-radius: 5px;
    }

    .cabecera .menu a.registrarse:hover,
    .cabecera .menu a.btn-cliente:hover {
        background-color: #33485C;
        color: #FFFFFF;
    }

    .cabecera .menu a.ingresar::before {
        content: "👤";
        margin-right: 5px;
    }

    @media (max-width: 700px) {
        .cabecera { justify-content: center; }
        .cabecera .menu { gap: 12px; font-size: 14px; }
    }
</style>
<div class="cabecera">
    <div class="titulo">
        <img src="/reciclaje/img/logo.png?v=4" alt="Logo EPSIC" style="width: 2.2rem; height: 2.2rem; object-fit: contain;">
        <span style="font-size: 26px; font-weight: 800; letter-spacing: .5px;">EPSIC</span>
    </div>
    <div class="menu">
        <a href="/reciclaje/index.php">Inicio</a>
        <a href="/reciclaje/views/public/servicios.php">Servicios</a>
        <a href="/reciclaje/views/public/cotizacion.php">Cotización</a>
        <a href="/reciclaje/views/public/nosotros.php">Nosotros</a>
    </div>
    <div class="menu">
        <a href="/reciclaje/views/public/login.php" class="btn-cliente">Área de Clientes</a>
    </div>
</div>
