<?php

namespace app\models;

use \PDO;

// =============================================
// app/models/notificacionModel.php — Notificaciones por usuario
// Genera una notificación individual por cada pendiente real.
// No guarda estado: si el pendiente se resuelve, desaparece.
// =============================================

class notificacionModel extends mainModel {

    private $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /**
     * Devuelve todas las notificaciones pendientes del usuario.
     *
     * @param array      $user Usuario autenticado (id, rol_id, ...)
     * @param PDO|null   $pdo  Conexión opcional
     * @return array
     */
    public function paraUsuario(array $user, $pdo = null): array {
        if (!$pdo instanceof PDO) {
            $pdo = $this->conectar();
        }

        $rol = (int)($user['rol_id'] ?? 0);
        $uid = (int)($user['id'] ?? 0);

        try {
            switch ($rol) {
                case 1: $items = $this->paraAdmin($pdo); break;
                case 2: $items = $this->paraGestor($pdo); break;
                case 3: $items = $this->paraPersonal($pdo); break;
                case 5: $items = $this->paraBarrio($pdo, $uid); break;
                case 6: $items = $this->paraCalle($pdo, $uid); break;
                default: $items = [];
            }
        } catch (\Throwable $e) {
            $items = [];
        }

        usort($items, function ($a, $b) {
            if ($a['ts'] === $b['ts']) return 0;
            return ($a['ts'] > $b['ts']) ? -1 : 1;
        });

        return $items;
    }

    // ─────────────────────────────────────────
    // ADMIN
    // ─────────────────────────────────────────
    private function paraAdmin(PDO $pdo): array {
        $items = [];

        // Solicitudes de vivienda pendientes (una por cada una)
        $rows = $pdo->query(
            "SELECT s.id, s.tipo, s.fecha_creacion, s.propietario, s.numero_casa, s.vivienda_id,
                    c.nombre AS calle_nombre, b.nombre AS barrio_nombre
             FROM solicitudes_vivienda s
             JOIN calles c ON c.id = s.calle_id
             LEFT JOIN barrios b ON b.id = c.barrio_id
             WHERE s.estado = 'Pendiente'
             ORDER BY s.fecha_creacion DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            [$tipo, $icono, $titulo] = $this->datosSolicitud($r['tipo']);
            $detalle = $this->calleEtiqueta($r['calle_nombre']);
            if (!empty($r['barrio_nombre'])) $detalle .= ' · ' . $r['barrio_nombre'];
            if (!empty($r['propietario']))   $detalle .= ' · ' . $r['propietario'];
            $items[] = $this->item(
                $tipo, $icono, $titulo, $detalle,
                'router.php?page=solicitudes&tipo=' . rawurlencode((string)$r['tipo']) . '&solicitud_id=' . (int)$r['id'] . '#solicitud-' . (int)$r['id'],
                $r['fecha_creacion'], 'Solicitudes'
            );
        }

        // Lotes de barrio enviados
        $rows = $pdo->query(
            "SELECT lb.id, lb.periodo_mes, lb.periodo_anio, lb.fecha_envio,
                    lb.monto_total_recolectado, b.nombre AS barrio_nombre
             FROM lotes_barrio lb
             JOIN barrios b ON b.id = lb.barrio_id
             WHERE lb.estado = 'Enviado'
             ORDER BY lb.fecha_envio DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $items[] = $this->item(
                'lote_barrio', '🏘️', 'Lote de barrio por aprobar',
                $r['barrio_nombre'] . ' · ' . $this->periodo($r['periodo_mes'], $r['periodo_anio']) .
                ' · Bs. ' . number_format((float)$r['monto_total_recolectado'], 2),
                'router.php?page=revisar_lotes&lote_id=' . (int)$r['id'] . '#lote-' . (int)$r['id'],
                $r['fecha_envio'], 'Lotes'
            );
        }

        return $items;
    }

    // ─────────────────────────────────────────
    // GESTOR
    // ─────────────────────────────────────────
    private function paraGestor(PDO $pdo): array {
        $items = [];

        // Lotes de barrio enviados
        $rows = $pdo->query(
            "SELECT lb.id, lb.periodo_mes, lb.periodo_anio, lb.fecha_envio,
                    lb.monto_total_recolectado, b.nombre AS barrio_nombre
             FROM lotes_barrio lb
             JOIN barrios b ON b.id = lb.barrio_id
             WHERE lb.estado = 'Enviado'
             ORDER BY lb.fecha_envio DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $items[] = $this->item(
                'lote_barrio', '🏘️', 'Lote de barrio por aprobar',
                $r['barrio_nombre'] . ' · ' . $this->periodo($r['periodo_mes'], $r['periodo_anio']) .
                ' · Bs. ' . number_format((float)$r['monto_total_recolectado'], 2),
                'router.php?page=revisar_lotes&lote_id=' . (int)$r['id'] . '#lote-' . (int)$r['id'],
                $r['fecha_envio'], 'Lotes'
            );
        }

        // Lotes aprobados a los que aún no se les enviaron facturas al barrio
        $rows = $pdo->query(
            "SELECT lb.id, lb.periodo_mes, lb.periodo_anio, lb.fecha_aprobacion,
                    b.nombre AS barrio_nombre
             FROM lotes_barrio lb
             JOIN barrios b ON b.id = lb.barrio_id
             WHERE lb.estado = 'Aprobado' AND lb.facturas_enviadas_barrio = 0
             ORDER BY lb.fecha_aprobacion DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $items[] = $this->item(
                'facturas_por_enviar', '🧾', 'Lote aprobado sin facturas enviadas',
                $r['barrio_nombre'] . ' · ' . $this->periodo($r['periodo_mes'], $r['periodo_anio']),
                'router.php?page=barrios&lote_id=' . (int)$r['id'] . '#lote-barrio-' . (int)$r['id'],
                $r['fecha_aprobacion'], 'Facturación'
            );
        }

        $rows = $pdo->query(
            "SELECT s.id, s.tipo, s.fecha_creacion, b.nombre AS barrio_nombre,
                    c.nombre AS calle_nombre, s.nombre_calle, s.nombre, s.apellido
             FROM solicitudes_gestion s
             JOIN barrios b ON b.id = s.barrio_id
             LEFT JOIN calles c ON c.id = s.calle_id
             WHERE s.estado = 'Pendiente'
             ORDER BY s.fecha_creacion DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $detalle = $r['barrio_nombre'] . ' · ' . (
                $r['tipo'] === 'NuevaCalle'
                    ? $r['nombre_calle']
                    : ($r['calle_nombre'] . ' · ' . trim($r['nombre'] . ' ' . $r['apellido']))
            );
            $items[] = $this->item(
                'solicitud_gestion', '📝', 'Solicitud del Barrio pendiente', $detalle,
                'router.php?page=solicitudes_barrio&tipo=' . rawurlencode($r['tipo']) . '#solicitud-' . (int)$r['id'],
                $r['fecha_creacion'], 'Solicitudes'
            );
        }

        return $items;
    }

    // ─────────────────────────────────────────
    // PERSONAL / RECOLECTOR
    // ─────────────────────────────────────────
    private function paraPersonal(PDO $pdo): array {
        $items = [];

        foreach ($this->reportesCiudadanos($pdo) as $r) {
            $items[] = $this->item(
                'reporte_ciudadano', '📣',
                $r['estado'] === 'En proceso' ? 'Reporte ciudadano en proceso' : 'Reporte ciudadano pendiente',
                $r['ubicacion_nombre'] . ' · ' . $r['tipo_residuo'],
                'router.php?page=reportes_ciudadanos&reporte_id=' . (int)$r['id'] . '#reporte-' . (int)$r['id'],
                $r['fecha_reporte'], 'Reportes'
            );
        }

        return $items;
    }

    // ─────────────────────────────────────────
    // ENCARGADO DE BARRIO
    // ─────────────────────────────────────────
    private function paraBarrio(PDO $pdo, int $uid): array {
        $items = [];

        $stmt = $pdo->prepare("SELECT barrio_id FROM detalles_encargado_barrio WHERE usuario_id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $bid = (int)$stmt->fetchColumn();
        if ($bid <= 0) return [];

        // Solicitudes pendientes del barrio (una por cada una)
        $stmt = $pdo->prepare(
            "SELECT s.id, s.tipo, s.fecha_creacion, s.propietario, s.vivienda_id,
                    v.estado_servicio, c.nombre AS calle_nombre
             FROM solicitudes_vivienda s
             JOIN calles c ON c.id = s.calle_id
             LEFT JOIN viviendas v ON v.id = s.vivienda_id
             WHERE c.barrio_id = ? AND s.estado = 'Pendiente'
             ORDER BY s.fecha_creacion DESC"
        );
        $stmt->execute([$bid]);
        $solicitudesPendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mostrar las altas como una notificación clara de registros pendientes.
        // Se agrupan para que el encargado identifique la acción sin revisar
        // solicitudes de baja o renovación una por una.
        $registrosPendientes = array_values(array_filter(
            $solicitudesPendientes,
            static fn(array $solicitud): bool => $solicitud['tipo'] === 'Alta'
        ));
        if ($registrosPendientes) {
            $ultima = $registrosPendientes[0];
            $cantidad = count($registrosPendientes);
            $items[] = $this->item(
                'solicitud_alta',
                '📝',
                'Solicitudes de registro pendientes',
                $cantidad . ' registro' . ($cantidad === 1 ? '' : 's') . ' pendiente' . ($cantidad === 1 ? '' : 's') .
                    ' · ' . $this->calleEtiqueta($ultima['calle_nombre']),
                'router.php?page=solicitudes&tipo=Alta&solicitud_id=' . (int)$ultima['id'] . '#solicitud-' . (int)$ultima['id'],
                $ultima['fecha_creacion'],
                'Solicitudes'
            );
        }

        foreach ($solicitudesPendientes as $r) {
            if ($r['tipo'] === 'Alta') {
                continue;
            }
            [$tipo, $icono, $titulo] = $this->datosSolicitud($r['tipo']);

            if ($r['tipo'] === 'Baja') {
                $page = 'ordenes_baja';
            } elseif ($r['tipo'] === 'Renovacion' && !empty($r['estado_servicio']) && $r['estado_servicio'] !== 'Activo') {
                $page = 'solicitudes_renovacion';
            } else {
                $page = 'solicitudes';
            }

            $detalle = $this->calleEtiqueta($r['calle_nombre']);
            if (!empty($r['propietario'])) $detalle .= ' · ' . $r['propietario'];

            $items[] = $this->item(
                $tipo, $icono, $titulo, $detalle,
                'router.php?page=' . $page . '&solicitud_id=' . (int)$r['id'] . '#solicitud-' . (int)$r['id'],
                $r['fecha_creacion'], 'Solicitudes'
            );
        }

        // Lotes de calle enviados al barrio
        $stmt = $pdo->prepare(
            "SELECT lc.id, lc.periodo_mes, lc.periodo_anio, lc.fecha_envio,
                    lc.monto_recolectado, lc.monto_esperado, c.nombre AS calle_nombre
             FROM lotes_calle lc
             JOIN calles c ON c.id = lc.calle_id
             WHERE c.barrio_id = ? AND lc.estado = 'Enviado'
             ORDER BY lc.fecha_envio DESC"
        );
        $stmt->execute([$bid]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $items[] = $this->item(
                'lote_calle', '📋', 'Lote de calle por verificar',
                $r['calle_nombre'] . ' · ' . $this->periodo($r['periodo_mes'], $r['periodo_anio']) .
                ' · Bs. ' . number_format((float)$r['monto_recolectado'], 2),
                'router.php?page=reportar_pago&mes=' . (int)$r['periodo_mes'] . '&anio=' . (int)$r['periodo_anio'] .
                '&lote_id=' . (int)$r['id'] . '#lote-calle-' . (int)$r['id'],
                $r['fecha_envio'], 'Lotes'
            );
        }

        // Pagos registrados por calle que aún requieren verificación del barrio.
        $stmt = $pdo->prepare(
            "SELECT co.id, co.mes, co.anio, co.fecha_emision,
                    v.propietario, v.numero_casa, c.nombre AS calle_nombre
             FROM cobros co
             JOIN viviendas v ON v.id = co.vivienda_id
             JOIN calles c ON c.id = v.calle_id
             WHERE v.barrio_id = ? AND co.estado = 'Pagado'
               AND (co.estado_verificacion = 'Pendiente' OR co.estado_verificacion IS NULL)
             ORDER BY co.fecha_emision DESC, co.id DESC"
        );
        $stmt->execute([$bid]);
        $pagosPorVerificar = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($pagosPorVerificar) {
            $ultimo = $pagosPorVerificar[0];
            $cantidad = count($pagosPorVerificar);
            $items[] = $this->item(
                'pago_por_verificar',
                '💳',
                'Pagos pendientes de verificación',
                $cantidad . ' pago' . ($cantidad === 1 ? '' : 's') . ' · ' .
                    $this->calleEtiqueta($ultimo['calle_nombre']) . ' · ' .
                    $this->periodo($ultimo['mes'], $ultimo['anio']),
                'router.php?page=reportar_pago&mes=' . (int)$ultimo['mes'] .
                    '&anio=' . (int)$ultimo['anio'] . '&cobro_id=' . (int)$ultimo['id'] .
                    '#cobro-' . (int)$ultimo['id'],
                $ultimo['fecha_emision'],
                'Pagos'
            );
        }

        // Exenciones pendientes
        $stmt = $pdo->prepare(
            "SELECT e.id, e.tipo_exencion, e.fecha_creacion,
                    v.numero_casa, v.propietario, c.nombre AS calle_nombre
             FROM exenciones_cobro e
             JOIN viviendas v ON v.id = e.vivienda_id
             JOIN calles c ON c.id = e.calle_id
             WHERE e.barrio_id = ? AND e.estado = 'Pendiente'
             ORDER BY e.fecha_creacion DESC"
        );
        $stmt->execute([$bid]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $items[] = $this->item(
                'exencion', '🛡️', 'Solicitud de exención pendiente',
                'Casa #' . ($r['numero_casa'] ?: '?') . ' · ' . $r['calle_nombre'] . ' · ' . $r['propietario'],
                'router.php?page=exonerados&tab=pendientes&exencion_id=' . (int)$r['id'] . '#exencion-' . (int)$r['id'],
                $r['fecha_creacion'], 'Exenciones'
            );
        }

        // Facturas listas para reenviar a la calle
        $stmt = $pdo->prepare(
            "SELECT lc.id, lc.periodo_mes, lc.periodo_anio, lc.fecha_certificado,
                    c.nombre AS calle_nombre
             FROM lotes_calle lc
             JOIN calles c ON c.id = lc.calle_id
             JOIN lotes_barrio lb ON lb.id = lc.lote_barrio_id
             WHERE lb.barrio_id = ? AND lb.facturas_enviadas_barrio = 1
               AND lc.certificado_generado = 1 AND lc.certificado_enviado_calle = 0
             ORDER BY lc.fecha_certificado DESC"
        );
        $stmt->execute([$bid]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $items[] = $this->item(
                'certificado', '📄', 'Factura por enviar a la calle',
                $r['calle_nombre'] . ' · ' . $this->periodo($r['periodo_mes'], $r['periodo_anio']),
                'router.php?page=certificados&lote_calle_id=' . (int)$r['id'] . '#factura-calle-' . (int)$r['id'],
                $r['fecha_certificado'], 'Facturas'
            );
        }

        // Lotes de barrio rechazados por el gestor
        $stmt = $pdo->prepare(
            "SELECT id, periodo_mes, periodo_anio, fecha_envio, fecha_aprobacion, observaciones_gestor
             FROM lotes_barrio
             WHERE barrio_id = ? AND estado = 'Rechazado'
             ORDER BY COALESCE(fecha_aprobacion, fecha_envio) DESC"
        );
        $stmt->execute([$bid]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $detalle = $this->periodo($r['periodo_mes'], $r['periodo_anio']);
            if (!empty($r['observaciones_gestor'])) {
                $detalle .= ' · ' . $r['observaciones_gestor'];
            }
            $items[] = $this->item(
                'lote_barrio_rechazado', '❌', 'Lote de barrio rechazado', $detalle,
                'router.php?page=reportar_pago&mes=' . (int)$r['periodo_mes'] . '&anio=' . (int)$r['periodo_anio'] .
                '&lote_barrio_id=' . (int)$r['id'] . '#lote-barrio-' . (int)$r['id'],
                $r['fecha_aprobacion'] ?: $r['fecha_envio'], 'Lotes'
            );
        }

        $stmt = $pdo->prepare(
            "SELECT id, tipo, estado, fecha_revision, motivo_rechazo, nombre_calle
             FROM solicitudes_gestion
             WHERE solicitado_por = ? AND estado IN ('Aprobada','Rechazada')
             ORDER BY fecha_revision DESC LIMIT 10"
        );
        $stmt->execute([$uid]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rechazada = $r['estado'] === 'Rechazada';
            $detalle = $r['tipo'] === 'NuevaCalle' ? $r['nombre_calle'] : 'Encargado de calle';
            if ($rechazada && !empty($r['motivo_rechazo'])) {
                $detalle .= ' · ' . $r['motivo_rechazo'];
            }
            $items[] = $this->item(
                $rechazada ? 'solicitud_gestion_rechazada' : 'solicitud_gestion',
                $rechazada ? '❌' : '✓',
                $rechazada ? 'Solicitud rechazada' : 'Solicitud aprobada',
                $detalle,
                'router.php?page=solicitar_gestion#solicitud-' . (int)$r['id'],
                $r['fecha_revision'], 'Solicitudes'
            );
        }

        return $items;
    }

    // ─────────────────────────────────────────
    // ENCARGADO DE CALLE
    // ─────────────────────────────────────────
    private function paraCalle(PDO $pdo, int $uid): array {
        $items = [];

        $stmt = $pdo->prepare("SELECT calle_id FROM detalles_encargado_calle WHERE usuario_id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $cid = (int)$stmt->fetchColumn();
        if ($cid <= 0) return [];

        $mesActual = (int)date('n');
        $anioActual = (int)date('Y');
        $nombreMes = $this->meses[$mesActual] ?? date('m');

        // Lotes de calle rechazados
        $stmt = $pdo->prepare(
            "SELECT id, periodo_mes, periodo_anio, fecha_evaluacion, actualizado_en, observaciones
             FROM lotes_calle
             WHERE calle_id = ? AND estado = 'Rechazado'
             ORDER BY COALESCE(fecha_evaluacion, actualizado_en) DESC"
        );
        $stmt->execute([$cid]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $detalle = $this->periodo($r['periodo_mes'], $r['periodo_anio']);
            if (!empty($r['observaciones'])) {
                $detalle .= ' · ' . $r['observaciones'];
            }
            $items[] = $this->item(
                'lote_calle_rechazado', '❌', 'Lote de calle rechazado', $detalle,
                'router.php?page=reportar_pago&mes=' . (int)$r['periodo_mes'] . '&anio=' . (int)$r['periodo_anio'] . '#estado-lote',
                $r['fecha_evaluacion'] ?: $r['actualizado_en'], 'Lotes'
            );
        }

        // Cobros rechazados (por el barrio o el gestor)
        $stmt = $pdo->prepare(
            "SELECT co.id, co.vivienda_id, co.mes, co.anio, co.estado, co.estado_verificacion,
                    co.motivo_rechazo, co.fecha_emision, v.numero_casa, v.propietario
             FROM cobros co
             JOIN viviendas v ON v.id = co.vivienda_id
             WHERE v.calle_id = ? AND (co.estado = 'Rechazado' OR co.estado_verificacion = 'Rechazado')
             ORDER BY co.fecha_emision DESC"
        );
        $stmt->execute([$cid]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $detalle = 'Casa #' . ($r['numero_casa'] ?: '?') . ' · ' . $r['propietario'] .
                       ' · ' . $this->periodo($r['mes'], $r['anio']);
            if (!empty($r['motivo_rechazo'])) {
                $detalle .= ' · ' . $r['motivo_rechazo'];
            }
            $items[] = $this->item(
                'cobro_rechazado', '⚠️', 'Pago rechazado', $detalle,
                'router.php?page=viviendas&vivienda_id=' . (int)$r['vivienda_id'] . '#vivienda-' . (int)$r['vivienda_id'],
                $r['fecha_emision'], 'Pagos'
            );
        }

        // Viviendas activas sin pago del mes actual (una por cada una)
        $stmt = $pdo->prepare(
            "SELECT v.id, v.numero_casa, v.propietario
             FROM viviendas v
             WHERE v.calle_id = ? AND v.estado_servicio = 'Activo' AND v.exento_cobro = 0
               AND NOT EXISTS (
                   SELECT 1 FROM cobros co
                   WHERE co.vivienda_id = v.id AND co.mes = ? AND co.anio = ? AND co.estado = 'Pagado'
               )
             ORDER BY CAST(v.numero_casa AS UNSIGNED), v.numero_casa"
        );
        $stmt->execute([$cid, $mesActual, $anioActual]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $items[] = $this->item(
                'pago_pendiente', '🏠',
                'Casa #' . ($r['numero_casa'] ?: '?') . ' sin pago de ' . $nombreMes,
                $r['propietario'],
                'router.php?page=viviendas&vivienda_id=' . (int)$r['id'] . '#vivienda-' . (int)$r['id'],
                null, 'Pagos'
            );
        }

        // Facturas recibidas recientemente
        $stmt = $pdo->prepare(
            "SELECT id, periodo_mes, periodo_anio, fecha_certificado, actualizado_en
             FROM lotes_calle
             WHERE calle_id = ? AND certificado_enviado_calle = 1
             ORDER BY COALESCE(fecha_certificado, actualizado_en) DESC
             LIMIT 10"
        );
        $stmt->execute([$cid]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $items[] = $this->item(
                'factura_recibida', '📄', 'Factura recibida',
                $this->periodo($r['periodo_mes'], $r['periodo_anio']),
                'router.php?page=facturas&lote_calle_id=' . (int)$r['id'] . '#factura-' . (int)$r['id'],
                $r['fecha_certificado'] ?: $r['actualizado_en'], 'Facturas'
            );
        }

        // Solicitudes propias rechazadas
        $stmt = $pdo->prepare(
            "SELECT id, tipo, propietario, fecha_creacion, fecha_revision
             FROM solicitudes_vivienda
             WHERE creado_por = ? AND estado = 'Rechazado'
             ORDER BY COALESCE(fecha_revision, fecha_creacion) DESC
             LIMIT 20"
        );
        $stmt->execute([$uid]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            [$tipo, $icono, $titulo] = $this->datosSolicitudRechazada($r['tipo']);
            $detalle = $titulo;
            if (!empty($r['propietario'])) $detalle .= ' · ' . $r['propietario'];
            $items[] = $this->item(
                $tipo, $icono, $titulo, $detalle,
                'router.php?page=historial_solicitudes&solicitud_id=' . (int)$r['id'] . '#solicitud-' . (int)$r['id'],
                $r['fecha_revision'] ?: $r['fecha_creacion'], 'Solicitudes'
            );
        }

        return $items;
    }

    // ─────────────────────────────────────────
    // Auxiliares
    // ─────────────────────────────────────────
    private function reportesCiudadanos(PDO $pdo): array {
        return $pdo->query(
            "SELECT id, ubicacion_nombre, tipo_residuo, estado, fecha_reporte
             FROM reportes
             WHERE estado IN ('Pendiente', 'En proceso')
             ORDER BY fecha_reporte DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    private function datosSolicitud(string $tipo): array {
        switch ($tipo) {
            case 'Baja':
                return ['solicitud_baja', '🚫', 'Solicitud de baja'];
            case 'Renovacion':
                return ['solicitud_renovacion', '🔄', 'Solicitud de renovación'];
            case 'Alta':
            default:
                return ['solicitud_alta', '📩', 'Solicitud de alta'];
        }
    }

    private function datosSolicitudRechazada(string $tipo): array {
        switch ($tipo) {
            case 'Baja':
                return ['solicitud_baja_rechazada', '🚫', 'Tu solicitud de baja fue rechazada'];
            case 'Renovacion':
                return ['solicitud_renovacion_rechazada', '🔄', 'Tu solicitud de renovación fue rechazada'];
            case 'Alta':
            default:
                return ['solicitud_alta_rechazada', '📩', 'Tu solicitud de alta fue rechazada'];
        }
    }

    private function calleEtiqueta($nombre): string {
        $nombre = trim((string)$nombre);
        if ($nombre === '') return 'Calle';
        return preg_match('/^calle\b/i', $nombre) ? $nombre : 'Calle ' . $nombre;
    }

    private function periodo($mes, $anio): string {
        $mes = (int)$mes;
        $anio = (int)$anio;
        return ($this->meses[$mes] ?? 'Mes ' . $mes) . ' ' . $anio;
    }

    private function item(string $tipo, string $icono, string $titulo, string $detalle, string $link, $fecha = null, string $grupo = 'Otros'): array {
        $ts = 0;
        if (!empty($fecha)) {
            $tmp = strtotime((string)$fecha);
            if ($tmp !== false) $ts = $tmp;
        }

        return [
            'tipo'     => $tipo,
            'icono'    => $icono,
            'titulo'   => $titulo,
            'detalle'  => $detalle,
            'link'     => $link,
            'fecha'    => $fecha,
            'grupo'    => $grupo,
            'ts'       => $ts,
        ];
    }
}
