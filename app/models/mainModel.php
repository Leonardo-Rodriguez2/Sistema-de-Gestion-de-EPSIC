<?php

namespace app\models;
use \PDO;

// =============================================
// app/models/mainModel.php — Modelo Base
// Todas las clases del sistema heredan de aquí.
// Contiene la conexión a BD y métodos CRUD genéricos.
// =============================================

require_once __DIR__ . '/../../app/config.php';

class mainModel {

    private $server = DB_SERVER;
    private $db     = DB_NAME;
    private $user   = DB_USER;
    private $pass   = DB_PASS;


    // --- Conexión PDO (se reutiliza via static) ---
    public function conectar() {
        static $conexion = null;
        if ($conexion === null) {
            $dsn = "mysql:host={$this->server};port=" . DB_PORT . ";dbname={$this->db};charset=utf8mb4";
            $conexion = new PDO(
                $dsn,
                $this->user,
                $this->pass
            );
            $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return $conexion;
    }


    // --- Ejecutar cualquier consulta con parámetros ---
    protected function ejecutarConsulta($sql, $params = []) {
        $stmt = $this->conectar()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }


    // --- Obtener un usuario por ID (usado en todos los roles) ---
    protected function obtenerUsuario($id) {
        $stmt = $this->ejecutarConsulta(
            "SELECT u.id, u.nombre, u.apellido, u.email, u.rol_id, r.nombre as rol_nombre
             FROM usuarios u JOIN roles r ON u.rol_id = r.id
             WHERE u.id = ? AND u.estado = 'Activo'",
            [$id]
        );
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    // --- Limpiar texto de entrada del usuario ---
    public function limpiarCadena($cadena) {
        $cadena = trim($cadena);
        $cadena = stripslashes($cadena);
        $cadena = htmlspecialchars($cadena, ENT_QUOTES, 'UTF-8');
        return $cadena;
    }

    protected function asegurarColumnasPagos() {
        $pdo = $this->conectar();

        $columnasBarrios = [
            ['parroquia_municipio', 'VARCHAR(150) NULL'],
            ['lider_comunidad', 'VARCHAR(150) NULL'],
            ['limite_referencia', 'TEXT NULL'],
        ];
        foreach ($columnasBarrios as [$col, $def]) {
            $chk = $pdo->prepare("SHOW COLUMNS FROM barrios LIKE ?");
            $chk->execute([$col]);
            if ($chk->rowCount() === 0) {
                $pdo->exec("ALTER TABLE barrios ADD COLUMN `{$col}` {$def}");
            }
        }

        // --- Columnas en configuraciones_barrio ---
        // fecha_limite_pago: fecha exigida por el encargado de barrio a los
        // encargados de calle para realizar el pago del periodo.
        $columnasConfigBarrio = [
            ['fecha_limite_pago',   'DATE NULL'],
            ['precio_comercio',     'DECIMAL(10,2) DEFAULT NULL'],
            ['precio_tienda',       'DECIMAL(10,2) DEFAULT NULL'],
            ['precio_empresa',      'DECIMAL(10,2) DEFAULT NULL'],
            ['precio_urbanizacion', 'DECIMAL(10,2) DEFAULT NULL'],
            ['precio_otro',         'DECIMAL(10,2) DEFAULT NULL'],
            // Pago único por recojo especial (eventos, desmonte, electrónicos).
            ['precio_especial',     'DECIMAL(10,2) NOT NULL DEFAULT 20.00'],
            // Bolívares por cada dólar. Se usa para mostrar todos los importes
            // en US$ y en Bs. a la vez.
            ['precio_bolivar',              'DECIMAL(14,4) NOT NULL DEFAULT 130.0000'],
            // Control de la tasa obtenida automáticamente desde internet.
            ['precio_bolivar_actualizado_en', 'DATETIME NULL'],
            ['precio_bolivar_fuente',         'VARCHAR(60) NULL'],
            ['tasa_automatica',               'TINYINT(1) NOT NULL DEFAULT 1'],
        ];
        foreach ($columnasConfigBarrio as [$col, $def]) {
            $chk = $pdo->prepare("SHOW COLUMNS FROM configuraciones_barrio LIKE ?");
            $chk->execute([$col]);
            if ($chk->rowCount() === 0) {
                $pdo->exec("ALTER TABLE configuraciones_barrio ADD COLUMN `{$col}` {$def}");
            }
        }
        // Valor inicial para barrios que aún nunca guardaron la configuración.
        $pdo->exec("UPDATE configuraciones_barrio SET fecha_limite_pago = LAST_DAY(CURDATE()) WHERE fecha_limite_pago IS NULL");
        $pdo->exec("UPDATE configuraciones_barrio SET precio_bolivar = 130.0000 WHERE precio_bolivar IS NULL OR precio_bolivar <= 0");

        // --- Columnas en cobros ---
        $columnasCobros = [
            ['tipo_cobro',              'VARCHAR(30) NOT NULL DEFAULT "Servicio"'],
            ['estado_verificacion',     'VARCHAR(20) NOT NULL DEFAULT "Pendiente"'],
            ['verificado_por',          'INT NULL'],
            ['verificado_en',           'TIMESTAMP NULL'],
            ['motivo_rechazo',          'TEXT NULL'],
            ['comprobante_calle',       'VARCHAR(255) NULL'],
            ['comprobante_barrio',      'VARCHAR(255) NULL'],
            ['observaciones',           'TEXT NULL'],
            ['fecha_confirmacion_calle','DATE NULL'],
            ['fecha_confirmacion_barrio','DATE NULL'],
            ['lote_calle_id',           'INT(11) NULL'],
            ['referencia_pago',        'VARCHAR(255) NULL'],
        ];
        foreach ($columnasCobros as [$col, $def]) {
            $chk = $pdo->prepare("SHOW COLUMNS FROM cobros LIKE ?");
            $chk->execute([$col]);
            if ($chk->rowCount() === 0) {
                $pdo->exec("ALTER TABLE cobros ADD COLUMN {$col} {$def}");
            }

        }

        $columnasSolicitudes = [
            ['referencia_pago', 'VARCHAR(255) NULL'],
            ['comprobante_pago', 'VARCHAR(255) NULL'],
        ];
        foreach ($columnasSolicitudes as [$col, $def]) {
            $chk = $pdo->prepare("SHOW COLUMNS FROM solicitudes_vivienda LIKE ?");
            $chk->execute([$col]);
            if ($chk->rowCount() === 0) {
                $pdo->exec("ALTER TABLE solicitudes_vivienda ADD COLUMN `{$col}` {$def}");
            }
        }

        // Ampliar el ENUM de estado para soportar anulaciones y rechazos
        $colEstado = $pdo->query("SHOW COLUMNS FROM cobros LIKE 'estado'")->fetch(PDO::FETCH_ASSOC);
        if ($colEstado && strpos((string)$colEstado['Type'], 'Anulado') === false) {
            $pdo->exec("ALTER TABLE cobros MODIFY COLUMN `estado` ENUM('Pendiente','Pagado','Vencido','Anulado','Rechazado') NULL DEFAULT 'Pendiente'");
        }

        // Asegurar todas las columnas necesarias en lotes_calle
        $columnasLotesCalle = [
            ['periodo_mes',         'INT(2) NOT NULL DEFAULT 0'],
            ['periodo_anio',        'INT(4) NOT NULL DEFAULT 0'],
            ['barrio_id',           'INT(11) NOT NULL DEFAULT 0'],
            ['encargado_calle_id',  'INT(11) NOT NULL DEFAULT 0'],
            ['encargado_barrio_id', 'INT(11) DEFAULT NULL'],
            ['monto_esperado',      'DECIMAL(10,2) NOT NULL DEFAULT 0.00'],
            ['total_casas',         'INT(11) NOT NULL DEFAULT 0'],
            ['casas_pagadas',       'INT(11) NOT NULL DEFAULT 0'],
            ['casas_morosas',       'INT(11) NOT NULL DEFAULT 0'],
            ['alerta_deuda',        'TINYINT(1) NOT NULL DEFAULT 0'],
            ['observaciones_calle', 'TEXT DEFAULT NULL'],
            ['observaciones_barrio','TEXT DEFAULT NULL'],
            ['lote_barrio_id',      'INT(11) DEFAULT NULL'],
            ['fecha_revision',      'TIMESTAMP NULL DEFAULT NULL'],
            ['comprobante_lote',    'VARCHAR(255) NULL'],
            ['referencia_lote',     'VARCHAR(255) NULL'],
            ['certificado_generado','TINYINT(1) NOT NULL DEFAULT 0'],
            ['certificado_enviado_calle','TINYINT(1) NOT NULL DEFAULT 0'],
            ['factura_personalizada','VARCHAR(255) NULL'],
            ['fecha_certificado',   'TIMESTAMP NULL DEFAULT NULL'],
        ];
        foreach ($columnasLotesCalle as [$col, $def]) {
            $chk = $pdo->prepare("SHOW COLUMNS FROM lotes_calle LIKE ?");
            $chk->execute([$col]);
            if ($chk->rowCount() === 0) {
                $pdo->exec("ALTER TABLE lotes_calle ADD COLUMN `{$col}` {$def}");
            }

            $columnasCalles = [
                ['tipo_via', 'VARCHAR(30) NOT NULL DEFAULT "Calle"'],
                ['descripcion', 'VARCHAR(255) DEFAULT NULL'],
            ];
            foreach ($columnasCalles as [$col, $def]) {
                $chk = $pdo->prepare("SHOW COLUMNS FROM calles LIKE ?");
                $chk->execute([$col]);
                if ($chk->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE calles ADD COLUMN `{$col}` {$def}");
                }
            }
        }

        // Asegurar la llave única (calle_id, periodo_mes, periodo_anio) que usa el
        // "ON DUPLICATE KEY UPDATE" al enviar el lote de calle.
        $idxRows = $pdo->query("SHOW INDEX FROM lotes_calle")->fetchAll(PDO::FETCH_ASSOC);
        $hasUqLote = false;
        foreach ($idxRows as $r) {
            if (($r['Key_name'] ?? '') === 'uq_lote_calle') { $hasUqLote = true; break; }
        }
        if (!$hasUqLote) {
            // Eliminar posibles duplicados conservando el registro de menor id
            $pdo->exec("DELETE lc1 FROM lotes_calle lc1 INNER JOIN lotes_calle lc2
                        WHERE lc1.id > lc2.id AND lc1.calle_id = lc2.calle_id
                          AND lc1.periodo_mes = lc2.periodo_mes AND lc1.periodo_anio = lc2.periodo_anio");
            try {
                $pdo->exec("ALTER TABLE lotes_calle ADD UNIQUE KEY `uq_lote_calle` (calle_id, periodo_mes, periodo_anio)");
            } catch (\Exception $e) {
                // El índice ya existe o los datos no lo permiten: no bloquear la app
            }
        }
        // Copiar datos existentes de mes/anio a periodo_mes/periodo_anio si están vacíos
        $pdo->exec("UPDATE lotes_calle SET periodo_mes = mes WHERE periodo_mes = 0 AND mes > 0");
        $pdo->exec("UPDATE lotes_calle SET periodo_anio = anio WHERE periodo_anio = 0 AND anio > 0");
        // Completar el barrio de lotes antiguos usando la calle asignada.
        // Sin este dato no pueden vincularse al lote de barrio ni mostrarse como certificados.
        $pdo->exec(
            "UPDATE lotes_calle lc
             INNER JOIN calles c ON c.id = lc.calle_id
             SET lc.barrio_id = c.barrio_id
             WHERE lc.barrio_id IS NULL OR lc.barrio_id = 0"
        );
        // Data migration: marcar certificado_generado=1 para lotes ya aprobados antes de la migración
        $pdo->exec("UPDATE lotes_calle lc JOIN lotes_barrio lb ON lc.lote_barrio_id = lb.id SET lc.certificado_generado = 1 WHERE lb.estado = 'Aprobado' AND lc.certificado_generado = 0");

        // Asegurar columnas en lotes_barrio
        $chkBarrio = $pdo->prepare("SHOW COLUMNS FROM lotes_barrio LIKE 'comprobante_lote'");
        $chkBarrio->execute();
        if ($chkBarrio->rowCount() === 0) {
            $pdo->exec("ALTER TABLE lotes_barrio ADD COLUMN comprobante_lote VARCHAR(255) NULL");
        }
        $chkFacturasEnviadas = $pdo->prepare("SHOW COLUMNS FROM lotes_barrio LIKE 'facturas_enviadas_barrio'");
        $chkFacturasEnviadas->execute();
        if ($chkFacturasEnviadas->rowCount() === 0) {
            $pdo->exec("ALTER TABLE lotes_barrio ADD COLUMN facturas_enviadas_barrio TINYINT(1) NOT NULL DEFAULT 0");
        }
        
        // Asegurar que existan columnas mes y anio en lotes_barrio
        $chkMesBarrio = $pdo->prepare("SHOW COLUMNS FROM lotes_barrio LIKE 'mes'");
        $chkMesBarrio->execute();
        if ($chkMesBarrio->rowCount() === 0) {
            $pdo->exec("ALTER TABLE lotes_barrio ADD COLUMN mes INT(2) NOT NULL DEFAULT 0");
            $pdo->exec("ALTER TABLE lotes_barrio ADD COLUMN anio INT(4) NOT NULL DEFAULT 0");
            $pdo->exec("UPDATE lotes_barrio SET mes = periodo_mes WHERE mes = 0");
            $pdo->exec("UPDATE lotes_barrio SET anio = periodo_anio WHERE anio = 0");
        }

        // --- Columnas en recaudaciones ---
        $columnasRec = [
            ['comprobante', 'VARCHAR(255) NULL'],
            ['observaciones', 'TEXT NULL'],
        ];
        foreach ($columnasRec as [$col, $def]) {
            $chk = $pdo->prepare("SHOW COLUMNS FROM recaudaciones LIKE ?");
            $chk->execute([$col]);
            if ($chk->rowCount() === 0) {
                $pdo->exec("ALTER TABLE recaudaciones ADD COLUMN {$col} {$def}");
            }
        }

        // --- Crear tabla lotes_calle si no existe ---
        $pdo->exec("CREATE TABLE IF NOT EXISTS `lotes_calle` (
            `id`                  INT(11) NOT NULL AUTO_INCREMENT,
            `periodo_mes`         INT(2)  NOT NULL,
            `periodo_anio`        INT(4)  NOT NULL,
            `calle_id`            INT(11) NOT NULL,
            `barrio_id`           INT(11) NOT NULL,
            `encargado_calle_id`  INT(11) NOT NULL,
            `encargado_barrio_id` INT(11) DEFAULT NULL,
            `monto_esperado`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `monto_recolectado`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `total_casas`         INT(11) NOT NULL DEFAULT 0,
            `casas_pagadas`       INT(11) NOT NULL DEFAULT 0,
            `casas_morosas`       INT(11) NOT NULL DEFAULT 0,
            `estado`              ENUM('Borrador','Enviado','Aprobado','Rechazado') NOT NULL DEFAULT 'Borrador',
            `alerta_deuda`        TINYINT(1) NOT NULL DEFAULT 0,
            `observaciones_calle` TEXT DEFAULT NULL,
            `observaciones_barrio` TEXT DEFAULT NULL,
            `lote_barrio_id`      INT(11) DEFAULT NULL,
            `fecha_creacion`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `fecha_envio`         TIMESTAMP NULL DEFAULT NULL,
            `fecha_revision`      TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_lote_calle` (`calle_id`,`periodo_mes`,`periodo_anio`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // --- Crear tabla lotes_barrio si no existe ---
        $pdo->exec("CREATE TABLE IF NOT EXISTS `lotes_barrio` (
            `id`                     INT(11) NOT NULL AUTO_INCREMENT,
            `periodo_mes`            INT(2)  NOT NULL,
            `periodo_anio`           INT(4)  NOT NULL,
            `barrio_id`              INT(11) NOT NULL,
            `encargado_barrio_id`    INT(11) NOT NULL,
            `gestor_id`              INT(11) DEFAULT NULL,
            `monto_total_esperado`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `monto_total_recolectado` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `total_calles`           INT(11) NOT NULL DEFAULT 0,
            `calles_completas`       INT(11) NOT NULL DEFAULT 0,
            `estado`                 ENUM('Borrador','Enviado','Aprobado','Rechazado') NOT NULL DEFAULT 'Borrador',
            `alerta_deuda`           TINYINT(1) NOT NULL DEFAULT 0,
            `comprobante_barrio`     VARCHAR(255) DEFAULT NULL,
            `observaciones_barrio`   TEXT DEFAULT NULL,
            `observaciones_gestor`   TEXT DEFAULT NULL,
            `recibo_generado`        TINYINT(1) NOT NULL DEFAULT 0,
            `facturas_enviadas_barrio` TINYINT(1) NOT NULL DEFAULT 0,
            `fecha_creacion`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `fecha_envio`            TIMESTAMP NULL DEFAULT NULL,
            `fecha_aprobacion`       TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_lote_barrio` (`barrio_id`,`periodo_mes`,`periodo_anio`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // --- Crear tabla recibos_finiquito si no existe ---
        $pdo->exec("CREATE TABLE IF NOT EXISTS `recibos_finiquito` (
            `id`             INT(11) NOT NULL AUTO_INCREMENT,
            `lote_barrio_id` INT(11) NOT NULL,
            `barrio_id`      INT(11) NOT NULL,
            `periodo_mes`    INT(2)  NOT NULL,
            `periodo_anio`   INT(4)  NOT NULL,
            `monto_aprobado` DECIMAL(10,2) NOT NULL,
            `generado_por`   INT(11) NOT NULL,
            `numero_recibo`  VARCHAR(30) NOT NULL,
            `fecha_emision`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_numero_recibo` (`numero_recibo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // --- Migración: crear tabla detalles_personal_obrero (si solo existe detalles_recolector) ---
        $chkPersonal = $pdo->prepare("SHOW TABLES LIKE 'detalles_personal_obrero'");
        $chkPersonal->execute();
        if ($chkPersonal->rowCount() === 0) {
            $chkRec = $pdo->prepare("SHOW TABLES LIKE 'detalles_recolector'");
            $chkRec->execute();
            if ($chkRec->rowCount() > 0) {
                // Renombrar la tabla existente y agregar columna cargo
                $pdo->exec("RENAME TABLE detalles_recolector TO detalles_personal_obrero");
                $chkCargo = $pdo->prepare("SHOW COLUMNS FROM detalles_personal_obrero LIKE 'cargo'");
                $chkCargo->execute();
                if ($chkCargo->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE detalles_personal_obrero ADD COLUMN cargo VARCHAR(50) DEFAULT 'Recolector' AFTER usuario_id");
                }
            } else {
                // Crear desde cero
                $pdo->exec("CREATE TABLE IF NOT EXISTS `detalles_personal_obrero` (
                    `id`          INT(11) NOT NULL AUTO_INCREMENT,
                    `usuario_id`  INT(11) NOT NULL,
                    `cargo`       VARCHAR(50) DEFAULT 'Recolector',
                    `dni`         VARCHAR(20) DEFAULT NULL,
                    `telefono`    VARCHAR(20) DEFAULT NULL,
                    `turno`       ENUM('Mañana','Tarde','Noche') DEFAULT 'Mañana',
                    `contacto_emergencia` VARCHAR(255) DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }
        }
    }

    protected function asegurarEstadoUsuarios(): void {
        $pdo = $this->conectar();
        $column = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'estado'")->fetch(PDO::FETCH_ASSOC);
        if (!$column) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN estado ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo' AFTER rol_id");
        }
    }

    // --- Migración / creación de tabla exenciones_cobro ---
    protected function asegurarExenciones() {
        $pdo = $this->conectar();

        // 1. Crear tabla de solicitudes/registros de exención
        $pdo->exec("CREATE TABLE IF NOT EXISTS `exenciones_cobro` (
            `id`           INT(11) NOT NULL AUTO_INCREMENT,
            `vivienda_id`  INT(11) NOT NULL,
            `calle_id`     INT(11) NOT NULL,
            `barrio_id`    INT(11) NOT NULL,
            `tipo_exencion` ENUM('pobreza','adulto_mayor','empleado','espacio_publico','otro') NOT NULL DEFAULT 'otro',
            `descripcion`  TEXT DEFAULT NULL,
            `creado_por`   INT(11) NOT NULL,
            `estado`       ENUM('Pendiente','Aprobado','Rechazado') NOT NULL DEFAULT 'Pendiente',
            `aprobado_por` INT(11) DEFAULT NULL,
            `motivo_rechazo` TEXT DEFAULT NULL,
            `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `fecha_revision` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_vivienda` (`vivienda_id`),
            KEY `idx_barrio_estado` (`barrio_id`,`estado`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 2. Agregar columna exento_cobro a viviendas (para consulta rápida)
        $chk = $pdo->prepare("SHOW COLUMNS FROM viviendas LIKE 'exento_cobro'");
        $chk->execute();
        if ($chk->rowCount() === 0) {
            $pdo->exec("ALTER TABLE viviendas ADD COLUMN `exento_cobro` TINYINT(1) NOT NULL DEFAULT 0 AFTER `estado_servicio`");
        }

        // 3. Migrar exenciones aprobadas: actualizar columna en viviendas
        $pdo->exec("UPDATE viviendas v JOIN exenciones_cobro e ON v.id = e.vivienda_id AND e.estado = 'Aprobado' SET v.exento_cobro = 1 WHERE v.exento_cobro = 0");
    }

    // --- Migración / creación de las columnas de identificación del dueño de la vivienda ---
    protected function asegurarCedula() {
        $pdo = $this->conectar();

        $tablas = ['viviendas', 'solicitudes_vivienda'];

        foreach ($tablas as $tabla) {
            // Columna cédula
            $chk = $pdo->prepare("SHOW COLUMNS FROM `$tabla` LIKE 'cedula'");
            $chk->execute();
            if ($chk->rowCount() === 0) {
                $pdo->exec("ALTER TABLE `$tabla` ADD COLUMN `cedula` VARCHAR(20) DEFAULT NULL AFTER `propietario`");
            }

            // Tipo de cédula: V = Venezolano, E = Extranjero
            $chk = $pdo->prepare("SHOW COLUMNS FROM `$tabla` LIKE 'cedula_tipo'");
            $chk->execute();
            if ($chk->rowCount() === 0) {
                $pdo->exec("ALTER TABLE `$tabla` ADD COLUMN `cedula_tipo` VARCHAR(1) NOT NULL DEFAULT 'V' AFTER `cedula`");
            }

            // Otro documento de identidad (pasaporte, cédula extranjera, RIF, etc.)
            $chk = $pdo->prepare("SHOW COLUMNS FROM `$tabla` LIKE 'otro_identificador'");
            $chk->execute();
            if ($chk->rowCount() === 0) {
                $pdo->exec("ALTER TABLE `$tabla` ADD COLUMN `otro_identificador` VARCHAR(100) DEFAULT NULL AFTER `cedula_tipo`");
            }
        }
    }

    /**
     * Predios grandes (comercios, tiendas, empresas, urbanizaciones, etc.) se
     * registran en `viviendas` con un tipo de predio. El precio se configura por
     * barrio y tipo de predio en "Configuración de Pagos", no en el alta.
     */
    protected function asegurarTiposPredio() {
        $pdo = $this->conectar();

        $columnas = [
            ['tipo_predio',    "ENUM('Vivienda','Comercio','Tienda','Empresa','Urbanizacion','Otro') NOT NULL DEFAULT 'Vivienda'"],
            ['monto_mensual',  'DECIMAL(10,2) DEFAULT NULL'],
            ['cantidad_casas', 'INT(11) NOT NULL DEFAULT 1'],
        ];
        foreach ($columnas as [$col, $def]) {
            $chk = $pdo->prepare("SHOW COLUMNS FROM viviendas LIKE ?");
            $chk->execute([$col]);
            if ($chk->rowCount() === 0) {
                $pdo->exec("ALTER TABLE viviendas ADD COLUMN `{$col}` {$def}");
            }
        }

        // Las tarifas ahora viven en configuraciones_barrio; cualquier monto
        // guardado antes por predio dejaría de tener efecto.
        $pdo->exec("UPDATE viviendas SET monto_mensual = NULL WHERE monto_mensual IS NOT NULL");
    }

    /**
     * Expresión SQL reutilizable que calcula el monto mensual de un predio:
     * tarifa configurada para su tipo en el barrio, y en urbanizaciones la
     * tarifa por casa multiplicada por la cantidad de casas declarada.
     * Requiere los alias v (viviendas) y cb (configuraciones_barrio).
     */
    public static function sqlMontoPredio($v = 'v', $cb = 'cb') {
        return "(CASE
            WHEN {$v}.tipo_predio = 'Comercio' THEN COALESCE({$cb}.precio_comercio, {$cb}.cuota_mensual, 10.00)
            WHEN {$v}.tipo_predio = 'Tienda' THEN COALESCE({$cb}.precio_tienda, {$cb}.cuota_mensual, 10.00)
            WHEN {$v}.tipo_predio = 'Empresa' THEN COALESCE({$cb}.precio_empresa, {$cb}.cuota_mensual, 10.00)
            WHEN {$v}.tipo_predio = 'Urbanizacion' THEN COALESCE({$cb}.precio_urbanizacion, {$cb}.cuota_mensual, 10.00) * GREATEST(COALESCE({$v}.cantidad_casas, 1), 1)
            WHEN {$v}.tipo_predio = 'Otro' THEN COALESCE({$cb}.precio_otro, {$cb}.cuota_mensual, 10.00)
            ELSE COALESCE({$cb}.cuota_mensual, 10.00)
        END)";
    }

    /** Tipo de predio: por defecto Vivienda si no viene o no es reconocido. */
    protected function normalizarTipoPredio($valor) {
        $valor = trim((string)$valor);
        $permitidos = defined('TIPOS_PREDIO') ? TIPOS_PREDIO : ['Vivienda'];
        return in_array($valor, $permitidos, true) ? $valor : 'Vivienda';
    }

    /**
     * Cantidad de casas que agrupa una urbanización. El resto de predios usa 1.
     */
    protected function normalizarCantidadCasas($valor, $tipoPredio) {
        $valor = trim((string)$valor);
        if ($valor === '') {
            if ($tipoPredio === 'Urbanizacion') {
                throw new \InvalidArgumentException('Indica la cantidad de casas de la urbanización.');
            }
            return 1;
        }
        if (!ctype_digit($valor) || (int)$valor < 1 || (int)$valor > 100000) {
            throw new \InvalidArgumentException('La cantidad de casas debe ser un número entero entre 1 y 100000.');
        }
        return (int)$valor;
    }

    /**
     * Monto que se cobra al predio según la tarifa configurada para su tipo.
     * En urbanizaciones la tarifa es por casa y se multiplica por su cantidad.
     */
    protected function montoMensualEfectivo($vivienda_id) {
        $sql = "SELECT " . self::sqlMontoPredio() . "
                  FROM viviendas v
                  LEFT JOIN configuraciones_barrio cb ON cb.barrio_id = v.barrio_id
                 WHERE v.id = ?";
        $monto = $this->ejecutarConsulta($sql, [(int)$vivienda_id])->fetchColumn();
        return $monto === false ? 10.00 : (float)$monto;
    }

    // --- Normaliza y valida la cédula: solo se almacenan números ---
    protected function normalizarCedula($valor) {
        $valor = trim((string)$valor);
        if ($valor === '') return null;

        // Se toleran separadores visuales heredados (puntos, espacios y guiones),
        // pero la cédula debe quedar almacenada como exactamente 8 números.
        $numeros = preg_replace('/[\s.\-]/', '', $valor);
        if ($numeros === '' || !ctype_digit($numeros) || strlen($numeros) !== 8) {
            throw new \InvalidArgumentException("La cédula debe contener exactamente 8 números.");
        }
        return $numeros;
    }

    // --- Tipo de cédula: V (Venezolano) o E (Extranjero) ---
    protected function normalizarTipoCedula($valor) {
        return strtoupper(trim((string)$valor)) === 'E' ? 'E' : 'V';
    }

    // --- Otro identificador opcional (pasaporte, documento extranjero, etc.) ---
    protected function normalizarOtroIdentificador($valor) {
        $valor = trim((string)$valor);
        return $valor !== '' ? mb_substr($valor, 0, 100) : null;
    }

    protected function asegurarSolicitudesGestion(): void {
        $pdo = $this->conectar();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `solicitudes_gestion` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `tipo` ENUM('NuevaCalle','NuevoEncargadoCalle') NOT NULL,
            `barrio_id` INT(11) NOT NULL,
            `calle_id` INT(11) DEFAULT NULL,
            `solicitado_por` INT(11) NOT NULL,
            `nombre_calle` VARCHAR(150) DEFAULT NULL,
            `tipo_via` VARCHAR(30) DEFAULT 'Calle',
            `descripcion_calle` TEXT DEFAULT NULL,
            `nombre` VARCHAR(100) DEFAULT NULL,
            `apellido` VARCHAR(100) DEFAULT NULL,
            `email` VARCHAR(150) DEFAULT NULL,
            `dni` VARCHAR(20) DEFAULT NULL,
            `telefono` VARCHAR(30) DEFAULT NULL,
            `estado` ENUM('Pendiente','Aprobada','Rechazada') NOT NULL DEFAULT 'Pendiente',
            `motivo_rechazo` TEXT DEFAULT NULL,
            `procesado_por` INT(11) DEFAULT NULL,
            `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `fecha_revision` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_solicitudes_gestion_estado` (`estado`),
            KEY `idx_solicitudes_gestion_barrio` (`barrio_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // --- Migración / creación de la tabla de recepción de residuos ---
    protected function asegurarRecepcionesResiduos(): void {
        $pdo = $this->conectar();

        $pdo->exec("CREATE TABLE IF NOT EXISTS `recepciones_residuos` (
            `id`                     INT(11) NOT NULL AUTO_INCREMENT,
            `tipo_material`          VARCHAR(40) NOT NULL,
            `peso_kg`                DECIMAL(10,2) NOT NULL,
            `fecha_recepcion`        DATE NOT NULL,
            `origen_tipo`            VARCHAR(20) NOT NULL DEFAULT 'Barrio',
            `barrio_id`              INT(11) DEFAULT NULL,
            `calle_id`               INT(11) DEFAULT NULL,
            `referencia_origen`      VARCHAR(255) DEFAULT NULL,
            `observaciones_recepcion` TEXT DEFAULT NULL,
            `registrado_por`         INT(11) NOT NULL,
            `rol_id`                 INT(11) NOT NULL,
            `estado`                 ENUM('Registrada','Anulada') NOT NULL DEFAULT 'Registrada',
            `anulado_por`            INT(11) DEFAULT NULL,
            `motivo_anulacion`       TEXT DEFAULT NULL,
            `fecha_anulacion`        TIMESTAMP NULL DEFAULT NULL,
            `fecha_creacion`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_recepciones_fecha` (`fecha_recepcion`),
            KEY `idx_recepciones_material` (`tipo_material`),
            KEY `idx_recepciones_barrio` (`barrio_id`),
            KEY `idx_recepciones_calle` (`calle_id`),
            KEY `idx_recepciones_usuario` (`registrado_por`),
            KEY `idx_recepciones_estado` (`estado`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // --- Verificar si una vivienda está exenta de cobro ---
    protected function esViviendaExenta($vivienda_id): bool {
        $stmt = $this->ejecutarConsulta(
            "SELECT exento_cobro FROM viviendas WHERE id = ?",
            [$vivienda_id]
        );
        $exento = (int)$stmt->fetchColumn();
        return $exento === 1;
    }

    protected function guardarArchivoPago($archivo, $subdir = 'pagos') {
        if (!isset($archivo) || !is_array($archivo)) {
            throw new \Exception("No se recibió ningún archivo.");
        }
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $codigos = [
                UPLOAD_ERR_INI_SIZE   => 'El archivo excede el tamaño máximo permitido por el servidor (upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE  => 'El archivo excede el tamaño máximo del formulario (MAX_FILE_SIZE)',
                UPLOAD_ERR_PARTIAL    => 'El archivo solo se subió parcialmente',
                UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor',
                UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
            ];
            $msg = $codigos[$archivo['error']] ?? 'Error desconocido: ' . $archivo['error'];
            throw new \Exception("Error al subir archivo: " . $msg);
        }

        $permitidos = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $permitidos, true)) {
            throw new \Exception("Extensión no permitida: \"{$ext}\". Solo se aceptan: " . implode(', ', $permitidos));
        }

        $directorio = __DIR__ . '/../../uploads/' . $subdir;
        if (!is_dir($directorio)) {
            if (!mkdir($directorio, 0755, true)) {
                throw new \Exception("No se pudo crear el directorio: " . $directorio);
            }
        }

        $nombre = uniqid('pago_', true) . '.' . $ext;
        $destino = $directorio . '/' . $nombre;

        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            throw new \Exception("No se pudo mover el archivo a: " . $destino . " (permisos o disco lleno)");
        }

        return '/reciclaje/uploads/' . $subdir . '/' . $nombre;
    }

    // --- INSERT genérico ---
    // $datos = [['campo_nombre'=>'col', 'campo_marcador'=>':col', 'campo_valor'=>$val], ...]
    protected function guardarDatos($tabla, $datos) {
        $campos   = implode(',', array_column($datos, 'campo_nombre'));
        $marcadores = implode(',', array_column($datos, 'campo_marcador'));
        $sql = "INSERT INTO $tabla ($campos) VALUES ($marcadores)";
        $stmt = $this->conectar()->prepare($sql);
        foreach ($datos as $d) {
            $stmt->bindParam($d['campo_marcador'], $d['campo_valor']);
        }
        $stmt->execute();
        return $stmt;
    }


    // --- UPDATE genérico ---
    // $condicion = ['condicion_campo'=>'id', 'condicion_marcador'=>':id', 'condicion_valor'=>$val]
    protected function actualizarDatos($tabla, $datos, $condicion) {
        $sets = implode(',', array_map(fn($d) => $d['campo_nombre'] . '=' . $d['campo_marcador'], $datos));
        $sql = "UPDATE $tabla SET $sets WHERE {$condicion['condicion_campo']} = {$condicion['condicion_marcador']}";
        $stmt = $this->conectar()->prepare($sql);
        foreach ($datos as $d) {
            $stmt->bindParam($d['campo_marcador'], $d['campo_valor']);
        }
        $stmt->bindParam($condicion['condicion_marcador'], $condicion['condicion_valor']);
        $stmt->execute();
        return $stmt;
    }


    // --- DELETE genérico ---
    protected function eliminarRegistro($tabla, $campo, $id) {
        $stmt = $this->conectar()->prepare("DELETE FROM $tabla WHERE $campo = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt;
    }
}
