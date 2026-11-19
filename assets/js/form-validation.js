/**
 * assets/js/form-validation.js
 * Validación de formularios en el navegador, compartida por el área privada y pública.
 *
 * Reglas principales:
 *  - Cédula / DNI: solo números, exactamente 8.
 *  - Teléfono: solo números, entre 7 y 11.
 *  - Correos con formato válido.
 *  - Nombres de personas: letras, números, espacios, apóstrofes, puntos y guiones.
 *  - Fechas de nacimiento: no futuras y mayores de 18 años.
 *  - Montos, años, meses y archivos con rangos/tamaños válidos.
 *
 * La validación de servidor está en app/validacion.php y es la fuente de verdad.
 */
(function () {
    'use strict';

    var MAX_FILE_BYTES = 5 * 1024 * 1024;
    var ALLOWED_FILE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    var CURRENT_YEAR = new Date().getFullYear();
    var PERSON_NAME_ACTIONS = ['add_user', 'edit_user', 'solicitar_encargado_calle', 'crear_encargado_calle_gestor', 'solicitud'];
    var PERSON_NAMES = ['nombre', 'apellido', 'propietario', 'lider_comunidad', 'representante'];
    var PLACE_NAMES = ['ciudad', 'parroquia_municipio', 'barrio', 'nombre_calle', 'tipo_via', 'area'];
    var PERSON_RE = /^(?=.*\p{L})[\p{L}\p{M}\p{N}\s'’.\-]+$/u;

    var REQUIRED_BY_ACTION = {
        actualizar_reporte: ['reporte_id', 'estado'],
        actualizar_solicitud_servicio: ['solicitud_id', 'estado'],
        add_user: ['nombre', 'apellido', 'email', 'rol_id'],
        edit_user: ['user_id', 'nombre', 'apellido', 'email', 'rol_id'],
        nueva_calle: ['nombre', 'barrio_id'],
        editar_calle: ['id', 'nombre', 'barrio_id'],
        nuevo_barrio: ['nombre', 'ciudad', 'parroquia_municipio', 'lider_comunidad'],
        editar_barrio: ['id', 'nombre', 'ciudad', 'parroquia_municipio', 'lider_comunidad'],
        editar_vivienda: ['id', 'propietario', 'barrio_id', 'calle_id', 'direccion'],
        nuevo_vecino_admin: ['propietario', 'barrio_id', 'calle_id', 'direccion'],
        nuevo_vecino: ['calle_id', 'propietario', 'direccion'],
        solicitar_alta: ['propietario', 'numero_casa'],
        solicitar_baja: ['vivienda_id'],
        solicitar_renovacion: ['vivienda_id'],
        reactivar_servicio: ['vivienda_id'],
        ordenar_baja: ['vivienda_id'],
        marcar_pago_vivienda: ['vivienda_id'],
        marcar_pago_simple: ['vivienda_id', 'referencia_pago'],
        solicitar_exoneracion: ['vivienda_id', 'tipo_exencion', 'descripcion'],
        anular_pago: ['cobro_id', 'motivo_anulacion'],
        editar_cobro: ['cobro_id', 'referencia_pago'],
        enviar_lote_calle: ['mes', 'anio', 'referencia_lote'],
        enviar_lote_barrio: ['periodo_mes', 'periodo_anio'],
        actualizar_configuracion_barrio: ['cuota_mensual', 'precio_comercio', 'precio_tienda', 'precio_empresa', 'precio_urbanizacion', 'precio_otro', 'precio_especial', 'precio_bolivar', 'multa_renovacion', 'fecha_limite_pago'],
        actualizar_tasa_bolivar: [],
        enviar_certificado_calle: ['lote_calle_id'],
        verificar_pago_casa: ['cobro_id', 'decision'],
        procesar_solicitud: ['solicitud_id', 'estado'],
        aprobar_lote_calle: ['lote_id'],
        rechazar_lote_calle: ['lote_id', 'motivo_rechazo'],
        aprobar_exencion: ['exencion_id'],
        rechazar_exencion: ['exencion_id', 'motivo_rechazo'],
        agregar_exencion_directa: ['vivienda_id', 'tipo_exencion'],
        quitar_exencion: ['exencion_id'],
        solicitar_nueva_calle: ['nombre_calle'],
        solicitar_encargado_calle: ['calle_id', 'nombre', 'apellido'],
        crear_calle_gestor: ['barrio_id', 'nombre_calle'],
        crear_encargado_calle_gestor: ['barrio_id', 'calle_id', 'nombre', 'apellido'],
        aprobar_solicitud_gestion: ['solicitud_id'],
        rechazar_solicitud_gestion: ['solicitud_id', 'motivo_rechazo'],
        aprobar_lote_barrio: ['lote_id'],
        rechazar_lote_barrio: ['lote_id', 'motivo_rechazo'],
        enviar_facturas_barrio: ['lote_id'],
        registrar_recepcion_residuo: ['tipo_material', 'peso_kg', 'fecha_recepcion', 'origen_tipo'],
        anular_recepcion_residuo: ['recepcion_id', 'motivo_anulacion'],
        solicitud: ['nombre', 'telefono', 'barrio', 'tipo', 'correo']
    };

    function fieldName(el) {
        return String(el.getAttribute('name') || '').replace(/\[\]$/, '');
    }

    function actionOf(form) {
        var action = form.querySelector('input[name="action"]');
        var formType = form.querySelector('input[name="form_type"]');
        return String((action && action.value) || (formType && formType.value) || '').trim();
    }

    function isPostForm(form) {
        return form &&
            form.tagName === 'FORM' &&
            String(form.getAttribute('method') || 'get').toLowerCase() === 'post';
    }

    function digitsOnly(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function sepSoloNumeros(value) {
        return String(value || '').replace(/[\s.\-]/g, '');
    }

    function isPersonField(name, action) {
        if (name === 'nombre') return PERSON_NAME_ACTIONS.indexOf(action) !== -1;
        return PERSON_NAMES.indexOf(name) !== -1;
    }

    function isPlaceField(name, action) {
        if (name === 'nombre') return !isPersonField(name, action);
        return PLACE_NAMES.indexOf(name) !== -1;
    }

    function addError(el, mensaje) {
        if (!el || typeof el.setCustomValidity !== 'function') return;
        el.setCustomValidity(mensaje);
        el.classList.add('campo-invalido');
    }

    function clearError(el) {
        if (!el) return;
        if (typeof el.setCustomValidity === 'function') el.setCustomValidity('');
        el.classList.remove('campo-invalido');
    }

    function markRequired(el) {
        if (!el || el.disabled) return;
        var type = String(el.type || '').toLowerCase();
        if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'reset') return;
        el.required = true;
    }

    function setIfMissing(el, attr, value) {
        if (!el.hasAttribute(attr)) el.setAttribute(attr, value);
    }

    function applyRequiredFields(form, action) {
        var required = REQUIRED_BY_ACTION[action] || [];
        var fields = form.querySelectorAll('input, select, textarea');

        Array.prototype.forEach.call(fields, function (el) {
            if (!el.name || el.disabled) return;
            if (required.indexOf(fieldName(el)) !== -1) markRequired(el);
        });

        // Datos obligatorios según el rol del usuario.
        if (action === 'add_user' || action === 'edit_user') {
            var rol = form.querySelector('[name="rol_id"]');
            var rolVal = rol ? String(rol.value) : '';
            if (rolVal === '5') {
                var barrio = form.querySelector('[name="barrio_id"]');
                if (barrio && !barrio.disabled) markRequired(barrio);
            }
            if (rolVal === '6') {
                ['calle_barrio_id', 'calle_id'].forEach(function (name) {
                    var field = form.querySelector('[name="' + name + '"]');
                    if (field && !field.disabled) markRequired(field);
                });
            }
        }

        // Solo la urbanización necesita declarar cuántas casas agrupa; las
        // tarifas se configuran por barrio en "Configuración de Pagos".
        if (action === 'nuevo_vecino_admin' || action === 'nuevo_vecino' || action === 'editar_vivienda') {
            var tipoPredio = form.querySelector('[name="tipo_predio"]');
            var cantidadCasas = form.querySelector('[name="cantidad_casas"]');
            if (tipoPredio && cantidadCasas) {
                var actualizarCasas = function () {
                    var esUrbanizacion = String(tipoPredio.value) === 'Urbanizacion';
                    cantidadCasas.required = esUrbanizacion;
                    if (cantidadCasas.hasAttribute('disabled') && !esUrbanizacion) return;
                    var bloque = document.getElementById('campo_cantidad_casas');
                    if (bloque) bloque.style.display = esUrbanizacion ? '' : 'none';
                };
                tipoPredio.addEventListener('change', actualizarCasas);
                actualizarCasas();
            }
        }

        // La procedencia de una recepción depende del origen seleccionado.
        if (action === 'registrar_recepcion_residuo') {
            var origen = form.querySelector('[name="origen_tipo"]');
            var origenVal = origen ? String(origen.value) : 'Barrio';
            var barrioOrigen = form.querySelector('[name="barrio_id"]');
            var calleOrigen = form.querySelector('[name="calle_id"]');
            var referencia = form.querySelector('[name="referencia_origen"]');

            if (barrioOrigen) barrioOrigen.required = false;
            if (calleOrigen) calleOrigen.required = false;
            if (referencia) referencia.required = false;

            if (origenVal !== 'Externo' && barrioOrigen && !barrioOrigen.disabled) {
                markRequired(barrioOrigen);
            }
            if ((origenVal === 'Calle' || origenVal === 'Vivienda') && calleOrigen && !calleOrigen.disabled) {
                markRequired(calleOrigen);
            }
            if (origenVal === 'Externo' && referencia) {
                markRequired(referencia);
            }
        }
    }

    function applyFieldRules(form, action) {
        var fields = form.querySelectorAll('input, select, textarea');

        Array.prototype.forEach.call(fields, function (el) {
            var name = fieldName(el);
            if (!name) return;

            var tag = el.tagName.toLowerCase();
            var type = String(el.type || '').toLowerCase();
            if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'reset' || type === 'image') return;

            clearError(el);

            if (name === 'cedula' || name === 'dni') {
                el.setAttribute('inputmode', 'numeric');
                el.setAttribute('pattern', '[0-9]{8}');
                el.setAttribute('maxlength', '8');
                el.setAttribute('minlength', '8');
                el.setAttribute('title', 'Ingrese exactamente 8 números, sin letras ni símbolos.');
                var cedula = sepSoloNumeros(el.value);
                if (cedula !== '' && digitsOnly(el.value) === cedula) {
                    el.value = cedula;
                }
                return;
            }

            if (name === 'telefono' || name === 'celular') {
                el.setAttribute('inputmode', 'numeric');
                el.setAttribute('pattern', '[0-9]{7,11}');
                el.setAttribute('maxlength', '11');
                el.setAttribute('minlength', '7');
                el.setAttribute('title', 'Ingrese entre 7 y 11 números, sin letras ni símbolos.');
                var tel = digitsOnly(el.value).slice(0, 11);
                if (tel !== '' && /^[\d\s().+\-]*$/.test(el.value)) {
                    el.value = tel;
                }
                return;
            }

            if (name === 'email' || name === 'correo') {
                if (type === 'text') el.type = 'email';
                el.setAttribute('maxlength', '150');
                return;
            }

            if (name === 'password') {
                if (action === 'add_user' || action === 'edit_user') el.setAttribute('minlength', '6');
                el.setAttribute('maxlength', '72');
                return;
            }

            if (name === 'fecha_nacimiento') {
                el.type = 'date';
                el.setAttribute('max', todayISO());
                el.setAttribute('min', '1900-01-01');
                return;
            }

            if (name === 'fecha_recepcion') {
                el.type = 'date';
                el.setAttribute('max', todayISO());
                el.setAttribute('min', '2000-01-01');
                return;
            }

            if (name === 'peso_kg') {
                if (type !== 'number') el.type = 'number';
                el.setAttribute('step', '0.01');
                el.setAttribute('min', '0.01');
                el.setAttribute('max', '999999.99');
                return;
            }

            if (name === 'referencia_origen') {
                el.setAttribute('maxlength', '255');
                el.setAttribute('minlength', '3');
                return;
            }

            if (name === 'observaciones_recepcion') {
                el.setAttribute('maxlength', '1000');
                return;
            }

            if (name === 'cuota_mensual' || name === 'multa_renovacion' || name === 'monto' || name === 'precio_comercio' || name === 'precio_tienda' || name === 'precio_empresa' || name === 'precio_urbanizacion' || name === 'precio_otro' || name === 'precio_especial') {
                if (type !== 'number') el.type = 'number';
                el.setAttribute('step', '0.01');
                el.setAttribute('min', name === 'cuota_mensual' ? '0.01' : '0');
                el.setAttribute('max', '999999999.99');
                return;
            }

            if (name === 'cantidad_casas') {
                if (tag === 'input') {
                    el.type = 'number';
                    el.setAttribute('step', '1');
                    el.setAttribute('min', '1');
                    el.setAttribute('max', '100000');
                }
                return;
            }

            if (name === 'anio' || name === 'periodo_anio') {
                if (tag === 'input') {
                    el.type = 'number';
                    el.setAttribute('step', '1');
                    el.setAttribute('min', '2000');
                    el.setAttribute('max', String(CURRENT_YEAR + 1));
                }
                return;
            }

            if (name === 'mes' || name === 'periodo_mes') {
                if (tag === 'input') {
                    el.type = 'number';
                    el.setAttribute('step', '1');
                    el.setAttribute('min', '1');
                    el.setAttribute('max', '12');
                }
                return;
            }

            if (name === 'otro_identificador') {
                setIfMissing(el, 'maxlength', '100');
                return;
            }

            if (name === 'numero' || name === 'numero_casa') {
                setIfMissing(el, 'maxlength', '20');
                return;
            }

            if (isPersonField(name, action)) {
                el.setAttribute('minlength', '2');
                el.setAttribute('maxlength', '100');
                el.setAttribute('title', 'Solo letras, números, espacios, apóstrofes, puntos y guiones.');
                return;
            }

            if (isPlaceField(name, action)) {
                el.setAttribute('minlength', '2');
                el.setAttribute('maxlength', name === 'nombre' || name === 'nombre_calle' ? '150' : '150');
                el.setAttribute('title', 'Ingrese un nombre o lugar válido (mínimo 2 caracteres).');
                return;
            }

            if (name === 'direccion') {
                el.setAttribute('maxlength', '255');
                return;
            }
            if (name === 'referencia') {
                el.setAttribute('maxlength', '500');
                return;
            }
            if (name === 'descripcion' || name === 'descripcion_calle') {
                el.setAttribute('maxlength', '2000');
                return;
            }
            if (name === 'limite_referencia') {
                el.setAttribute('maxlength', '1000');
                return;
            }
            if (name === 'motivo_rechazo') {
                el.setAttribute('maxlength', '1000');
                return;
            }
            if (name === 'motivo_anulacion') {
                el.setAttribute('maxlength', '500');
                return;
            }
            if (name === 'referencia_pago' || name === 'referencia_lote') {
                el.setAttribute('maxlength', '150');
                return;
            }
            if (name === 'observaciones_lote_barrio') {
                el.setAttribute('maxlength', '2000');
                return;
            }
            if (name === 'ubicacion') {
                el.setAttribute('maxlength', '255');
                return;
            }
            if (name === 'tipo_via') {
                el.setAttribute('maxlength', '50');
            }
        });
    }

    function todayISO() {
        var d = new Date();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    function edadEnAnios(value) {
        var parts = String(value || '').split('-');
        if (parts.length !== 3) return null;
        var nacimiento = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        if (isNaN(nacimiento.getTime())) return null;
        if (nacimiento.getFullYear() !== Number(parts[0]) ||
            nacimiento.getMonth() !== Number(parts[1]) - 1 ||
            nacimiento.getDate() !== Number(parts[2])) return null;

        var hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        nacimiento.setHours(0, 0, 0, 0);
        if (nacimiento > hoy) return -1;

        var edad = hoy.getFullYear() - nacimiento.getFullYear();
        var m = hoy.getMonth() - nacimiento.getMonth();
        if (m < 0 || (m === 0 && hoy.getDate() < nacimiento.getDate())) edad--;
        return edad;
    }

    function validateFile(el) {
        if (!el.files || !el.files.length) return;
        var file = el.files[0];
        if (file.size > MAX_FILE_BYTES) {
            addError(el, 'El archivo no puede superar los 5 MB.');
            return;
        }
        var ext = String(file.name || '').split('.').pop().toLowerCase();
        if (ALLOWED_FILE_EXT.indexOf(ext) === -1) {
            addError(el, 'Solo se permiten imágenes JPG, PNG, GIF, WEBP o archivos PDF.');
        }
    }

    function validateCheckboxGroups(form) {
        var groups = {};
        var boxes = form.querySelectorAll('input[type="checkbox"][name$="[]"]');
        Array.prototype.forEach.call(boxes, function (box) {
            var name = box.getAttribute('name');
            if (!groups[name]) groups[name] = [];
            groups[name].push(box);
        });

        Object.keys(groups).forEach(function (name) {
            var group = groups[name];
            var marcados = group.filter(function (box) { return box.checked; }).length;

            group.forEach(clearError);

            if (name === 'tipo_incidente[]') {
                if (marcados === 0) {
                    addError(group[0], 'Seleccione al menos un tipo de incidente.');
                } else if (marcados > 3) {
                    addError(group[0], 'Seleccione como máximo 3 tipos de incidente.');
                }
            } else if (group.some(function (box) { return box.required; }) && marcados === 0) {
                addError(group[0], 'Seleccione al menos una opción.');
            }
        });
    }

    function validateCustom(form, action) {
        var fields = form.querySelectorAll('input, select, textarea');

        Array.prototype.forEach.call(fields, function (el) {
            var name = fieldName(el);
            if (!name) return;

            var type = String(el.type || '').toLowerCase();
            if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'reset') return;

            clearError(el);

            if (type === 'file') {
                validateFile(el);
                return;
            }

            var value = String(el.value || '').trim();
            if (value === '') return;

            if (name === 'cedula' || name === 'dni') {
                var cedula = sepSoloNumeros(value);
                if (!/^\d{8}$/.test(cedula)) {
                    addError(el, 'La cédula / DNI debe contener exactamente 8 números, sin letras ni símbolos.');
                } else {
                    el.value = cedula;
                }
                return;
            }

            if (name === 'telefono' || name === 'celular') {
                var tel = digitsOnly(value);
                if (!/^\d{7,11}$/.test(tel)) {
                    addError(el, 'El teléfono debe contener entre 7 y 11 números, sin letras ni símbolos.');
                } else {
                    el.value = tel;
                }
                return;
            }

            if (name === 'email' || name === 'correo') {
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) {
                    addError(el, 'Ingrese un correo electrónico válido.');
                }
                return;
            }

            if (name === 'fecha_nacimiento') {
                var edad = edadEnAnios(value);
                if (edad === null) {
                    addError(el, 'Ingrese una fecha de nacimiento válida.');
                } else if (edad === -1) {
                    addError(el, 'La fecha de nacimiento no puede estar en el futuro.');
                } else if (edad < 18) {
                    addError(el, 'La persona debe ser mayor de edad (mínimo 18 años).');
                } else if (edad > 120) {
                    addError(el, 'Ingrese una fecha de nacimiento válida (máximo 120 años).');
                }
                return;
            }

            if (name === 'fecha_recepcion') {
                var fechaRecepcion = String(value || '');
                if (!/^\d{4}-\d{2}-\d{2}$/.test(fechaRecepcion)) {
                    addError(el, 'Ingrese una fecha de recepción válida.');
                } else if (fechaRecepcion > todayISO()) {
                    addError(el, 'La fecha de recepción no puede ser futura.');
                } else if (fechaRecepcion < '2000-01-01') {
                    addError(el, 'La fecha de recepción no puede ser anterior al 01/01/2000.');
                }
                return;
            }

            if (name === 'peso_kg') {
                var peso = Number(value.replace(',', '.'));
                if (!isFinite(peso) || peso < 0.01 || peso > 999999.99) {
                    addError(el, 'Ingrese un peso válido entre 0.01 y 999999.99 kg.');
                }
                return;
            }

            if (name === 'cuota_mensual' || name === 'multa_renovacion' || name === 'monto' || name === 'precio_comercio' || name === 'precio_tienda' || name === 'precio_empresa' || name === 'precio_urbanizacion' || name === 'precio_otro' || name === 'precio_especial') {
                var monto = Number(value.replace(',', '.'));
                var minimo = (name === 'cuota_mensual' || name.indexOf('precio_') === 0) ? 0.01 : 0;
                if (!isFinite(monto) || monto < minimo) {
                    addError(el, 'Ingrese un monto válido' + (minimo > 0 ? ' mayor que cero.' : ' igual o mayor que cero.'));
                }
                return;
            }

            if (name === 'cantidad_casas') {
                var casas = Number(value);
                if (!Number.isInteger(casas) || casas < 1 || casas > 100000) {
                    addError(el, 'Ingrese una cantidad de casas válida (entre 1 y 100000).');
                }
                return;
            }

            if (name === 'anio' || name === 'periodo_anio') {
                var anio = Number(value);
                if (!Number.isInteger(anio) || anio < 2000 || anio > CURRENT_YEAR + 1) {
                    addError(el, 'Ingrese un año válido entre 2000 y ' + (CURRENT_YEAR + 1) + '.');
                }
                return;
            }

            if (name === 'mes' || name === 'periodo_mes') {
                var mes = Number(value);
                if (!Number.isInteger(mes) || mes < 1 || mes > 12) {
                    addError(el, 'Ingrese un mes válido entre 1 y 12.');
                }
                return;
            }

            if (isPersonField(name, action)) {
                if (!PERSON_RE.test(value)) {
                    addError(el, 'Solo se permiten letras, números, espacios, apóstrofes, puntos y guiones.');
                }
                return;
            }

            if (isPlaceField(name, action)) {
                if (/[<>{}|\\`]/.test(value)) {
                    addError(el, 'El nombre contiene caracteres no permitidos.');
                }
                return;
            }

            if (name === 'direccion' && value.length < 3) {
                addError(el, 'La dirección debe tener al menos 3 caracteres.');
            } else if (name === 'descripcion') {
                // La solicitud de exoneración y el reporte público exigen detalle.
                if ((action === 'solicitar_exoneracion' || action === '') && value.length < 10) {
                    addError(el, 'La descripción debe tener al menos 10 caracteres.');
                } else if (value.length < 3) {
                    addError(el, 'La descripción debe tener al menos 3 caracteres.');
                }
            } else if (name === 'motivo_rechazo' && value.length < 3) {
                addError(el, 'El motivo del rechazo debe tener al menos 3 caracteres.');
            } else if (name === 'motivo_anulacion' && value.length < 3) {
                addError(el, 'El motivo de anulación debe tener al menos 3 caracteres.');
            } else if ((name === 'referencia_pago' || name === 'referencia_lote' || name === 'referencia_origen') && value.length < 3) {
                addError(el, 'La referencia debe tener al menos 3 caracteres.');
            } else if (name === 'ubicacion' && value.length < 5) {
                addError(el, 'La dirección debe tener al menos 5 caracteres.');
            }
        });

        validateCheckboxGroups(form);
    }

    function prepareForm(form, validate) {
        if (!isPostForm(form)) return true;

        var action = actionOf(form);
        applyFieldRules(form, action);
        applyRequiredFields(form, action);
        if (validate) validateCustom(form, action);

        return true;
    }

    function clearGroupErrors(el) {
        var name = el.getAttribute('name') || '';
        if (name.slice(-2) !== '[]') return;
        var form = el.form;
        if (!form) return;
        Array.prototype.forEach.call(form.querySelectorAll('input[type="checkbox"]'), function (box) {
            if (box.getAttribute('name') === name) clearError(box);
        });
    }

    function init() {
        var style = document.createElement('style');
        style.textContent = '.campo-invalido{border-color:#B45F5F !important;box-shadow:0 0 0 2px rgba(180,95,95,.16) !important;}';
        document.head.appendChild(style);

        Array.prototype.forEach.call(document.querySelectorAll('form'), function (form) {
            if (isPostForm(form)) prepareForm(form, false);
        });
    }

    // Capturamos el submit antes de los onsubmit inline (confirmaciones).
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!isPostForm(form)) return;

        prepareForm(form, true);
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopImmediatePropagation();
            if (typeof form.reportValidity === 'function') {
                form.reportValidity();
            }
        }
    }, true);

    document.addEventListener('input', function (event) {
        var el = event.target;
        if (!el || !el.matches || !el.matches('input, select, textarea')) return;

        var name = fieldName(el);

        if (name === 'cedula' || name === 'dni') {
            el.value = digitsOnly(el.value).slice(0, 8);
        } else if (name === 'telefono' || name === 'celular') {
            el.value = digitsOnly(el.value).slice(0, 11);
        }

        clearError(el);
        clearGroupErrors(el);
    }, true);

    document.addEventListener('paste', function (event) {
        var el = event.target;
        if (!el || !el.matches || !el.matches('input, select, textarea')) return;

        var name = fieldName(el);
        if (name !== 'cedula' && name !== 'dni' && name !== 'telefono' && name !== 'celular') return;

        var clipboard = event.clipboardData || window.clipboardData;
        if (!clipboard || typeof clipboard.getData !== 'function') return;

        // Evita que maxlength corte el texto antes de quitar puntos/guiones.
        event.preventDefault();
        var text = clipboard.getData('text') || '';
        el.value = (name === 'cedula' || name === 'dni')
            ? digitsOnly(text).slice(0, 8)
            : digitsOnly(text).slice(0, 11);
        clearError(el);
    }, true);

    document.addEventListener('change', function (event) {
        var el = event.target;
        if (!el || !el.matches || !el.matches('input, select, textarea')) return;
        clearError(el);
        clearGroupErrors(el);

        // El origen de una recepción cambia qué campos de procedencia son obligatorios.
        if (fieldName(el) === 'origen_tipo' && el.form) {
            applyRequiredFields(el.form, actionOf(el.form));
        }
    }, true);

    document.addEventListener('invalid', function (event) {
        var el = event.target;
        if (!el || !el.classList) return;
        el.classList.add('campo-invalido');
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
