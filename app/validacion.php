<?php

/**
 * app/validacion.php
 * Validaciones centralizadas para todos los formularios del sistema.
 *
 * Las funciones devuelven un mensaje de error (string) cuando el dato no es
 * válido, o null cuando el dato es correcto / el campo opcional está vacío.
 * La validación de servidor se complementa con assets/js/form-validation.js.
 */

// Catálogos del módulo de recepción de residuos. Se declaran aquí para que
// validación, controlador y vista compartan exactamente las mismas opciones.
if (!defined('RECEPCION_TIPOS_MATERIAL')) {
    define('RECEPCION_TIPOS_MATERIAL', [
        'Plástico',
        'Papel',
        'Cartón',
        'Vidrio',
        'Metal',
        'Orgánico',
        'Electrónico',
        'Textil',
        'Otro',
    ]);
}
if (!defined('RECEPCION_ORIGENES')) {
    define('RECEPCION_ORIGENES', ['Barrio', 'Calle', 'Vivienda', 'Externo']);
}
if (!defined('RECEPCION_ESTADOS')) {
    define('RECEPCION_ESTADOS', ['Registrada', 'Anulada']);
}

// Tipos de predio que pueden registrarse. Los predios distintos de "Vivienda"
// (comercios, tiendas, empresas, urbanizaciones) tienen monto mensual propio.
if (!defined('TIPOS_PREDIO')) {
    define('TIPOS_PREDIO', ['Vivienda', 'Comercio', 'Tienda', 'Empresa', 'Urbanizacion', 'Otro']);
}

if (!function_exists('validacion_agregar_error')) {
    function validacion_agregar_error(array &$errores, $mensaje) {
        $mensaje = trim((string)$mensaje);
        if ($mensaje !== '' && !in_array($mensaje, $errores, true)) {
            $errores[] = $mensaje;
        }
    }
}

if (!function_exists('validacion_texto')) {
    function validacion_texto($valor, $etiqueta, $min = 1, $max = 255, $obligatorio = false) {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return $obligatorio ? "Complete el campo obligatorio: {$etiqueta}." : null;
        }

        $largo = function_exists('mb_strlen') ? mb_strlen($valor, 'UTF-8') : strlen($valor);
        if ($largo < $min) {
            return "{$etiqueta} debe tener al menos {$min} caracteres.";
        }
        if ($largo > $max) {
            return "{$etiqueta} no puede superar los {$max} caracteres.";
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $valor)) {
            return "{$etiqueta} contiene caracteres no válidos.";
        }

        return null;
    }
}

if (!function_exists('validacion_nombre_persona')) {
    function validacion_nombre_persona($valor, $etiqueta, $obligatorio = false) {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return $obligatorio ? "Complete el campo obligatorio: {$etiqueta}." : null;
        }

        $error = validacion_texto($valor, $etiqueta, 2, 100, true);
        if ($error) return $error;

        // Permite letras (con acentos), números, espacios, apóstrofes, puntos y guiones.
        // Exige al menos una letra para evitar valores puramente numéricos.
        if (!preg_match("/^(?=.*\p{L})[\p{L}\p{M}\p{N}\s'’.\-]+$/u", $valor)) {
            return "{$etiqueta} solo puede contener letras, números, espacios, apóstrofes, puntos y guiones.";
        }

        return null;
    }
}

if (!function_exists('validacion_nombre_lugar')) {
    function validacion_nombre_lugar($valor, $etiqueta, $min = 2, $max = 150, $obligatorio = false) {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return $obligatorio ? "Complete el campo obligatorio: {$etiqueta}." : null;
        }

        $error = validacion_texto($valor, $etiqueta, $min, $max, true);
        if ($error) return $error;

        if (preg_match('/[<>{}|\\\\`]/', $valor)) {
            return "{$etiqueta} contiene caracteres no permitidos.";
        }

        return null;
    }
}

if (!function_exists('validacion_cedula')) {
    function validacion_cedula($valor, $etiqueta = 'La cédula / DNI', $obligatorio = false) {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return $obligatorio ? "Complete el campo obligatorio: {$etiqueta}." : null;
        }

        // Se toleran separadores visuales (puntos, espacios y guiones) al
        // recibir datos antiguos, pero solo se aceptan exactamente 8 números.
        $numeros = preg_replace('/[\s.\-]/', '', $valor);
        if ($numeros === '' || !ctype_digit($numeros) || strlen($numeros) !== 8) {
            return "{$etiqueta} debe contener exactamente 8 números, sin letras ni símbolos.";
        }

        return null;
    }
}

if (!function_exists('validacion_telefono')) {
    function validacion_telefono($valor, $etiqueta = 'El teléfono', $obligatorio = false) {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return $obligatorio ? "Complete el campo obligatorio: {$etiqueta}." : null;
        }

        $numeros = preg_replace('/[\s().\-]/', '', $valor);
        if ($numeros === '' || !ctype_digit($numeros) || strlen($numeros) < 7 || strlen($numeros) > 11) {
            return "{$etiqueta} debe contener entre 7 y 11 números, sin letras ni símbolos.";
        }

        return null;
    }
}

if (!function_exists('validacion_email')) {
    function validacion_email($valor, $etiqueta = 'El correo electrónico', $obligatorio = false) {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return $obligatorio ? "Complete el campo obligatorio: {$etiqueta}." : null;
        }

        if (strlen($valor) > 150) {
            return "{$etiqueta} no puede superar los 150 caracteres.";
        }
        if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            return "{$etiqueta} no tiene un formato válido.";
        }

        return null;
    }
}

if (!function_exists('validacion_fecha_nacimiento')) {
    function validacion_fecha_nacimiento($valor, $etiqueta = 'La fecha de nacimiento', $obligatorio = false, $edadMinima = 18) {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return $obligatorio ? "Complete el campo obligatorio: {$etiqueta}." : null;
        }

        $fecha = DateTime::createFromFormat('!Y-m-d', $valor);
        if (!$fecha || $fecha->format('Y-m-d') !== $valor) {
            return "{$etiqueta} no tiene un formato válido (AAAA-MM-DD).";
        }

        $hoy = new DateTime('today');
        if ($fecha > $hoy) {
            return "{$etiqueta} no puede ser una fecha futura.";
        }

        $edad = $fecha->diff($hoy)->y;
        if ($edad < (int)$edadMinima) {
            return "{$etiqueta} indica que la persona es menor de {$edadMinima} años.";
        }
        if ($edad > 120) {
            return "{$etiqueta} no parece válida (más de 120 años).";
        }

        return null;
    }
}

if (!function_exists('validacion_monto')) {
    function validacion_monto($valor, $etiqueta, $min = 0.01, $max = 999999999.99, $obligatorio = false) {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return $obligatorio ? "Complete el campo obligatorio: {$etiqueta}." : null;
        }

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $valor)) {
            return "{$etiqueta} debe ser un monto válido con hasta 2 decimales.";
        }

        $numero = (float)$valor;
        if ($numero < $min) {
            return "{$etiqueta} debe ser mayor o igual a {$min}.";
        }
        if ($numero > $max) {
            return "{$etiqueta} no puede superar {$max}.";
        }

        return null;
    }
}

if (!function_exists('validacion_peso')) {
    function validacion_peso($valor, $etiqueta = 'El peso', $min = 0.01, $max = 999999.99, $obligatorio = false) {
        $valor = str_replace(',', '.', trim((string)$valor));
        if ($valor === '') {
            return $obligatorio ? "Complete el campo obligatorio: {$etiqueta}." : null;
        }

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $valor)) {
            return "{$etiqueta} debe ser un número válido con hasta 2 decimales.";
        }

        $numero = (float)$valor;
        if ($numero < $min) {
            return "{$etiqueta} debe ser mayor o igual a {$min} kg.";
        }
        if ($numero > $max) {
            return "{$etiqueta} no puede superar {$max} kg.";
        }

        return null;
    }
}

if (!function_exists('validacion_fecha_registro')) {
    /**
     * Valida una fecha AAAA-MM-DD dentro de un rango razonable. Por defecto
     * no permite fechas futuras (útil para recepciones y otros registros).
     */
    function validacion_fecha_registro($valor, $etiqueta = 'La fecha', $obligatorio = false, $permiteFuturo = false) {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return $obligatorio ? "Complete el campo obligatorio: {$etiqueta}." : null;
        }

        $fecha = DateTime::createFromFormat('!Y-m-d', $valor);
        if (!$fecha || $fecha->format('Y-m-d') !== $valor) {
            return "{$etiqueta} no tiene un formato válido (AAAA-MM-DD).";
        }

        $hoy = new DateTime('today');
        if (!$permiteFuturo && $fecha > $hoy) {
            return "{$etiqueta} no puede ser una fecha futura.";
        }

        $minima = new DateTime('2000-01-01');
        if ($fecha < $minima) {
            return "{$etiqueta} no puede ser anterior al 01/01/2000.";
        }

        return null;
    }
}

if (!function_exists('validacion_entero')) {
    function validacion_entero($valor, $etiqueta, $min = 1, $max = PHP_INT_MAX, $obligatorio = false) {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return $obligatorio ? "Complete el campo obligatorio: {$etiqueta}." : null;
        }

        if (!preg_match('/^\d+$/', $valor)) {
            return "{$etiqueta} debe ser un número entero.";
        }

        $numero = (int)$valor;
        if ($numero < $min || $numero > $max) {
            if ($max >= PHP_INT_MAX) {
                return "{$etiqueta} debe ser un número entero mayor o igual a {$min}.";
            }
            return "{$etiqueta} debe estar entre {$min} y {$max}.";
        }

        return null;
    }
}

if (!function_exists('validacion_opcion')) {
    function validacion_opcion($valor, array $permitidos, $etiqueta, $obligatorio = false) {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return $obligatorio ? "Complete el campo obligatorio: {$etiqueta}." : null;
        }

        if (!in_array($valor, $permitidos, true)) {
            return "{$etiqueta} no contiene una opción válida.";
        }

        return null;
    }
}

if (!function_exists('validacion_archivo')) {
    function validacion_archivo($archivo, $etiqueta, $obligatorio = false, $maxBytes = 5242880) {
        if (!is_array($archivo) || !isset($archivo['error']) || (int)$archivo['error'] === UPLOAD_ERR_NO_FILE) {
            return $obligatorio ? "Adjunte el archivo obligatorio: {$etiqueta}." : null;
        }

        if ((int)$archivo['error'] !== UPLOAD_ERR_OK) {
            return "No se pudo subir {$etiqueta}. Verifique el tamaño y vuelva a intentarlo.";
        }

        if ((int)($archivo['size'] ?? 0) > $maxBytes) {
            $mb = round($maxBytes / 1048576, 1);
            return "{$etiqueta} no puede superar los {$mb} MB.";
        }

        $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        $extension = strtolower(pathinfo((string)($archivo['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, $permitidas, true)) {
            return "{$etiqueta} debe ser una imagen (JPG, PNG, GIF, WEBP) o un PDF.";
        }

        return null;
    }
}

if (!function_exists('normalizar_datos_post')) {
    /**
     * Normaliza identificaciones y teléfonos antes de guardarlos.
     * Solo debe llamarse después de que la validación haya pasado.
     */
    function normalizar_datos_post(array &$data) {
        foreach (['cedula', 'dni'] as $campo) {
            if (isset($data[$campo]) && !is_array($data[$campo])) {
                $data[$campo] = preg_replace('/[\s.\-]/', '', trim((string)$data[$campo]));
            }
        }

        if (isset($data['telefono']) && !is_array($data['telefono'])) {
            $data['telefono'] = preg_replace('/[\s().\-]/', '', trim((string)$data['telefono']));
        }
    }
}

if (!function_exists('validacion_requerir_campos')) {
    function validacion_requerir_campos(array $data, array $campos, array &$errores) {
        foreach ($campos as $campo => $etiqueta) {
            $valor = trim((string)($data[$campo] ?? ''));
            if ($valor === '') {
                validacion_agregar_error($errores, "Complete el campo obligatorio: {$etiqueta}.");
            }
        }
    }
}

if (!function_exists('validar_accion_post')) {
    /**
     * Valida un POST del área privada según la acción/form_type del formulario.
     *
     * @param string $accion  Valor de $_POST['action'] o $_POST['form_type'].
     * @param array  $data    $_POST
     * @param array  $files   $_FILES
     * @return array Lista de mensajes de error (vacía si todo es válido).
     */
    function validar_accion_post($accion, array $data, array $files = []) {
        $errores = [];
        $accion  = trim((string)$accion);
        $get     = function ($campo) use ($data) {
            return isset($data[$campo]) ? trim((string)$data[$campo]) : '';
        };
        $tiene   = function ($campo) use ($data) {
            return isset($data[$campo]) && trim((string)$data[$campo]) !== '';
        };
        $add     = function ($mensaje) use (&$errores) {
            validacion_agregar_error($errores, $mensaje);
        };

        /* ------------------------------------------------------------------
         * 1. Validaciones comunes por nombre de campo
         * ------------------------------------------------------------------ */

        // Identificaciones: exactamente 8 números cuando el campo tenga valor.
        if ($tiene('cedula')) $add(validacion_cedula($get('cedula'), 'La cédula'));
        if ($tiene('dni'))    $add(validacion_cedula($get('dni'), 'La cédula / DNI'));
        if ($tiene('cedula_tipo')) {
            $add(validacion_opcion($get('cedula_tipo'), ['V', 'E'], 'El tipo de cédula'));
        }

        // Teléfonos: entre 7 y 11 números.
        if ($tiene('telefono')) $add(validacion_telefono($get('telefono'), 'El teléfono'));
        if ($tiene('celular'))  $add(validacion_telefono($get('celular'), 'El celular'));

        // Correos electrónicos.
        if ($tiene('email'))  $add(validacion_email($get('email'), 'El correo electrónico'));
        if ($tiene('correo')) $add(validacion_email($get('correo'), 'El correo electrónico'));

        // Contraseñas: solo se validan cuando se escribe una nueva.
        if ($tiene('password')) $add(validacion_texto($get('password'), 'La contraseña', 6, 72, true));

        // Fecha de nacimiento: no futura y mayor de edad.
        if ($tiene('fecha_nacimiento')) {
            $add(validacion_fecha_nacimiento($get('fecha_nacimiento'), 'La fecha de nacimiento', true, 18));
        }

        // Montos.
        if ($tiene('cuota_mensual')) {
            $add(validacion_monto($get('cuota_mensual'), 'La cuota mensual', 0.01, 999999999.99, true));
        }
        if ($tiene('multa_renovacion')) {
            $add(validacion_monto($get('multa_renovacion'), 'La multa de renovación', 0, 999999999.99, true));
        }
        foreach (['precio_comercio', 'precio_tienda', 'precio_empresa', 'precio_urbanizacion', 'precio_otro', 'precio_especial'] as $campoPrecio) {
            if ($tiene($campoPrecio)) {
                $add(validacion_monto($get($campoPrecio), 'La tarifa de ' . str_replace('precio_', '', $campoPrecio), 0.01, 999999999.99, true));
            }
        }
        if ($tiene('precio_bolivar')) {
            $add(validacion_monto($get('precio_bolivar'), 'El precio del bolívar', 0.01, 999999999.99, true));
        }
        if ($tiene('cantidad_casas')) {
            $add(validacion_entero($get('cantidad_casas'), 'La cantidad de casas', 1, 100000, true));
        }
        if ($tiene('tipo_predio')) {
            $add(validacion_opcion($get('tipo_predio'), TIPOS_PREDIO, 'El tipo de predio'));
        }
        if ($tiene('fecha_limite_pago')) {
            $add(validacion_fecha_registro($get('fecha_limite_pago'), 'La fecha límite de pago', true, true));
        }
        if ($tiene('monto')) {
            $add(validacion_monto($get('monto'), 'El monto', 0, 999999999.99, true));
        }

        // Periodos.
        $anioMaximo = (int)date('Y') + 1;
        if ($tiene('anio'))         $add(validacion_entero($get('anio'), 'El año', 2000, $anioMaximo));
        if ($tiene('periodo_anio')) $add(validacion_entero($get('periodo_anio'), 'El año del periodo', 2000, $anioMaximo));
        if ($tiene('mes'))         $add(validacion_entero($get('mes'), 'El mes', 1, 12));
        if ($tiene('periodo_mes')) $add(validacion_entero($get('periodo_mes'), 'El mes del periodo', 1, 12));

        // Identificadores numéricos.
        $identificadores = [
            'id'              => 'El identificador',
            'user_id'         => 'El usuario',
            'vivienda_id'     => 'La vivienda',
            'cobro_id'        => 'El cobro',
            'solicitud_id'    => 'La solicitud',
            'lote_id'         => 'El lote',
            'lote_calle_id'   => 'El lote de calle',
            'exencion_id'     => 'La exención',
            'reporte_id'      => 'El reporte',
            'recepcion_id'    => 'La recepción',
            'barrio_id'       => 'El barrio',
            'calle_id'        => 'La calle',
            'calle_barrio_id' => 'El barrio de la calle',
        ];
        foreach ($identificadores as $campo => $etiqueta) {
            if ($tiene($campo)) {
                $add(validacion_entero($get($campo), $etiqueta, 1, PHP_INT_MAX));
            }
        }

        // Textos generales (solo se valida longitud y contenido de control).
        // Los nombres de personas y lugares se validan por acción más abajo.
        $textos = [
            'nombre_calle'               => ['El nombre de la calle', 1, 150],
            'ciudad'                     => ['La ciudad', 1, 150],
            'parroquia_municipio'        => ['La parroquia / municipio', 1, 150],
            'barrio'                     => ['El barrio', 1, 150],
            'direccion'                  => ['La dirección', 1, 255],
            'numero'                     => ['El número de casa', 1, 20],
            'numero_casa'                => ['El número de casa', 1, 20],
            'referencia'                 => ['La referencia', 1, 500],
            'descripcion'                => ['La descripción', 1, 2000],
            'descripcion_calle'          => ['La descripción', 1, 2000],
            'limite_referencia'          => ['El límite / referencia', 1, 1000],
            'area'                       => ['El área', 1, 150],
            'motivo_rechazo'             => ['El motivo del rechazo', 1, 1000],
            'motivo_anulacion'           => ['El motivo de anulación', 1, 500],
            'referencia_pago'            => ['La referencia de pago', 1, 150],
            'referencia_lote'            => ['La referencia del lote', 1, 150],
            'observaciones_lote_barrio'  => ['Las observaciones', 1, 2000],
            'ubicacion'                  => ['La ubicación', 1, 255],
            'otro_identificador'         => ['El otro identificador', 1, 100],
            'tipo_via'                   => ['El tipo de vía', 1, 50],
        ];
        foreach ($textos as $campo => $regla) {
            if ($tiene($campo)) {
                $add(validacion_texto($get($campo), $regla[0], $regla[1], $regla[2]));
            }
        }

        // Archivos adjuntos.
        $archivos = [
            'comprobante_pago'        => 'El comprobante de pago',
            'comprobante_lote'        => 'El comprobante del lote',
            'comprobante_lote_barrio' => 'El comprobante del lote',
            'factura_archivo'         => 'La factura',
        ];
        foreach ($archivos as $campo => $etiqueta) {
            if (isset($files[$campo])) {
                $maxBytes = $accion === 'marcar_pago_barrio' && $campo === 'comprobante_pago'
                    ? 2097152
                    : 5242880;
                $add(validacion_archivo($files[$campo], $etiqueta, false, $maxBytes));
            }
        }

        /* ------------------------------------------------------------------
         * 2. Campos obligatorios por acción
         * ------------------------------------------------------------------ */

        $requeridos = [
            'actualizar_reporte'            => ['reporte_id' => 'Reporte', 'estado' => 'Estado'],
            'add_user'                      => ['nombre' => 'Nombre', 'apellido' => 'Apellido', 'email' => 'Correo electrónico', 'rol_id' => 'Rol'],
            'edit_user'                     => ['user_id' => 'Usuario', 'nombre' => 'Nombre', 'apellido' => 'Apellido', 'email' => 'Correo electrónico', 'rol_id' => 'Rol'],
            'nueva_calle'                   => ['nombre' => 'Nombre de la calle', 'barrio_id' => 'Barrio'],
            'editar_calle'                  => ['id' => 'Calle', 'nombre' => 'Nombre de la calle', 'barrio_id' => 'Barrio'],
            'nuevo_barrio'                  => ['nombre' => 'Nombre del barrio', 'ciudad' => 'Ciudad', 'parroquia_municipio' => 'Parroquia / municipio', 'lider_comunidad' => 'Líder de la comunidad'],
            'editar_barrio'                 => ['id' => 'Barrio', 'nombre' => 'Nombre del barrio', 'ciudad' => 'Ciudad', 'parroquia_municipio' => 'Parroquia / municipio', 'lider_comunidad' => 'Líder de la comunidad'],
            'editar_vivienda'               => ['id' => 'Vivienda', 'propietario' => 'Propietario', 'barrio_id' => 'Barrio', 'calle_id' => 'Calle', 'direccion' => 'Dirección'],
            'nuevo_vecino_admin'            => ['propietario' => 'Propietario', 'barrio_id' => 'Barrio', 'calle_id' => 'Calle', 'direccion' => 'Dirección'],
            'nuevo_vecino'                  => ['calle_id' => 'Calle', 'propietario' => 'Propietario', 'direccion' => 'Dirección'],
            'solicitar_alta'                => ['propietario' => 'Propietario', 'numero_casa' => 'Número de casa'],
            'solicitar_baja'                => ['vivienda_id' => 'Vivienda'],
            'solicitar_renovacion'          => ['vivienda_id' => 'Vivienda'],
            'reactivar_servicio'            => ['vivienda_id' => 'Vivienda'],
            'ordenar_baja'                  => ['vivienda_id' => 'Vivienda'],
            'marcar_pago_vivienda'          => ['vivienda_id' => 'Vivienda'],
            'marcar_pago_simple'            => ['vivienda_id' => 'Vivienda', 'referencia_pago' => 'Referencia de pago'],
            'marcar_pago_barrio'            => ['vivienda_id' => 'Vivienda', 'referencia_pago' => 'Referencia de pago'],
            'solicitar_exoneracion'         => ['vivienda_id' => 'Vivienda', 'tipo_exencion' => 'Tipo de exención', 'descripcion' => 'Descripción / motivo'],
            'anular_pago'                   => ['cobro_id' => 'Cobro', 'motivo_anulacion' => 'Motivo de anulación'],
            'editar_cobro'                  => ['cobro_id' => 'Cobro', 'referencia_pago' => 'Referencia de pago'],
            'enviar_lote_calle'             => ['mes' => 'Mes', 'anio' => 'Año', 'referencia_lote' => 'Referencia del lote'],
            'enviar_lote_barrio'            => ['periodo_mes' => 'Mes del periodo', 'periodo_anio' => 'Año del periodo'],
            'actualizar_configuracion_barrio' => [
                'cuota_mensual'      => 'Cuota mensual de vivienda',
                'precio_comercio'    => 'Precio de comercio',
                'precio_tienda'      => 'Precio de tienda',
                'precio_empresa'     => 'Precio de empresa',
                'precio_urbanizacion' => 'Precio por casa de urbanización',
                'precio_otro'        => 'Precio de otros predios',
                'precio_especial'    => 'Precio de recojo especial',
                'precio_bolivar'     => 'Precio del bolívar (Bs. por US$)',
                'multa_renovacion'   => 'Multa de renovación',
                'fecha_limite_pago'  => 'Fecha límite de pago',
            ],
            'enviar_certificado_calle'      => ['lote_calle_id' => 'Lote de calle'],
            'verificar_pago_casa'           => ['cobro_id' => 'Cobro', 'decision' => 'Decisión'],
            'procesar_solicitud'            => ['solicitud_id' => 'Solicitud', 'estado' => 'Estado'],
            'aprobar_lote_calle'            => ['lote_id' => 'Lote'],
            'rechazar_lote_calle'           => ['lote_id' => 'Lote', 'motivo_rechazo' => 'Motivo del rechazo'],
            'aprobar_exencion'              => ['exencion_id' => 'Exención'],
            'rechazar_exencion'             => ['exencion_id' => 'Exención', 'motivo_rechazo' => 'Motivo del rechazo'],
            'agregar_exencion_directa'      => ['vivienda_id' => 'Vivienda', 'tipo_exencion' => 'Tipo de exención'],
            'quitar_exencion'               => ['exencion_id' => 'Exención'],
            'solicitar_nueva_calle'         => ['nombre_calle' => 'Nombre de la calle'],
            'solicitar_encargado_calle'     => ['calle_id' => 'Calle', 'nombre' => 'Nombre', 'apellido' => 'Apellido'],
            'crear_calle_gestor'            => ['barrio_id' => 'Barrio', 'nombre_calle' => 'Nombre de la calle'],
            'crear_encargado_calle_gestor'  => ['barrio_id' => 'Barrio', 'calle_id' => 'Calle', 'nombre' => 'Nombre', 'apellido' => 'Apellido'],
            'aprobar_solicitud_gestion'     => ['solicitud_id' => 'Solicitud'],
            'rechazar_solicitud_gestion'    => ['solicitud_id' => 'Solicitud', 'motivo_rechazo' => 'Motivo del rechazo'],
            'aprobar_lote_barrio'           => ['lote_id' => 'Lote'],
            'rechazar_lote_barrio'          => ['lote_id' => 'Lote', 'motivo_rechazo' => 'Motivo del rechazo'],
            'enviar_facturas_barrio'        => ['lote_id' => 'Lote'],
            'registrar_recepcion_residuo'   => [
                'tipo_material'  => 'Tipo de material',
                'peso_kg'        => 'Peso (kg)',
                'fecha_recepcion' => 'Fecha de recepción',
                'origen_tipo'    => 'Origen',
            ],
            'anular_recepcion_residuo'      => [
                'recepcion_id'    => 'Recepción',
                'motivo_anulacion' => 'Motivo de anulación',
            ],
        ];

        if (isset($requeridos[$accion])) {
            validacion_requerir_campos($data, $requeridos[$accion], $errores);
        }

        /* ------------------------------------------------------------------
         * 3. Reglas específicas por acción
         * ------------------------------------------------------------------ */

        // Las tarifas se configuran por barrio; solo la urbanización necesita
        // declarar cuántas casas agrupa para calcular su pago mensual.
        if (in_array($accion, ['nuevo_vecino_admin', 'nuevo_vecino', 'editar_vivienda'], true)) {
            $tipoPredio = $get('tipo_predio') !== '' ? $get('tipo_predio') : 'Vivienda';
            if ($tipoPredio === 'Urbanizacion' && !$tiene('cantidad_casas')) {
                $add('Indica la cantidad de casas de la urbanización.');
            }
        }

        if (in_array($accion, ['add_user', 'edit_user'], true)) {
            $add(validacion_nombre_persona($get('nombre'), 'El nombre', false));
            $add(validacion_nombre_persona($get('apellido'), 'El apellido', false));
            $add(validacion_email($get('email'), 'El correo electrónico', false));

            $rolesPermitidos = ['1', '2', '5', '6'];
            $add(validacion_opcion($get('rol_id'), $rolesPermitidos, 'El rol', false));

            if ($tiene('genero')) {
                $add(validacion_opcion($get('genero'), ['M', 'F', 'Otro'], 'El género'));
            }

            // Datos obligatorios según el rol seleccionado.
            if ($get('rol_id') === '5' && !$tiene('barrio_id')) {
                $add('Seleccione el barrio para el encargado de barrio.');
            }
            if ($get('rol_id') === '6') {
                if (!$tiene('calle_barrio_id')) $add('Seleccione el barrio de la calle para el encargado de calle.');
                if (!$tiene('calle_id')) $add('Seleccione la calle para el encargado de calle.');
            }
        }

        if (in_array($accion, ['nuevo_barrio', 'editar_barrio'], true)) {
            $add(validacion_nombre_lugar($get('nombre'), 'El nombre del barrio', 2, 150, false));
            $add(validacion_nombre_lugar($get('ciudad'), 'La ciudad', 2, 150, false));
            $add(validacion_nombre_lugar($get('parroquia_municipio'), 'La parroquia / municipio', 2, 150, false));
            $add(validacion_nombre_persona($get('lider_comunidad'), 'El líder de la comunidad', false));
        }

        if (in_array($accion, ['nueva_calle', 'editar_calle'], true)) {
            $add(validacion_nombre_lugar($get('nombre'), 'El nombre de la calle', 2, 150, false));
        }

        if ($accion === 'crear_calle_gestor') {
            $add(validacion_nombre_lugar($get('nombre_calle'), 'El nombre de la calle', 2, 150, false));
        }

        if ($accion === 'solicitar_nueva_calle') {
            $add(validacion_nombre_lugar($get('nombre_calle'), 'El nombre de la calle', 2, 150, false));
        }

        if (in_array($accion, ['solicitar_encargado_calle', 'crear_encargado_calle_gestor'], true)) {
            $add(validacion_nombre_persona($get('nombre'), 'El nombre', false));
            $add(validacion_nombre_persona($get('apellido'), 'El apellido', false));
        }

        if (in_array($accion, ['editar_vivienda', 'nuevo_vecino_admin', 'nuevo_vecino', 'solicitar_alta'], true)) {
            $add(validacion_nombre_persona($get('propietario'), 'El propietario', false));
        }

        if (in_array($accion, ['agregar_exencion_directa', 'solicitar_exoneracion'], true)) {
            $tiposExencion = ['pobreza', 'adulto_mayor', 'empleado', 'espacio_publico', 'otro'];
            $add(validacion_opcion($get('tipo_exencion'), $tiposExencion, 'El tipo de exención', false));
        }

        if ($accion === 'solicitar_exoneracion') {
            $add(validacion_texto($get('descripcion'), 'La descripción / motivo', 10, 2000, false));
        }

        if (in_array($accion, ['marcar_pago_simple', 'marcar_pago_barrio', 'editar_cobro'], true)) {
            $add(validacion_texto($get('referencia_pago'), 'La referencia de pago', 3, 150, false));
        }

        if ($accion === 'anular_pago') {
            $add(validacion_texto($get('motivo_anulacion'), 'El motivo de anulación', 3, 500, false));
        }

        if ($accion === 'enviar_lote_calle') {
            $add(validacion_texto($get('referencia_lote'), 'La referencia del lote', 3, 150, false));
        }

        if (in_array($accion, ['rechazar_lote_calle', 'rechazar_lote_barrio', 'rechazar_solicitud_gestion', 'rechazar_exencion'], true)) {
            $add(validacion_texto($get('motivo_rechazo'), 'El motivo del rechazo', 3, 1000, false));
        }

        if ($accion === 'registrar_recepcion_residuo') {
            $tipoMaterial = $get('tipo_material');
            $origen       = $get('origen_tipo');

            $add(validacion_opcion($tipoMaterial, RECEPCION_TIPOS_MATERIAL, 'El tipo de material', true));
            $add(validacion_peso($get('peso_kg'), 'El peso', 0.01, 999999.99, true));
            $add(validacion_fecha_registro($get('fecha_recepcion'), 'La fecha de recepción', true));
            $add(validacion_opcion($origen, RECEPCION_ORIGENES, 'El origen', true));

            // La procedencia debe ser coherente con el origen elegido.
            if (in_array($origen, ['Barrio', 'Calle', 'Vivienda'], true) && !$tiene('barrio_id')) {
                $add('Seleccione el barrio de origen.');
            }
            if (in_array($origen, ['Calle', 'Vivienda'], true) && !$tiene('calle_id')) {
                $add('Seleccione la calle de origen.');
            }
            if ($origen === 'Externo') {
                $add(validacion_texto($get('referencia_origen'), 'La referencia del origen', 3, 255, true));
            } else {
                $add(validacion_texto($get('referencia_origen'), 'La referencia del origen', 3, 255, false));
            }

            $add(validacion_texto($get('observaciones_recepcion'), 'Las observaciones', 1, 1000, false));
        }

        if ($accion === 'anular_recepcion_residuo') {
            $add(validacion_texto($get('motivo_anulacion'), 'El motivo de anulación', 3, 500, false));
        }

        // Estados y decisiones permitidas.
        $opciones = [
            'actualizar_reporte'            => ['estado' => ['Pendiente', 'En proceso', 'Completado']],
            'procesar_solicitud'            => ['estado' => ['Aprobado', 'Rechazado']],
            'verificar_pago_casa'           => ['decision' => ['Verificado', 'Rechazado']],
        ];
        if (isset($opciones[$accion])) {
            foreach ($opciones[$accion] as $campo => $permitidos) {
                $etiqueta = $campo === 'decision' ? 'La decisión' : 'El estado';
                $add(validacion_opcion($get($campo), $permitidos, $etiqueta, true));
            }
        }

        if ($accion === 'verificar_pago_casa' && $get('decision') === 'Rechazado') {
            $add(validacion_texto($get('motivo_rechazo'), 'El motivo del rechazo', 3, 1000, true));
        }

        return $errores;
    }
}
