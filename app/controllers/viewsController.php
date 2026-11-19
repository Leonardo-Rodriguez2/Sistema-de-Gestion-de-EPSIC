<?php

namespace app\controllers;
use app\models\viewsModel;
use \PDO;

class viewsController extends viewsModel {

    public function preparar() {
        $this->asegurarEstadoUsuarios();

        // 1. Obtener datos del usuario desde la identidad activa
        $sid = $_SESSION['active_sid'] ?? 'main';
        $user_id = $_SESSION['identities'][$sid]['user_id'] ?? $_SESSION['user_id'] ?? null;
        
        global $user, $mensaje_exito, $mensaje_error;
        $user = $this->obtenerUsuario($user_id);

        if (!$user) {
            session_destroy();
            header("Location: /reciclaje/views/public/login.php?error=user_not_found");
            exit;
        }

        // 2. Obtener carpeta del rol
        $folder = $this->obtenerCarpetaRol($user['rol_id']);

        if (!$folder) {
            session_destroy();
            header("Location: /reciclaje/views/public/login.php?error=invalid_role");
            exit;
        }

        // 3. Crear la conexión PDO
        $pdo = $this->conectar();

        $isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

        // 4. Página solicitada (también se acepta por POST para no perder
        //    la vista de origen cuando el formulario apunta a router.php)
        //    Si el formulario no indica página, usamos la última visitada en
        //    esta pestaña para no terminar siempre en el dashboard.
        if (!$isPost && !empty($_GET['page']) && strpos($_GET['page'], 'ajax_') !== 0) {
            $_SESSION['last_page_by_sid'][$sid]  = $_GET['page'];
            $_SESSION['last_query_by_sid'][$sid] = $_SERVER['REQUEST_URI'] ?? '';
        }

        $page = $_GET['page'] ?? $_POST['page'] ?? null;
        if (!$page) {
            $page = $isPost
                ? ($_SESSION['last_page_by_sid'][$sid] ?? 'dash')
                : 'dash';
        }

        // 5. Manejo de AJAX unificado
        if ($page === 'ajax_get_calles') {
            $barrio_id = (int)($_GET['barrio_id'] ?? 0);
            $soloDisponibles = ($_GET['available_only'] ?? '') === '1';
            $currentCalleId = (int)($_GET['current_calle_id'] ?? 0);
            $sql = "SELECT c.id, c.nombre
                    FROM calles c
                    WHERE c.barrio_id = ?";
            if ($soloDisponibles) {
                $sql .= " AND (c.id = ? OR NOT EXISTS (
                              SELECT 1 FROM detalles_encargado_calle dcalle
                              WHERE dcalle.calle_id = c.id
                          ))";
            }
            $sql .= " ORDER BY c.nombre ASC";
            $stmt = $pdo->prepare($sql);
            $params = [$barrio_id];
            if ($soloDisponibles) $params[] = $currentCalleId;
            $stmt->execute($params);
            header('Content-Type: application/json');
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }

        // Las acciones POST pueden usar columnas agregadas por migraciones automáticas.
        // Deben existir antes de ejecutar el controlador, no después del redirect.
        $this->asegurarColumnasPagos();
        $this->asegurarExenciones();
        $this->asegurarCedula();
        $this->asegurarTiposPredio();
        $this->asegurarSolicitudesGestion();
        $this->asegurarRecepcionesResiduos();

        // Mantener el precio del dólar en bolívares actualizado (máximo una
        // descarga cada 3 horas; si falla internet se conserva el valor guardado).
        \tasa_bolivar_actualizar($pdo);

        // 5.1 Validación centralizada de todos los formularios POST del área privada.
        // Si hay errores, se devuelve al formulario sin ejecutar la acción.
        if ($isPost) {
            $accionPost = $_POST['action'] ?? $_POST['form_type'] ?? null;
            if ($accionPost !== null && trim((string)$accionPost) !== '') {
                $erroresValidacion = \validar_accion_post($accionPost, $_POST, $_FILES);
                if (!empty($erroresValidacion)) {
                    $GLOBALS['mensaje_error'] = 'Revisa los datos: ' . implode(' ', $erroresValidacion);
                    $this->redirigirTrasPost($page, $sid);
                }

                // La validación ya pasó: guardamos identificaciones y teléfonos
                // solo con dígitos, sin puntos, espacios ni guiones.
                \normalizar_datos_post($_POST);
            }
        }

        // 6. Procesar formularios POST del rol
        $this->procesarPost($folder, $page);

        // 6.1 Post/Redirect/Get: después de crear/editar volvemos a la vista
        // de origen (nunca al dashboard) y conservamos el mensaje de resultado.
        if ($isPost && ($_POST['action'] ?? $_POST['form_type'] ?? null) !== null) {
            $this->redirigirTrasPost($page, $sid);
        }

        // 6.6 Especial: Verificar deudas si el encargado entra a Reportar Pagos
        if ($page === 'reportar_pago' && $user['rol_id'] == 5) {
            $bCtrl = new \app\controllers\barrioController();
            $bCtrl->verificarDeudasBarrio($user['id']);
        }

        // 6. Obtener ruta de vista validada (lista blanca)
        $vista = $this->obtenerVista($page, $folder);

        // Devolver todo lo que el scope global necesitará
        return [
            'pdo'   => $pdo,
            'user'  => $user,
            'page'  => $page,
            'vista' => $vista,
        ];
    }


    /**
     * Redirige tras procesar un POST (patrón PRG) a la página indicada por el
     * formulario o, en su defecto, a la misma página donde se envió.
     */
    private function redirigirTrasPost($page, $sid = 'main') {
        $pagePost = trim((string)($_POST['page'] ?? ''));
        $pageGet  = trim((string)($_GET['page'] ?? ''));

        if (!empty($_GET)) {
            // La URL del POST ya trae contexto (page, id, tab, filtros...)
            $params = $_GET;
        } else {
            // El formulario apuntaba a router.php sin parámetros: recuperamos
            // la última URL visitada en esta pestaña para volver al módulo.
            $lastQuery = $_SESSION['last_query_by_sid'][$sid] ?? '';
            parse_str((string)parse_url($lastQuery, PHP_URL_QUERY), $params);
        }

        unset($params['exito'], $params['error']);

        if ($pagePost !== '') {
            $params['page'] = $pagePost;
        } elseif ($pageGet !== '') {
            $params['page'] = $pageGet;
        } elseif (!empty($page)) {
            $params['page'] = $page;
        } else {
            $params['page'] = 'dash';
        }

        // Conservar parámetros de contexto (sesión, filtros, identificadores)
        foreach (['sid', 'rol_id', 'id', 'tab', 'barrio_id', 'calle_id', 'mes', 'anio'] as $clave) {
            if (!isset($params[$clave]) && isset($_POST[$clave]) && $_POST[$clave] !== '') {
                $params[$clave] = $_POST[$clave];
            }
        }

        $exito = $GLOBALS['mensaje_exito'] ?? null;
        $error = $GLOBALS['mensaje_error'] ?? null;
        if (!empty($exito)) $params['exito'] = $exito;
        if (!empty($error)) $params['error'] = $error;

        header('Location: router.php?' . http_build_query($params), true, 303);
        exit;
    }


    private function procesarPost($folder, $page = 'dash') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $controllerClass = "app\\controllers\\{$folder}Controller";
        $action = $_POST['action'] ?? $_POST['form_type'] ?? null;

        // Route gestor_* pages and revisar_lotes to gestorController for admin
        if ($folder === 'admin' && (strpos($page, 'gestor_') === 0 || $page === 'revisar_lotes')) {
            $controllerClass = "app\\controllers\\gestorController";
        }

        // El Gestor también registra/edita viviendas: esas acciones las maneja el adminController
        if ($folder === 'gestor' && in_array($action, ['nuevo_vecino_admin', 'editar_vivienda', 'edit_user'], true)) {
            $controllerClass = "app\\controllers\\adminController";
        }

        // Reportes ciudadanos: acción compartida por admin, gestor y personal.
        if ($action === 'actualizar_reporte') {
            $controllerClass = "app\\controllers\\adminController";
        }

        // Recepción de residuos: el módulo vive fuera de las carpetas por rol.
        if ($page === 'recepcion_residuos' || in_array($action, ['registrar_recepcion_residuo', 'anular_recepcion_residuo'], true)) {
            $controllerClass = "app\\controllers\\recepcionController";
        }

        if (class_exists($controllerClass)) {
            $ctrl = new $controllerClass();
            $ctrl->procesarAcciones();
        }
    }
}
