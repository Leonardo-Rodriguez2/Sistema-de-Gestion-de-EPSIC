<?php

namespace app\controllers;
use app\models\mainModel;

// =============================================
// app/controllers/barrioController.php - ROL 5
// =============================================

class barrioController extends mainModel {

    public function procesarAcciones() {
        global $user, $mensaje_exito, $mensaje_error;

        // Aseguramos columnas antes de procesar cualquier acción
        $this->asegurarColumnasPagos();

        $action = $_POST['action'] ?? $_POST['form_type'] ?? null;
        if (!$action) return;

        if (in_array($action, ['solicitar_nueva_calle', 'solicitar_encargado_calle'], true)) {
            try {
                $barrio_id = $this->obtenerBarrioId($user['id']);
                $tipo = $action === 'solicitar_nueva_calle' ? 'NuevaCalle' : 'NuevoEncargadoCalle';
                $calle_id = (int)($_POST['calle_id'] ?? 0);
                $nombre = trim($_POST['nombre'] ?? '');
                $apellido = trim($_POST['apellido'] ?? '');
                $email = trim($_POST['email'] ?? '');

                if ($tipo === 'NuevaCalle') {
                    $nombreCalle = trim($_POST['nombre_calle'] ?? '');
                    if ($nombreCalle === '') throw new \Exception("Indique el nombre de la nueva calle.");
                    if ($this->ejecutarConsulta(
                        "SELECT id FROM calles WHERE barrio_id = ? AND LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1",
                        [$barrio_id, $nombreCalle]
                    )->fetchColumn()) {
                        throw new \Exception("Ya existe una calle con ese nombre en tu barrio.");
                    }
                    $duplicada = $this->ejecutarConsulta(
                        "SELECT id FROM solicitudes_gestion WHERE tipo='NuevaCalle' AND barrio_id=? AND estado='Pendiente'
                         AND LOWER(TRIM(nombre_calle))=LOWER(TRIM(?)) LIMIT 1",
                        [$barrio_id, $nombreCalle]
                    )->fetchColumn();
                    if ($duplicada) throw new \Exception("Ya existe una solicitud pendiente para esa calle.");
                    $params = [
                        'NuevaCalle', $barrio_id, null, $user['id'], $nombreCalle,
                        $_POST['tipo_via'] ?? 'Calle', trim($_POST['descripcion_calle'] ?? ''),
                        null, null, null, null, trim($_POST['telefono'] ?? '')
                    ];
                } else {
                    if ($calle_id <= 0 || $nombre === '' || $apellido === '') {
                        throw new \Exception("Complete la calle, nombre y apellido del nuevo encargado.");
                    }
                    $calle = $this->ejecutarConsulta(
                        "SELECT id FROM calles WHERE id=? AND barrio_id=? LIMIT 1",
                        [$calle_id, $barrio_id]
                    )->fetchColumn();
                    if (!$calle) throw new \Exception("La calle no pertenece a tu barrio.");
                    if ($this->ejecutarConsulta("SELECT usuario_id FROM detalles_encargado_calle WHERE calle_id=? LIMIT 1", [$calle_id])->fetchColumn()) {
                        throw new \Exception("La calle ya tiene un encargado asignado.");
                    }
                    if ($this->ejecutarConsulta(
                        "SELECT id FROM solicitudes_gestion WHERE tipo='NuevoEncargadoCalle' AND calle_id=? AND estado='Pendiente' LIMIT 1",
                        [$calle_id]
                    )->fetchColumn()) {
                        throw new \Exception("Ya existe una solicitud pendiente para esa calle.");
                    }
                    if ($email !== '') {
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            throw new \Exception("El correo electrónico no tiene un formato válido.");
                        }
                        if ($this->ejecutarConsulta("SELECT id FROM usuarios WHERE email=? LIMIT 1", [$email])->fetchColumn()) {
                            throw new \Exception("El correo electrónico ya está registrado.");
                        }
                        if ($this->ejecutarConsulta(
                            "SELECT id FROM solicitudes_gestion WHERE email=? AND estado='Pendiente' LIMIT 1",
                            [$email]
                        )->fetchColumn()) {
                            throw new \Exception("Ya existe una solicitud pendiente con ese correo.");
                        }
                    }
                    $params = [
                        'NuevoEncargadoCalle', $barrio_id, $calle_id, $user['id'], null, null, null,
                        $nombre, $apellido, ($email !== '' ? $email : null), trim($_POST['dni'] ?? ''), trim($_POST['telefono'] ?? '')
                    ];
                }

                $this->ejecutarConsulta(
                    "INSERT INTO solicitudes_gestion
                     (tipo,barrio_id,calle_id,solicitado_por,nombre_calle,tipo_via,descripcion_calle,nombre,apellido,email,dni,telefono)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                    $params
                );
                $mensaje_exito = "Solicitud enviada al Gestor para su aprobación.";
            } catch (\Throwable $e) {
                $mensaje_error = $e->getMessage();
            }
        }

        // 1. Procesar Solicitud de Vivienda (Alta/Baja/Renovación)
        if ($action === 'procesar_solicitud') {
            $solicitud_id = (int)$_POST['solicitud_id'];
            $estado       = $_POST['estado'];
            try {
                $pdo = $this->conectar();
                $pdo->beginTransaction();

                $solicitud = $this->ejecutarConsulta("SELECT * FROM solicitudes_vivienda WHERE id = ?", [$solicitud_id])->fetch(\PDO::FETCH_ASSOC);
                if (!$solicitud) throw new \Exception("Solicitud no encontrada.");
                if ($solicitud['estado'] !== 'Pendiente') {
                    throw new \Exception("La solicitud ya fue procesada.");
                }
                $barrio_id = $this->obtenerBarrioId($user['id']);
                $solicitudBarrio = $this->ejecutarConsulta(
                    "SELECT id FROM calles WHERE id = ? AND barrio_id = ? LIMIT 1",
                    [(int)$solicitud['calle_id'], $barrio_id]
                )->fetchColumn();
                if (!$solicitudBarrio) {
                    throw new \Exception("La solicitud no pertenece a tu barrio.");
                }

                if ($estado === 'Aprobado') {
                    if ($solicitud['tipo'] === 'Alta') {
                        $calleData = $this->ejecutarConsulta("SELECT barrio_id, nombre FROM calles WHERE id = ?", [$solicitud['calle_id']])->fetch(\PDO::FETCH_ASSOC);
                        $cedulaTipoSolicitud = $this->normalizarTipoCedula($solicitud['cedula_tipo'] ?? 'V');
                        if (!empty($solicitud['cedula'])) {
                            $existingSql = "SELECT id FROM viviendas WHERE calle_id = ? AND cedula = ? AND cedula_tipo = ? LIMIT 1";
                            $existingParams = [$solicitud['calle_id'], $solicitud['cedula'], $cedulaTipoSolicitud];
                        } elseif (!empty($solicitud['otro_identificador'])) {
                            $existingSql = "SELECT id FROM viviendas WHERE calle_id = ? AND otro_identificador = ? LIMIT 1";
                            $existingParams = [$solicitud['calle_id'], $solicitud['otro_identificador']];
                        } else {
                            $existingSql = "SELECT id FROM viviendas
                               WHERE calle_id = ? AND numero_casa = ?
                                 AND LOWER(TRIM(propietario)) = LOWER(TRIM(?)) LIMIT 1";
                            $existingParams = [$solicitud['calle_id'], $solicitud['numero_casa'], $solicitud['propietario']];
                        }
                        $new_vivienda_id = $this->ejecutarConsulta($existingSql, $existingParams)->fetchColumn();

                        if (!$new_vivienda_id) {
                            $this->ejecutarConsulta(
                                "INSERT INTO viviendas (propietario, cedula, cedula_tipo, otro_identificador, barrio_id, calle_id, direccion, numero_casa, referencia, estado_servicio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Activo')",
                                [$solicitud['propietario'], $solicitud['cedula'] ?? null, $cedulaTipoSolicitud, $solicitud['otro_identificador'] ?? null, $calleData['barrio_id'], $solicitud['calle_id'], $calleData['nombre'], $solicitud['numero_casa'], $solicitud['referencia']]
                            );
                            $new_vivienda_id = $pdo->lastInsertId();
                        } else {
                            $this->ejecutarConsulta(
                                "UPDATE viviendas SET estado_servicio = 'Activo' WHERE id = ?",
                                [$new_vivienda_id]
                            );
                        }
                        $this->ejecutarConsulta("UPDATE solicitudes_vivienda SET vivienda_id = ? WHERE id = ?", [$new_vivienda_id, $solicitud_id]);
                    } elseif ($solicitud['tipo'] === 'Baja') {
                        $this->ejecutarConsulta("UPDATE viviendas SET estado_servicio = 'Anulado' WHERE id = ?", [$solicitud['vivienda_id']]);
                    } elseif ($solicitud['tipo'] === 'Renovacion') {
                        $this->ejecutarConsulta("UPDATE viviendas SET estado_servicio = 'Activo' WHERE id = ?", [$solicitud['vivienda_id']]);
                        $viviendaRenovada = $this->ejecutarConsulta(
                            "SELECT id, calle_id, cedula, cedula_tipo, otro_identificador, propietario, numero_casa
                             FROM viviendas WHERE id = ?",
                            [$solicitud['vivienda_id']]
                        )->fetch(\PDO::FETCH_ASSOC);
                        if ($viviendaRenovada) {
                            if (!empty($viviendaRenovada['cedula'])) {
                                $sqlDuplicados = "SELECT id FROM viviendas WHERE id <> ? AND calle_id = ? AND cedula = ? AND cedula_tipo = ? AND estado_servicio = 'Activo'";
                                $paramsDuplicados = [$viviendaRenovada['id'], $viviendaRenovada['calle_id'], $viviendaRenovada['cedula'], $this->normalizarTipoCedula($viviendaRenovada['cedula_tipo'] ?? 'V')];
                            } elseif (!empty($viviendaRenovada['otro_identificador'])) {
                                $sqlDuplicados = "SELECT id FROM viviendas WHERE id <> ? AND calle_id = ? AND otro_identificador = ? AND estado_servicio = 'Activo'";
                                $paramsDuplicados = [$viviendaRenovada['id'], $viviendaRenovada['calle_id'], $viviendaRenovada['otro_identificador']];
                            } else {
                                $sqlDuplicados = "SELECT id FROM viviendas
                                   WHERE id <> ? AND calle_id = ? AND numero_casa = ?
                                     AND LOWER(TRIM(propietario)) = LOWER(TRIM(?))
                                     AND estado_servicio = 'Activo'";
                                $paramsDuplicados = [$viviendaRenovada['id'], $viviendaRenovada['calle_id'], $viviendaRenovada['numero_casa'], $viviendaRenovada['propietario']];
                            }
                            $duplicadosActivos = $this->ejecutarConsulta($sqlDuplicados, $paramsDuplicados)->fetchAll(\PDO::FETCH_COLUMN);
                            if ($duplicadosActivos) {
                                $marcadores = implode(',', array_fill(0, count($duplicadosActivos), '?'));
                                $this->ejecutarConsulta(
                                    "UPDATE viviendas SET estado_servicio = 'Anulado' WHERE id IN ($marcadores)",
                                    $duplicadosActivos
                                );
                            }
                        }
                    }
                }

                $this->ejecutarConsulta("UPDATE solicitudes_vivienda SET estado = ?, revisado_por = ?, fecha_revision = CURRENT_TIMESTAMP WHERE id = ?", [$estado, $user['id'], $solicitud_id]);
                
                if (!empty($solicitud['vivienda_id'])) {
                    $this->ejecutarConsulta("UPDATE solicitudes_vivienda SET estado = ?, revisado_por = ?, fecha_revision = CURRENT_TIMESTAMP WHERE vivienda_id = ? AND tipo = ? AND estado = 'Pendiente'", [$estado, $user['id'], $solicitud['vivienda_id'], $solicitud['tipo']]);
                }

                $pdo->commit();
                $mensaje_exito = "Solicitud procesada: $estado.";
            } catch (\Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $mensaje_error = "Error: " . $e->getMessage();
            }
        }

        // 2. Registrar Nueva Vivienda
        if ($action === 'nuevo_vecino') {
            try {
                $barrio_id = $this->obtenerBarrioId($user['id']);
                $calle_id  = (int)($_POST['calle_id'] ?? 0);

                $calle = $this->ejecutarConsulta(
                    "SELECT c.id, d.usuario_id AS encargado_calle_id
                     FROM calles c
                     LEFT JOIN detalles_encargado_calle d ON d.calle_id = c.id
                     WHERE c.id = ? AND c.barrio_id = ?",
                    [$calle_id, $barrio_id]
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$calle) throw new \Exception("La calle seleccionada no pertenece a tu barrio.");
                if (empty($calle['encargado_calle_id'])) {
                    throw new \Exception("La calle seleccionada todavía no tiene un encargado de calle asignado.");
                }

                $propietario = trim($_POST['propietario'] ?? '');
                $cedula = $this->normalizarCedula($_POST['cedula'] ?? '');
                $cedulaTipo = $this->normalizarTipoCedula($_POST['cedula_tipo'] ?? 'V');
                $otroIdentificador = $this->normalizarOtroIdentificador($_POST['otro_identificador'] ?? '');
                $tipoPredio = $this->normalizarTipoPredio($_POST['tipo_predio'] ?? 'Vivienda');
                $cantidadCasas = $this->normalizarCantidadCasas($_POST['cantidad_casas'] ?? '', $tipoPredio);
                $numeroCasa = trim($_POST['numero'] ?? '');
                if ($cedula !== null) {
                    $duplicadoSql = "SELECT id FROM viviendas WHERE calle_id = ? AND cedula = ? AND cedula_tipo = ? LIMIT 1";
                    $duplicadoParams = [$calle_id, $cedula, $cedulaTipo];
                } elseif ($otroIdentificador !== null) {
                    $duplicadoSql = "SELECT id FROM viviendas WHERE calle_id = ? AND otro_identificador = ? LIMIT 1";
                    $duplicadoParams = [$calle_id, $otroIdentificador];
                } else {
                    $duplicadoSql = "SELECT id FROM viviendas
                       WHERE calle_id = ? AND numero_casa = ?
                         AND LOWER(TRIM(propietario)) = LOWER(TRIM(?)) LIMIT 1";
                    $duplicadoParams = [$calle_id, $numeroCasa, $propietario];
                }
                if ($this->ejecutarConsulta($duplicadoSql, $duplicadoParams)->fetchColumn()) {
                    throw new \Exception("La vivienda de este propietario ya está registrada en la calle.");
                }

                $pdo = $this->conectar();
                $pdo->beginTransaction();

                $this->ejecutarConsulta(
                    "INSERT INTO viviendas (propietario, cedula, cedula_tipo, otro_identificador, barrio_id, calle_id, direccion, numero_casa, tipo_predio, cantidad_casas, estado_servicio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Activo')",
                    [$propietario, $cedula, $cedulaTipo, $otroIdentificador, $barrio_id, $calle_id, $_POST['direccion'], $numeroCasa ?: null, $tipoPredio, $cantidadCasas]
                );
                $new_vivienda_id = $pdo->lastInsertId();

                // Si viene de una solicitud de alta, se marca como aprobada y se enlaza
                if (!empty($_POST['solicitud_id'])) {
                    $this->ejecutarConsulta(
                        "UPDATE solicitudes_vivienda SET vivienda_id = ?, estado = 'Aprobado', revisado_por = ?, fecha_revision = NOW() WHERE id = ? AND calle_id = ?",
                        [$new_vivienda_id, $user['id'], (int)$_POST['solicitud_id'], $calle_id]
                    );
                }

                $pdo->commit();
                $mensaje_exito = $tipoPredio === 'Vivienda'
                    ? "Vivienda registrada correctamente."
                    : "Establecimiento registrado correctamente.";
            } catch (\Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $mensaje_error = "Error: " . $e->getMessage();
            }
        }

        // 3. Verificar pago individual
        if ($action === 'verificar_pago_casa') {
            try {
                $cobro_id = (int)$_POST['cobro_id'];
                $decision = $_POST['decision'] ?? 'Verificado';
                $motivo   = trim($_POST['motivo_rechazo'] ?? '');

                $barrio_id = $this->obtenerBarrioId($user['id']);
                $cobro = $this->ejecutarConsulta(
                    "SELECT c.id, c.estado FROM cobros c
                     JOIN viviendas v ON c.vivienda_id = v.id
                     JOIN calles ca ON ca.id = v.calle_id
                     WHERE c.id = ? AND ca.barrio_id = ?",
                    [$cobro_id, $barrio_id]
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$cobro) throw new \Exception("Acceso denegado: este cobro no pertenece a tu barrio.");
                if ($cobro['estado'] !== 'Pagado') {
                    throw new \Exception("Solo se pueden verificar cobros que hayan sido reportados como pagados.");
                }

                if ($decision === 'Rechazado') {
                    if ($motivo === '') throw new \Exception("Debe indicar el motivo del rechazo.");
                    // Al rechazar, el cobro vuelve a Pendiente para que la calle pueda
                    // corregir la referencia o el comprobante y reportarlo de nuevo.
                    $this->ejecutarConsulta(
                        "UPDATE cobros
                         SET estado='Pendiente', estado_verificacion='Rechazado',
                             verificado_por=?, verificado_en=NOW(), motivo_rechazo=?
                         WHERE id=?",
                        [$user['id'], $motivo, $cobro_id]
                    );
                } else {
                    $this->ejecutarConsulta(
                        "UPDATE cobros
                         SET estado='Pagado', estado_verificacion='Verificado',
                             verificado_por=?, verificado_en=NOW(), motivo_rechazo=NULL
                         WHERE id=?",
                        [$user['id'], $cobro_id]
                    );
                }
                
                $mensaje_exito = "Pago marcado como: $decision.";
            } catch (\Exception $e) { $mensaje_error = "Error: " . $e->getMessage(); }
        }

        // 3b. Registrar pago de una vivienda del barrio (cualquier calle)
        if ($action === 'marcar_pago_barrio') {
            try {
                $vivienda_id = (int)($_POST['vivienda_id'] ?? 0);
                $mes = (int)($_POST['mes'] ?? date('n'));
                $anio = (int)($_POST['anio'] ?? date('Y'));
                $ref = trim($_POST['referencia_pago'] ?? '');

                if ($vivienda_id <= 0) throw new \Exception("Seleccione una vivienda válida.");
                if ($mes < 1 || $mes > 12) throw new \Exception("El mes seleccionado no es válido.");
                if ($anio < 2000 || $anio > 2100) throw new \Exception("El año seleccionado no es válido.");
                if ($ref === '') throw new \Exception("La referencia de pago es obligatoria.");

                $barrio_id = $this->obtenerBarrioId($user['id']);
                if (!$barrio_id) throw new \Exception("No tienes un barrio asignado.");

                $pdo = $this->conectar();
                $pdo->beginTransaction();
                $vivienda = $this->ejecutarConsulta(
                    "SELECT v.id, v.calle_id, v.estado_servicio, v.exento_cobro
                     FROM viviendas v
                     JOIN calles c ON c.id = v.calle_id
                     WHERE v.id = ? AND c.barrio_id = ?
                     LIMIT 1 FOR UPDATE",
                    [$vivienda_id, $barrio_id]
                )->fetch(\PDO::FETCH_ASSOC);

                if (!$vivienda) throw new \Exception("La vivienda no pertenece a tu barrio.");
                if (($vivienda['estado_servicio'] ?? '') !== 'Activo') {
                    throw new \Exception("La vivienda no está activa.");
                }
                if ((int)($vivienda['exento_cobro'] ?? 0) === 1) {
                    throw new \Exception("Esta vivienda está exonerada y no requiere pago.");
                }

                // Si la calle ya tiene un lote del periodo, vincular el pago para
                // que aparezca en la revisión del lote sin importar el momento.
                $loteCalle = $this->ejecutarConsulta(
                    "SELECT id, total_casas, monto_esperado FROM lotes_calle
                     WHERE calle_id = ?
                       AND COALESCE(NULLIF(periodo_mes,0), mes) = ?
                       AND COALESCE(NULLIF(periodo_anio,0), anio) = ?
                     LIMIT 1",
                    [$vivienda['calle_id'], $mes, $anio]
                )->fetch(\PDO::FETCH_ASSOC);
                $loteCalleId = $loteCalle ? (int)$loteCalle['id'] : null;

                $cuota = $this->montoMensualEfectivo($vivienda_id);
                if ($cuota <= 0) $cuota = 10.00;

                $chk = $this->ejecutarConsulta(
                    "SELECT id, estado FROM cobros
                     WHERE vivienda_id = ? AND mes = ? AND anio = ? AND estado != 'Anulado'
                     ORDER BY id DESC LIMIT 1",
                    [$vivienda_id, $mes, $anio]
                )->fetch(\PDO::FETCH_ASSOC);

                if ($chk && $chk['estado'] === 'Pagado') {
                    throw new \Exception("Esta vivienda ya tiene un pago registrado para este periodo.");
                }

                // Comprobante opcional (imagen o PDF)
                $comprobantePath = null;
                if (isset($_FILES['comprobante_pago'])) {
                    if ($_FILES['comprobante_pago']['error'] === UPLOAD_ERR_OK) {
                        $comprobantePath = $this->guardarArchivoPago($_FILES['comprobante_pago'], 'comprobantes');
                    } elseif ($_FILES['comprobante_pago']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $errorCodes = [
                            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo (2MB)',
                            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
                            UPLOAD_ERR_PARTIAL => 'El archivo solo se subió parcialmente',
                            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor',
                            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
                        ];
                        $errMsg = $errorCodes[$_FILES['comprobante_pago']['error']] ?? 'Código de error: ' . $_FILES['comprobante_pago']['error'];
                        throw new \Exception("Error al subir comprobante: " . $errMsg);
                    }
                }

                if ($chk) {
                    $this->ejecutarConsulta(
                        "UPDATE cobros
                         SET monto = ?, referencia_pago = ?,
                             comprobante_barrio = COALESCE(?, comprobante_barrio),
                             lote_calle_id = COALESCE(?, lote_calle_id),
                             estado = 'Pagado', estado_verificacion = 'Pendiente',
                             verificado_por = NULL, verificado_en = NULL,
                             motivo_rechazo = NULL, fecha_confirmacion_barrio = CURRENT_DATE()
                         WHERE id = ?",
                        [$cuota, $ref, $comprobantePath, $loteCalleId, $chk['id']]
                    );
                } else {
                    $this->ejecutarConsulta(
                        "INSERT INTO cobros
                            (vivienda_id, mes, anio, monto, fecha_emision, fecha_vencimiento,
                             referencia_pago, comprobante_barrio, lote_calle_id, estado, estado_verificacion,
                             tipo_cobro, fecha_confirmacion_barrio)
                         VALUES (?, ?, ?, ?, CURRENT_DATE(), CURRENT_DATE(), ?, ?, ?, 'Pagado', 'Pendiente', 'Servicio', CURRENT_DATE())",
                        [$vivienda_id, $mes, $anio, $cuota, $ref, $comprobantePath, $loteCalleId]
                    );
                }

                // Mantener sincronizados los totales del lote de calle si ya existe.
                try {
                    if ($loteCalle) {
                        $casasPagadasLote = (int)$this->ejecutarConsulta(
                            "SELECT COUNT(DISTINCT c.vivienda_id) FROM cobros c
                             JOIN viviendas v ON v.id = c.vivienda_id
                             WHERE v.calle_id = ? AND v.estado_servicio = 'Activo' AND COALESCE(v.exento_cobro,0) = 0
                               AND c.mes = ? AND c.anio = ? AND c.estado = 'Pagado'",
                            [$vivienda['calle_id'], $mes, $anio]
                        )->fetchColumn();
                        $montoRecolectadoLote = (float)$this->ejecutarConsulta(
                            "SELECT COALESCE(SUM(c.monto),0) FROM cobros c
                             JOIN viviendas v ON v.id = c.vivienda_id
                             WHERE v.calle_id = ? AND v.estado_servicio = 'Activo' AND COALESCE(v.exento_cobro,0) = 0
                               AND c.mes = ? AND c.anio = ? AND c.estado = 'Pagado'",
                            [$vivienda['calle_id'], $mes, $anio]
                        )->fetchColumn();
                        $casasMorosasLote = max(0, (int)$loteCalle['total_casas'] - $casasPagadasLote);
                        $alertaLote = ($montoRecolectadoLote < (float)$loteCalle['monto_esperado']) ? 1 : 0;

                        $this->ejecutarConsulta(
                            "UPDATE lotes_calle
                             SET monto_recolectado = ?, casas_pagadas = ?, casas_morosas = ?, alerta_deuda = ?
                             WHERE id = ?",
                            [$montoRecolectadoLote, $casasPagadasLote, $casasMorosasLote, $alertaLote, $loteCalle['id']]
                        );
                    }
                } catch (\Throwable $e) {
                    // La actualización de totales no debe impedir el registro del pago.
                }

                $pdo->commit();
                $mensaje_exito = "Pago registrado y enviado a verificación de pagos." . ($comprobantePath ? " Comprobante adjuntado." : "");
            } catch (\Throwable $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $mensaje_error = "Error: " . $e->getMessage();
            }
        }

        // 4 y 5. Gestión de Lotes de Calle
        if (in_array($action, ['aprobar_lote_calle', 'rechazar_lote_calle'])) {
            $lote_id = (int)$_POST['lote_id'];
            $motivo  = trim($_POST['motivo_rechazo'] ?? '');
            try {
                $this->validarBarrioLoteCalle($lote_id, $user['id']);
                $estadoLote = $this->ejecutarConsulta("SELECT estado FROM lotes_calle WHERE id=?", [$lote_id])->fetchColumn();
                if ($estadoLote !== 'Enviado') {
                    throw new \Exception("Solo se pueden revisar lotes en estado 'Enviado'.");
                }
                
                if ($action === 'aprobar_lote_calle') {
                    $this->ejecutarConsulta("UPDATE lotes_calle SET estado='Aprobado', fecha_revision=NOW() WHERE id=?", [$lote_id]);
                    $this->ejecutarConsulta(
                        "UPDATE cobros SET estado_verificacion='Verificado', verificado_por=?, verificado_en=NOW()
                         WHERE lote_calle_id=? AND estado='Pagado'",
                        [$user['id'], $lote_id]
                    );
                    $mensaje_exito = "Lote de calle aprobado.";
                } else {
                    if (empty($motivo)) throw new \Exception("Indique motivo de rechazo.");
                    $this->ejecutarConsulta("UPDATE lotes_calle SET estado='Rechazado', observaciones_barrio=?, fecha_revision=NOW() WHERE id=?", [$motivo, $lote_id]);
                    $this->ejecutarConsulta(
                        "UPDATE cobros
                         SET lote_calle_id=NULL, estado_verificacion='Pendiente',
                             verificado_por=NULL, verificado_en=NULL
                         WHERE lote_calle_id=?",
                        [$lote_id]
                    );
                    $mensaje_exito = "Lote rechazado.";
                }
            } catch (\Exception $e) { $mensaje_error = $e->getMessage(); }
        }

        // 6. Enviar Lote de Barrio
        if ($action === 'enviar_lote_barrio') {
            $this->procesarEnvioLoteBarrio($_POST, $_FILES);
        }

        // 7. Configurar tarifas por tipo de predio, multa y fecha límite de pago
        if ($action === 'actualizar_configuracion_barrio') {
            try {
                $barrio_id = $this->obtenerBarrioId($user['id']);
                $fecha_limite_pago = trim((string)($_POST['fecha_limite_pago'] ?? ''));
                if ($fecha_limite_pago === '') {
                    throw new \Exception("Indica la fecha límite de pago para los encargados de calle.");
                }
                $tarifa = function ($campo) {
                    return (float)($_POST[$campo] ?? $_POST['cuota_mensual'] ?? 10.00);
                };
                $this->ejecutarConsulta(
                    "INSERT INTO configuraciones_barrio
                        (barrio_id, cuota_mensual, precio_comercio, precio_tienda, precio_empresa, precio_urbanizacion, precio_otro, multa_renovacion, fecha_limite_pago, actualizado_por)
                     VALUES (?,?,?,?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                        cuota_mensual = VALUES(cuota_mensual),
                        precio_comercio = VALUES(precio_comercio),
                        precio_tienda = VALUES(precio_tienda),
                        precio_empresa = VALUES(precio_empresa),
                        precio_urbanizacion = VALUES(precio_urbanizacion),
                        precio_otro = VALUES(precio_otro),
                        multa_renovacion = VALUES(multa_renovacion),
                        fecha_limite_pago = VALUES(fecha_limite_pago),
                        actualizado_por = VALUES(actualizado_por)",
                    [
                        $barrio_id,
                        (float)$_POST['cuota_mensual'],
                        $tarifa('precio_comercio'),
                        $tarifa('precio_tienda'),
                        $tarifa('precio_empresa'),
                        $tarifa('precio_urbanizacion'),
                        $tarifa('precio_otro'),
                        (float)$_POST['multa_renovacion'],
                        $fecha_limite_pago,
                        $user['id']
                    ]
                );
                $mensaje_exito = "Configuración de pagos actualizada.";
            } catch (\Exception $e) { $mensaje_error = "Error: " . $e->getMessage(); }
        }

        // 8. Enviar certificado/factura al encargado de calle
        if ($action === 'enviar_certificado_calle') {
            $lote_calle_id = (int)$_POST['lote_calle_id'];
            try {
                $barrio_id = $this->obtenerBarrioId($user['id']);
                $lote = $this->ejecutarConsulta(
                    "SELECT lc.id, lc.barrio_id, lc.certificado_generado, lc.certificado_enviado_calle,
                            lc.lote_barrio_id, lb.facturas_enviadas_barrio
                     FROM lotes_calle lc
                     JOIN lotes_barrio lb ON lc.lote_barrio_id = lb.id
                     WHERE lc.id=?", [$lote_calle_id]
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$lote) throw new \Exception("Certificado no encontrado.");
                if ($lote['barrio_id'] != $barrio_id) throw new \Exception("Este certificado no pertenece a tu barrio.");
                if (!$lote['certificado_generado']) throw new \Exception("El certificado aún no ha sido generado por el gestor.");
                if (!$lote['facturas_enviadas_barrio']) throw new \Exception("El gestor aún no ha enviado las facturas al barrio.");
                if ($lote['certificado_enviado_calle']) throw new \Exception("Este certificado ya fue enviado al encargado de calle.");

                // Subir factura personalizada si se adjuntó
                $rutaFactura = null;
                if (isset($_FILES['factura_archivo']) && $_FILES['factura_archivo']['error'] === UPLOAD_ERR_OK) {
                    $rutaFactura = $this->guardarArchivoPago($_FILES['factura_archivo'], 'facturas');
                }

                $this->ejecutarConsulta(
                    "UPDATE lotes_calle SET certificado_enviado_calle=1" . ($rutaFactura ? ", factura_personalizada=?" : "") . " WHERE id=?",
                    $rutaFactura ? [$rutaFactura, $lote_calle_id] : [$lote_calle_id]
                );
                $mensaje_exito = "Certificado enviado al encargado de calle." . ($rutaFactura ? " Documento adjunto guardado." : "");
            } catch (\Exception $e) {
                $mensaje_error = $e->getMessage();
            }
        }

        // 9. Aprobar solicitud de exención de cobro
        if ($action === 'aprobar_exencion') {
            try {
                $exencion_id = (int)$_POST['exencion_id'];
                $barrio_id = $this->obtenerBarrioId($user['id']);

                $ex = $this->ejecutarConsulta(
                    "SELECT e.*, v.id as vid FROM exenciones_cobro e 
                     JOIN viviendas v ON e.vivienda_id = v.id 
                     WHERE e.id = ? AND e.barrio_id = ? AND e.estado = 'Pendiente'",
                    [$exencion_id, $barrio_id]
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$ex) throw new \Exception("Solicitud no encontrada o ya procesada.");

                $pdo = $this->conectar();
                $pdo->beginTransaction();

                $this->ejecutarConsulta(
                    "UPDATE exenciones_cobro SET estado = 'Aprobado', aprobado_por = ?, fecha_revision = NOW() WHERE id = ?",
                    [$user['id'], $exencion_id]
                );
                $this->ejecutarConsulta(
                    "UPDATE viviendas SET exento_cobro = 1 WHERE id = ?",
                    [$ex['vid']]
                );

                $pdo->commit();
                $mensaje_exito = "Exención aprobada. La vivienda ya no generará cobros.";
            } catch (\Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $mensaje_error = $e->getMessage();
            }
        }

        // 10. Rechazar solicitud de exención de cobro
        if ($action === 'rechazar_exencion') {
            try {
                $exencion_id = (int)$_POST['exencion_id'];
                $motivo = trim($_POST['motivo_rechazo'] ?? '');
                $barrio_id = $this->obtenerBarrioId($user['id']);

                if (empty($motivo)) throw new \Exception("Debes indicar el motivo del rechazo.");

                $ex = $this->ejecutarConsulta(
                    "SELECT id FROM exenciones_cobro WHERE id = ? AND barrio_id = ? AND estado = 'Pendiente'",
                    [$exencion_id, $barrio_id]
                )->fetch();
                if (!$ex) throw new \Exception("Solicitud no encontrada o ya procesada.");

                $this->ejecutarConsulta(
                    "UPDATE exenciones_cobro SET estado = 'Rechazado', aprobado_por = ?, motivo_rechazo = ?, fecha_revision = NOW() WHERE id = ?",
                    [$user['id'], $motivo, $exencion_id]
                );
                $mensaje_exito = "Solicitud de exención rechazada.";
            } catch (\Exception $e) {
                $mensaje_error = $e->getMessage();
            }
        }

        // 11. Agregar exención directa (sin solicitud de calle)
        if ($action === 'agregar_exencion_directa') {
            try {
                $vivienda_id = (int)($_POST['vivienda_id'] ?? 0);
                $barrio_id = (int)($_POST['barrio_id'] ?? 0);
                $tipo = $_POST['tipo_exencion'] ?? 'otro';
                $descripcion = trim($_POST['descripcion'] ?? '');

                $barrioAsignado = $this->ejecutarConsulta(
                    "SELECT barrio_id FROM detalles_encargado_barrio WHERE usuario_id = ? AND barrio_id = ?",
                    [$user['id'], $barrio_id]
                )->fetchColumn();
                if (!$barrioAsignado) throw new \Exception("El barrio seleccionado no está asignado a tu usuario.");
                if ($vivienda_id <= 0) throw new \Exception("Selecciona una vivienda para exonerar.");
                if (!in_array($tipo, ['pobreza', 'adulto_mayor', 'empleado', 'espacio_publico', 'otro'], true)) {
                    throw new \Exception("Selecciona un motivo de exoneración válido.");
                }

                // Validar que la vivienda pertenezca al barrio asignado y siga activa
                $v = $this->ejecutarConsulta(
                    "SELECT id, calle_id, exento_cobro FROM viviendas
                     WHERE id = ? AND barrio_id = ? AND estado_servicio = 'Activo'",
                    [$vivienda_id, $barrio_id]
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$v) throw new \Exception("La vivienda no pertenece al barrio seleccionado o no está activa.");
                if ($v['exento_cobro']) throw new \Exception("Esta vivienda ya está exenta de cobro.");

                $pdo = $this->conectar();
                $pdo->beginTransaction();

                $this->ejecutarConsulta(
                    "INSERT INTO exenciones_cobro (vivienda_id, calle_id, barrio_id, tipo_exencion, descripcion, creado_por, estado, aprobado_por, fecha_revision)
                     VALUES (?, ?, ?, ?, ?, ?, 'Aprobado', ?, NOW())",
                    [$vivienda_id, $v['calle_id'], $barrio_id, $tipo, $descripcion, $user['id'], $user['id']]
                );
                $this->ejecutarConsulta(
                    "UPDATE viviendas SET exento_cobro = 1 WHERE id = ?",
                    [$vivienda_id]
                );

                $pdo->commit();
                $mensaje_exito = "Vivienda agregada a la lista de exentas directamente.";
            } catch (\Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $mensaje_error = $e->getMessage();
            }
        }

        // 12. Quitar exención de cobro (revocar)
        if ($action === 'quitar_exencion') {
            try {
                $exencion_id = (int)$_POST['exencion_id'];
                $barrio_id = $this->obtenerBarrioId($user['id']);

                $ex = $this->ejecutarConsulta(
                    "SELECT e.*, v.id as vid FROM exenciones_cobro e 
                     JOIN viviendas v ON e.vivienda_id = v.id 
                     WHERE e.id = ? AND e.barrio_id = ? AND e.estado = 'Aprobado'",
                    [$exencion_id, $barrio_id]
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$ex) throw new \Exception("Exención no encontrada o ya revocada.");

                $pdo = $this->conectar();
                $pdo->beginTransaction();

                $this->ejecutarConsulta(
                    "UPDATE exenciones_cobro SET estado = 'Rechazado', aprobado_por = ?, fecha_revision = NOW() WHERE id = ?",
                    [$user['id'], $exencion_id]
                );
                $this->ejecutarConsulta(
                    "UPDATE viviendas SET exento_cobro = 0 WHERE id = ?",
                    [$ex['vid']]
                );

                $pdo->commit();
                $mensaje_exito = "Exención revocada. La vivienda volverá a generar cobros.";
            } catch (\Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $mensaje_error = $e->getMessage();
            }
        }

        // 13. Ordenar Baja (con cálculo de deuda)
        if ($action === 'ordenar_baja') {
            try {
                $v_id = (int)$_POST['vivienda_id'];
                $barrio_id = $this->obtenerBarrioId($user['id']);
                $v = $this->ejecutarConsulta(
                    "SELECT id, calle_id, estado_servicio FROM viviendas WHERE id = ? AND barrio_id = ?",
                    [$v_id, $barrio_id]
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$v) throw new \Exception("La vivienda no pertenece a tu barrio.");
                if ($v['estado_servicio'] !== 'Activo') {
                    throw new \Exception("El servicio de esta vivienda ya está inactivo.");
                }

                $pendiente = $this->ejecutarConsulta(
                    "SELECT id FROM solicitudes_vivienda
                     WHERE vivienda_id = ? AND tipo = 'Baja' AND estado = 'Pendiente'
                     LIMIT 1",
                    [$v_id]
                )->fetchColumn();
                if ($pendiente) throw new \Exception("Esta vivienda ya tiene una baja pendiente.");

                [$monto, $detalles] = $this->calcularDeudaBarrio($v_id);
                $this->ejecutarConsulta("INSERT INTO solicitudes_vivienda (tipo, calle_id, vivienda_id, creado_por, estado, monto_deuda, detalles_deuda) VALUES ('Baja', ?, ?, ?, 'Aprobado', ?, ?)",
                    [$v['calle_id'], $v_id, $user['id'], $monto, $detalles]);
                $this->ejecutarConsulta("UPDATE viviendas SET estado_servicio='Anulado' WHERE id=?", [$v_id]);
                $mensaje_exito = "Servicio anulado correctamente.";
            } catch (\Exception $e) { $mensaje_error = $e->getMessage(); }
        }

        // 14. Reactivar (renovar) un servicio dado de baja en el barrio
        if ($action === 'reactivar_servicio') {
            try {
                $v_id = (int)($_POST['vivienda_id'] ?? 0);
                $barrio_id = $this->obtenerBarrioId($user['id']);

                $v = $this->ejecutarConsulta(
                    "SELECT id, calle_id, cedula, cedula_tipo, otro_identificador, propietario, numero_casa, estado_servicio
                     FROM viviendas WHERE id = ? AND barrio_id = ?",
                    [$v_id, $barrio_id]
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$v) throw new \Exception("La vivienda no pertenece a tu barrio.");
                if ($v['estado_servicio'] === 'Activo') throw new \Exception("El servicio de esta vivienda ya está activo.");

                if (!empty($v['cedula'])) {
                    $duplicadaActiva = $this->ejecutarConsulta(
                        "SELECT id FROM viviendas
                         WHERE id <> ? AND calle_id = ? AND estado_servicio = 'Activo'
                           AND cedula = ? AND cedula_tipo = ?
                         LIMIT 1",
                        [$v_id, $v['calle_id'], $v['cedula'], $this->normalizarTipoCedula($v['cedula_tipo'] ?? 'V')]
                    )->fetchColumn();
                } elseif (!empty($v['otro_identificador'])) {
                    $duplicadaActiva = $this->ejecutarConsulta(
                        "SELECT id FROM viviendas
                         WHERE id <> ? AND calle_id = ? AND estado_servicio = 'Activo'
                           AND otro_identificador = ?
                         LIMIT 1",
                        [$v_id, $v['calle_id'], $v['otro_identificador']]
                    )->fetchColumn();
                } else {
                    $duplicadaActiva = $this->ejecutarConsulta(
                        "SELECT id FROM viviendas
                         WHERE id <> ? AND calle_id = ? AND estado_servicio = 'Activo'
                           AND numero_casa = ?
                           AND LOWER(TRIM(propietario)) = LOWER(TRIM(?))
                         LIMIT 1",
                        [$v_id, $v['calle_id'], $v['numero_casa'] ?? '', $v['propietario'] ?? '']
                    )->fetchColumn();
                }
                if ($duplicadaActiva) {
                    throw new \Exception("Ya existe otra vivienda activa con los mismos datos. No se puede renovar este duplicado.");
                }

                $pdo = $this->conectar();
                $pdo->beginTransaction();

                $this->ejecutarConsulta("UPDATE viviendas SET estado_servicio='Activo' WHERE id=?", [$v_id]);
                // Dejar registro histórico de la renovación
                $this->ejecutarConsulta(
                    "INSERT INTO solicitudes_vivienda (tipo, calle_id, vivienda_id, creado_por, revisado_por, estado, fecha_revision)
                     VALUES ('Renovacion', ?, ?, ?, ?, 'Aprobado', NOW())",
                    [$v['calle_id'], $v_id, $user['id'], $user['id']]
                );

                $pdo->commit();
                $mensaje_exito = "Servicio renovado. La vivienda vuelve a estar activa.";
            } catch (\Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $mensaje_error = $e->getMessage();
            }
        }
    }

    // --- MÉTODOS AUXILIARES ---

    private function procesarEnvioLoteBarrio($post, $files) {
        global $user, $mensaje_exito, $mensaje_error;
        try {
            $barrio_id = $this->obtenerBarrioId($user['id']);
            $mes = (int)($post['periodo_mes'] ?? date('n'));
            $anio = (int)($post['periodo_anio'] ?? date('Y'));
            
            $pdo = $this->conectar();
            $pdo->beginTransaction();
            
            // Obtener lotes de calle aprobados del periodo
            $lotesCalle = $pdo->prepare(
                "SELECT id, monto_esperado, monto_recolectado, total_casas, casas_pagadas, casas_morosas, alerta_deuda
                 FROM lotes_calle WHERE barrio_id=? AND periodo_mes=? AND periodo_anio=? AND estado='Aprobado' AND (lote_barrio_id IS NULL OR lote_barrio_id=0)"
            );
            $lotesCalle->execute([$barrio_id, $mes, $anio]);
            $aprobados = $lotesCalle->fetchAll(\PDO::FETCH_ASSOC);
            
            if (empty($aprobados)) {
                throw new \Exception("No hay lotes de calle aprobados pendientes de envío.");
            }
            
            // Calcular totales
            $totalEsp = 0;
            $totalRec = 0;
            $totalCalles = count($aprobados);
            $alertaDeuda = 0;
            foreach ($aprobados as $lc) {
                $totalEsp += (float)$lc['monto_esperado'];
                $totalRec += (float)$lc['monto_recolectado'];
                if ($lc['alerta_deuda']) $alertaDeuda = 1;
            }
            
            // Manejar comprobante global del lote de barrio
            $comprobanteLote = null;
            if (isset($files['comprobante_lote_barrio']) && $files['comprobante_lote_barrio']['error'] === UPLOAD_ERR_OK) {
                $comprobanteLote = $this->guardarArchivoPago($files['comprobante_lote_barrio'], 'comprobantes');
            }
            
            // Buscar o crear lote_barrio
            $stmt = $pdo->prepare("SELECT id FROM lotes_barrio WHERE barrio_id=? AND periodo_mes=? AND periodo_anio=?");
            $stmt->execute([$barrio_id, $mes, $anio]);
            $loteBarrioId = $stmt->fetchColumn();
            
            if ($loteBarrioId) {
                $upd = $pdo->prepare(
                    "UPDATE lotes_barrio SET estado='Enviado', fecha_envio=NOW(), monto_total_esperado=?, monto_total_recolectado=?, total_calles=?, calles_completas=?, alerta_deuda=?, comprobante_lote=COALESCE(?, comprobante_lote) WHERE id=?"
                );
                $upd->execute([$totalEsp, $totalRec, $totalCalles, $totalCalles, $alertaDeuda, $comprobanteLote, $loteBarrioId]);
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO lotes_barrio (barrio_id, periodo_mes, periodo_anio, mes, anio, estado, fecha_envio, encargado_barrio_id, monto_total_esperado, monto_total_recolectado, total_calles, calles_completas, alerta_deuda, comprobante_lote)
                     VALUES (?,?,?,?,?, 'Enviado', NOW(), ?,?,?,?,?,?,?)"
                );
                $ins->execute([$barrio_id, $mes, $anio, $mes, $anio, $user['id'], $totalEsp, $totalRec, $totalCalles, $totalCalles, $alertaDeuda, $comprobanteLote]);
                $loteBarrioId = $pdo->lastInsertId();
            }
            
            // Vincular lotes de calle aprobados al lote de barrio
            $link = $pdo->prepare("UPDATE lotes_calle SET lote_barrio_id=? WHERE id=?");
            foreach ($aprobados as $lc) {
                $link->execute([$loteBarrioId, $lc['id']]);
            }
            
            $pdo->commit();
            $mensaje_exito = "Lote de barrio enviado al gestor con " . count($aprobados) . " calle(s).";
        } catch (\Exception $e) { $mensaje_error = $e->getMessage(); }
    }

    protected function asegurarColumnasPagos() {
        parent::asegurarColumnasPagos();
        $columnas = ['estado_verificacion', 'verificado_por', 'verificado_en', 'motivo_rechazo'];
        foreach ($columnas as $col) {
            try {
                $this->ejecutarConsulta("SELECT $col FROM cobros LIMIT 1");
            } catch (\Exception $e) {
                $tipo = ($col === 'motivo_rechazo') ? 'TEXT' : ($col === 'verificado_en' ? 'DATETIME' : 'INT');
                if($col === 'estado_verificacion') $tipo = 'VARCHAR(50) DEFAULT "Pendiente"';
                $this->ejecutarConsulta("ALTER TABLE cobros ADD COLUMN $col $tipo NULL");
            }
        }
    }

    private function obtenerBarrioId(int $user_id): int {
        $id = $this->ejecutarConsulta("SELECT barrio_id FROM detalles_encargado_barrio WHERE usuario_id=?", [$user_id])->fetchColumn();
        if (!$id) throw new \Exception("No tienes barrio asignado.");
        return (int)$id;
    }

    private function validarBarrioLoteCalle(int $lote_id, int $user_id): void {
        $stmt = $this->ejecutarConsulta("SELECT lc.id FROM lotes_calle lc JOIN detalles_encargado_barrio deb ON lc.barrio_id = deb.barrio_id WHERE lc.id=? AND deb.usuario_id=?", [$lote_id, $user_id]);
        if (!$stmt->fetch()) throw new \Exception("Acceso denegado: Lote no pertenece a tu jurisdicción.");
    }

    private function calcularDeudaBarrio(int $vivienda_id): array {
        $d = $this->ejecutarConsulta("SELECT SUM(monto) as total, GROUP_CONCAT(CONCAT(tipo_cobro,' ',mes,'/',anio) SEPARATOR ', ') as resumen FROM cobros WHERE vivienda_id=? AND estado NOT IN ('Pagado','Anulado')", [$vivienda_id])->fetch(\PDO::FETCH_ASSOC);
        return [$d['total'] ?? 0, $d['resumen'] ?? 'Sin deudas'];
    }

    /**
     * Verificar y actualizar deudas del barrio.
     * Método requerido por viewsController al entrar a reportar_pago.
     */
    public function verificarDeudasBarrio(int $user_id): void {
        try {
            $barrio_id = $this->obtenerBarrioId($user_id);
            
            // Verificar que las tablas y columnas necesarias existan
            $this->asegurarColumnasPagos();
            
            // Obtener configuration si no existe
            $this->ejecutarConsulta(
                "INSERT INTO configuraciones_barrio (barrio_id, cuota_mensual, multa_renovacion) VALUES (?, 10.00, 5.00) ON DUPLICATE KEY UPDATE barrio_id=barrio_id",
                [$barrio_id]
            );
        } catch (\Exception $e) {
            // Silenciar errores para no bloquear el acceso a la vista
            error_log("Error en verificarDeudasBarrio: " . $e->getMessage());
        }
    }
}