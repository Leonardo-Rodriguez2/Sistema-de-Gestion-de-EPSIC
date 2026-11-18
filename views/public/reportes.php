<?php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../app/validacion.php';

$title = "Reportar Incidentes | EPSIC";

$reporte_ok    = false;
$reporte_error = '';
$pdo = (new \app\models\mainModel())->conectar();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $ubicacion   = trim($_POST['ubicacion'] ?? '');
        $tipos       = array_values(array_filter(array_map('trim', (array)($_POST['tipo_incidente'] ?? [])), 'strlen'));
        $urgencia    = trim($_POST['urgencia'] ?? 'Normal');
        $descripcion = trim($_POST['descripcion'] ?? '');

        $tiposPermitidos = [
            'Basura acumulada en lugar indebido',
            'Contenedor lleno o desbordado',
            'No pasó el camión recolector',
            'Quema o incendio de basura',
            'Animales esparciendo basura',
            'Vertido de residuos peligrosos',
        ];

        $errores = [];
        validacion_agregar_error($errores, validacion_texto($ubicacion, 'La dirección', 5, 255, true));
        if (empty($tipos)) {
            validacion_agregar_error($errores, 'Seleccione al menos un tipo de incidente.');
        } elseif (count($tipos) > 3) {
            validacion_agregar_error($errores, 'Seleccione como máximo 3 tipos de incidente.');
        }
        foreach ($tipos as $tipoIncidente) {
            if (!in_array($tipoIncidente, $tiposPermitidos, true)) {
                validacion_agregar_error($errores, 'Uno de los tipos de incidente seleccionados no es válido.');
                break;
            }
        }
        validacion_agregar_error($errores, validacion_opcion($urgencia, ['Normal', 'Alta', 'Crítica'], 'El nivel de urgencia', true));
        validacion_agregar_error($errores, validacion_texto($descripcion, 'La descripción', 10, 2000, true));

        if (!empty($errores)) {
            throw new \Exception(implode(' ', $errores));
        }

        $tipos    = array_slice($tipos, 0, 3);
        $tipo_txt = implode(', ', $tipos);

        $stmt = $pdo->prepare(
            "INSERT INTO reportes (usuario_id, ubicacion_nombre, tipo_residuo, descripcion, cantidad, estado)
             VALUES (NULL, ?, ?, ?, 1, 'Pendiente')"
        );
        $stmt->execute([$ubicacion, $tipo_txt, '[Urgencia: ' . $urgencia . '] ' . $descripcion]);
        $reporte_ok = true;
    } catch (\Throwable $e) {
        $reporte_error = $e->getMessage();
    }
}

// Estadísticas reales del portal
$stat_total = (int)$pdo->query("SELECT COUNT(*) FROM reportes")->fetchColumn();
$stat_ok    = (int)$pdo->query("SELECT COUNT(*) FROM reportes WHERE estado = 'Completado'")->fetchColumn();

ob_start();
?>

    <!-- Hero Section -->
    <section class="hero">
        <h1>Ayúdanos a mantener la comunidad limpia</h1>
        <p>Reporta basura acumulada o contenedores desbordados en menos de un minuto.</p>
    </section>

    <!-- Main Content -->
    <main class="report-main">
        <!-- Izquierda: Información Corporativa -->
        <div class="info-panel">
            <img src="/reciclaje/img/WhatsApp-Image-2026-05-05-at-2.53.46-PM.jpeg" alt="Personal de Limpieza de EPSIC">
            <h2>¿Por qué hacemos esto?</h2>
            <p>Cualquier ciudadano puede enviarnos alertas de zonas críticas en nuestra ruta.</p>
            
            <ul class="benefits-list">
                <li>
                    <i class="fas fa-truck-fast"></i>
                    <div>
                        <strong>Respuesta Rápida</strong><br>
                        Nuestras unidades en ruta reciben su alerta en instantes.
                    </div>
                </li>
                <li>
                    <i class="fas fa-leaf"></i>
                    <div>
                        <strong>Cuidado del Medio Ambiente</strong><br>
                        Evitamos la contaminación de calles, ríos y áreas turísticas previniendo focos infecciosos.
                    </div>
                </li>
                <li>
                    <i class="fas fa-chart-line"></i>
                    <div>
                        <strong>Mapeo Inteligente</strong><br>
                        Los reportes nos ayudan a rediseñar y mejorar las frecuencias de recolección en los barrios más vulnerables.
                    </div>
                </li>
            </ul>

            <div style="background: var(--light); padding: 20px; border-radius: 8px; text-align: center;">
                <h4 style="margin: 0 0 10px 0; color: var(--dark);">¿Quieres contratar servicio regular para tu zona?</h4>
                <p style="font-size: 14px; margin-bottom: 15px;">Habla con un asesor y pide el registro oficial para tu vivienda/barrio.</p>
                <a href="/reciclaje/views/public/nosotros.php" style="color: var(--primary); font-weight: 600; text-decoration: none;">Ver Planes y Estadísticas &rarr;</a>
            </div>
        </div>

        <!-- Derecha: El Formulario Funcional -->
        <div class="form-panel">
            <h3 style="margin-top: 0; font-size: 22px; color: var(--dark); margin-bottom: 25px;">
                <i class="fas fa-map-location-dot" style="color: var(--primary);"></i> Formulario de Incidentes
            </h3>

            <?php if ($reporte_ok): ?>
                <div class="success-banner">
                    <i class="fas fa-check-circle" style="font-size: 24px;"></i>
                    <div>
                        <strong>¡Reporte Enviado!</strong><br>
                        Gracias. Nuestro equipo ha sido notificado y se dirigirá a la zona.
                    </div>
                </div>
            <?php elseif ($reporte_error): ?>
                <div class="success-banner" style="background:#F7F2F2; border-color:#9A6A6A; color:#7A4F4F;">
                    <i class="fas fa-triangle-exclamation" style="font-size: 24px;"></i>
                    <div>
                        <strong>No se pudo enviar el reporte.</strong><br>
                        <?= htmlspecialchars($reporte_error) ?>
                    </div>
                </div>
            <?php endif; ?>

            <form action="" method="post">
                
                <div class="form-group">
                    <label>Dirección Exacta del Incidente *</label>
                    <input type="text" name="ubicacion" placeholder="Ej: Av. El Sol esq. Puente Rosario" required>
                    <div class="map-preview">
                        [ Imagen del insidente o de lo que deseas reportar ] 
                        [ Opcional ]
                    </div>
                </div>

                <div class="form-group">
                    <label>¿Qué tipo de insidente desea reportar? (Elige máximo 3)</label>
                    <div class="residue-grid">
                        <label class="residue-option">
                            <input type="checkbox" name="tipo_incidente[]" value="Basura acumulada en lugar indebido" class="tipo-residuo">
                            <div class="residue-card"><i class="fas fa-dumpster"></i> Basura acumulada en lugar indebido</div>
                        </label>
                        <label class="residue-option">
                            <input type="checkbox" name="tipo_incidente[]" value="Contenedor lleno o desbordado" class="tipo-residuo">
                            <div class="residue-card"><i class="fas fa-trash-alt"></i> Contenedor lleno o desbordado</div>
                        </label>
                        <label class="residue-option">
                            <input type="checkbox" name="tipo_incidente[]" value="No pasó el camión recolector" class="tipo-residuo">
                            <div class="residue-card"><i class="fas fa-truck"></i> No pasó el camión recolector</div>
                        </label>
                        <label class="residue-option">
                            <input type="checkbox" name="tipo_incidente[]" value="Quema o incendio de basura" class="tipo-residuo">
                            <div class="residue-card"><i class="fas fa-fire"></i> Quema o incendio de basura en la comunidad</div>
                        </label>
                        <label class="residue-option">
                            <input type="checkbox" name="tipo_incidente[]" value="Animales esparciendo basura" class="tipo-residuo">
                            <div class="residue-card"><i class="fas fa-dog"></i> Animales esparciendo basura</div>
                        </label>
                        <label class="residue-option">
                            <input type="checkbox" name="tipo_incidente[]" value="Vertido de residuos peligrosos" class="tipo-residuo">
                            <div class="residue-card"><i class="fas fa-biohazard"></i> Vertido de residuos peligrosos</div>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Nivel de Urgencia</label>
                    <select name="urgencia" required>
                        <option value="Normal">Normal - Basura acumulada en la vereda</option>
                        <option value="Alta">Alta - Contenedor desbordado impidiendo el paso</option>
                        <option value="Crítica">Crítica - Riesgo para la salud o animales escarbando</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Descripción / Detalles adicionales *</label>
                    <textarea name="descripcion" rows="4" placeholder="Bríndanos detalles que ayuden a los recolectores a identificar mejor el problema..." required></textarea>
                </div>

                <button type="submit" class="submit-btn" style="background-color: var(--primary); color: white; border: none; padding: 15px; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    Enviar Alerta al Equipo <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </main>

    <!-- IMPACT SECTION -->
    <section class="impact-section">
        <div class="impact-container">
            <div>
                <h2 style="font-size: 32px; margin-bottom: 20px;">El Impacto de tus Reportes Cívicos</h2>
                <p style="font-size: 16px; opacity: 0.8; line-height: 1.6; margin-bottom: 30px;">
                    Cada vez que utilizas este portal, no solo limpias una calle. Evitas focos infecciosos, previenes que los plásticos tapen los drenajes pluviales en época de lluvias y proteges a los animales callejeros de ingerir desechos tóxicos.
                </p>
                <div class="impact-stats">
                    <div class="impact-stat-card">
                        <h3 style="color: var(--primary); font-size: 28px; margin: 0 0 5px 0;"><?= $stat_total ?></h3>
                        <p style="margin: 0; font-size: 14px; opacity: 0.9;">Reportes Recibidos</p>
                    </div>
                    <div class="impact-stat-card">
                        <h3 style="color: var(--primary); font-size: 28px; margin: 0 0 5px 0;"><?= $stat_ok ?></h3>
                        <p style="margin: 0; font-size: 14px; opacity: 0.9;">Reportes Atendidos</p>
                    </div>
                    <div class="impact-stat-card">
                        <h3 style="color: var(--primary); font-size: 28px; margin: 0 0 5px 0;">24/7</h3>
                        <p style="margin: 0; font-size: 14px; opacity: 0.9;">Portal de Reportes</p>
                    </div>
                    <div class="impact-stat-card">
                        <h3 style="color: var(--primary); font-size: 28px; margin: 0 0 5px 0;">3h</h3>
                        <p style="margin: 0; font-size: 14px; opacity: 0.9;">Tiempo Resp. Promedio</p>
                    </div>
                </div>
            </div>
            <div>
                <img src="/reciclaje/img/WhatsApp-Image-2026-05-12-at-2.03.59-PM.jpeg" alt="Impacto Ambiental" style="width: 100%; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
            </div>
        </div>
    </section>

    <!-- SUCCESS CASES -->
    <section class="cases-section">
        <div class="cases-container text-center">
            <h2 class="fw-bold mb-3" style="color: var(--dark); font-size: 28px;">Casos de Éxito Recientes</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; text-align: left;">
                
                <div class="case-card">
                    <div class="case-images">
                        <div style="width:50%; background: #F4EDED; display:flex; align-items:center; justify-content:center; color:#7A4F4F; font-weight:bold; font-size:18px;">Antes</div>
                        <div style="width:50%; background: #E9EFF4; display:flex; align-items:center; justify-content:center; color:#33485C; font-weight:bold; font-size:18px;">Después</div>
                    </div>
                    <div class="case-content">
                        <h4 style="color: var(--dark); margin-bottom: 10px; font-weight: 700;">Recuperación Mercado Sur</h4>
                        <p class="text-muted small mb-0">Desmonte retirado de un acceso peatonal en 45 minutos.</p>
                    </div>
                </div>

                <div class="case-card">
                    <div class="case-images">
                        <div style="width:50%; background: #F4EDED; display:flex; align-items:center; justify-content:center; color:#7A4F4F; font-weight:bold; font-size:18px;">Antes</div>
                        <div style="width:50%; background: #E9EFF4; display:flex; align-items:center; justify-content:center; color:#33485C; font-weight:bold; font-size:18px;">Después</div>
                    </div>
                    <div class="case-content">
                        <h4 style="color: var(--dark); margin-bottom: 10px; font-weight: 700;">Contenedores Sector A</h4>
                        <p class="text-muted small mb-0">Se instalaron contenedores fijos tras varios reportes.</p>
                    </div>
                </div>

                <div class="case-card">
                    <div class="case-images">
                        <div style="width:50%; background: #F4EDED; display:flex; align-items:center; justify-content:center; color:#7A4F4F; font-weight:bold; font-size:18px;">Antes</div>
                        <div style="width:50%; background: #E9EFF4; display:flex; align-items:center; justify-content:center; color:#33485C; font-weight:bold; font-size:18px;">Después</div>
                    </div>
                    <div class="case-content">
                        <h4 style="color: var(--dark); margin-bottom: 10px; font-weight: 700;">Prevención Vía Evitamiento</h4>
                        <p class="text-muted small mb-0">Se detuvo una quema ilegal en las riberas del río.</p>
                    </div>
                </div>

            </div>
        </div>
    </section>

<script>
        // Limitar selección de checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.tipo-residuo');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const selected = document.querySelectorAll('.tipo-residuo:checked');
                    if (selected.length > 3) {
                        this.checked = false;
                        // Notificar silenciosamente si fuera necesario, o simplemente impedir la acción
                    }
                });
            });
        });
</script>

<style>
    :root {
            --primary: #4C6E91;
            --primary-dark: #3E5C76;
            --dark: #1F2937;
            --light: #F3F4F6;
            --text-gray: #4B5563;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(76,110,145, 0.9), rgba(62,92,118, 0.9)), url('/reciclaje/img/WhatsApp-Image-2026-05-05-at-2.53.48-PM.jpeg') center/cover no-repeat;
            padding: 60px 20px;
            text-align: center;
            color: white;
        }

        .hero h1 {
            font-size: 40px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .hero p {
            font-size: 18px;
            max-width: 600px;
            margin: 0 auto;
            opacity: 0.9;
        }

        /* Split Layout */
        .report-main {
            max-width: 1200px;
            margin: -40px auto 40px;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            position: relative;
            z-index: 10;
        }

        @media (max-width: 900px) {
            .report-main { grid-template-columns: 1fr; margin-top: 20px; }
        }

        /* Left Side: Info & Marketing */
        .info-panel {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }

        .info-panel img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .info-panel h2 {
            font-size: 24px;
            color: var(--primary-dark);
            margin-bottom: 15px;
        }

        .info-panel p {
            color: var(--text-gray);
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .benefits-list {
            list-style: none;
            padding: 0;
            margin-bottom: 30px;
        }

        .benefits-list li {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            color: var(--text-gray);
        }
        
        .benefits-list i {
            color: var(--primary);
            font-size: 20px;
            margin-top: 2px;
        }

        /* Right Side: Form */
        .form-panel {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border-top: 5px solid var(--primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }

        input[type="text"], textarea, select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            color: var(--dark);
            box-sizing: border-box;
            transition: all 0.3s;
            background: #F9FAFB;
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(76,110,145, 0.2);
            background: white;
        }

        /* Residuos Custom Checkboxes */
        .residue-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .residue-option {
            position: relative;
        }

        .residue-option input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }

        .residue-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            border: 2px solid #E5E7EB;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
            color: var(--text-gray);
            font-weight: 500;
            font-size: 14px;
        }

        .residue-option input:checked ~ .residue-card {
            border-color: var(--primary);
            background: #EEF2F6;
            color: var(--primary-dark);
        }

        .map-preview {
            width: 100%;
            height: 200px;
            background: #E5E7EB;
            border-radius: 8px;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9CA3AF;
            font-size: 14px;
            border: 1px dashed #9CA3AF;
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .submit-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76,110,145, 0.3);
        }

        .success-banner {
            background: #E9EFF4;
            border: 1px solid #4C6E91;
            color: #33485C;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 500;
        }

        /* New Sections */
        .impact-section {
            background-color: var(--dark);
            color: white;
            padding: 60px 20px;
            margin-top: 60px;
        }
        .impact-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }
        .impact-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .impact-stat-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        .cases-section {
            padding: 80px 20px;
            background-color: white;
        }
        .cases-container {
            max-width: 1200px; margin: 0 auto;
        }
        .case-card {
            border: 1px solid #E5E7EB; border-radius: 12px; overflow: hidden;
            display: flex; flex-direction: column;
        }
        .case-images { display: flex; height: 200px; }
        .case-images img { width: 50%; object-fit: cover; }
        .case-content { padding: 25px; }

        @media (max-width: 900px) {
            .impact-container { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .hero h1 { font-size: 28px; }
            .hero p { font-size: 16px; }
            .residue-grid { grid-template-columns: 1fr; }
            .info-panel, .form-panel { padding: 25px 20px; }
        }
</style>
<?php

$content = ob_get_clean();
include __DIR__ . '/../layouts/public_layout.php';
