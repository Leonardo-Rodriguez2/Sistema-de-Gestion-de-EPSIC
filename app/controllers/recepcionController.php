<?php

namespace app\controllers;

use app\models\mainModel;
use app\models\recepcionModel;

// =============================================
// app/controllers/recepcionController.php
// Acciones POST del módulo de recepción de residuos.
// Disponible para todos los roles internos (1, 2, 3, 5 y 6).
// =============================================

class recepcionController extends mainModel {

    public function procesarAcciones() {
        global $user, $mensaje_exito, $mensaje_error;

        $action = $_POST['action'] ?? $_POST['form_type'] ?? null;
        if (!in_array($action, ['registrar_recepcion_residuo', 'anular_recepcion_residuo'], true)) {
            return;
        }

        $model = new recepcionModel();
        $model->asegurarTabla();

        if ($action === 'registrar_recepcion_residuo') {
            try {
                $id = $model->registrar($user, $_POST);
                $peso = number_format((float)str_replace(',', '.', (string)($_POST['peso_kg'] ?? 0)), 2, '.', ',');
                $material = trim((string)($_POST['tipo_material'] ?? ''));
                $mensaje_exito = "Recepción #{$id} registrada: {$peso} kg de {$material}.";
            } catch (\Throwable $e) {
                $mensaje_error = $e->getMessage();
            }
            return;
        }

        if ($action === 'anular_recepcion_residuo') {
            try {
                $model->anular(
                    $user,
                    (int)($_POST['recepcion_id'] ?? 0),
                    trim((string)($_POST['motivo_anulacion'] ?? ''))
                );
                $mensaje_exito = 'Recepción anulada correctamente.';
            } catch (\Throwable $e) {
                $mensaje_error = $e->getMessage();
            }
        }
    }
}
