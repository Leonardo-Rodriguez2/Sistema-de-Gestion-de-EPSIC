<?php
// views/public/inicio.php — Página de Inicio Pública
$title = "EPSIC - Servicio de Recolección Corporativa y Residencial";

ob_start();
?>
  <section class="hero-section">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-md-7">
          <span class="badge bg-light text-success mb-3 px-3 py-2 fs-6 rounded-pill">Expertos en Recolección y Reciclaje</span>
          <h1 style="color:#EEF2F6;">Líderes en Gestión Ambiental Integral</h1>
          <p class="lead mb-4 mt-3">Recolección de residuos para zonas comerciales y residenciales, con rutas optimizadas y plataforma digital.</p>
          <a href="/reciclaje/views/public/servicios.php" class="btn btn-light text-success fw-bold px-4 py-3 me-3 rounded-pill shadow-sm">Ver Planes Disponibles</a>
          <a href="#contacto" class="btn btn-outline-light px-4 py-3 rounded-pill fw-bold">Contactar a un Asesor</a>
        </div>
      </div>
    </div>
  </section>

  <!-- SECCIÓN: Servicios Rápidos -->
  <section class="features-section">
    <div class="container">
      <div class="text-center mb-5">
        <h2 class="fw-bold" style="color: #2A3B4A;">Nuestros Servicios Corporativos</h2>
      </div>
      <div class="row g-4">
        <div class="col-md-4">
          <div class="card h-100 card-feature p-4 text-center">
            <div class="mb-4"><i class="fas fa-truck fa-3x" style="color: #4C6E91;"></i></div>
            <h4 class="fw-bold mb-3">Recolección Residencial Diaria</h4>
            <p class="text-muted">Flotas propias certificadas y recojo puntual.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100 card-feature p-4 text-center">
            <div class="mb-4"><i class="fas fa-recycle fa-3x" style="color: #4C6E91;"></i></div>
            <h4 class="fw-bold mb-3">Programa de Valorización</h4>
            <p class="text-muted">Campañas de segregación en la fuente con alianzas vecinales.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100 card-feature p-4 text-center">
            <div class="mb-4"><i class="fas fa-file-invoice-dollar fa-3x" style="color: #4C6E91;"></i></div>
            <h4 class="fw-bold mb-3">Cobranza y Tableros Digitales</h4>
            <p class="text-muted">Reportes, historial de pagos y estado de deuda en línea.</p>
          </div>
        </div>
      </div>
      <div class="text-center mt-5">
        <a href="/reciclaje/views/public/servicios.php" class="btn btn-custom px-5 py-3 rounded-pill shadow">Descubre las diferencias entre Planes</a>
      </div>
    </div>
  </section>

  <!-- SECCIÓN: Cómo Funciona -->
  <section class="steps-section">
    <div class="container">
      <div class="text-center mb-5">
        <h2 class="fw-bold" style="color: #2A3B4A;">¿Cómo empezar a trabajar con nosotros?</h2>
      </div>
      <div class="row text-center g-4 position-relative">
        <div class="d-none d-lg-block" style="position:absolute; top:40px; left:16%; right:16%; height:3px; background:#E5E7EB; z-index:0;"></div>
        <div class="col-lg-3 col-md-6 position-relative z-1">
          <div class="step-circle shadow-sm">1</div>
          <h5 class="fw-bold mt-4">Contacto y Auditoría</h5>
          <p class="text-muted small px-2">Coordinamos una visita y calculamos tu tarifa.</p>
        </div>
        <div class="col-lg-3 col-md-6 position-relative z-1">
          <div class="step-circle shadow-sm">2</div>
          <h5 class="fw-bold mt-4">Contrato y Credenciales</h5>
          <p class="text-muted small px-2">Firmamos el contrato y entregamos accesos.</p>
        </div>
        <div class="col-lg-3 col-md-6 position-relative z-1">
          <div class="step-circle shadow-sm">3</div>
          <h5 class="fw-bold mt-4">Ejecución Constante</h5>
          <p class="text-muted small px-2">Recojo puntual según el cronograma acordado.</p>
        </div>
        <div class="col-lg-3 col-md-6 position-relative z-1">
          <div class="step-circle shadow-sm">4</div>
          <h5 class="fw-bold mt-4">Plataforma Online</h5>
          <p class="text-muted small px-2">Los residentes gestionan pagos y reportes en línea.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- SECCIÓN: Por qué Elegirnos -->
  <section class="why-choose-us">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-5 mb-5 mb-lg-0">
          <h2 class="fw-bold mb-4 display-5">Más que camiones de basura, somos promotores de calidad de vida.</h2>
          <ul class="list-unstyled">
            <li class="mb-3 fs-5"><i class="fas fa-check-circle text-success me-3"></i> Trazabilidad de camiones en ruta.</li>
            <li class="mb-3 fs-5"><i class="fas fa-check-circle text-success me-3"></i> Personal unificado, uniformado y asegurado.</li>
            <li class="mb-3 fs-5"><i class="fas fa-check-circle text-success me-3"></i> Atención al cliente 24/7 para emergencias.</li>
            <li class="fs-5"><i class="fas fa-check-circle text-success me-3"></i> Promotores activos de certificación verde comercial.</li>
          </ul>
        </div>
        <div class="col-lg-6 offset-lg-1">
          <div class="row g-4 text-center">
            <div class="col-6"><div class="p-4 bg-white bg-opacity-10 rounded-4"><i class="fas fa-shield-halved why-icon"></i><h4 class="fw-bold">100% Legal</h4><p class="small opacity-75 mb-0">Disposición final solo en rellenos sanitarios certificado.</p></div></div>
            <div class="col-6"><div class="p-4 bg-white bg-opacity-10 rounded-4"><i class="fas fa-hand-holding-dollar why-icon"></i><h4 class="fw-bold">Costos Claros</h4><p class="small opacity-75 mb-0">Sin sorpresas. Tarifas fijas estandarizadas facturadas.</p></div></div>
            <div class="col-6"><div class="p-4 bg-white bg-opacity-10 rounded-4"><i class="fas fa-seedling why-icon"></i><h4 class="fw-bold">Baja Emisión</h4><p class="small opacity-75 mb-0">Vehículos modernos con estándar y limitados en ruido.</p></div></div>
            <div class="col-6"><div class="p-4 bg-white bg-opacity-10 rounded-4"><i class="fas fa-laptop-code why-icon"></i><h4 class="fw-bold">Alta Tecnología</h4><p class="small opacity-75 mb-0">Sistema Web para gestionar reportes de incidentes al instante.</p></div></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="py-5" style="background-color: #EEF2F6;">
    <div class="container text-center py-5">
      <h2 class="fw-bold mb-4" style="color: #2A3B4A;">¿Listo para darle la bienvenida al progreso y la limpieza?</h2>
      <a href="/reciclaje/views/public/nosotros.php" class="btn btn-custom btn-lg shadow-sm px-5 py-3 rounded-pill">
        Llenar Solicitud de Contratación <i class="fas fa-file-signature ms-2"></i>
      </a>
    </div>
  </section>

  <section class="stats-section">
    <div class="container">
      <div class="row g-4">
        <div class="col-md-3"><h2 class="display-4 fw-bold">0</h2><p class="fs-5 text-dark fw-bold">Años</p></div>
        <div class="col-md-3"><h2 class="display-4 fw-bold">0</h2><p class="fs-5 text-dark fw-bold">Familias Afiliadas</p></div>
        <div class="col-md-3"><h2 class="display-4 fw-bold">0</h2><p class="fs-5 text-dark fw-bold">Barrios Afiliados</p></div>
        <div class="col-md-3"><h2 class="display-4 fw-bold">0</h2><p class="fs-5 text-dark fw-bold">Vehículos Activos</p></div>
      </div>
    </div>
  </section>

<style>
    .hero-section {
      background: linear-gradient(rgba(76,110,145, 0.9), rgba(62,92,118, 0.9)), url('/reciclaje/img/WhatsApp-Image-2026-05-12-at-2.03.59-PM.jpeg') center/cover no-repeat;
      padding: 100px 0; color: white; text-shadow: 1px 1px 3px rgba(0,0,0,0.3);
    }
    .hero-section h1 { font-weight: 700; font-size: 3rem; }
    .features-section { background-color: #f8f9fa; padding: 80px 0; }
    .steps-section { padding: 80px 0; background-color: white; }
    .step-circle { width: 80px; height: 80px; border-radius: 50%; background-color: #EEF2F6; color: #4C6E91; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold; margin: 0 auto 20px; border: 3px solid #4C6E91; }
    .why-choose-us { background-color: #2A3B4A; color: white; padding: 80px 0; }
    .why-icon { color: #7A99B8; font-size: 2.5rem; margin-bottom: 20px; }
    .testimonials-section { padding: 80px 0; background-color: #f8f9fa; }
    .stats-section { background-color: #4C6E91; color: white; padding: 60px 0; text-align: center; }
    .btn-custom { background-color: #3E5C76; color: white; border: none; padding: 12px 30px; font-weight: 600; font-size: 1.1rem; border-radius: 8px;}
    .btn-custom:hover { background-color: #33485C; color: white; }
    .card-feature { transition: transform 0.3s ease; border: none; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-radius: 12px;}
    .card-feature:hover { transform: translateY(-5px); }
</style>
<?php

$content = ob_get_clean();
include __DIR__ . '/../layouts/public_layout.php';
