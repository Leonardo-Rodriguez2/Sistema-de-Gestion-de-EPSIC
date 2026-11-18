<?php
// views/layouts/dashboard_layout.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $title ?? 'Dashboard - EPSIC'; ?></title>
  <link rel="icon" href="/reciclaje/img/logo.png?v=4" type="image/png">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/reciclaje/assets/css/style.css">
  <link rel="stylesheet" href="/reciclaje/assets/css/system-modal.css">
  <link rel="stylesheet" href="/reciclaje/assets/css/table-pagination.css">
  <style>
    :root {
      --primary: #176b87;
      --secondary: #0e4f68;
      --accent: #d98b35;
      --bg: #f3f6f8;
      --text: #344454;
      --card-bg: #fff;
      --border: #d9e1e7;
    }
    * { box-sizing: border-box; }
    body { font-family: 'Poppins', sans-serif; background: var(--bg); margin: 0; display: flex; color: var(--text); font-size: 12px; }
    
    /* Layout */
    .main { flex-grow: 1; margin-left: 232px; padding: 30px 38px 48px; min-height: 100vh; box-sizing: border-box; width: 100%; }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; border-bottom: 1px solid #d9e1e7; padding-bottom: 20px; gap: 18px; }
    h1 { font-size: 21px; font-weight: 700; margin: 0; color: #111827; letter-spacing: -.02em; }
    .user-info { font-size: 12px; color: #6B7280; font-weight: 500; white-space: nowrap;}

    /* Hamburger Toggle */
    .menu-toggle {
      display: none;
      background: var(--primary);
      color: white;
      border: none;
      width: 40px;
      height: 40px;
      border-radius: 0;
      font-size: 20px;
      cursor: pointer;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: 0.2s;
      z-index: 1001;
      position: relative;
    }
    .menu-toggle:hover { background: var(--secondary); }
    .menu-toggle.active { background: #8A5A5A; }

    /* Sidebar Overlay */
    .sidebar-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.5);
      z-index: 998;
      opacity: 0;
      transition: opacity 0.3s;
    }
    .sidebar-overlay.show { opacity: 1; }

    /* Comunes */
    .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 0; padding: 24px; box-shadow: 0 4px 14px rgba(23,33,43,.04); }
    .badge { padding: 4px 10px; border-radius: 0; font-size: 10px; font-weight: 600; text-transform: uppercase; }
    .badge.admin { background: #F3F4F6; color: #374151; border: 1px solid #D1D5DB; }
    .badge.gestor { background: #F3F4F6; color: #374151; border: 1px solid #D1D5DB; }
    .badge.recolector { background: #F3F4F6; color: #374151; border: 1px solid #D1D5DB; }

    .alert { padding: 12px 16px; border-radius: 0; font-size: 13px; border: 1px solid #E5E7EB; border-left: 4px solid #6B7280; background: #F9FAFB; color: #374151; margin-bottom: 16px; }
    .alert-success { background: #F4F6F8; color: #33485C; border-color: #D6E1EA; border-left-color: #4C6E91; }
    .alert-warning { background: #F7F4EE; color: #6E5F45; border-color: #E8E0CF; border-left-color: #8A7A5A; }
    .alert-danger, .alert-error { background: #F7F2F2; color: #7A4F4F; border-color: #E8D8D8; border-left-color: #9A6A6A; }
    .alert-info { background: #EEF2F6; color: #33485C; border-color: #E4ECF3; border-left-color: #4C6E91; }
    
    .btn-primary { background: var(--primary); color: white; border: none; padding: 9px 16px; border-radius: 3px; font-weight: 600; font-size: 12px; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; }
    .btn-primary:hover { background: var(--secondary); }
    .btn-edit { background: #EEF2F6; color: #33485C; padding: 4px 10px; border-radius: 0; font-size: 11px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
    .btn-edit:hover { background: #E4ECF3; }

    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 0 0 8px; }
    .table-wrap table { min-width: 480px; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th { text-align: left; padding: 15px 16px; border-bottom: 1px solid var(--border); color: #6B7280; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; }
    td { padding: 15px 16px; border-bottom: 1px solid #F9FAFB; vertical-align: middle; }
    @media (max-width: 768px) { th { white-space: normal; } }

    /* Grid helper */
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
    .card a { transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
    .card a:hover { transform: translateY(-2px); }

    /* Separación consistente para que cada bloque de información sea legible. */
    .main > .card,
    .main > .premium-form,
    .main > .compact-form,
    .main > .form-container { margin-bottom: 24px; }
    .main > .card:last-child,
    .main > .premium-form:last-child,
    .main > .compact-form:last-child,
    .main > .form-container:last-child { margin-bottom: 0; }
    .card h2, .card h3, .card h4 { margin-top: 0; margin-bottom: 18px; }
    .card p { line-height: 1.65; }
    .page-intro { display:flex; align-items:flex-start; gap:14px; padding:18px 20px; margin-bottom:22px; background:#eef4f7; border:1px solid #d5e2e8; border-left:4px solid var(--primary); }
    .page-intro-icon { width:34px; height:34px; display:flex; align-items:center; justify-content:center; flex:0 0 34px; background:var(--primary); color:#fff; font-size:17px; }
    .page-intro strong { display:block; margin-bottom:4px; color:var(--ink,#17212b); font-size:14px; }
    .page-intro span { color:#657384; line-height:1.55; }
    .page-tabs { display:flex; flex-wrap:wrap; gap:0; margin-bottom:22px; border-bottom:1px solid var(--border); }
    .page-tab { display:inline-flex; align-items:center; gap:7px; padding:11px 16px; border:1px solid var(--border); border-bottom:0; margin-right:4px; background:#f7f9fa; color:#657384; text-decoration:none; font-weight:600; }
    .page-tab:hover, .page-tab.active { background:var(--primary); color:#fff; border-color:var(--primary); }
    .panel-heading { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:20px; }
    .panel-heading h2, .panel-heading h3 { margin:0 0 5px; }
    .panel-heading p { margin:0; color:#657384; font-size:12px; }
    .eyebrow { color:var(--primary); font-size:10px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; }
    .empty-state { padding:34px 20px; text-align:center; border:1px dashed var(--border); color:#657384; background:#fbfcfd; }
    .empty-state strong { display:block; color:#344454; margin-bottom:5px; }
    .table-actions { display:flex; justify-content:flex-end; align-items:center; gap:8px; flex-wrap:wrap; }
    .form-group label { display:flex; flex-direction:column; gap:7px; color:#344454; font-weight:600; }
    .form-group input, .form-group select, .form-group textarea { width:100%; min-height:42px; border:1px solid var(--border); padding:10px 12px; background:#fff; color:#344454; outline:none; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(23,107,135,.12); }
    .status-pill { display:inline-flex; align-items:center; gap:6px; padding:5px 9px; border:1px solid currentColor; font-size:10px; font-weight:700; text-transform:uppercase; }

    /* Iconos de línea monocromos: heredan el color del texto, sin relleno */
    .sys-icon { display:inline-flex; width:1em; height:1em; align-items:center; justify-content:center; vertical-align:-0.15em; line-height:1; flex:0 0 auto; }
    .sys-icon svg { width:100%; height:100%; display:block; fill:none; stroke:currentColor; }
    .gestion-form-panel { display:none; }
    .gestion-form-panel.active { display:block; }
    .gestion-form-tab { cursor:pointer; font:inherit; }
    .gestion-status { color:#657384; font-weight:700; }
    .gestion-status.aprobada, .gestion-status.aprobado { color:#2f806b; }
    .gestion-status.rechazada, .gestion-status.rechazado { color:#b34d4d; }
    .form-grid, .f-grid { row-gap: 22px !important; column-gap: 24px !important; }
    .form-actions, .f-actions { margin-top: 24px !important; padding-top: 20px !important; }
    .main .table-wrap + .table-wrap { margin-top: 24px; }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
        .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
        .sidebar.open { transform: translateX(0); }
        .main { margin-left: 0; padding: 24px 26px 36px; }
        .menu-toggle { display: flex; }
        .header { flex-wrap: wrap; }
        .user-info { font-size: 12px; }
        h1 { font-size: 18px; }
    }

    @media (max-width: 768px) {
        .main { padding: 20px 16px 30px; }
        .card { padding: 20px; border-radius: 0; }
        h1 { font-size: 16px; }
        .header { flex-direction: column; align-items: stretch; gap: 8px; }
        .notif-bar { max-width: 100%; }
        .notif-dropdown {
            position: fixed; top: 50%; left: 50%; right: auto;
            transform: translate(-50%, -50%);
            width: calc(100vw - 24px); max-width: 420px;
            max-height: 75vh; margin-top: 0;
        }
        .user-info { text-align: right; font-size: 11px; white-space: normal; }
        table { font-size: 12px; }
        th, td { padding: 11px 9px; }
        .btn-primary { padding: 10px 14px; font-size: 12px; min-height: 44px; }
        .grid { grid-template-columns: 1fr; gap: 18px; }
        [style*="grid-column: span"] { grid-column: span 1 !important; }
        div[style*="grid-template-columns:repeat(3,"],
        div[style*="grid-template-columns: repeat(3,"] { grid-template-columns: 1fr !important; }
        div[style*="grid-template-columns:repeat(4,"],
        div[style*="grid-template-columns: repeat(4,"] { grid-template-columns: 1fr 1fr !important; }
        div[style*="grid-template-columns: 2fr 1fr"],
        div[style*="grid-template-columns: 1fr 320px"],
        div[style*="grid-template-columns: 350px 1fr"] { grid-template-columns: 1fr !important; }
    }
    @media (max-width: 480px) {
        .main { padding: 14px 10px 24px; }
        .card { padding: 16px; border-radius: 0; }
        h1 { font-size: 15px; }
        .btn-primary { width: 100%; justify-content: center; }
        div[style*="grid-template-columns:repeat(4,"],
        div[style*="grid-template-columns: repeat(4,"] { grid-template-columns: 1fr 1fr !important; }
    }

    /* Modal de comprobantes */
    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.75);
      z-index: 9999;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .modal-overlay.show { display: flex; }
    .modal-box {
      background: white;
      border-radius: 0;
      max-width: 90vw;
      max-height: 90vh;
      overflow: auto;
      position: relative;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    .modal-box img {
      display: block;
      max-width: 100%;
      max-height: 80vh;
      border-radius: 0;
    }
    .modal-close {
      position: absolute;
      top: 10px;
      right: 14px;
      background: rgba(0,0,0,0.5);
      color: white;
      border: none;
      width: 36px;
      height: 36px;
      border-radius: 0;
      font-size: 20px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10;
      transition: 0.2s;
    }
    .modal-close:hover { background: rgba(0,0,0,0.8); }
    .modal-pdf-fallback {
      padding: 40px;
      text-align: center;
    }
    .modal-pdf-fallback a {
      display: inline-block;
      background: #3E5C76;
      color: white;
      padding: 12px 24px;
      border-radius: 0;
      text-decoration: none;
      font-weight: 600;
    }

    /* Notificaciones */
    .notif-bar {
      display:flex; align-items:center; gap:4px; padding:2px 0;
      flex-wrap:wrap; max-width:min(560px, 58vw);
    }
    .notif-wrap { position:relative; display:inline-flex; align-items:center; }
    .notif-bell {
      background:none; border:none; font-size:20px; cursor:pointer; padding:6px 10px;
      border-radius:8px; transition:.2s; position:relative; color:#6B7280; line-height:1;
    }
    .notif-bell:hover { background:#F3F4F6; color:#111827; }
    .notif-tipo-btn {
      border:1px solid transparent; padding:6px 9px;
    }
    .notif-tipo-btn:hover { filter:brightness(0.97); border-color:#E5E7EB; }
    .notif-tipo-icon { font-size:17px; line-height:1; }
    .notif-tipo-btn .notif-badge { top:-4px; right:-2px; }
    .notif-badge {
      position:absolute; top:0; right:2px; background:#8A5A5A; color:white;
      font-size:10px; font-weight:700; min-width:18px; height:18px;
      border-radius:9px; display:flex; align-items:center; justify-content:center;
      border:2px solid white;
    }
    .notif-dropdown {
      display:none; position:absolute; top:100%; right:0; margin-top:6px;
      width:390px; max-height:480px; overflow-y:auto; background:white;
      border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,0.15);
      z-index:9999; border:1px solid #E5E7EB;
    }
    .notif-dropdown.show { display:block; }
    .notif-dropdown-header {
      padding:12px 16px; border-bottom:1px solid #E5E7EB;
      font-size:13px; font-weight:700; color:#111827;
      display:flex; align-items:center; justify-content:space-between; gap:10px;
      position:sticky; top:0; background:white; z-index:1;
    }
    .notif-count-pill {
      background:#F2EEE7; color:#6E5F45; font-size:10px; font-weight:700;
      padding:3px 9px; border-radius:20px; white-space:nowrap;
    }
    .notif-list { display:flex; flex-direction:column; }
    .notif-item {
      display:flex; align-items:flex-start; gap:12px; padding:12px 16px;
      text-decoration:none; color:#374151; border-bottom:1px solid #F9FAFB;
      transition:.15s;
    }
    .notif-item:hover { background:#F9FAFB; }
    .notif-item:last-child { border-bottom:none; }
    .notif-icon-box {
      width:36px; height:36px; border-radius:8px; display:flex;
      align-items:center; justify-content:center; font-size:18px;
      flex-shrink:0; line-height:1;
    }
    .notif-body { display:flex; flex-direction:column; gap:2px; min-width:0; flex:1; }
    .notif-title { font-size:13px; font-weight:600; line-height:1.25; color:#111827; }
    .notif-detail {
      font-size:11px; color:#6B7280; line-height:1.35;
      overflow:hidden; text-overflow:ellipsis; display:-webkit-box;
      -webkit-line-clamp:2; -webkit-box-orient:vertical;
    }
    .notif-meta { font-size:10px; color:#9CA3AF; margin-top:2px; }
    .notif-cat { font-weight:700; text-transform:uppercase; letter-spacing:.3px; }
    .notif-more {
      padding:8px 16px; text-align:center; font-size:11px;
      color:#9CA3AF; background:#F9FAFB; border-top:1px solid #F3F4F6;
    }
    .notif-footer {
      display:block; padding:11px 16px; text-align:center;
      font-size:12px; font-weight:700; color:#33485C; text-decoration:none;
      background:#F9FAFB; border-top:1px solid #E5E7EB; position:sticky; bottom:0;
    }
    .notif-footer:hover { background:#EEF2F6; }
    .notif-empty { padding:30px; text-align:center; color:#9CA3AF; font-size:13px; }

    /* ===== SQUARE/BOXY DESIGN SYSTEM ===== */
    .card, .premium-form, .compact-form, .f-section, .modal-box,
    .btn-primary, .btn-submit, .btn-cancel, .btn-edit, .btn,
    input[type="text"], input[type="email"], input[type="password"],
    input[type="number"], input[type="date"], input[type="month"],
    select, textarea, .badge, .notif-dropdown, .menu-toggle {
      border-radius: 0 !important;
    }
    /* Sistema cuadrado: también normaliza estilos inline de las vistas antiguas. */
    .main *:not(.progress-track):not(.chart-column-track),
    .sidebar, .sidebar *,
    .modal-box, .notif-dropdown, .notif-icon-box {
      border-radius: 0 !important;
    }
    .progress-track, .chart-column-track { border-radius: 0 !important; }
    .card { box-shadow: 0 4px 14px rgba(23,33,43,.04) !important; border: 1px solid var(--border); }
    .form-section, .role-section { border-radius: 0 !important; }
    table { border-collapse: collapse; }
    th, td { border-radius: 0 !important; }

    /* ===== Reportes: barra de acciones y encabezado imprimible ===== */
    .report-toolbar {
      display: flex; justify-content: flex-end; align-items: center;
      gap: 8px; flex-wrap: wrap; margin: 0 0 16px;
    }
    .unified-report-toolbar { padding: 10px 12px; background:#f8fafb; border:1px solid var(--border); }
    .report-toolbar .btn-primary { text-decoration: none; }
    .report-btn-excel { background: #2f806b; }
    .report-btn-excel:hover { background: #256b59; }
    .report-btn-print { background: #4C6E91; }
    .report-btn-print:hover { background: #3E5C76; }
    .report-print-head { display: none; }
    .table-tools {
      display:flex; align-items:flex-end; justify-content:space-between; gap:12px;
      flex-wrap:wrap; padding:12px 14px; background:#f8fafb; border:1px solid var(--border);
      border-bottom:0;
    }
    .table-tools-search { display:flex; flex-direction:column; gap:5px; color:#657384;
      font-size:10px; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
      flex:1; min-width:220px;
    }
    .table-tools-search input { width:100%; min-height:38px; padding:8px 10px;
      border:1px solid var(--border); background:#fff; color:var(--text); text-transform:none;
      letter-spacing:normal; font-weight:400;
    }
    .table-tools-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .table-tools-actions button { padding:8px 12px; }
    .table-tools-count { color:#657384; font-size:11px; white-space:nowrap; }
    .unified-filter, form.card.no-print, .actions-bar.no-print {
      display:flex !important; align-items:flex-end; gap:10px; flex-wrap:wrap;
      padding:14px 16px !important; margin-bottom:18px !important;
      background:#f8fafb !important; border:1px solid var(--border) !important;
    }
    .unified-filter > div, form.card.no-print > div, .actions-bar.no-print > div { min-width:150px; flex:1 1 150px; }
    .unified-filter label, form.card.no-print label, .actions-bar.no-print label {
      display:block; margin-bottom:6px; color:#657384; font-size:10px;
      font-weight:700; letter-spacing:.05em; text-transform:uppercase;
    }
    .unified-filter input, .unified-filter select,
    form.card.no-print input, form.card.no-print select,
    .actions-bar.no-print input, .actions-bar.no-print select {
      width:100%; min-height:38px; padding:8px 10px;
      border:1px solid var(--border); background:#fff; color:var(--text);
    }
    .unified-filter button, .unified-filter a,
    form.card.no-print button, form.card.no-print a,
    .actions-bar.no-print button, .actions-bar.no-print a { min-height:38px; }
    .auto-filter button[type="submit"] { display:none !important; }
    .report-filter-heading { display:block !important; margin-bottom:16px; }
    .report-filter-heading > h3,
    .report-filter-heading > div:first-child { margin-bottom:12px !important; }
    .report-filter-heading > .unified-filter { width:100%; margin-bottom:0 !important; }

    @media print {
      @page { margin: 10mm; }
      html, body {
        background: #fff !important;
        display: block !important;
        color: #111827 !important;
        font-size: 10px !important;
      }
      .sidebar, .sidebar-overlay, .menu-toggle, .header,
      .notif-bar, .notif-wrap, .no-print, .report-toolbar,
      .page-intro, .page-tabs, .alert, .modal-overlay,
      .recep-filters, .recep-card, .recep-materials,
      .recep-stats, .table-actions, .btn-primary, .btn-edit {
        display: none !important;
      }
      .main {
        margin: 0 !important; padding: 0 !important;
        width: 100% !important; min-height: 0 !important;
      }
      .report-print-head {
        display: block !important; border-bottom: 2px solid #176b87;
        padding-bottom: 10px; margin-bottom: 14px;
      }
      .report-print-brand {
        color: #176b87; font-size: 9px; font-weight: 700;
        letter-spacing: .14em; text-transform: uppercase; margin-bottom: 4px;
      }
      .report-print-head h2 { margin: 0 0 4px; font-size: 18px; color: #111827; }
      .report-print-subtitle { margin: 0 0 6px; color: #4b5563; font-size: 11px; }
      .report-print-meta { color: #4b5563; font-size: 9px; line-height: 1.6; }
      .report-print-date { color: #6b7280; font-size: 9px; margin-top: 5px; }
      .card {
        border: 1px solid #d1d5db !important; box-shadow: none !important;
        padding: 0 !important; margin: 0 !important; background: #fff !important;
      }
      .table-wrap { overflow: visible !important; margin: 0 !important; }
      table { width: 100% !important; min-width: 0 !important; font-size: 9px !important; }
      th, td { border: 1px solid #d1d5db !important; padding: 5px 6px !important; }
      th { background: #f3f6f8 !important; color: #374151 !important; }
      thead { display: table-header-group; }
      tfoot { display: table-footer-group; }
      tr { page-break-inside: avoid; }
      a { color: #111827 !important; text-decoration: none !important; }
      .badge, .status-pill { border: 1px solid #9ca3af !important; }
      h1, h2, h3, h4 { color: #111827 !important; }
      .empty-state { border: 1px solid #d1d5db !important; }
    }

    <?php echo $extra_css ?? ''; ?>
  </style>
</head>
<body>

  <?php
  // ─── Notificaciones individuales, agrupadas por tipo y por usuario ───
  $notificaciones = [];
  try {
    $notifModel = new \app\models\notificacionModel();
    $notificaciones = $notifModel->paraUsuario($user, $pdo);
  } catch (\Throwable $e) {
    $notificaciones = [];
  }
  $notif_count = count($notificaciones);

  // Un grupo por tipo: cada tipo tendrá su propio icono en la cabecera.
  $notif_por_tipo = [];
  foreach ($notificaciones as $n) {
    $tipo = $n['tipo'] ?? 'otros';
    $notif_por_tipo[$tipo][] = $n;
  }

  // Orden estable de los iconos (solo se muestran los tipos con pendientes).
  $notif_orden_tipos = [
    'solicitud_baja', 'solicitud_renovacion', 'solicitud_alta',
    'solicitud_baja_rechazada', 'solicitud_renovacion_rechazada', 'solicitud_alta_rechazada',
    'solicitud_servicio', 'lote_barrio', 'lote_barrio_rechazado',
    'lote_calle', 'lote_calle_rechazado', 'exencion',
    'facturas_por_enviar', 'certificado', 'factura_recibida',
    'pago_por_verificar', 'pago_pendiente', 'cobro_rechazado', 'reporte_ciudadano',
    'solicitud_rechazada', 'solicitud_gestion', 'solicitud_gestion_rechazada',
  ];
  uksort($notif_por_tipo, function ($a, $b) use ($notif_orden_tipos) {
    $ia = array_search($a, $notif_orden_tipos, true);
    $ib = array_search($b, $notif_orden_tipos, true);
    return ($ia === false ? 999 : $ia) <=> ($ib === false ? 999 : $ib);
  });
  ?>

  <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

  <?php include __DIR__ . '/../components/sidebar.php'; ?>

  <main class="main">
    <header class="header">
      <div style="display: flex; align-items: center; gap: 12px;">
        <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()" aria-label="Toggle menu">☰</button>
        <div>
          <h1><?php echo $header_title ?? 'Panel de Control'; ?></h1>
          <div style="color: #6B7280; margin-top: 5px;"><?php echo $header_subtitle ?? ''; ?></div>
        </div>
      </div>
      <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; justify-content:flex-end;">
        <div class="notif-bar">
          <?php if(empty($notif_por_tipo)): ?>
            <div class="notif-wrap">
              <button class="notif-bell" onclick="toggleNotif(event, 'vacio')" title="Notificaciones">🔔</button>
              <div class="notif-dropdown" id="notifDropdown-vacio">
                <div class="notif-dropdown-header"><span>Notificaciones</span></div>
                <div class="notif-empty">✅ No tienes pendientes</div>
                <a href="router.php?page=notificaciones" class="notif-footer" onclick="cerrarNotif()">Ir al centro de notificaciones →</a>
              </div>
            </div>
          <?php else: ?>
            <?php foreach($notif_por_tipo as $tipo => $items):
              $est = notif_estilo($tipo);
              $n_items = count($items);
              $visibles = array_slice($items, 0, 8);
            ?>
              <div class="notif-wrap">
                <button class="notif-bell notif-tipo-btn"
                        style="background:<?= $est['bg'] ?>; color:<?= $est['color'] ?>;"
                        onclick="toggleNotif(event, '<?= htmlspecialchars($tipo) ?>')"
                        aria-haspopup="true"
                        aria-label="<?= htmlspecialchars($est['label']) ?>: <?= $n_items ?> pendiente<?= $n_items === 1 ? '' : 's' ?>"
                        title="<?= htmlspecialchars($est['label']) ?>: <?= $n_items ?> pendiente<?= $n_items === 1 ? '' : 's' ?>">
                  <span class="notif-tipo-icon"><?= $items[0]['icono'] ?></span>
                  <span class="notif-badge"><?= $n_items ?></span>
                </button>
                <div class="notif-dropdown" id="notifDropdown-<?= htmlspecialchars($tipo) ?>">
                  <div class="notif-dropdown-header">
                    <span><?= $items[0]['icono'] ?> <?= htmlspecialchars($est['label']) ?></span>
                    <span class="notif-count-pill"><?= $n_items ?> pendiente<?= $n_items === 1 ? '' : 's' ?></span>
                  </div>
                  <div class="notif-list">
                    <?php foreach($visibles as $n): ?>
                      <a href="<?= htmlspecialchars($n['link']) ?>" class="notif-item" onclick="cerrarNotif()">
                        <span class="notif-icon-box" style="background:<?= $est['bg'] ?>; color:<?= $est['color'] ?>;"><?= $n['icono'] ?></span>
                        <span class="notif-body">
                          <span class="notif-title"><?= htmlspecialchars($n['titulo']) ?></span>
                          <?php if(!empty($n['detalle'])): ?>
                            <span class="notif-detail"><?= htmlspecialchars($n['detalle']) ?></span>
                          <?php endif; ?>
                          <span class="notif-meta">
                            <?php if(!empty($n['fecha'])): ?><?= htmlspecialchars(notif_tiempo_relativo($n['fecha'])) ?><?php endif; ?>
                          </span>
                        </span>
                      </a>
                    <?php endforeach; ?>
                  </div>
                  <?php if($n_items > count($visibles)): ?>
                    <div class="notif-more">Hay <?= $n_items - count($visibles) ?> más de este tipo</div>
                  <?php endif; ?>
                  <a href="router.php?page=notificaciones#notif-tipo-<?= htmlspecialchars($tipo) ?>" class="notif-footer" onclick="cerrarNotif()">Ver todas las notificaciones →</a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <div class="user-info">
          <?php echo $user_greeting ?? 'Hola'; ?>, <strong><?php echo htmlspecialchars($user['nombre']); ?></strong>
        </div>
      </div>
    </header>

    <?php
    // Alertas globales: si la vista no las imprimió, se muestran aquí.
    if (empty($GLOBALS['dashboard_alerts_rendered'])) {
        render_dashboard_alerts($mensaje_exito ?? null, $mensaje_error ?? null);
    }
    echo $content;
    ?>

    <!-- Modal de comprobante -->
    <div class="modal-overlay" id="comprobanteModal" onclick="closeComprobanteModal(event)">
      <div class="modal-box" id="comprobanteModalBox">
        <button class="modal-close" onclick="closeComprobanteModal()">&times;</button>
        <div id="comprobanteModalContent"></div>
      </div>
    </div>
  </main>

  <script>
  function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('menuToggle');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');
    overlay.style.display = overlay.classList.contains('show') ? 'block' : 'none';
    toggle.classList.toggle('active');
    document.body.style.overflow = overlay.classList.contains('show') ? 'hidden' : '';
  }
  // Close sidebar on resize if going to desktop
  window.addEventListener('resize', function() {
    if (window.innerWidth > 1024) {
      const sidebar = document.querySelector('.sidebar');
      const overlay = document.getElementById('sidebarOverlay');
      const toggle = document.getElementById('menuToggle');
      sidebar.classList.remove('open');
      overlay.classList.remove('show');
      overlay.style.display = 'none';
      toggle.classList.remove('active');
      document.body.style.overflow = '';
    }
  });
  function openComprobanteModal(el) {
    const url = el.dataset.url;
    if (!url) return true;
    const modal = document.getElementById('comprobanteModal');
    const content = document.getElementById('comprobanteModalContent');
    const isImage = url.match(/\.(jpe?g|png|gif|webp|bmp)(\?.*)?$/i);
    if (isImage) {
      content.innerHTML = '<img src="' + encodeURI(url) + '" alt="Comprobante" onerror="this.parentElement.innerHTML=\'<div class=modal-pdf-fallback><p style=color:#6B7280;margin-bottom:16px;>No se pudo mostrar la imagen.</p><a href=' + encodeURI(url) + ' target=_blank>Abrir enlace directo</a></div>\'">';
    } else {
      content.innerHTML = '<div class="modal-pdf-fallback"><p style="color:#6B7280;margin-bottom:16px;">El comprobante es un archivo PDF. Haz clic para abrirlo:</p><a href="' + encodeURI(url) + '" target="_blank">Ver PDF</a></div>';
    }
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    return false;
  }
  function closeComprobanteModal(e) {
    if (e && e.target !== e.currentTarget) return;
    const modal = document.getElementById('comprobanteModal');
    modal.classList.remove('show');
    document.body.style.overflow = '';
  }
  function toggleNotif(e, tipo) {
    e.stopPropagation();
    const dd = document.getElementById('notifDropdown-' + tipo);
    if (!dd) return;
    document.querySelectorAll('.notif-dropdown.show').forEach(function(el) {
      if (el !== dd) el.classList.remove('show');
    });
    dd.classList.toggle('show');
  }
  function cerrarNotif() {
    document.querySelectorAll('.notif-dropdown.show').forEach(function(el) {
      el.classList.remove('show');
    });
  }
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.notif-wrap')) {
      cerrarNotif();
    }
  });
  </script>
  <script src="/reciclaje/assets/js/system-modal.js"></script>
  <script src="/reciclaje/assets/js/table-pagination.js"></script>
  <script src="/reciclaje/assets/js/table-tools.js"></script>
  <script src="/reciclaje/assets/js/auto-filters.js"></script>

  <script>
    // Bridge Multi-Sesión (Aislamiento por Pestaña)
    (function() {
        // 1. Obtener o generar SID único para esta pestaña
        if (!sessionStorage.getItem('eco_sid')) {
            sessionStorage.setItem('eco_sid', 'ts' + Date.now() + Math.floor(Math.random() * 1000));
        }
        const sid = sessionStorage.getItem('eco_sid');

        // 2. Función para inyectar SID en URLs internas
        function injectSid(url) {
            if (!url || url.startsWith('javascript:') || url.startsWith('#')) return url;
            try {
                const u = new URL(url, window.location.href);
                if (u.origin === window.location.origin) {
                    u.searchParams.set('sid', sid);
                    return u.pathname + u.search + u.hash;
                }
            } catch(e) {}
            return url;
        }

        // 3. Interceptar clics en enlaces
        document.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (link && link.href) {
                link.href = injectSid(link.href);
            }
        }, true);

        // 4. Inyectar en formularios al enviar
        document.addEventListener('submit', function(e) {
            const form = e.target;
            const action = form.getAttribute('action') || window.location.href;
            if (new URL(action, window.location.href).origin === window.location.origin) {
                if (!form.querySelector('input[name="sid"]')) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'sid';
                    input.value = sid;
                    form.appendChild(input);
                }
            }
        }, true);

        // 5. Forzar SID en la URL si falta (Asegura que el servidor conozca la identidad)
        const urlParams = new URLSearchParams(window.location.search);
        if (!urlParams.has('sid')) {
            urlParams.set('sid', sid);
            // No hacer replaceState, hacer una recarga real si es la primera vez que entra sin SID
            // Esto evita que vea el dashboard de otra cuenta por error
            window.location.search = urlParams.toString();
        }

        // 6. Polleo de enlaces dinámicos (opcional por si se añaden enlaces vía JS)
        setInterval(() => {
            document.querySelectorAll('a').forEach(link => {
                if (link.href && !link.href.includes('sid=') && !link.href.startsWith('javascript:') && !link.href.startsWith('#')) {
                    const u = new URL(link.href, window.location.href);
                    if (u.origin === window.location.origin) {
                        u.searchParams.set('sid', sid);
                        link.href = u.pathname + u.search + u.hash;
                    }
                }
            });
        }, 1000);
    })();
  </script>
  <script src="/reciclaje/assets/js/form-validation.js"></script>
  <script src="/reciclaje/assets/js/system-icons.js"></script>
</body>
</html>
