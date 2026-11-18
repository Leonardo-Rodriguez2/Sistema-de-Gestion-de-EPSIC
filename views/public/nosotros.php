<?php
// Lógica Formulario Solicitud
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../app/validacion.php';

$mensaje_form = '';
$error_form   = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && ($_POST['form_type'] ?? '') === 'solicitud') {
    try {
        $pdo = (new \app\models\mainModel())->conectar();

        $nombre   = trim($_POST['nombre'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $barrio   = trim($_POST['barrio'] ?? '');
        $tipo     = trim($_POST['tipo'] ?? '');
        $correo   = trim($_POST['correo'] ?? '');

        $tiposContrato = [
            'Comité Vecinal (Varias casas)',
            'Empresa Privada',
            'Condominio/Edificio',
            'Vivienda Unifamiliar',
        ];

        $errores = [];
        validacion_agregar_error($errores, validacion_nombre_persona($nombre, 'El nombre del representante', true));
        validacion_agregar_error($errores, validacion_telefono($telefono, 'El teléfono', true));
        validacion_agregar_error($errores, validacion_nombre_lugar($barrio, 'El barrio / urbanización', 2, 150, true));
        validacion_agregar_error($errores, validacion_opcion($tipo, $tiposContrato, 'El tipo de contrato', true));
        validacion_agregar_error($errores, validacion_email($correo, 'El correo electrónico', true));

        if (!empty($errores)) {
            throw new \Exception(implode(' ', $errores));
        }

        // Guardar el teléfono únicamente con dígitos.
        normalizar_datos_post($_POST);
        $telefono = $_POST['telefono'];

        $stmt = $pdo->prepare(
            "INSERT INTO solicitudes_servicio (representante, telefono, barrio, tipo_contrato, correo, estado)
             VALUES (?, ?, ?, ?, ?, 'Pendiente')"
        );
        $stmt->execute([$nombre, $telefono, $barrio, $tipo, $correo]);
        $mensaje_form = "¡Solicitud registrada correctamente! Un asesor de ventas se comunicará pronto al celular proporcionado.";
    } catch (\Throwable $e) {
        $error_form = $e->getMessage();
    }
}

$title = "Nosotros | EPSIC";
ob_start();
?>
<main>
    <section class="hero">
        <h1>Una comunidad limpia, el orgullo de todos</h1>
        <p>Somos EPSIC, la empresa líder en gestión de residuos sólidos en la ciudad imperial encargada de modernizar la recolección comunitaria.</p>
    </section>
        
        <section class="section-about container">
            <div class="about-text">
                <h2>¿Quiénes Somos?</h2>
                <p>Transformamos la recolección de residuos con tecnología, rutas optimizadas y atención transparente para cada barrio.</p>
                
                <div style="margin-top: 30px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <i class="fas fa-bullseye" style="font-size: 24px; color: var(--primary); margin-bottom: 10px;"></i>
                        <h4 style="margin: 0 0 5px;">Misión Integral</h4>
                        <p style="font-size: 13px; color: var(--text-gray); margin:0;">Garantizar trazabilidad del residuo desde su origen hasta el centro de acopio.</p>
                    </div>
                    <div>
                        <i class="fas fa-earth-americas" style="font-size: 24px; color: var(--primary); margin-bottom: 10px;"></i>
                        <h4 style="margin: 0 0 5px;">Visión Cero-Huella</h4>
                        <p style="font-size: 13px; color: var(--text-gray); margin:0;">Ser el estándar nacional en recolección automatizada y reducción de emisiones.</p>
                    </div>
                </div>
            </div>
            <div class="about-image">
                <img src="/reciclaje/img/servicio.jpeg" alt="Reciclaje EPSIC">
                <div class="badge-float">
                    <span><?php echo isset($totalReportes) ? $totalReportes : '100+'; ?></span>
                    <span style="font-size: 12px; font-weight: normal;">Zonas Atendidas</span>
                </div>
            </div>
        </section>

        <!-- Valores Corporativos -->
        <h2 class="text-center" style="font-size: 32px; color: var(--dark); margin-top: 40px; text-align: center;">Nuestros Valores</h2>
        <div class="values-grid container mb-5" style="margin: 0 auto; display: flex; justify-content: center;">
            <div class="value-card">
                <i class="fas fa-clock"></i>
                <h3 style="margin-bottom: 15px;">Puntualidad Extrema</h3>
                <p style="color: var(--text-gray); font-size: 14px;">Recojo puntual bajo cronogramas estrictos.</p>
            </div>
            <div class="value-card">
                <i class="fas fa-leaf"></i>
                <h3 style="margin-bottom: 15px;">Ecología Real</h3>
                <p style="color: var(--text-gray); font-size: 14px;">Separamos, reciclamos y disponemos correctamente. Disminuimos el impacto de la comunidad en los entornos naturales.</p>
            </div>
            <div class="value-card">
                <i class="fas fa-handshake"></i>
                <h3 style="margin-bottom: 15px;">Transparencia</h3>
                <p style="color: var(--text-gray); font-size: 14px;">Facturación clara, sin cobros ocultos, visible en la plataforma.</p>
            </div>
        </div>
// hola mundo
        <!-- Historia -->
        <div class="history-section">
            <h2 style="font-size: 32px; color: var(--primary-dark); margin-bottom: 20px;">Nuestra Historia</h2>
            <p style="color: var(--text-gray); line-height: 1.8; max-width: 800px; margin: 0 auto;">Nacimos hace más de 12 años en Cusco y hoy somos una de las principales flotas privadas de recolección, al servicio de condominios, municipalidades y comercios.</p>
        </div>

        <!-- Equipo -->
        <div class="team-section">
            <h2 style="font-size: 32px; color: var(--dark); margin-bottom: 15px;">Nuestro Equipo Directivo y Operativo</h2>
            
            <div class="team-grid container" style="margin: 40px auto;">
                <div class="team-card">
                    <img src="/reciclaje/img/WhatsApp-Image-2026-05-03-at-5.05.32-PM.jpeg" alt="Equipo EPSIC">
                    <div class="team-info">
                        <h4 style="margin: 0 0 5px; font-size: 20px;">Ing. Carlos Mendoza</h4>
                        <p style="color: var(--primary); font-weight: 600; margin: 0 0 15px; font-size: 14px;">Director Ejecutivo (CEO)</p>
                        <p style="color: var(--text-gray); font-size: 13px; margin: 0; line-height: 1.6;">Logística urbana y alianzas vecinales.</p>
                    </div>
                </div>
                <div class="team-card">
                    <img src="/reciclaje/img/WhatsApp-Image-2026-07-19-at-11.22.52-AM.jpeg" alt="Operaciones EPSIC">
                    <div class="team-info">
                        <h4 style="margin: 0 0 5px; font-size: 20px;">Arq. Valeria Ríos</h4>
                        <p style="color: var(--primary); font-weight: 600; margin: 0 0 15px; font-size: 14px;">Gerente de Operaciones GPS</p>
                        <p style="color: var(--text-gray); font-size: 13px; margin: 0; line-height: 1.6;">Diseño de rutas y coordinación de incidentes.</p>
                    </div>
                </div>
                <div class="team-card">
                                        <img src="/reciclaje/img/WhatsApp-Image-2026-07-19-at-11.16.05-AM-1.jpeg" alt="Equipo EPSIC">
                    <div class="team-info">
                        <h4 style="margin: 0 0 5px; font-size: 20px;">Julio Ramírez</h4>
                        <p style="color: var(--primary); font-weight: 600; margin: 0 0 15px; font-size: 14px;">Líder de Flota de Recojo</p>
                        <p style="color: var(--text-gray); font-size: 13px; margin: 0; line-height: 1.6;">Lidera al equipo de recolectores en ruta.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="cta-section">
            <h2>¿Es usted jefe o encargado de un Barrio o Condominio?</h2>
            <p>Solicite precios corporativos mensuales y reportes para todas sus viviendas.</p>
            
            <?php if(!empty($mensaje_form)): ?>
                <div style="background: #E9EFF4; color: #33485C; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje_form); ?>
                </div>
            <?php elseif(!empty($error_form)): ?>
                <div style="background: #F7F2F2; color: #7A4F4F; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                    <i class="fas fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error_form); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="nosotros.php">
                <input type="hidden" name="form_type" value="solicitud">
                <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 22px;"><i class="fas fa-file-signature" style="color:var(--primary);"></i> Formulario de Solicitud de Servicio</h3>
                
                <div class="form-grid">
                    <div>
                        <label>Nombre del Representante *</label>
                        <input type="text" name="nombre" placeholder="" required>
                    </div>
                    <div>
                        <label>Teléfono Celular *</label>
                        <input type="text" name="telefono" placeholder="" inputmode="numeric" pattern="[0-9]{7,11}" maxlength="11" minlength="7" title="Ingrese entre 7 y 11 números" required>
                    </div>
                    <div>
                        <label>Barrio / Urbanización *</label>
                        <input type="text" name="barrio" placeholder="" required>
                    </div>
                    <div>
                        <label>Tipo de Contrato</label>
                        <select name="tipo">
                            <option>Comité Vecinal (Varias casas)</option>
                            <option>Empresa Privada</option>
                            <option>Condominio/Edificio</option>
                            <option>Vivienda Unifamiliar</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 20px; text-align: left;">
                    <label>Correo Electrónico *</label>
                    <input type="email" name="correo" placeholder="" required>
                </div>

                <button type="submit" class="cta-btn">Solicitar Cotización Gratuita <i class="fas fa-arrow-right"></i></button>
            </form>
            
            <div style="margin-top: 25px; font-size: 14px; opacity: 0.6;">O llame al +51 000--000000 de Lunes a Viernes (08:00 - 18:00)</div>
        </div>
    </main>

<?php
$extra_css = "
        :root {
            --primary: #4C6E91;
            --primary-dark: #3E5C76;
            --dark: #1F2937;
            --light: #F3F4F6;
            --text-gray: #4B5563;
        }

        /* Hero Nosotros */
        .hero {
            background: linear-gradient(rgba(31, 41, 55, 0.8), rgba(76,110,145, 0.8)), url('/reciclaje/img/WhatsApp-Image-2026-05-05-at-2.53.46-PM.jpeg') center/cover no-repeat;
            padding: 100px 20px;
            text-align: center;
            color: white;
            clip-path: polygon(0 0, 100% 0, 100% 90%, 0% 100%);
        }

        .hero h1 {
            font-size: 50px;
            font-weight: 800;
            margin-bottom: 20px;
            letter-spacing: -1.5px;
        }

        .hero p {
            font-size: 20px;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.9;
        }

        /* Acerca de la Empresa */
        .section-about {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            padding: 60px 0;
            align-items: center;
        }

        .about-text h2 {
            font-size: 32px;
            color: var(--primary-dark);
            margin-top: 0;
            margin-bottom: 20px;
        }

        .about-text p {
            color: var(--text-gray);
            line-height: 1.8;
            font-size: 16px;
            margin-bottom: 20px;
        }

        .about-image {
            position: relative;
        }

        .about-image img {
            width: 100%;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .badge-float {
            position: absolute;
            background: var(--primary);
            color: white;
            padding: 20px;
            border-radius: 50%;
            font-weight: bold;
            font-size: 20px;
            text-align: center;
            bottom: -20px;
            right: -20px;
            box-shadow: 0 10px 20px rgba(76,110,145,0.3);
            width: 120px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        /* Sección de Impacto Real (Estadísticas Dinámicas) */
        .section-stats {
            background: white;
            padding: 80px 0;
            text-align: center;
        }

        .section-stats h2 {
            font-size: 36px;
            margin-bottom: 10px;
        }

        .section-stats .subtitle {
            color: var(--text-gray);
            font-size: 18px;
            margin-bottom: 50px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 50px;
        }

        .stat-card {
            background: var(--light);
            padding: 30px 20px;
            border-radius: 12px;
            border-bottom: 4px solid var(--primary);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 40px;
            font-weight: 800;
            color: var(--dark);
            margin: 10px 0;
        }

        .stat-label {
            color: var(--text-gray);
            text-transform: uppercase;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 1px;
        }

        .stat-trend.positive { color: var(--primary); font-weight: 600; font-size: 14px; margin-top: 10px;}
        .stat-trend.negative { color: #9A6A6A; font-weight: 600; font-size: 14px; margin-top: 10px;}

        /* Call to Action - Contratar Servicio */
        .cta-section {
            background: var(--dark);
            color: white;
            padding: 60px 20px;
            text-align: center;
            border-radius: 16px;
            margin: 60px 0;
        }

        .cta-section h2 { font-size: 32px; margin-bottom: 15px; }
        .cta-section p { font-size: 18px; margin-bottom: 30px; opacity: 0.8; max-width: 600px; margin-left: auto; margin-right: auto;}
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            text-align: left;
            margin-bottom: 20px;
        }

        .cta-section form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin: 20px auto 0;
            max-width: 700px;
            color: var(--dark);
        }

        .cta-section input, .cta-section select {
            width: 100%;
            padding: 12px;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            box-sizing: border-box;
            background: #F9FAFB;
        }

        .cta-section input:focus, .cta-section select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(76,110,145, 0.2);
        }

        .cta-section label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .cta-btn {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 15px 40px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 700;
            font-size: 18px;
            transition: 0.3s;
            border: none;
            width: 100%;
            cursor: pointer;
        }

        .cta-btn:hover { background: var(--primary-dark); transform: scale(1.05); }

        /* Nuevas Secciones */
        .history-section { text-align: center; margin: 60px 0; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        
        .values-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin: 60px 0; }
        .value-card { background: white; padding: 40px 30px; border-radius: 12px; text-align: center; border-top: 5px solid var(--primary); box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        .value-card i { font-size: 40px; color: var(--primary); margin-bottom: 20px; }
        
        .team-section { margin: 80px 0; text-align: center; }
        .team-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-top: 40px; }
        .team-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.03); text-align: left; }
        .team-card img { width: 100%; height: 250px; object-fit: cover; }
        .team-info { padding: 25px; }

        @media (max-width: 900px) {
            .section-about { grid-template-columns: 1fr; }
            .stats-grid, .values-grid, .team-grid { grid-template-columns: repeat(2, 1fr); }
            .form-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .stats-grid, .values-grid, .team-grid { grid-template-columns: 1fr; }
            .badge-float { display: none; }
            .hero h1 { font-size: 32px; }
            .hero p { font-size: 16px; }
            .cta-section h2 { font-size: 24px; }
            .cta-section form { padding: 20px 15px; }
        }
";
$extra_js = "";
$content = ob_get_clean();
include __DIR__ . '/../layouts/public_layout.php';
?>

