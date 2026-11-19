<?php

namespace app\controllers;
use app\models\mainModel;

// =============================================
// app/controllers/adminController.php
// Acciones POST exclusivas del Administrador.
// Gestión completa de usuarios del sistema.
// =============================================

class adminController extends mainModel {

    public function procesarAcciones() {
        global $user, $mensaje_exito, $mensaje_error;

        $action = $_POST['action'] ?? $_POST['form_type'] ?? null;

        // 0. Actualizar estado de un reporte ciudadano
        if ($action === 'actualizar_reporte') {
            $reporte_id = (int)($_POST['reporte_id'] ?? 0);
            $estado     = $_POST['estado'] ?? 'Pendiente';
            if (!in_array($estado, ['Pendiente', 'En proceso', 'Completado'], true)) {
                $mensaje_error = "Estado de reporte no válido.";
            } elseif ($reporte_id > 0) {
                try {
                    $this->ejecutarConsulta("UPDATE reportes SET estado = ? WHERE id = ?", [$estado, $reporte_id]);
                    $mensaje_exito = "Reporte #{$reporte_id} actualizado.";
                } catch (\Exception $e) {
                    $mensaje_error = "No se pudo actualizar el reporte: " . $e->getMessage();
                }
            }
        }

        // 1. Crear nuevo usuario
        if ($action === 'add_user') {
            $nombre    = trim($_POST['nombre'] ?? '');
            $apellido  = trim($_POST['apellido'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $genero    = $_POST['genero'] ?? null ?: null;
            $fecha_nac = $_POST['fecha_nacimiento'] ?? null ?: null;
            $password  = !empty($_POST['password'])
                ? password_hash($_POST['password'], PASSWORD_DEFAULT)
                : password_hash('123456', PASSWORD_DEFAULT);
            $rol_id    = (int)($_POST['rol_id'] ?? 0);
            if (in_array($rol_id, [2, 5, 6], true)) {
                $genero = null;
            }
            
            if ($nombre === '' || $apellido === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $rol_id <= 0) {
                $mensaje_error = "Complete nombre, apellido, correo y rol.";
                return;
            }
            if ($rol_id === 3) {
                $mensaje_error = "El rol de Personal Obrero ya no está disponible.";
                return;
            }
            if ($fecha_nac !== null && $fecha_nac >= date('Y-m-d')) {
                $mensaje_error = "La fecha de nacimiento debe ser anterior a la fecha de hoy.";
                return;
            }

            try {
                $pdo = $this->conectar();
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(  
                    "INSERT INTO usuarios (nombre, apellido, email, genero, fecha_nacimiento, password_hash, rol_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$nombre, $apellido, $email, $genero, $fecha_nac, $password, $rol_id]);
                $new_id = $pdo->lastInsertId();

                $this->insertarDetallesRol($pdo, $new_id, $rol_id);
                $pdo->commit();

                $mensaje_exito = "Usuario creado correctamente.";
            } catch (\Throwable $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $mensaje_error = "Error al crear usuario: " . $e->getMessage();
            }
        }

        // 2. Editar usuario existente
        if ($action === 'edit_user') {
            $u_id      = (int)($_POST['user_id'] ?? 0);
            $genero    = $_POST['genero'] ?? null ?: null;
            $fecha_nac = $_POST['fecha_nacimiento'] ?? null ?: null;
            $rol_id    = (int)($_POST['rol_id'] ?? 0);

            try {
                if ($u_id <= 0 || $rol_id <= 0) {
                    throw new \InvalidArgumentException("Datos de usuario incompletos.");
                }
                if ($rol_id === 3 && ($user['rol_id'] ?? null) != 2) {
                    throw new \InvalidArgumentException("El rol de Personal Obrero ya no está disponible.");
                }

                if ($rol_id === 3) {
                    throw new \InvalidArgumentException("El rol de Personal Obrero ya no está disponible.");
                }

                if ($rol_id === 5 && (int)($_POST['barrio_id'] ?? 0) <= 0) {
                    throw new \InvalidArgumentException("Debe seleccionar un barrio para el encargado.");
                }
                if ($rol_id === 6 && (int)($_POST['calle_id'] ?? 0) <= 0) {
                    throw new \InvalidArgumentException("Debe seleccionar una calle para el encargado.");
                }

                $pdo = $this->conectar();
                $pdo->beginTransaction();

                $sql    = "UPDATE usuarios SET nombre=?, apellido=?, email=?, genero=?, fecha_nacimiento=?, rol_id=?";
                $params = [$_POST['nombre'], $_POST['apellido'], $_POST['email'], $genero, $fecha_nac, $rol_id];
                if (!empty($_POST['password'])) {
                    $sql     .= ", password_hash=?";
                    $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }
                $sql     .= " WHERE id=?";
                $params[] = $u_id;
                $pdo->prepare($sql)->execute($params);

                // Limpiar y re-insertar detalles
                $pdo->prepare("DELETE FROM detalles_encargado_barrio WHERE usuario_id=?")->execute([$u_id]);
                $pdo->prepare("DELETE FROM detalles_encargado_calle WHERE usuario_id=?")->execute([$u_id]);
                $pdo->prepare("DELETE FROM detalles_gestor WHERE usuario_id=?")->execute([$u_id]);
                $pdo->prepare("DELETE FROM detalles_personal_obrero WHERE usuario_id=?")->execute([$u_id]);
                $this->insertarDetallesRol($pdo, $u_id, $rol_id, $u_id);

                $pdo->commit();
                $mensaje_exito = "Usuario modificado correctamente.";
            } catch (\Throwable $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $mensaje_error = "Error al editar usuario: " . $e->getMessage();
            }
        }

        // 3. Crear nueva calle
        if ($action === 'nueva_calle') {
            try {
                $this->ejecutarConsulta(
                    "INSERT INTO calles (nombre, barrio_id, tipo_via, descripcion) VALUES (?, ?, ?, ?)",
                    [trim($_POST['nombre']), (int)$_POST['barrio_id'], 'Calle', trim($_POST['descripcion'] ?? '') ?: null]
                );
                $mensaje_exito = "Calle registrada correctamente.";
            } catch (\PDOException $e) {
                $mensaje_error = "Error al registrar calle.";
            }
        }

        // 4. Crear nuevo barrio
        if ($action === 'nuevo_barrio') {
            $nombre = trim($_POST['nombre'] ?? '');
            $ciudad = trim($_POST['ciudad'] ?? '');
            $parroquia = trim($_POST['parroquia_municipio'] ?? '');
            $lider = trim($_POST['lider_comunidad'] ?? '');
            $limite = trim($_POST['limite_referencia'] ?? '');
            if ($nombre === '' || $ciudad === '' || $parroquia === '' || $lider === '') {
                $mensaje_error = "Complete el nombre, ciudad, parroquia/municipio y líder asignado.";
                return;
            }
            try {
                $this->ejecutarConsulta(
                    "INSERT INTO barrios (nombre, ciudad, parroquia_municipio, lider_comunidad, limite_referencia)
                     VALUES (?, ?, ?, ?, ?)",
                    [$nombre, $ciudad, $parroquia, $lider, $limite !== '' ? $limite : null]
                );
                $mensaje_exito = "Barrio creado correctamente.";
            } catch (\PDOException $e) {
                $mensaje_error = "Error al crear barrio.";
            }
        }

        // 5. Editar barrio
        if ($action === 'editar_barrio') {
            $nombre = trim($_POST['nombre'] ?? '');
            $ciudad = trim($_POST['ciudad'] ?? '');
            $parroquia = trim($_POST['parroquia_municipio'] ?? '');
            $lider = trim($_POST['lider_comunidad'] ?? '');
            $limite = trim($_POST['limite_referencia'] ?? '');
            if ($nombre === '' || $ciudad === '' || $parroquia === '' || $lider === '') {
                $mensaje_error = "Complete el nombre, ciudad, parroquia/municipio y líder asignado.";
                return;
            }
            try {
                $this->ejecutarConsulta(
                    "UPDATE barrios
                     SET nombre=?, ciudad=?, parroquia_municipio=?, lider_comunidad=?, limite_referencia=?
                     WHERE id=?",
                    [$nombre, $ciudad, $parroquia, $lider, $limite !== '' ? $limite : null, (int)$_POST['id']]
                );
                $mensaje_exito = "Barrio actualizado correctamente.";
            } catch (\PDOException $e) {
                $mensaje_error = "Error al actualizar barrio.";
            }
        }

        // 6. Editar calle
        if ($action === 'editar_calle') {
            try {
                $this->ejecutarConsulta(
                    "UPDATE calles SET nombre=?, barrio_id=?, tipo_via=?, descripcion=? WHERE id=?",
                    [trim($_POST['nombre']), (int)$_POST['barrio_id'], 'Calle', trim($_POST['descripcion'] ?? '') ?: null, (int)$_POST['id']]
                );
                $mensaje_exito = "Calle actualizada correctamente.";
            } catch (\PDOException $e) {
                $mensaje_error = "Error al actualizar calle.";
            }
        }

        // 7. Editar vivienda
        if ($action === 'editar_vivienda') {
            try {
                $cedula = $this->normalizarCedula($_POST['cedula'] ?? '');
                $cedulaTipo = $this->normalizarTipoCedula($_POST['cedula_tipo'] ?? 'V');
                $otroIdentificador = $this->normalizarOtroIdentificador($_POST['otro_identificador'] ?? '');
                $tipoPredio = $this->normalizarTipoPredio($_POST['tipo_predio'] ?? 'Vivienda');
                $cantidadCasas = $this->normalizarCantidadCasas($_POST['cantidad_casas'] ?? '', $tipoPredio);

                $this->ejecutarConsulta(
                    "UPDATE viviendas SET propietario=?, cedula=?, cedula_tipo=?, otro_identificador=?, barrio_id=?, calle_id=?, direccion=?, numero_casa=?, referencia=?, tipo_predio=?, cantidad_casas=? WHERE id=?",
                    [
                        $_POST['propietario'],
                        $cedula,
                        $cedulaTipo,
                        $otroIdentificador,
                        (int)$_POST['barrio_id'],
                        (int)$_POST['calle_id'],
                        $_POST['direccion'],
                        $_POST['numero_casa'] ?? null,
                        $_POST['referencia'] ?? null,
                        $tipoPredio,
                        $cantidadCasas,
                        (int)$_POST['id']
                    ]
                );
                $mensaje_exito = "Vivienda actualizada correctamente.";
            } catch (\Throwable $e) {
                $mensaje_error = "Error al actualizar vivienda: " . $e->getMessage();
            }
        }

        // 8. Registrar Vivienda (Directo Admin)
        if ($action === 'nuevo_vecino_admin') {
            try {
                $barrio_id = (int)($_POST['barrio_id'] ?? 0);
                $calle_id = (int)($_POST['calle_id'] ?? 0);
                if ($barrio_id <= 0 || $calle_id <= 0) {
                    throw new \InvalidArgumentException("Debe seleccionar un barrio y una calle.");
                }

                $calleValida = $this->ejecutarConsulta(
                    "SELECT COUNT(*) FROM calles WHERE id = ? AND barrio_id = ?",
                    [$calle_id, $barrio_id]
                )->fetchColumn();
                if (!$calleValida) {
                    throw new \InvalidArgumentException("La calle no pertenece al barrio seleccionado.");
                }

                $cedula = $this->normalizarCedula($_POST['cedula'] ?? '');
                $cedulaTipo = $this->normalizarTipoCedula($_POST['cedula_tipo'] ?? 'V');
                $otroIdentificador = $this->normalizarOtroIdentificador($_POST['otro_identificador'] ?? '');
                $tipoPredio = $this->normalizarTipoPredio($_POST['tipo_predio'] ?? 'Vivienda');
                $cantidadCasas = $this->normalizarCantidadCasas($_POST['cantidad_casas'] ?? '', $tipoPredio);

                $this->ejecutarConsulta(
                    "INSERT INTO viviendas (propietario, cedula, cedula_tipo, otro_identificador, barrio_id, calle_id, direccion, numero_casa, referencia, tipo_predio, cantidad_casas)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $_POST['propietario'],
                        $cedula,
                        $cedulaTipo,
                        $otroIdentificador,
                        $barrio_id,
                        $calle_id,
                        $_POST['direccion'],
                        $_POST['numero_casa'] ?? null,
                        $_POST['referencia'] ?? null,
                        $tipoPredio,
                        $cantidadCasas
                    ]
                );
                $mensaje_exito = $tipoPredio === 'Vivienda'
                    ? "Vivienda registrada correctamente."
                    : "Establecimiento registrado correctamente.";
            } catch (\Throwable $e) {
                $mensaje_error = "Error al registrar vivienda: " . $e->getMessage();
            }
        }

        // 6. Ordenar Baja Directa (Administrador)
        if ($action === 'ordenar_baja') {
            try {
                $vivienda_id = (int)$_POST['vivienda_id'];
                
                // 1. Obtener datos básicos
                $vStmt = $this->ejecutarConsulta(
                    "SELECT v.calle_id, v.barrio_id, v.estado_servicio FROM viviendas v WHERE v.id = ?",
                    [$vivienda_id]
                );
                $vData = $vStmt->fetch(\PDO::FETCH_ASSOC);

                if (!$vData) throw new \Exception("Vivienda no encontrada.");
                if ($vData['estado_servicio'] !== 'Activo') {
                    throw new \Exception("El servicio de esta vivienda ya está inactivo.");
                }

                // 2. Calcular deuda actual (los cobros anulados no son deuda)
                $deudaStmt = $this->ejecutarConsulta(
                    "SELECT SUM(monto) as total, COUNT(*) as cantidad, 
                            GROUP_CONCAT(CONCAT(tipo_cobro, ' ', mes, '/', anio) SEPARATOR ', ') as resumen
                     FROM cobros 
                     WHERE vivienda_id = ? AND estado NOT IN ('Pagado', 'Anulado')",
                    [$vivienda_id]
                );
                $deuda_info = $deudaStmt->fetch(\PDO::FETCH_ASSOC);
                $monto_deuda = $deuda_info['total'] ?? 0;
                $detalles_deuda = $deuda_info['resumen'] ?? 'Sin deudas pendientes';

                $pdo = $this->conectar();
                $pdo->beginTransaction();

                // 3. Crear solicitud ya aprobada (Baja Directa por Admin)
                $this->ejecutarConsulta(
                    "INSERT INTO solicitudes_vivienda (tipo, calle_id, vivienda_id, creado_por, revisado_por, estado, fecha_revision, monto_deuda, detalles_deuda)
                     VALUES ('Baja', ?, ?, ?, ?, 'Aprobado', CURRENT_TIMESTAMP, ?, ?)",
                    [$vData['calle_id'], $vivienda_id, $user['id'], $user['id'], $monto_deuda, $detalles_deuda]
                );

                // 4. Anular servicio
                $this->ejecutarConsulta("UPDATE viviendas SET estado_servicio = 'Anulado' WHERE id = ?", [$vivienda_id]);

                $pdo->commit();
                $mensaje_exito = "Servicio anulado directamente por Administración. Deuda registrada: " . monto($monto_deuda);
            } catch (\Exception $e) {
                if(isset($pdo)) $pdo->rollBack();
                $mensaje_error = "Error al ejecutar baja: " . $e->getMessage();
            }
        }
    }


    // Inserta en la tabla de detalles según el rol
    private function insertarDetallesRol($pdo, $user_id, $rol_id, $usuarioExistenteId = 0) {
        $common_dni = $_POST['dni'] ?? null;
        $common_tel = $_POST['telefono'] ?? null;

        if ($rol_id == 5) { // Encargado de Barrio
            $barrio_id = (int)($_POST['barrio_id'] ?? 0);
            if ($barrio_id <= 0) {
                throw new \InvalidArgumentException("El barrio seleccionado no es válido.");
            }
            $encargadoExistente = $pdo->prepare(
                "SELECT usuario_id FROM detalles_encargado_barrio WHERE barrio_id = ? AND usuario_id <> ? LIMIT 1"
            );
            $encargadoExistente->execute([$barrio_id, $usuarioExistenteId]);
            if ($encargadoExistente->fetchColumn()) {
                throw new \InvalidArgumentException(
                    "El barrio seleccionado ya tiene un encargado asignado."
                );
            }
            $pdo->prepare(
                "INSERT INTO detalles_encargado_barrio (usuario_id, barrio_id, dni, telefono, direccion)
                 VALUES (?, ?, ?, ?, ?)"
            )->execute([
                $user_id,
                $barrio_id,
                $common_dni,
                $common_tel,
                $_POST['direccion'] ?? null,
            ]);
        } elseif ($rol_id == 6) { // Encargado de Calle
            $calle_id = (int)($_POST['calle_id'] ?? 0);
            $calle_barrio_id = (int)($_POST['calle_barrio_id'] ?? 0);
            if ($calle_id <= 0) {
                throw new \InvalidArgumentException("La calle seleccionada no es válida.");
            }
            $calleValida = $pdo->prepare(
                "SELECT id FROM calles WHERE id = ? AND barrio_id = ?
                 AND NOT EXISTS (
                    SELECT 1 FROM detalles_encargado_calle dcalle
                    WHERE dcalle.calle_id = calles.id AND dcalle.usuario_id <> ?
                 )"
            );
            $calleValida->execute([$calle_id, $calle_barrio_id, $usuarioExistenteId]);
            if (!$calleValida->fetchColumn()) {
                throw new \InvalidArgumentException("La calle no pertenece al barrio seleccionado o ya tiene encargado.");
            }
            $encargadoExistente = $pdo->prepare(
                "SELECT usuario_id FROM detalles_encargado_calle WHERE calle_id = ? AND usuario_id <> ? LIMIT 1"
            );
            $encargadoExistente->execute([$calle_id, $usuarioExistenteId]);
            if ($encargadoExistente->fetchColumn()) {
                throw new \InvalidArgumentException(
                    "La calle seleccionada ya tiene un encargado asignado."
                );
            }
            $pdo->prepare(
                "INSERT INTO detalles_encargado_calle (usuario_id, calle_id, dni, telefono)
                 VALUES (?, ?, ?, ?)"
            )->execute([
                $user_id,
                $calle_id,
                $common_dni,
                $common_tel,
            ]);
        } elseif ($rol_id == 2) { // Gestor
            $pdo->prepare(
                "INSERT INTO detalles_gestor (usuario_id, dni, telefono, area)
                 VALUES (?, ?, ?, ?)"
            )->execute([
                $user_id,
                $common_dni,
                $common_tel,
                $_POST['area'] ?? null,
            ]);
        } elseif ($rol_id == 3) { // Personal Obrero
            $pdo->prepare(
                "INSERT INTO detalles_personal_obrero (usuario_id, cargo, dni, telefono, turno)
                 VALUES (?, ?, ?, ?, ?)"
            )->execute([
                $user_id,
                $_POST['cargo'] ?? 'Recolector',
                $common_dni,
                $common_tel,
                $_POST['turno'] ?? 'Mañana',
            ]);
        }
    }
}
