<?php
// views/layouts/public_layout.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo $title ?? 'EcoReciclaje'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="/reciclaje/img/logo.png?v=4" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/reciclaje/assets/css/style.css">
    <link rel="stylesheet" href="/reciclaje/assets/css/system-modal.css">
    <link rel="stylesheet" href="/reciclaje/assets/css/table-pagination.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; margin: 0; padding: 0; }
        .card, .btn, .badge, .form-control, .form-select, .alert, .rounded,
        .rounded-1, .rounded-2, .rounded-3, .rounded-4, .rounded-pill,
        input, select, textarea, .modal, .toast { border-radius: 0 !important; }
        .container *, .navbar *, .footer * { border-radius: 0 !important; }
        .card { border: 1px solid #d9e1e7; box-shadow: 0 4px 14px rgba(23,33,43,.05); }
        .btn { font-weight: 600; }

        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-wrap table { min-width: 400px; }

        img { max-width: 100%; height: auto; }

        @media (max-width: 768px) {
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
                font-size: 12px !important;
            }
            .container { padding-left: 10px; padding-right: 10px; }
            h1 { font-size: 18px !important; }
            h2 { font-size: 16px !important; }
            h3 { font-size: 14px !important; }
            .card { padding: 12px !important; box-shadow: none !important; }
            .btn, .form-control, .form-select { font-size: 12px !important; min-height: 38px !important; }
            .table-wrap table { min-width: 420px !important; }
        }

        /* Paleta unificada: ajustar colores semanticos de Bootstrap */
        .text-success { color: #3E5C76 !important; }
        .text-primary { color: #3E5C76 !important; }
        .btn-primary, .btn-success { background-color: #176B87 !important; border-color: #176B87 !important; }
        .btn-primary:hover, .btn-success:hover { background-color: #0E4F68 !important; border-color: #0E4F68 !important; }
        .btn-outline-primary, .btn-outline-success { color: #176B87 !important; border-color: #176B87 !important; }
        .alert-success { background: #EEF2F6 !important; color: #33485C !important; border-color: #D6E1EA !important; }
        .alert-info { background: #EEF2F6 !important; color: #33485C !important; border-color: #E4ECF3 !important; }
        .alert-warning { background: #F7F4EE !important; color: #6E5F45 !important; border-color: #E8E0CF !important; }
        .alert-danger { background: #F7F2F2 !important; color: #7A4F4F !important; border-color: #E8D8D8 !important; }
        .bg-success { background-color: #3E5C76 !important; }
        .border-success { border-color: #3E5C76 !important; }

        <?php echo $extra_css ?? ''; ?>
    </style>
    <?php echo $extra_head ?? ''; ?>
</head>
<body>

    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <?php echo $content; ?>

    <?php include __DIR__ . '/../components/footer_public.php'; ?>

    <nav class="public-tabbar" id="publicTabbar" aria-label="Navegación principal">
      <a class="public-tab" href="/reciclaje/index.php" data-page="inicio" aria-label="Inicio">
        <span class="ptab-icon">🏠</span><span class="ptab-label">Inicio</span>
      </a>
      <a class="public-tab" href="/reciclaje/views/public/servicios.php" data-page="servicios" aria-label="Servicios">
        <span class="ptab-icon">🧾</span><span class="ptab-label">Servicios</span>
      </a>
      <a class="public-tab" href="/reciclaje/views/public/cotizacion.php" data-page="cotizacion" aria-label="Cotización">
        <span class="ptab-icon">💰</span><span class="ptab-label">Cotizar</span>
      </a>
      <a class="public-tab" href="/reciclaje/views/public/nosotros.php" data-page="nosotros" aria-label="Nosotros">
        <span class="ptab-icon">👥</span><span class="ptab-label">Nosotros</span>
      </a>
      <a class="public-tab" href="/reciclaje/views/public/login.php" data-page="clientes" aria-label="Área de Clientes">
        <span class="ptab-icon">🔒</span><span class="ptab-label">Clientes</span>
      </a>
    </nav>

    <style>
    .public-tabbar { display: none; }

    /* Ajustes finales móvil: van al final para ganar a los estilos inline de cada vista. */
    @media (max-width: 768px) {
      html, body { overflow-x: hidden !important; }
      body {
        font-size: 11px !important;
        padding-bottom: calc(74px + env(safe-area-inset-bottom)) !important;
      }
      .cabecera { padding: 8px 10px !important; gap: 6px !important; justify-content: center !important; }
      .cabecera .titulo { font-size: 16px !important; gap: 6px !important; }
      .cabecera .titulo img { width: 26px !important; height: 26px !important; }
      .cabecera .titulo span { font-size: 18px !important; }
      .cabecera .menu { display: none !important; }

      .public-tabbar {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        position: fixed;
        left: 0; right: 0; bottom: 0;
        height: calc(62px + env(safe-area-inset-bottom));
        padding-bottom: env(safe-area-inset-bottom);
        background: rgba(255, 255, 255, .97);
        border-top: 1px solid #e5e7eb;
        box-shadow: 0 -3px 14px rgba(15, 42, 58, .08);
        z-index: 1040;
      }
      .public-tab {
        position: relative;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        gap: 3px;
        color: #66717b;
        text-decoration: none;
        font-size: 10px !important;
        font-weight: 600;
      }
      .ptab-icon {
        width: 40px; height: 27px;
        display: flex; align-items: center; justify-content: center;
        font-size: 19px; line-height: 1;
        border-radius: 9px;
        transition: background .2s, color .2s;
      }
      .ptab-icon .sys-icon { width: 20px !important; height: 20px !important; }
      .ptab-label { line-height: 1.1; white-space: nowrap; }
      .public-tab.active { color: #176b87 !important; }
      .public-tab.active .ptab-icon { background: #e7f1f4; color: #176b87; }
      .public-tab.active::before {
        content: '';
        position: absolute; top: 0; left: 50%;
        transform: translateX(-50%);
        width: 22px; height: 3px;
        border-radius: 0 0 3px 3px;
        background: #176b87;
      }
      .container { padding-left: 10px !important; padding-right: 10px !important; }
      h1 { font-size: 20px !important; line-height: 1.2 !important; }
      h2 { font-size: 16px !important; line-height: 1.25 !important; }
      h3, h4 { font-size: 13px !important; line-height: 1.3 !important; }
      p, li, label, td, th { font-size: 11px !important; }
      .lead { font-size: 12px !important; }
      .btn, .btn-custom { padding: 7px 12px !important; font-size: 11px !important; min-height: 34px !important; }
      .card { padding: 12px !important; box-shadow: none !important; }
      .card h4 { font-size: 13px !important; }
      .hero-section, .features-section, .steps-section,
      .why-choose-us, .testimonials-section, .stats-section { padding: 30px 0 !important; }
      .hero-section h1 { font-size: 20px !important; }
      .step-circle { width: 52px !important; height: 52px !important; font-size: 16px !important; margin-bottom: 10px !important; }
      .process-grid { grid-template-columns: 1fr !important; gap: 12px !important; }
      .process-step i, .value-card i { font-size: 20px !important; margin-bottom: 8px !important; }
      .table-wrap { overflow-x: auto !important; max-width: 100% !important; }
      .compare-table { table-layout: fixed !important; width: 100% !important; font-size: 10px !important; word-break: break-word !important; }
      .compare-table th, .compare-table td { padding: 6px 4px !important; font-size: 10px !important; }
      .values-grid { display: grid !important; grid-template-columns: 1fr !important; gap: 12px !important; margin: 0 0 24px !important; }
      .value-card { min-width: 0 !important; padding: 12px !important; }
      .footer { padding: 28px 0 !important; }
      .footer h5 { font-size: 13px !important; }
      .footer h6 { font-size: 12px !important; }
      .footer p, .footer li, .footer small, .footer .small { font-size: 11px !important; }
      .login-row { width: 100% !important; margin: 0 !important; padding: 8px !important; }
      .login-left { padding: 16px 14px !important; }
      .login-brand-icon { width: 44px !important; height: 44px !important; }
      .login-brand-text { font-size: 26px !important; }
      .login-subtitle { font-size: 11px !important; margin-bottom: 14px !important; }
      .login-form { gap: 12px !important; }
      .login-input-group label { font-size: 11px !important; margin-bottom: 5px !important; }
      .login-form input { padding: 10px 10px 10px 36px !important; min-height: 44px !important; }
      .login-btn { padding: 11px !important; font-size: 14px !important; }
    }
    @media (max-width: 480px) {
      body { font-size: 10.5px !important; }
      .cabecera .titulo span { font-size: 16px !important; }
      .cabecera .menu a { font-size: 10px !important; }
      .public-tab { font-size: 9.5px !important; }
      .ptab-icon { width: 36px !important; }
      h1 { font-size: 18px !important; }
      .hero-section h1 { font-size: 18px !important; }
      .card { padding: 10px !important; }
      .btn, .btn-custom { font-size: 10.5px !important; }
    }
    </style>

    <script src="/reciclaje/assets/js/system-icons.js"></script>
    <script>
    // Marca la pestaña activa de la barra inferior pública.
    (function () {
      var path = window.location.pathname.toLowerCase();
      var active = 'inicio';
      if (/servicios\.php$/.test(path)) active = 'servicios';
      else if (/cotizacion\.php$/.test(path)) active = 'cotizacion';
      else if (/nosotros\.php$/.test(path)) active = 'nosotros';
      else if (/login\.php$/.test(path)) active = 'clientes';
      else if (/index\.php$/.test(path) || /inicio\.php$/.test(path) || /\/$/.test(path)) active = 'inicio';

      Array.prototype.forEach.call(document.querySelectorAll('.public-tab'), function (tab) {
        var isActive = tab.getAttribute('data-page') === active;
        tab.classList.toggle('active', isActive);
        if (isActive) tab.setAttribute('aria-current', 'page');
        else tab.removeAttribute('aria-current');
      });
    })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Bridge Multi-Sesión (Público)
    (function() {
        if (!sessionStorage.getItem('eco_sid')) {
            sessionStorage.setItem('eco_sid', 'ts' + Date.now() + Math.floor(Math.random() * 1000));
        }
        const sid = sessionStorage.getItem('eco_sid');

        // Inyectar en todos los formularios al enviar
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (!form.querySelector('input[name="sid"]')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'sid';
                input.value = sid;
                form.appendChild(input);
            }
        });

        // Forzar SID en la URL si falta (para que el controlador lo reciba vía GET si es necesario)
        const urlParams = new URLSearchParams(window.location.search);
        if (!urlParams.has('sid')) {
            urlParams.set('sid', sid);
            window.location.search = urlParams.toString();
        }
    })();
    </script>
    <script src="/reciclaje/assets/js/form-validation.js"></script>
    <script src="/reciclaje/assets/js/system-modal.js"></script>
    <script src="/reciclaje/assets/js/table-pagination.js"></script>
    <?php echo $extra_js ?? ''; ?>
</body>
</html>
