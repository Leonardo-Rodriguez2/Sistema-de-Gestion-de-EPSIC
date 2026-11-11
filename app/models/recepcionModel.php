<?php

namespace app\models;

use \PDO;

require_once __DIR__ . '/../validacion.php';

// =============================================
// app/models/recepcionModel.php — Recepción de residuos
// Registra, consulta y anula el material que ingresa al sistema.
// Todos los roles internos pueden registrar; admin/gestor ven todo,
// barrio ve su barrio y calle ve su calle.
// =============================================

class recepcionModel extends mainModel {

    private $rolesPermitidos = [1, 2, 3, 5, 6];

    /** Crea la tabla si todavía no existe. */
    public function asegurarTabla(): void {
        $this->asegurarRecepcionesResiduos();
    }

    /**
     * Devuelve la zona que corresponde al usuario.
     * Los roles 1, 2 y 3 no tienen zona fija.
     */
    public function alcanceUsuario(array $user): array {
        $alcance = ['barrio_id' => null, 'calle_id' => null];
        $rol = (int)($user['rol_id'] ?? 0);
        $uid = (int)($user['id'] ?? 0);

        if ($rol === 5) {
            $barrio = $this->ejecutarConsulta(
                "SELECT barrio_id FROM detalles_encargado_barrio WHERE usuario_id = ? LIMIT 1",
                [$uid]
            )->fetchColumn();
            $alcance['barrio_id'] = $barrio ? (int)$barrio : null;
        }

        if ($rol === 6) {
            $fila = $this->ejecutarConsulta(
                "SELECT dc.calle_id, c.barrio_id
                 FROM detalles_encargado_calle dc
                 JOIN calles c ON c.id = dc.calle_id
                 WHERE dc.usuario_id = ? LIMIT 1",
                [$uid]
            )->fetch(PDO::FETCH_ASSOC);

            if ($fila) {
                $alcance['calle_id']  = (int)$fila['calle_id'];
                $alcance['barrio_id'] = (int)$fila['barrio_id'];
            }
        }

        return $alcance;
    }

    /** Barrios disponibles para los formularios y filtros. */
    public function barrios(): array {
        return $this->ejecutarConsulta(
            "SELECT id, nombre FROM barrios ORDER BY nombre ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Calles disponibles; si se indica barrio_id, solo las de ese barrio. */
    public function calles($barrio_id = null): array {
        $sql = "SELECT c.id, c.nombre, c.barrio_id, b.nombre AS barrio_nombre
                FROM calles c
                LEFT JOIN barrios b ON b.id = c.barrio_id";
        $params = [];

        if ((int)$barrio_id > 0) {
            $sql .= " WHERE c.barrio_id = ?";
            $params[] = (int)$barrio_id;
        }

        $sql .= " ORDER BY b.nombre ASC, c.nombre ASC";
        return $this->ejecutarConsulta($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Historial de recepciones con los filtros indicados.
     * El alcance por rol se aplica siempre, antes de los filtros de la vista.
     */
    public function listar(array $user, array $filtros = [], int $limite = 200): array {
        [$where, $params] = $this->construirFiltros($user, $filtros);
        // El historial visible usa 200; la exportación puede solicitar más.
        $limite = max(1, min(5000, $limite));

        $sql = "SELECT r.*,
                       b.nombre AS barrio_nombre,
                       c.nombre AS calle_nombre,
                       u.nombre AS registrado_nombre,
                       u.apellido AS registrado_apellido,
                       ua.nombre AS anulado_nombre,
                       ua.apellido AS anulado_apellido
                FROM recepciones_residuos r
                LEFT JOIN barrios b ON b.id = r.barrio_id
                LEFT JOIN calles c ON c.id = r.calle_id
                LEFT JOIN usuarios u ON u.id = r.registrado_por
                LEFT JOIN usuarios ua ON ua.id = r.anulado_por
                WHERE {$where}
                ORDER BY r.fecha_recepcion DESC, r.id DESC
                LIMIT {$limite}";

        return $this->ejecutarConsulta($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Totales del conjunto filtrado (no solo de la página visible). */
    public function resumen(array $user, array $filtros = []): array {
        [$where, $params] = $this->construirFiltros($user, $filtros);

        $sql = "SELECT COUNT(*) AS total,
                       COALESCE(SUM(CASE WHEN r.estado = 'Registrada' THEN 1 ELSE 0 END), 0) AS activas,
                       COALESCE(SUM(CASE WHEN r.estado = 'Registrada' THEN r.peso_kg ELSE 0 END), 0) AS kg,
                       COALESCE(SUM(CASE WHEN r.estado = 'Registrada' AND r.fecha_recepcion = CURDATE() THEN r.peso_kg ELSE 0 END), 0) AS kg_hoy
                FROM recepciones_residuos r
                LEFT JOIN barrios b ON b.id = r.barrio_id
                LEFT JOIN calles c ON c.id = r.calle_id
                LEFT JOIN usuarios u ON u.id = r.registrado_por
                WHERE {$where}";

        $fila = $this->ejecutarConsulta($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return $fila ?: ['total' => 0, 'activas' => 0, 'kg' => 0, 'kg_hoy' => 0];
    }

    /** Kilogramos agrupados por material para el periodo filtrado. */
    public function resumenPorMaterial(array $user, array $filtros = []): array {
        [$where, $params] = $this->construirFiltros($user, $filtros);

        $sql = "SELECT r.tipo_material,
                       COUNT(*) AS total,
                       COALESCE(SUM(r.peso_kg), 0) AS kg
                FROM recepciones_residuos r
                LEFT JOIN barrios b ON b.id = r.barrio_id
                LEFT JOIN calles c ON c.id = r.calle_id
                LEFT JOIN usuarios u ON u.id = r.registrado_por
                WHERE {$where} AND r.estado = 'Registrada'
                GROUP BY r.tipo_material
                ORDER BY kg DESC, r.tipo_material ASC";

        return $this->ejecutarConsulta($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Indica si el usuario puede anular una recepción concreta. */
    public function puedeAnular(array $user, array $fila): bool {
        if (($fila['estado'] ?? '') !== 'Registrada') return false;

        $rol = (int)($user['rol_id'] ?? 0);
        if (in_array($rol, [1, 2], true)) return true;

        return (int)($fila['registrado_por'] ?? 0) === (int)($user['id'] ?? 0);
    }

    /**
     * Registra una recepción. Devuelve el ID creado.
     * Lanza \InvalidArgumentException con un mensaje apto para el usuario.
     */
    public function registrar(array $user, array $datos): int {
        $rol = (int)($user['rol_id'] ?? 0);
        $uid = (int)($user['id'] ?? 0);

        if (!in_array($rol, $this->rolesPermitidos, true) || $uid <= 0) {
            throw new \InvalidArgumentException('No tienes permisos para registrar recepciones de residuos.');
        }

        $tipo = trim((string)($datos['tipo_material'] ?? ''));
        if (!in_array($tipo, RECEPCION_TIPOS_MATERIAL, true)) {
            throw new \InvalidArgumentException('Seleccione un tipo de material válido.');
        }

        $pesoTexto = str_replace(',', '.', trim((string)($datos['peso_kg'] ?? '')));
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $pesoTexto)) {
            throw new \InvalidArgumentException('Ingrese un peso válido, con hasta 2 decimales.');
        }
        $peso = (float)$pesoTexto;
        if ($peso < 0.01 || $peso > 999999.99) {
            throw new \InvalidArgumentException('El peso debe estar entre 0.01 y 999999.99 kg.');
        }

        $fecha = trim((string)($datos['fecha_recepcion'] ?? ''));
        $fechaObj = \DateTime::createFromFormat('!Y-m-d', $fecha);
        $hoy = new \DateTime('today');
        $minima = new \DateTime('2000-01-01');
        if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
            throw new \InvalidArgumentException('La fecha de recepción no tiene un formato válido.');
        }
        if ($fechaObj > $hoy) {
            throw new \InvalidArgumentException('La fecha de recepción no puede ser futura.');
        }
        if ($fechaObj < $minima) {
            throw new \InvalidArgumentException('La fecha de recepción no puede ser anterior al 01/01/2000.');
        }

        $origen = trim((string)($datos['origen_tipo'] ?? ''));
        if (!in_array($origen, RECEPCION_ORIGENES, true)) {
            throw new \InvalidArgumentException('Seleccione un origen válido.');
        }

        $barrioId = (int)($datos['barrio_id'] ?? 0);
        $calleId  = (int)($datos['calle_id'] ?? 0);
        $referencia = trim((string)($datos['referencia_origen'] ?? ''));
        $observaciones = trim((string)($datos['observaciones_recepcion'] ?? ''));
        $alcance = $this->alcanceUsuario($user);

        if ($rol === 5) {
            if (empty($alcance['barrio_id'])) {
                throw new \InvalidArgumentException('No tienes un barrio asignado para registrar recepciones.');
            }
            $barrioId = (int)$alcance['barrio_id'];
        }

        if ($rol === 6) {
            if (empty($alcance['calle_id']) || empty($alcance['barrio_id'])) {
                throw new \InvalidArgumentException('No tienes una calle asignada para registrar recepciones.');
            }
            $barrioId = (int)$alcance['barrio_id'];
            $calleId  = (int)$alcance['calle_id'];
        }

        // Verificar que la calle exista y pertenezca al barrio indicado.
        if ($calleId > 0) {
            $calle = $this->ejecutarConsulta(
                "SELECT id, barrio_id FROM calles WHERE id = ? LIMIT 1",
                [$calleId]
            )->fetch(PDO::FETCH_ASSOC);

            if (!$calle) {
                throw new \InvalidArgumentException('La calle seleccionada no existe.');
            }

            if ($barrioId > 0 && (int)$calle['barrio_id'] !== $barrioId) {
                throw new \InvalidArgumentException('La calle seleccionada no pertenece al barrio indicado.');
            }

            if ($barrioId <= 0) {
                $barrioId = (int)$calle['barrio_id'];
            }
        }

        if ($origen !== 'Externo' && $barrioId <= 0) {
            throw new \InvalidArgumentException('Seleccione el barrio de origen.');
        }
        if (in_array($origen, ['Calle', 'Vivienda'], true) && $calleId <= 0) {
            throw new \InvalidArgumentException('Seleccione la calle de origen.');
        }
        if ($origen === 'Externo' && $referencia === '') {
            throw new \InvalidArgumentException('Indique la referencia del origen externo.');
        }

        if ($referencia !== '') {
            $referencia = function_exists('mb_substr') ? mb_substr($referencia, 0, 255) : substr($referencia, 0, 255);
        }
        if ($observaciones !== '') {
            $observaciones = function_exists('mb_substr') ? mb_substr($observaciones, 0, 1000) : substr($observaciones, 0, 1000);
        }

        // Las recepciones externas de admin/gestor/personal no se fuerzan a una zona.
        if ($origen === 'Externo' && in_array($rol, [1, 2, 3], true)) {
            $barrioId = 0;
            $calleId = 0;
        }

        $this->ejecutarConsulta(
            "INSERT INTO recepciones_residuos
                (tipo_material, peso_kg, fecha_recepcion, origen_tipo, barrio_id, calle_id,
                 referencia_origen, observaciones_recepcion, registrado_por, rol_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $tipo,
                number_format($peso, 2, '.', ''),
                $fecha,
                $origen,
                $barrioId > 0 ? $barrioId : null,
                $calleId > 0 ? $calleId : null,
                $referencia !== '' ? $referencia : null,
                $observaciones !== '' ? $observaciones : null,
                $uid,
                $rol,
            ]
        );

        return (int)$this->conectar()->lastInsertId();
    }

    /** Anula una recepción. Solo admin/gestor pueden anular las de otros. */
    public function anular(array $user, int $recepcionId, string $motivo): void {
        $rol = (int)($user['rol_id'] ?? 0);
        $uid = (int)($user['id'] ?? 0);

        if (!in_array($rol, $this->rolesPermitidos, true) || $uid <= 0) {
            throw new \InvalidArgumentException('No tienes permisos para anular recepciones.');
        }

        $motivo = trim($motivo);
        if ($recepcionId <= 0) {
            throw new \InvalidArgumentException('No se indicó la recepción a anular.');
        }
        if ($motivo === '' || (function_exists('mb_strlen') ? mb_strlen($motivo) : strlen($motivo)) < 3) {
            throw new \InvalidArgumentException('Indique un motivo de anulación de al menos 3 caracteres.');
        }

        $fila = $this->ejecutarConsulta(
            "SELECT id, registrado_por, estado FROM recepciones_residuos WHERE id = ? LIMIT 1",
            [$recepcionId]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            throw new \InvalidArgumentException('La recepción indicada no existe.');
        }
        if ($fila['estado'] !== 'Registrada') {
            throw new \InvalidArgumentException('La recepción ya fue anulada.');
        }
        if (!in_array($rol, [1, 2], true) && (int)$fila['registrado_por'] !== $uid) {
            throw new \InvalidArgumentException('Solo puedes anular las recepciones que registraste tú.');
        }

        $this->ejecutarConsulta(
            "UPDATE recepciones_residuos
             SET estado = 'Anulada', anulado_por = ?, motivo_anulacion = ?, fecha_anulacion = NOW()
             WHERE id = ? AND estado = 'Registrada'",
            [$uid, $motivo, $recepcionId]
        );
    }

    /**
     * Construye WHERE + parámetros aplicando primero el alcance del rol.
     * Así ninguna vista puede saltarse las restricciones de barrio/calle.
     */
    private function construirFiltros(array $user, array $filtros): array {
        $where = '1=1';
        $params = [];
        $rol = (int)($user['rol_id'] ?? 0);
        $alcance = $this->alcanceUsuario($user);

        if ($rol === 5) {
            if (empty($alcance['barrio_id'])) {
                $where .= ' AND 1=0';
            } else {
                $where .= ' AND r.barrio_id = ?';
                $params[] = (int)$alcance['barrio_id'];
            }
        } elseif ($rol === 6) {
            if (empty($alcance['calle_id'])) {
                $where .= ' AND 1=0';
            } else {
                $where .= ' AND r.calle_id = ?';
                $params[] = (int)$alcance['calle_id'];
            }
        }

        $tipo = trim((string)($filtros['tipo_material'] ?? ''));
        if (in_array($tipo, RECEPCION_TIPOS_MATERIAL, true)) {
            $where .= ' AND r.tipo_material = ?';
            $params[] = $tipo;
        }

        $origen = trim((string)($filtros['origen_tipo'] ?? ''));
        if (in_array($origen, RECEPCION_ORIGENES, true)) {
            $where .= ' AND r.origen_tipo = ?';
            $params[] = $origen;
        }

        $estado = trim((string)($filtros['estado'] ?? ''));
        if (in_array($estado, RECEPCION_ESTADOS, true)) {
            $where .= ' AND r.estado = ?';
            $params[] = $estado;
        }

        $barrioId = (int)($filtros['barrio_id'] ?? 0);
        if ($barrioId > 0) {
            $where .= ' AND r.barrio_id = ?';
            $params[] = $barrioId;
        }

        $calleId = (int)($filtros['calle_id'] ?? 0);
        if ($calleId > 0) {
            $where .= ' AND r.calle_id = ?';
            $params[] = $calleId;
        }

        $desde = trim((string)($filtros['desde'] ?? ''));
        if ($this->fechaISOValida($desde)) {
            $where .= ' AND r.fecha_recepcion >= ?';
            $params[] = $desde;
        }

        $hasta = trim((string)($filtros['hasta'] ?? ''));
        if ($this->fechaISOValida($hasta)) {
            $where .= ' AND r.fecha_recepcion <= ?';
            $params[] = $hasta;
        }

        $busqueda = trim((string)($filtros['q'] ?? ''));
        if ($busqueda !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busqueda) . '%';
            $where .= " AND (r.tipo_material LIKE ?
                        OR r.referencia_origen LIKE ?
                        OR r.observaciones_recepcion LIKE ?
                        OR CONCAT_WS(' ', u.nombre, u.apellido) LIKE ?
                        OR b.nombre LIKE ?
                        OR c.nombre LIKE ?)";
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        return [$where, $params];
    }

    private function fechaISOValida(string $fecha): bool {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) return false;
        $obj = \DateTime::createFromFormat('!Y-m-d', $fecha);
        return $obj && $obj->format('Y-m-d') === $fecha;
    }
}
