<?php

require_once __DIR__ . '/validacion.php';

/* =============================================================
 * Monedas del sistema
 * -------------------------------------------------------------
 * Todos los montos se guardan en DÓLARES (US$). Cada barrio define
 * cuántos bolívares vale 1 dólar en su configuración, y ese valor
 * se usa para mostrar siempre ambos importes en pantalla.
 * ============================================================= */

/** Tasa provisional de emergencia (solo si no hay BD ni internet). */
const TASA_BOLIVAR_PREDETERMINADA = 130.00;

/** Tiempo de validez de la tasa descargada antes de volver a consultarla. */
const TASA_BOLIVAR_TTL_SEGUNDOS = 10800; // 3 horas

/**
 * Barrio asociado al usuario en sesión (encargado de barrio o de calle).
 * Devuelve 0 cuando no puede determinarse (roles globales).
 */
function barrio_id_actual($pdo = null) {
    static $barrioCache = null;
    if ($barrioCache !== null) return $barrioCache;

    if ($pdo === null) {
        global $pdo;
    }
    if (!$pdo instanceof \PDO) return 0;

    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
    $sid = $_SESSION['active_sid'] ?? 'main';
    $user_id = (int)($_SESSION['identities'][$sid]['user_id'] ?? $_SESSION['user_id'] ?? 0);

    $barrioCache = 0;
    if ($user_id > 0) {
        $stmt = $pdo->prepare("SELECT barrio_id FROM detalles_encargado_barrio WHERE usuario_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $barrioCache = (int)$stmt->fetchColumn();

        if ($barrioCache === 0) {
            $stmt = $pdo->prepare("SELECT c.barrio_id FROM detalles_encargado_calle dc
                                   JOIN calles c ON c.id = dc.calle_id
                                   WHERE dc.usuario_id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $barrioCache = (int)$stmt->fetchColumn();
        }
    }
    if ($barrioCache === 0) {
        $barrioCache = (int)$pdo->query("SELECT barrio_id FROM configuraciones_barrio
                                         WHERE precio_bolivar IS NOT NULL AND precio_bolivar > 0
                                         ORDER BY barrio_id LIMIT 1")->fetchColumn();
    }
    return $barrioCache;
}

/**
 * Precio del bolívar (bolívares por 1 US$) configurado para un barrio.
 * Si no se indica barrio se usa el de la sesión o el primero configurado.
 */
function tasa_bolivar($pdo = null, $barrio_id = null) {
    // La caché es global para poder invalidarla cuando se actualiza la tasa.
    $cache =& $GLOBALS['tasa_bolivar_cache'];
    if (!is_array($cache)) $cache = [];

    if ($pdo === null) {
        global $pdo;
    }
    if (!$pdo instanceof \PDO) return TASA_BOLIVAR_PREDETERMINADA;

    if ($barrio_id === null) {
        $barrio_id = barrio_id_actual($pdo);
    }
    $key = (int)$barrio_id;
    if (!array_key_exists($key, $cache)) {
        $valor = null;
        if ($key > 0) {
            $stmt = $pdo->prepare("SELECT precio_bolivar FROM configuraciones_barrio WHERE barrio_id = ?");
            $stmt->execute([$key]);
            $valor = $stmt->fetchColumn();
        }
        if ($valor === false || $valor === null || (float)$valor <= 0) {
            $valor = $pdo->query("SELECT precio_bolivar FROM configuraciones_barrio
                                  WHERE precio_bolivar IS NOT NULL AND precio_bolivar > 0
                                  ORDER BY barrio_id LIMIT 1")->fetchColumn();
        }
        $cache[$key] = ($valor === false || $valor === null || (float)$valor <= 0)
            ? TASA_BOLIVAR_PREDETERMINADA
            : (float)$valor;
    }
    return $cache[$key];
}

/**
 * Descarga el contenido de una URL usando cURL (o file_get_contents como
 * alternativa). Nunca lanza excepciones: devuelve null si falla.
 */
function tasa_bolivar_http_get($url, $timeout = 5) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'EcoReciclaje/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $codigo < 200 || $codigo >= 300) return null;
        return $body;
    }

    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => ['timeout' => $timeout, 'user_agent' => 'EcoReciclaje/1.0'],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return $body === false ? null : $body;
    }

    return null;
}

/**
 * Consulta el precio USD/VES en APIs públicas. Prueba varias fuentes y
 * devuelve la primera válida: ['tasa', 'fuente', 'fecha'] o null.
 */
function tasa_bolivar_consultar_api($timeout = 5) {
    $fuentes = [
        [
            'nombre' => 'ExchangeRate-API',
            'url'    => 'https://open.er-api.com/v6/latest/USD',
            'ruta'   => ['rates', 'VES'],
        ],
        [
            'nombre' => 'Currency API',
            'url'    => 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json',
            'ruta'   => ['usd', 'ves'],
        ],
    ];

    foreach ($fuentes as $fuente) {
        $cuerpo = tasa_bolivar_http_get($fuente['url'], $timeout);
        if ($cuerpo === null) continue;

        $datos = json_decode($cuerpo, true);
        if (!is_array($datos)) continue;

        $valor = $datos;
        foreach ($fuente['ruta'] as $clave) {
            if (!is_array($valor) || !array_key_exists($clave, $valor)) {
                $valor = null;
                break;
            }
            $valor = $valor[$clave];
        }
        $tasa = (float)$valor;
        if ($tasa < 1 || $tasa > 100000000) continue;

        return [
            'tasa'   => round($tasa, 4),
            'fuente' => $fuente['nombre'],
            'fecha'  => (string)($datos['time_last_update_utc'] ?? $datos['date'] ?? date('Y-m-d H:i:s')),
        ];
    }

    return null;
}

/**
 * Actualiza automáticamente la tasa USD/VES guardada en la BD.
 *
 * Respeta un periodo de validez (TASA_BOLIVAR_TTL_SEGUNDOS) para no consultar
 * internet en cada página. Con $forzar = true descarga de inmediato.
 * Si falla la conexión, conserva el valor anterior sin interrumpir el sistema.
 *
 * @return array ok, actualizado, tasa, fuente, mensaje
 */
function tasa_bolivar_actualizar($pdo = null, $forzar = false) {
    static $resultado = null;
    if ($resultado !== null && !$forzar) return $resultado;

    if ($pdo === null) {
        global $pdo;
    }
    if (!$pdo instanceof \PDO) {
        return ['ok' => false, 'actualizado' => false, 'mensaje' => 'Sin conexión a la base de datos.'];
    }

    try {
        $auto = (int)$pdo->query("SELECT COUNT(*) FROM configuraciones_barrio WHERE tasa_automatica = 1")->fetchColumn();
        if ($auto === 0) {
            return $resultado = [
                'ok' => false, 'actualizado' => false,
                'mensaje' => 'La actualización automática está desactivada.',
            ];
        }

        if (!$forzar) {
            $ultima = $pdo->query("SELECT MAX(precio_bolivar_actualizado_en)
                                   FROM configuraciones_barrio WHERE tasa_automatica = 1")->fetchColumn();
            if ($ultima && (time() - strtotime((string)$ultima)) < TASA_BOLIVAR_TTL_SEGUNDOS) {
                $vigente = (float)$pdo->query("SELECT precio_bolivar FROM configuraciones_barrio
                                               WHERE tasa_automatica = 1 ORDER BY barrio_id LIMIT 1")->fetchColumn();
                return $resultado = [
                    'ok' => true, 'actualizado' => false, 'tasa' => $vigente,
                    'mensaje' => 'Tasa vigente.',
                ];
            }
        }
    } catch (\PDOException $e) {
        return ['ok' => false, 'actualizado' => false, 'mensaje' => 'No se pudo consultar la tasa guardada.'];
    }

    $api = tasa_bolivar_consultar_api();
    if ($api === null) {
        return $resultado = [
            'ok' => false, 'actualizado' => false,
            'mensaje' => 'No se pudo obtener la tasa desde internet. Se conserva el valor anterior.',
        ];
    }

    try {
        $pdo->beginTransaction();

        $upd = $pdo->prepare("UPDATE configuraciones_barrio
                              SET precio_bolivar = ?,
                                  precio_bolivar_actualizado_en = NOW(),
                                  precio_bolivar_fuente = ?
                              WHERE tasa_automatica = 1");
        $upd->execute([$api['tasa'], $api['fuente']]);

        // Los barrios que aún no tienen fila de configuración también deben
        // mostrar la tasa vigente.
        $ins = $pdo->prepare("INSERT INTO configuraciones_barrio
                (barrio_id, cuota_mensual, multa_renovacion, precio_bolivar,
                 precio_bolivar_actualizado_en, precio_bolivar_fuente,
                 fecha_limite_pago, tasa_automatica)
             SELECT b.id, 10.00, 5.00, ?, NOW(), ?, LAST_DAY(CURDATE()), 1
               FROM barrios b
               LEFT JOIN configuraciones_barrio c ON c.barrio_id = b.id
              WHERE c.id IS NULL");
        $ins->execute([$api['tasa'], $api['fuente']]);

        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return $resultado = [
            'ok' => false, 'actualizado' => false,
            'mensaje' => 'No se pudo guardar la tasa automática.',
        ];
    }

    $GLOBALS['tasa_bolivar_cache'] = [];
    $resultado = [
        'ok' => true,
        'actualizado' => true,
        'tasa' => $api['tasa'],
        'fuente' => $api['fuente'],
        'fecha_api' => $api['fecha'],
        'mensaje' => 'Tasa actualizada: US$ 1 = Bs. ' . number_format($api['tasa'], 2, '.', ',')
                   . ' (' . $api['fuente'] . ').',
    ];
    return $resultado;
}

/** Texto del importe en dólares. */
function usd($monto) {
    return 'US$ ' . number_format((float)$monto, 2, '.', ',');
}

/** Convierte un monto en dólares a bolívares con la tasa indicada. */
function bolivares($monto, $tasa = null) {
    if ($tasa === null) $tasa = tasa_bolivar();
    return round((float)$monto * (float)$tasa, 2);
}

/** Texto del importe en bolívares (el parámetro siempre está en US$). */
function bs($monto, $tasa = null) {
    return 'Bs. ' . number_format(bolivares($monto, $tasa), 2, '.', ',');
}

/** Texto con ambos importes: dólares y bolívares. */
function monto($monto, $tasa = null) {
    return usd($monto) . ' / ' . bs($monto, $tasa);
}

/** Versión HTML del importe, con los bolívares en una segunda línea. */
function monto_html($monto, $tasa = null) {
    return '<span class="cur-usd">' . usd($monto) . '</span><br>'
         . '<span class="cur-bs">' . bs($monto, $tasa) . '</span>';
}

function check_dashboard_access($allowed_roles = [1]) {
    global $pdo;
    
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }

    // Manejo de identidad activa
    $sid = $_SESSION['active_sid'] ?? 'main';
    $user_id = $_SESSION['identities'][$sid]['user_id'] ?? $_SESSION['user_id'] ?? null;

    if (!$user_id) {
        header("Location: /reciclaje/views/public/login.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT u.id, u.nombre, u.apellido, u.email, u.rol_id, r.nombre as rol_nombre 
                           FROM usuarios u JOIN roles r ON u.rol_id = r.id 
                           WHERE u.id = ? AND u.estado = 'Activo'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !in_array((int)$user['rol_id'], $allowed_roles)) {
        die("Acceso denegado. No tienes permisos para ver este panel.");
    }

    return $user;
}

/**
 * Genera el HTML de las alertas (éxito/error) si están definidas.
 */
function render_dashboard_alerts($exito, $error) {
    // Evita que el layout vuelva a imprimir las mismas alertas.
    $GLOBALS['dashboard_alerts_rendered'] = true;
    if (!$exito && !$error) return;
    include __DIR__ . '/../views/components/dashboard_alerts.php';
}

/**
 * Formatea la identificación de un propietario: V-12345678, E-12345678
 * o el otro identificador (pasaporte, documento extranjero, etc.).
 */
function formato_identificacion($cedula, $tipo = 'V', $otro = '') {
    $cedula = trim((string)$cedula);
    if ($cedula !== '') {
        $tipo = strtoupper(trim((string)$tipo));
        return ($tipo === 'E' ? 'E' : 'V') . '-' . $cedula;
    }

    return trim((string)$otro);
}

/**
 * Etiqueta legible para el tipo de predio registrado.
 */
function tipo_predio_etiqueta($tipo) {
    $etiquetas = [
        'Vivienda'     => 'Vivienda',
        'Comercio'     => 'Comercio',
        'Tienda'       => 'Tienda',
        'Empresa'      => 'Empresa',
        'Urbanizacion' => 'Urbanización',
        'Otro'         => 'Otro',
    ];
    $tipo = (string)$tipo;
    return $etiquetas[$tipo] ?? ($tipo !== '' ? $tipo : 'Vivienda');
}

/**
 * Configuración de tarifas de un barrio (con caché por petición).
 */
function tarifas_barrio($pdo, $barrio_id) {
    static $cache = [];
    $barrio_id = (int)$barrio_id;
    if (!array_key_exists($barrio_id, $cache)) {
        $stmt = $pdo->prepare("SELECT * FROM configuraciones_barrio WHERE barrio_id = ?");
        $stmt->execute([$barrio_id]);
        $cache[$barrio_id] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    return $cache[$barrio_id];
}

/**
 * Monto mensual efectivo de un predio según la tarifa configurada para su tipo.
 * Debe mantenerse sincronizado con mainModel::sqlMontoPredio().
 */
function monto_predio_efectivo($tipo_predio, $cantidad_casas, array $config) {
    $cuota = (float)($config['cuota_mensual'] ?? 10.00);
    $campo = [
        'Comercio'     => 'precio_comercio',
        'Tienda'       => 'precio_tienda',
        'Empresa'      => 'precio_empresa',
        'Urbanizacion' => 'precio_urbanizacion',
        'Otro'         => 'precio_otro',
    ][$tipo_predio] ?? null;

    $monto = ($campo !== null && isset($config[$campo]) && $config[$campo] !== null)
        ? (float)$config[$campo]
        : $cuota;
    if ($tipo_predio === 'Urbanizacion') {
        $monto *= max(1, (int)$cantidad_casas);
    }
    return round($monto, 2);
}

/**
 * Texto descriptivo del cobro de un predio (útil para urbanizaciones).
 */
function etiqueta_monto_predio($tipo_predio, $cantidad_casas, array $config) {
    $monto = monto_predio_efectivo($tipo_predio, $cantidad_casas, $config);
    $tasa = (float)($config['precio_bolivar'] ?? 0) > 0
        ? (float)$config['precio_bolivar']
        : tasa_bolivar();
    if ($tipo_predio === 'Urbanizacion') {
        $porCasa = (float)($config['precio_urbanizacion'] ?? $config['cuota_mensual'] ?? 10.00);
        $casas = max(1, (int)$cantidad_casas);
        return $casas . ' casas × ' . usd($porCasa) . ' = ' . monto($monto, $tasa);
    }
    return monto($monto, $tasa);
}

/**
 * Genera la cuadrícula de estadísticas.
 */
function render_dashboard_stats($stats = []) {
    include __DIR__ . '/../views/components/dashboard_stats.php';
}

/**
 * Estilo visual de cada tipo de notificación.
 * Devuelve fondo, color de texto y etiqueta corta.
 */
function notif_estilo($tipo) {
    $mapa = [
        'solicitud_alta'        => ['bg' => '#E9EFF4', 'color' => '#33485C', 'label' => 'Alta'],
        'solicitud_baja'        => ['bg' => '#F4EDED', 'color' => '#7A4F4F', 'label' => 'Baja'],
        'solicitud_renovacion'  => ['bg' => '#EEF2F6', 'color' => '#3E5C76', 'label' => 'Renovación'],
        'solicitud_rechazada'   => ['bg' => '#F4EDED', 'color' => '#7A4F4F', 'label' => 'Solicitud'],
        'solicitud_alta_rechazada'       => ['bg' => '#F4EDED', 'color' => '#7A4F4F', 'label' => 'Alta rechazada'],
        'solicitud_baja_rechazada'       => ['bg' => '#F4EDED', 'color' => '#7A4F4F', 'label' => 'Baja rechazada'],
        'solicitud_renovacion_rechazada' => ['bg' => '#F4EDED', 'color' => '#7A4F4F', 'label' => 'Renovación rechazada'],
        'solicitud_servicio'    => ['bg' => '#E9EFF4', 'color' => '#33485C', 'label' => 'Servicio'],
        'lote_barrio'           => ['bg' => '#F2EEE7', 'color' => '#6E5F45', 'label' => 'Lote de barrio'],
        'lote_barrio_rechazado' => ['bg' => '#F4EDED', 'color' => '#7A4F4F', 'label' => 'Lote rechazado'],
        'lote_calle'            => ['bg' => '#F2EEE7', 'color' => '#6E5F45', 'label' => 'Lote de calle'],
        'lote_calle_rechazado'  => ['bg' => '#F4EDED', 'color' => '#7A4F4F', 'label' => 'Lote rechazado'],
        'facturas_por_enviar'   => ['bg' => '#F2EEE7', 'color' => '#6E5F45', 'label' => 'Facturación'],
        'certificado'           => ['bg' => '#E9EFF4', 'color' => '#33485C', 'label' => 'Factura'],
        'factura_recibida'      => ['bg' => '#E9EFF4', 'color' => '#33485C', 'label' => 'Factura'],
        'pago_por_verificar'    => ['bg' => '#FFF4E5', 'color' => '#8A5A00', 'label' => 'Pago por verificar'],
        'exencion'              => ['bg' => '#EEF2F6', 'color' => '#3E5C76', 'label' => 'Exención'],
        'reporte_ciudadano'     => ['bg' => '#F2EEE7', 'color' => '#6E5F45', 'label' => 'Reporte'],
        'pago_pendiente'        => ['bg' => '#F4EDED', 'color' => '#7A4F4F', 'label' => 'Pago pendiente'],
        'cobro_rechazado'       => ['bg' => '#F4EDED', 'color' => '#7A4F4F', 'label' => 'Pago rechazado'],
        'solicitud_gestion'     => ['bg' => '#EEF2F6', 'color' => '#3E5C76', 'label' => 'Solicitud de gestión'],
        'solicitud_gestion_rechazada' => ['bg' => '#F4EDED', 'color' => '#7A4F4F', 'label' => 'Solicitud rechazada'],
    ];

    return $mapa[$tipo] ?? ['bg' => '#F3F4F6', 'color' => '#6B7280', 'label' => 'Aviso'];
}

/* =============================================================
 * Reportes: filtros, exportación a Excel e impresión
 * -------------------------------------------------------------
 * Estas funciones permiten que cualquier vista convierta su
 * listado filtrado en un archivo Excel compatible o en un reporte
 * imprimible sin duplicar la lógica de consulta.
 * ============================================================= */

/**
 * Indica si la petición actual solicita descargar el reporte en Excel.
 */
function reporte_exportar_solicitado() {
    $formato = strtolower(trim((string)($_GET['export'] ?? $_GET['formato'] ?? '')));
    return in_array($formato, ['excel', 'xls'], true);
}

/**
 * Construye una URL de la vista actual conservando todos sus filtros.
 * Se usa para los botones de exportar e imprimir.
 */
function reporte_url(array $params = []) {
    $base = $_GET;

    // El formato no debe arrastrarse entre enlaces.
    unset($base['export'], $base['formato']);

    $query = array_merge($base, $params);
    return 'router.php?' . http_build_query($query);
}

/**
 * Barra de acciones reutilizable: exportar a Excel e imprimir.
 * Se oculta automáticamente al imprimir.
 */
function reporte_botonera($textoExcel = 'Exportar Excel', $textoImprimir = 'Imprimir reporte') {
    $urlExcel = htmlspecialchars(reporte_url(['export' => 'excel']), ENT_QUOTES, 'UTF-8');

    return '<div class="report-toolbar unified-report-toolbar no-print">'
        . '<a class="btn-primary report-btn-excel" href="' . $urlExcel . '">📊 ' . htmlspecialchars($textoExcel, ENT_QUOTES, 'UTF-8') . '</a>'
        . '<button type="button" class="btn-primary report-btn-print" onclick="window.print()">🖨️ ' . htmlspecialchars($textoImprimir, ENT_QUOTES, 'UTF-8') . '</button>'
        . '</div>';
}

/**
 * Encabezado visible únicamente al imprimir el reporte.
 */
function reporte_encabezado_impresion($titulo, $subtitulo = '', array $meta = []) {
    $lineasMeta = [];
    foreach ($meta as $etiqueta => $valor) {
        if ($valor === '' || $valor === null || $valor === false) continue;
        $lineasMeta[] = '<strong>' . htmlspecialchars((string)$etiqueta, ENT_QUOTES, 'UTF-8') . ':</strong> '
            . htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
    }

    $html = '<div class="report-print-head">';
    $html .= '<div class="report-print-brand">EPSIC · EcoReciclaje</div>';
    $html .= '<h2>' . htmlspecialchars((string)$titulo, ENT_QUOTES, 'UTF-8') . '</h2>';
    if ($subtitulo !== '') {
        $html .= '<p class="report-print-subtitle">' . htmlspecialchars((string)$subtitulo, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    if ($lineasMeta) {
        $html .= '<div class="report-print-meta">' . implode(' &nbsp;·&nbsp; ', $lineasMeta) . '</div>';
    }
    $html .= '<div class="report-print-date">Generado el ' . date('d/m/Y H:i') . '</div>';
    $html .= '</div>';

    return $html;
}

/**
 * Convierte un valor a texto seguro para una celda de Excel.
 */
function reporte_texto_celda($valor) {
    if (is_bool($valor)) return $valor ? 'Sí' : 'No';
    if ($valor === null) return '';
    return (string)$valor;
}

/**
 * Descarga un listado como archivo Excel (.xls) con formato de tabla.
 *
 * @param string $archivo   Nombre del archivo sin extensión.
 * @param string $titulo    Título principal del reporte.
 * @param array  $columnas  Etiquetas de las columnas. Puede ser lista o
 *                          arreglo asociativo [clave => etiqueta].
 * @param array  $filas     Filas. Cada fila puede ser lista o asociativa.
 * @param array  $meta      Metadatos del reporte (filtros, totales, etc.).
 * @param string $subtitulo Texto secundario opcional.
 */
function reporte_exportar_excel($archivo, $titulo, array $columnas, array $filas, array $meta = [], $subtitulo = '') {
    if (headers_sent()) return;

    $nombre = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string)$archivo);
    $nombre = trim($nombre, '_') ?: 'reporte';
    $nombre .= '_' . date('Ymd_His') . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
    header('Pragma: public');

    // BOM para que Excel respete las tildes y ñ.
    echo "\xEF\xBB\xBF";
    ?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<style>
    body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; color: #1f2937; }
    table { border-collapse: collapse; }
    .report-title { font-size: 17pt; font-weight: bold; color: #176b87; }
    .report-subtitle { font-size: 11pt; color: #4b5563; }
    .report-meta { font-size: 9pt; color: #6b7280; }
    .report-date { font-size: 9pt; color: #6b7280; }
    th {
        background: #176b87; color: #ffffff; font-weight: bold; font-size: 10pt;
        border: 1px solid #0e4f68; padding: 8px; text-align: left;
        vertical-align: middle; white-space: nowrap;
    }
    td { border: 1px solid #cbd5e1; padding: 6px 8px; vertical-align: top; }
    tr:nth-child(even) td { background: #f3f6f8; }
    .num { mso-number-format: '\@'; text-align: right; }
    .text { mso-number-format: '\@'; }
</style>
</head>
<body>
<table>
    <tr><td colspan="<?= max(1, count($columnas)) ?>" class="report-title"><?= htmlspecialchars((string)$titulo, ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php if ($subtitulo !== ''): ?>
    <tr><td colspan="<?= max(1, count($columnas)) ?>" class="report-subtitle"><?= htmlspecialchars((string)$subtitulo, ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php endif; ?>
    <?php foreach ($meta as $etiqueta => $valor): ?>
        <?php if ($valor === '' || $valor === null || $valor === false) continue; ?>
        <tr><td colspan="<?= max(1, count($columnas)) ?>" class="report-meta">
            <strong><?= htmlspecialchars((string)$etiqueta, ENT_QUOTES, 'UTF-8') ?>:</strong>
            <?= htmlspecialchars(reporte_texto_celda($valor), ENT_QUOTES, 'UTF-8') ?>
        </td></tr>
    <?php endforeach; ?>
    <tr><td colspan="<?= max(1, count($columnas)) ?>" class="report-date">Generado el <?= date('d/m/Y H:i') ?></td></tr>
    <tr><td colspan="<?= max(1, count($columnas)) ?>"></td></tr>
    <tr>
        <?php foreach ($columnas as $etiqueta): ?>
            <th><?= htmlspecialchars((string)$etiqueta, ENT_QUOTES, 'UTF-8') ?></th>
        <?php endforeach; ?>
    </tr>
    <?php if (empty($filas)): ?>
        <tr><td colspan="<?= max(1, count($columnas)) ?>">Sin registros para los filtros seleccionados.</td></tr>
    <?php else: ?>
        <?php foreach ($filas as $fila): ?>
            <?php
            // Permitir filas asociativas (clave => valor) o listas.
            if (array_keys($columnas) !== range(0, count($columnas) - 1)) {
                $valores = [];
                foreach (array_keys($columnas) as $clave) {
                    $valores[] = $fila[$clave] ?? '';
                }
            } else {
                $valores = array_values((array)$fila);
            }
            ?>
            <tr>
                <?php foreach ($valores as $valor): ?>
                    <?php
                    $texto = reporte_texto_celda($valor);
                    $clase = is_numeric($texto) && $texto !== '' ? 'num' : 'text';
                    ?>
                    <td class="<?= $clase ?>"><?= htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>
</body>
</html>
    <?php
    exit;
}

/**
 * Ejecuta la descarga de Excel si la vista recibió ?export=excel.
 * Devuelve false cuando no se solicitó exportar.
 */
function reporte_exportar_si_solicitado($archivo, $titulo, array $columnas, array $filas, array $meta = [], $subtitulo = '') {
    if (!reporte_exportar_solicitado()) return false;
    reporte_exportar_excel($archivo, $titulo, $columnas, $filas, $meta, $subtitulo);
    return true;
}

/**
 * Texto relativo en español para una fecha (ej. "hace 5 min").
 */
function notif_tiempo_relativo($fecha) {
    if (empty($fecha)) return '';

    $ts = strtotime((string)$fecha);
    if ($ts === false) return '';

    $diff = time() - $ts;
    if ($diff < 0) $diff = 0;

    if ($diff < 60) {
        return 'ahora mismo';
    }
    if ($diff < 3600) {
        $min = (int)floor($diff / 60);
        return 'hace ' . $min . ' min';
    }
    if ($diff < 86400) {
        $horas = (int)floor($diff / 3600);
        return 'hace ' . $horas . ' h';
    }
    if ($diff < 604800) {
        $dias = (int)floor($diff / 86400);
        return 'hace ' . $dias . ' d';
    }

    return date('d/m/Y', $ts);
}
?>
