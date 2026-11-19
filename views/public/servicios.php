<?php
$title = "Nuestros Servicios | EcoReciclaje";
ob_start();
?>

    <section class="hero">
        <h1>Soluciones de Recolección a Medida</h1>
    </section>

    <div class="container">
        
        <div class="section-title">
            <h2 class="mt-4">Nuestros Planes de Servicio</h2>
        </div>

        <div class="services-grid">
            
            <!-- Plan Residencial -->
            <div class="service-card popular">
                <div class="popular-badge">Más Elegido</div>
                <div class="service-icon"><i class="fas fa-house-chimney-window"></i></div>
                <h3 class="service-title">Plan Urbanización</h3>
                <p class="service-desc">Para Comités, Urbanizaciones o Barrios completos que desean ordenar su recolección de forma unificada.</p>
                
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> Frecuencia de pagos (3 por sem.)</li>
                    <li><i class="fas fa-check"></i> Perfil individual por cada familia</li>
                    <li><i class="fas fa-check"></i> Dashboard Financiero por Vivienda</li>
                    <li><i class="fas fa-check"></i> Recolección diferenciada básica</li>
                    <li><i class="fas fa-check"></i> Seguimiento de tu servicio en línea</li>
                </ul>

                <a href="cotizacion.php?plan=urbanizacion" class="btn-hire">Solicitar Cotización</a>
            </div>

            <!-- Plan Comercial -->
            <div class="service-card commercial">
                <div class="service-icon"><i class="fas fa-store"></i></div>
                <h3 class="service-title">Plan Comercial</h3>
                <p class="service-desc">Atención exclusiva para Negocios, Restaurantes o Plazas Comerciales con gran volumen de generación diaria.</p>
                
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> Frecuencia de pagos (3 por sem.)</li>
                    <li><i class="fas fa-check"></i> Recolección dedicada al solicitar el servicio</li>
                    <li><i class="fas fa-check"></i> Mas alcance de ruta de busqueda de basura </li>
                    <li><i class="fas fa-check"></i>  </li>
                    <li><i class="fas fa-check"></i> Mas capacidad al recoletar la basura </li>
                </ul>

                <a href="cotizacion.php?plan=comercial" class="btn-hire">Solicitar Cotización</a>
            </div>

            <!-- Servicios Especiales -->
            <div class="service-card special">
                <div class="service-icon"><i class="fas fa-truck-ramp-box"></i></div>
                <h3 class="service-title">Recojo Especial</h3>
                <p class="service-desc">Contratación por evento (Pago Único) para recolección de volúmenes excepcionales, desmonte o electrónicos.</p>
                
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> Maquinaria para ayudar Pesada disponible</li>
                    <li><i class="fas fa-check"></i> Disposición certificada</li>
                    <li><i class="fas fa-check"></i> Retiro de Muebles y aparatos electronicos grandes</li>
                    <li><i class="fas fa-check"></i> Limpieza post-recolecion</li>
                    <li><i class="fas fa-check"></i> Un equipo de trabajadores dispuestos a ayudar </li>
                </ul>

                <a href="cotizacion.php?plan=especial" class="btn-hire">Solicitar Cotización</a>
            </div>

        </div>

        <!-- Proceso Logístico -->
        <div class="process-section px-4">
            <h2 class="fw-bold mb-4" style="color: var(--dark); font-size: 28px;">Nuestro Proceso Logístico de Recolección</h2>
            
            <div class="process-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                <div class="process-step">
                    <i class="fas fa-route"></i>
                    <h5 class="fw-bold">1. Planificación</h5>
                    <p class="text-muted small">Mapeo del barrio para optimizar la ruta.</p>
                </div>
                <div class="process-step">
                    <i class="fas fa-trash-can"></i>
                    <h5 class="fw-bold">2. Recojo Exacto</h5>
                    <p class="text-muted small">Recogemos puntualmente en tu puerta o punto de acopio.</p>
                </div>
                <div class="process-step">
                    <i class="fas fa-recycle"></i>
                    <h5 class="fw-bold">3. Segregación</h5>
                    <p class="text-muted small">Traslado y procesamiento en nuestras instalaciones.</p>
                </div>
                <div class="process-step">
                    <i class="fas fa-earth-americas"></i>
                    <h5 class="fw-bold">4. Disp. Final</h5>
                    <p class="text-muted small">Disposición final certificada de lo no reciclable.</p>
                </div>
            </div>
        </div>

        <!-- Tabla Comparativa -->
        <div class="compare-section text-center">
            <h2 class="fw-bold mb-2" style="color: var(--dark); font-size: 28px;">¿Por qué elegir el servicio de EPSIC?</h2>
            <p class="text-muted mb-4">Atención pública para barrios, comercios y condominios.</p>
            
            <div class="table-wrap">
                <table class="compare-table">
                    <thead>
                        <tr>
                            <th style="text-align: left; background: var(--light); color: var(--dark);">Característica del Servicio</th>
                            <th style="background: var(--primary);">Con EPSIC</th>
                            <th style="background: #9A6A6A;">Recolección Informal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: left; font-weight: 600;">Puntualidad de Rutas</td>
                            <td class="eco-col"><i class="fas fa-check-circle"></i> Horarios Garantizados</td>
                            <td style="color: #6B7280;">Incierto (Demoras)</td>
                        </tr>
                        <tr>
                            <td style="text-align: left; font-weight: 600;">Personal</td>
                            <td class="eco-col"><i class="fas fa-check-circle"></i> Uniformado y Asegurado</td>
                            <td style="color: #6B7280;">Irregular</td>
                        </tr>
                        <tr>
                            <td style="text-align: left; font-weight: 600;">Gestión de Multas</td>
                            <td class="eco-col"><i class="fas fa-check-circle"></i> Emisión de Certificados Ambientales</td>
                            <td style="color: #6B7280;">Sin Respaldo Documentario</td>
                        </tr>
                        <tr>
                            <td style="text-align: left; font-weight: 600;">Atención al Vecino</td>
                            <td class="eco-col"><i class="fas fa-check-circle"></i> Canal directo 24/7</td>
                            <td style="color: #6B7280;">Sin canal de reclamos directos</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FAQ -->
        <div class="faq-section">
            <h2 class="fw-bold mb-4 text-center" style="color: var(--dark); font-size: 28px;">Preguntas Frecuentes</h2>
            
            <div class="accordion-item">
                <div class="accordion-header" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'block' ? 'none' : 'block'">
                    ¿Tienen cobertura en toda la región? <i class="fas fa-chevron-down"></i>
                </div>
                <div class="accordion-body">
                    Actualmente operamos en la region de socopo, bum-bum, pedraza. Estamos planificando la expansión hacia mas setores para el segundo semestre del año. Consúltanos para habilitar un circuito si tu asociación es grande.
                </div>
            </div>
            
            <div class="accordion-item">
                <div class="accordion-header" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'block' ? 'none' : 'block'">
                    ¿Cómo se realiza el pago del Plan Urbanización? <i class="fas fa-chevron-down"></i>
                </div>
                <div class="accordion-body">
                    Para realizar el pago del Plan Urbanización, por favor comuníquese con su Jefe de Barrio. Puede pagar en efectivo o por medios digitales; sin embargo, es indispensable coordinar con el encargado para confirmar el proceso y asegurar que su pago sea debidamente registrado.
                </div>
            </div>

            <div class="accordion-item">
                <div class="accordion-header" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'block' ? 'none' : 'block'">
                    ¿Qué ocurre si la basura recolectada sobrepasa mi plan? <i class="fas fa-chevron-down"></i>
                </div>
                <div class="accordion-body">
                    Nuestro personal está capacitado para recoger la carga ordinaria. En caso de volúmenes excepcionales, se registra en el sistema y se emite un recargo por "Recojo Especial" a esa sola vivienda o local comercial tras notificárnos.
                </div>
            </div>
        </div>

        <div class="bottom-banner">
            <div class="content">
                <h2 style="font-size:32px; margin-bottom: 20px;">¿Si estas seguro qué nuestro plan te conviene?</h2>
                <a href="cotizacion.php" class="btn-hire" style="background:var(--primary); color:white; border:none; width:auto; padding: 15px 40px; border-radius:30px;">Calcular mi Cotización <i class="fas fa-arrow-right" style="margin-left: 10px;"></i></a>
            </div>
        </div>

    </div>

<style>
            :root {
            --primary: #4C6E91;
            --primary-dark: #3E5C76;
            --dark: #1F2937;
            --light: #F3F4F6;
            --text-gray: #4B5563;
            --accent: #8A7A5A;
        }

        /* Hero */
        .hero {
            background: linear-gradient(rgba(31, 41, 55, 0.85), rgba(76,110,145, 0.85)), url('/reciclaje/img/servicio.jpeg') center/cover no-repeat;
            padding: 80px 20px;
            text-align: center;
            color: white;
            position: relative;
        }

        .hero h1 {
            font-size: 45px;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .hero p {
            font-size: 18px;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.9;
        }

        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-title h2 {
            font-size: 32px;
            color: var(--dark);
            margin-bottom: 15px;
        }

        .section-title p {
            color: var(--text-gray);
            max-width: 600px;
            margin: 0 auto;
            font-size: 16px;
        }

        /* Pricing/Services Cards */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 60px;
        }

        .service-card {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            text-align: center;
            border-top: 5px solid transparent;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        .service-card.popular {
            border-top-color: var(--primary);
            box-shadow: 0 15px 35px rgba(76,110,145, 0.15);
        }

        .popular-badge {
            background: var(--primary);
            color: white;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 30px;
            position: absolute;
            top: 20px;
            right: -30px;
            transform: rotate(45deg);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .service-icon {
            width: 80px;
            height: 80px;
            background: var(--light);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 35px;
            color: var(--primary);
        }

        .service-card.commercial .service-icon { color: var(--accent); background: #F2EEE7;}
        .service-card.special .service-icon { color: #5A6B7D; background: #E7EDF3;}

        .service-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .service-desc {
            color: var(--text-gray);
            font-size: 14px;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            text-align: left;
            margin-bottom: 30px;
        }

        .feature-list li {
            margin-bottom: 12px;
            font-size: 14px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .feature-list i {
            color: var(--primary);
            font-size: 16px;
        }

        .btn-hire {
            display: inline-block;
            width: 100%;
            padding: 12px;
            background: var(--light);
            color: var(--dark);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: 0.3s;
            box-sizing: border-box;
        }

        .service-card.popular .btn-hire {
            background: var(--primary);
            color: white;
        }

        .btn-hire:hover {
            background: var(--dark);
            color: white;
        }

        /* Banner Final */
        .bottom-banner {
            background: url('/reciclaje/img/WhatsApp-Image-2026-05-05-at-2.53.48-PM.jpeg') center/cover no-repeat;
            border-radius: 16px;
            padding: 60px;
            text-align: center;
            color: white;
            position: relative;
        }

        .bottom-banner::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(31, 41, 55, 0.85);
            border-radius: 16px;
            z-index: 1;
        }

        .bottom-banner .content {
            position: relative;
            z-index: 2;
        }

        /* Secciones extra (Proceso, FAQ, Tabla) */
        .process-section { padding: 60px 0; background-color: white; border-radius: 16px; margin-bottom: 60px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .process-step { margin-bottom: 30px; }
        .process-step i { font-size: 3rem; color: var(--primary); margin-bottom: 15px; }
        
        .faq-section { margin-bottom: 60px; }
        .accordion-item { border: 1px solid #E5E7EB; border-radius: 8px; margin-bottom: 10px; overflow: hidden; }
        .accordion-header { background: white; padding: 20px; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .accordion-body { background: #F9FAFB; padding: 20px; font-size: 14px; display: none; border-top: 1px solid #E5E7EB; color: var(--text-gray); line-height: 1.6;}
        
        .compare-section { margin-bottom: 60px; }
        .compare-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .compare-table th { background: var(--dark); color: white; padding: 15px; text-align: center; }
        .compare-table td { padding: 15px; text-align: center; border-bottom: 1px solid #E5E7EB; }
        .compare-table td.eco-col { background: #EEF2F6; color: var(--primary-dark); font-weight: 600; }
        
        @media (max-width: 900px) {
            .services-grid { grid-template-columns: 1fr; }
            .process-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .process-grid { grid-template-columns: 1fr; }
            .hero h1 { font-size: 32px; }
            .hero p { font-size: 16px; }
            .section-title h2 { font-size: 26px; }
        }
</style>
<?php

$content = ob_get_clean();
include __DIR__ . '/../layouts/public_layout.php';
