<?php

namespace app\controllers;
use app\models\mainModel;

// =============================================
// app/controllers/gestorController.php
// Acciones POST del Gestor/Administrador (Rol 1, 2)
// =============================================

class gestorController extends mainModel {

    public function procesarAcciones() {
        global $user, $mensaje_exito, $mensaje_error;

        $this->asegurarColumnasPagos();

        $action = $_POST['action'] ?? $_POST['form_type'] ?? null;

        if (in_array($action, ['aprobar_solicitud_gestion', 'rechazar_solicitud_gestion'], true)) {
            $solicitud_id = (int)($_POST['solicitud_id'] ?? 0);
            try {
                $pdo = $this->conectar();
                $pdo->beginTransaction();
                $solicitud = $pdo->prepare("SELECT * FROM solicitudes_gestion WHERE id=? AND estado='Pendiente' FOR UPDATE");
                $solicitud->execute([$solicitud_id]);
                $solicitud = $solicitud->fetch(\PDO::FETCH_ASSOC);
                if (!$solicitud) throw new \Exception("La solicitud no existe o ya fue procesada.");
                if ($action === 'rechazar_solicitud_gestion') {
                    $motivo = trim($_POST['motivo_rechazo'] ?? '');
                    if ($motivo === '') throw new \Exception("Indique el motivo del rechazo.");
                    $pdo->prepare("UPDATE solicitudes_gestion SET estado='Rechazada', motivo_rechazo=?, procesado_por=?, fecha_revision=NOW() WHERE id=?")
                        ->execute([$motivo, $user['id'], $solicitud_id]);
                    $pdo->commit();
                    $mensaje_exito = "Solicitud rechazada.";
                } elseif ($solicitud['tipo'] === 'NuevaCalle') {
                    $existe = $pdo->prepare("SELECT id FROM calles WHERE barrio_id=? AND LOWER(TRIM(nombre))=LOWER(TRIM(?)) LIMIT 1");
                    $existe->execute([$solicitud['barrio_id'], $solicitud['nombre_calle']]);
                    if ($existe->fetchColumn()) throw new \Exception("La calle ya existe en ese barrio.");
                    $pdo->prepare("INSERT INTO calles (nombre,barrio_id,tipo_via,descripcion) VALUES (?,?,?,?)")
                        ->execute([$solicitud['nombre_calle'], $solicitud['barrio_id'], $solicitud['tipo_via'] ?: 'Calle', $solicitud['descripcion_calle'] ?: null]);
                    $pdo->prepare("UPDATE solicitudes_gestion SET estado='Aprobada', procesado_por=?, fecha_revision=NOW() WHERE id=?")
                        ->execute([$user['id'], $solicitud_id]);
                    $pdo->commit();
                    $mensaje_exito = "Calle creada y solicitud aprobada.";
                } else {
                    $ocupadoStmt = $pdo->prepare("SELECT usuario_id FROM detalles_encargado_calle WHERE calle_id=? LIMIT 1");
                    $ocupadoStmt->execute([$solicitud['calle_id']]);
                    if ($ocupadoStmt->fetchColumn()) throw new \Exception("La calle ya tiene encargado asignado.");
                    $email = trim((string)$solicitud['email']);
                    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $email = 'encargado.' . bin2hex(random_bytes(8)) . '@epsic.local';
                    }
                    $emailStmt = $pdo->prepare("SELECT id FROM usuarios WHERE email=? LIMIT 1");
                    $emailStmt->execute([$email]);
                    if ($emailStmt->fetchColumn()) throw new \Exception("El correo ya está registrado.");
                    $pdo->prepare("INSERT INTO usuarios (nombre,apellido,email,password_hash,rol_id) VALUES (?,?,?,?,6)")
                        ->execute([$solicitud['nombre'], $solicitud['apellido'], $email, password_hash('123456', PASSWORD_DEFAULT)]);
                    $usuario_id = (int)$pdo->lastInsertId();
                    $pdo->prepare("INSERT INTO detalles_encargado_calle (usuario_id,calle_id,dni,telefono) VALUES (?,?,?,?)")
                        ->execute([$usuario_id, $solicitud['calle_id'], $solicitud['dni'], $solicitud['telefono']]);
                    $pdo->prepare("UPDATE solicitudes_gestion SET estado='Aprobada', procesado_por=?, fecha_revision=NOW() WHERE id=?")
                        ->execute([$user['id'], $solicitud_id]);
                    $pdo->commit();
                    $mensaje_exito = "Encargado de calle creado y asignado. Correo: {$email}. Contraseña inicial: 123456.";
                }
            } catch (\Throwable $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $mensaje_error = "No se pudo procesar la solicitud: " . $e->getMessage();
            }
        }

        if (in_array($action, ['crear_calle_gestor', 'crear_encargado_calle_gestor'], true)) {
            try {
                $pdo = $this->conectar();
                $pdo->beginTransaction();
                $barrio_id = (int)($_POST['barrio_id'] ?? 0);
                if ($barrio_id <= 0) throw new \Exception("Seleccione un barrio.");
                if ($action === 'crear_calle_gestor') {
                    $nombre = trim($_POST['nombre_calle'] ?? '');
                    if ($nombre === '') throw new \Exception("Indique el nombre de la calle.");
                    $chk = $pdo->prepare("SELECT id FROM calles WHERE barrio_id=? AND LOWER(TRIM(nombre))=LOWER(TRIM(?)) LIMIT 1");
                    $chk->execute([$barrio_id, $nombre]);
                    if ($chk->fetchColumn()) throw new \Exception("La calle ya existe en ese barrio.");
                    $pdo->prepare("INSERT INTO calles (nombre,barrio_id,tipo_via,descripcion) VALUES (?,?,?,?)")
                        ->execute([$nombre, $barrio_id, $_POST['tipo_via'] ?? 'Calle', trim($_POST['descripcion_calle'] ?? '') ?: null]);
                    $mensaje_exito = "Calle creada directamente por el Gestor.";
                } else {
                    $calle_id = (int)($_POST['calle_id'] ?? 0);
                    $nombre = trim($_POST['nombre'] ?? '');
                    $apellido = trim($_POST['apellido'] ?? '');
                    $email = trim($_POST['email'] ?? '');
                    if ($calle_id <= 0 || $nombre === '' || $apellido === '') throw new \Exception("Complete todos los datos del encargado.");
                    if ($email === '') {
                        $email = 'encargado.' . bin2hex(random_bytes(8)) . '@epsic.local';
                    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new \Exception("El correo electrónico no tiene un formato válido.");
                    }
                    $valid = $pdo->prepare("SELECT id FROM calles WHERE id=? AND barrio_id=?");
                    $valid->execute([$calle_id, $barrio_id]);
                    if (!$valid->fetchColumn()) throw new \Exception("La calle no pertenece al barrio.");
                    $busy = $pdo->prepare("SELECT usuario_id FROM detalles_encargado_calle WHERE calle_id=?");
                    $busy->execute([$calle_id]);
                    if ($busy->fetchColumn()) throw new \Exception("La calle ya tiene encargado.");
                    $emailChk = $pdo->prepare("SELECT id FROM usuarios WHERE email=?");
                    $emailChk->execute([$email]);
                    if ($emailChk->fetchColumn()) throw new \Exception("El correo ya está registrado.");
                    $pdo->prepare("INSERT INTO usuarios (nombre,apellido,email,password_hash,rol_id) VALUES (?,?,?,?,6)")
                        ->execute([$nombre, $apellido, $email, password_hash('123456', PASSWORD_DEFAULT)]);
                    $uid = (int)$pdo->lastInsertId();
                    $pdo->prepare("INSERT INTO detalles_encargado_calle (usuario_id,calle_id,dni,telefono) VALUES (?,?,?,?)")
                        ->execute([$uid, $calle_id, trim($_POST['dni'] ?? ''), trim($_POST['telefono'] ?? '')]);
                    $mensaje_exito = "Encargado de calle creado directamente. Correo: {$email}. Contraseña inicial: 123456.";
                }
                $pdo->commit();
            } catch (\Throwable $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $mensaje_error = $e->getMessage();
            }
        }

        // 1. Aprobar Lote de Barrio → genera Recibo de Finiquito
        if ($action === 'aprobar_lote_barrio') {
            $lote_id = (int)$_POST['lote_id'];
            $pdo = null;
            try {
                $pdo = $this->conectar();
                $pdo->beginTransaction();

                $lote = $this->ejecutarConsulta("SELECT * FROM lotes_barrio WHERE id=?", [$lote_id])
                    ->fetch(\PDO::FETCH_ASSOC);

                if (!$lote) throw new \Exception("Lote no encontrado.");
                if ($lote['estado'] !== 'Enviado') throw new \Exception("Solo se pueden aprobar lotes en estado 'Enviado'.");

                // Aprobar el lote
                $this->ejecutarConsulta(
                    "UPDATE lotes_barrio SET estado='Aprobado', gestor_id=?, fecha_aprobacion=NOW(), recibo_generado=1 WHERE id=?",
                    [$user['id'], $lote_id]
                );

                // Generar número de recibo único: RF-YYYYMM-BARRIO-RAND
                $numero_recibo = sprintf(
                    'RF-%04d%02d-%03d-%04d',
                    $lote['periodo_anio'], $lote['periodo_mes'],
                    $lote['barrio_id'], rand(1000, 9999)
                );

                // Insertar recibo de finiquito
                $this->ejecutarConsulta(
                    "INSERT INTO recibos_finiquito
                     (lote_barrio_id, barrio_id, periodo_mes, periodo_anio, monto_aprobado, generado_por, numero_recibo)
                     VALUES (?,?,?,?,?,?,?)",
                    [
                        $lote_id, $lote['barrio_id'], $lote['periodo_mes'], $lote['periodo_anio'],
                        $lote['monto_total_recolectado'], $user['id'], $numero_recibo
                    ]
                );

                // Vincular también lotes de calle antiguos que no quedaron enlazados
                // cuando el Encargado de Barrio envió el lote.
                $this->ejecutarConsulta(
                    "UPDATE lotes_calle
                     SET lote_barrio_id = ?
                     WHERE lote_barrio_id IS NULL
                       AND barrio_id = ?
                       AND periodo_mes = ?
                       AND periodo_anio = ?
                       AND estado IN ('Aprobado', 'Enviado')",
                    [
                        $lote_id, $lote['barrio_id'],
                        $lote['periodo_mes'], $lote['periodo_anio']
                    ]
                );

                // Generar certificados para cada calle incluida en el lote
                $this->ejecutarConsulta(
                    "UPDATE lotes_calle SET certificado_generado=1, fecha_certificado=NOW() WHERE lote_barrio_id=?",
                    [$lote_id]
                );

                // Aplicar multas/suspensiones a casas morosas del periodo
                $this->aplicarSancionesAlCerrar($pdo, $lote['barrio_id'], $lote['periodo_mes'], $lote['periodo_anio']);

                $pdo->commit();
                $mensaje_exito = "Lote aprobado. Recibo #{$numero_recibo} generado.";
            } catch (\Exception $e) {
                if ($pdo) $pdo->rollBack();
                $mensaje_error = $e->getMessage();
            }
        }

        // 2. Rechazar Lote de Barrio (devuelve al Encargado de Barrio)
        if ($action === 'rechazar_lote_barrio') {
            $lote_id = (int)$_POST['lote_id'];
            $motivo  = trim($_POST['motivo_rechazo'] ?? '');
            try {
                if (empty($motivo)) throw new \Exception("Debe indicar un motivo de rechazo.");

                $lote = $this->ejecutarConsulta("SELECT * FROM lotes_barrio WHERE id=?", [$lote_id])
                    ->fetch(\PDO::FETCH_ASSOC);

                if (!$lote || $lote['estado'] !== 'Enviado') {
                    throw new \Exception("Solo se pueden rechazar lotes en estado 'Enviado'.");
                }

                $pdo = $this->conectar();
                $pdo->beginTransaction();

                $this->ejecutarConsulta(
                    "UPDATE lotes_barrio SET estado='Rechazado', observaciones_gestor=?, gestor_id=? WHERE id=?",
                    [$motivo, $user['id'], $lote_id]
                );

                // Desenlazar lotes de calle para que el Barrio pueda reenviar
                $this->ejecutarConsulta(
                    "UPDATE lotes_calle SET lote_barrio_id=NULL WHERE lote_barrio_id=?",
                    [$lote_id]
                );

                $pdo->commit();
                $mensaje_exito = "Lote rechazado. El Encargado de Barrio puede corregir y reenviar.";
            } catch (\Exception $e) {
                if (isset($pdo)) $pdo->rollBack();
                $mensaje_error = $e->getMessage();
            }
        }

        // 3. Enviar facturas/certificados al Encargado de Barrio
        if ($action === 'enviar_facturas_barrio') {
            $lote_id = (int)$_POST['lote_id'];
            try {
                $lote = $this->ejecutarConsulta("SELECT * FROM lotes_barrio WHERE id=?", [$lote_id])
                    ->fetch(\PDO::FETCH_ASSOC);
                if (!$lote) throw new \Exception("Lote no encontrado.");
                if ($lote['estado'] !== 'Aprobado') throw new \Exception("Solo se pueden enviar lotes aprobados.");

                $this->ejecutarConsulta(
                    "UPDATE lotes_barrio SET facturas_enviadas_barrio=1 WHERE id=?",
                    [$lote_id]
                );
                $mensaje_exito = $lote['facturas_enviadas_barrio']
                    ? "Facturas reenviadas al Encargado de Barrio."
                    : "Facturas enviadas al Encargado de Barrio. Ahora puede verlas y descargarlas desde su panel.";
            } catch (\Exception $e) {
                $mensaje_error = $e->getMessage();
            }
        }

    }

    // ─── Aplicar multas/suspensiones al cerrar un ciclo ──────────────────────

    private function aplicarSancionesAlCerrar(\PDO $pdo, int $barrio_id, int $mes, int $anio): void {
        // Obtener viviendas activas del barrio que NO tienen cobro pagado en el periodo
        $montoSql = self::sqlMontoPredio();
        $stmt = $pdo->prepare(
            "SELECT v.id, {$montoSql} AS cuota_mensual FROM viviendas v
             LEFT JOIN configuraciones_barrio cb ON v.barrio_id = cb.barrio_id
             WHERE v.barrio_id = ? AND v.estado_servicio = 'Activo' AND v.exento_cobro = 0
             AND v.id NOT IN (
                 SELECT DISTINCT c.vivienda_id FROM cobros c
                 WHERE c.mes = ? AND c.anio = ? AND c.estado = 'Pagado'
             )"
        );
        $stmt->execute([$barrio_id, $mes, $anio]);
        $morosas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($morosas as $v) {
            $vid   = $v['id'];
            $monto = $v['cuota_mensual'] ?? 0;

            // Suspender servicio
            $pdo->prepare("UPDATE viviendas SET estado_servicio='Suspendido' WHERE id=?")->execute([$vid]);

            // Insertar cobro Pendiente del mes si no existe
            $existe = $pdo->prepare(
                "SELECT id FROM cobros WHERE vivienda_id=? AND mes=? AND anio=? AND tipo_cobro='Servicio'"
            );
            $existe->execute([$vid, $mes, $anio]);
            if (!$existe->fetch()) {
                $pdo->prepare(
                    "INSERT INTO cobros (vivienda_id, mes, anio, monto, fecha_emision, fecha_vencimiento, estado, tipo_cobro)
                     VALUES (?,?,?,?,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 30 DAY),'Pendiente','Servicio')"
                )->execute([$vid, $mes, $anio, $monto]);
            }
        }
    }
}
