<?php
// views/layouts/public_layout.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'EcoCusco'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="/reciclaje/assets/img/icono.png" type="image/png">
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
            .container { padding-left: 15px; padding-right: 15px; }
            h1 { font-size: 22px !important; }
            h2 { font-size: 18px !important; }
        }

        @media (max-width: 768px) {
            .container { padding-left: 15px; padding-right: 15px; }
            h1 { font-size: 22px !important; }
            h2 { font-size: 18px !important; }
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
