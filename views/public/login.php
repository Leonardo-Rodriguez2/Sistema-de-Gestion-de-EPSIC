<?php
session_start();
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../autoload.php';

use app\controllers\loginController;

// Delegar login/logout al controlador
$loginCtrl = new loginController();

if (isset($_GET['logout'])) {
    $loginCtrl->cerrarSesion();
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = $loginCtrl->iniciarSesion(); // Redirige si OK, devuelve mensaje si falla
}


$title = "Iniciar Sesión | EPSIC";
ob_start();
?>

  <div class="login-row">
    <div class="login-left">
      <div class="login-brand">
        <img class="login-brand-icon" src="/reciclaje/assets/img/icono.png?v=3" alt="">
        <span class="login-brand-text">EPSIC</span>
      </div>
      <div class="login-subtitle">Sistema de Gestión de Reciclaje</div>

      <!-- Mostrar errores -->
      <?php if (isset($error)): ?>
        <div class="login-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <!-- Formulario -->
      <form method="POST" class="login-form">
        <!-- Correo Electrónico -->
        <div class="login-input-group">
          <label for="email">Correo Electrónico</label>
          <div class="login-input-relative">
            <span class="login-input-icon">&#9993;</span> <!-- Ícono de carta -->
            <input type="email" id="email" name="email" placeholder="tu@email.com" required />
          </div>
        </div>

        <!-- Contraseña -->
        <div class="login-input-group">
          <label for="password">Contraseña</label>
          <div class="login-input-relative">
            <span class="login-input-icon">&#3E5C76;</span> <!-- Ícono de candado -->
            <input type="password" id="password" name="password" placeholder="***********" required />
          </div>
        </div>

        <div class="login-forgot">
          Contacte al administrador para que le asigne una nueva contraseña.
        </div>

        <!-- Botón Iniciar Sesión -->
        <button type="submit" class="login-btn">
          Iniciar Sesión
        </button>

      </form>
    </div>

    <div class="login-right"></div>
  </div>

<?php
$extra_css = "
    body {
      background-color: #E9EFF4; /* Color de fondo específico para login */
    }

    .login-row {
      display: flex;
      width: 90%;
      max-width: 1200px;
      gap: 40px;
      margin: 50px auto;
      min-height: 500px;
    }

    .login-left, .login-right {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    .login-left {
      background-color: white;
      align-items: center;
      padding: 40px;
      text-align: center;
    }

    .login-right {
      background-image: url('https://images.unsplash.com/photo-1511497584788-876760111969?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80');
      background-size: cover;
      background-position: center;
    }

    .login-brand {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-bottom: 10px;
    }

    .login-brand-icon {
      width: 56px;
      height: 56px;
      object-fit: contain;
      flex-shrink: 0;
    }

    .login-brand-text {
      color: #1F2937;
      font-size: 40px;
      font-weight: 800;
      letter-spacing: 1px;
      line-height: 1;
    }

    .login-subtitle {
      color: #6B7280;
      margin-bottom: 25px;
    }

    .login-error {
      background: #F7F2F2;
      border: 1px solid #E8D8D8;
      border-left: 3px solid #9A6A6A;
      color: #7A4F4F;
      padding: 10px 14px;
      margin-bottom: 15px;
      font-size: 13px;
      border-radius: 3px;
    }

    .login-form {
      display: flex;
      flex-direction: column;
      gap: 20px;
      width: 100%;
      max-width: 400px;
    }

    .login-input-group {
      text-align: left;
    }

    .login-input-group label {
      display: block;
      margin-bottom: 8px;
      font-size: 14px;
      font-weight: 500;
    }

    .login-input-relative {
      position: relative;
    }

    .login-input-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #9CA3AF;
    }

    .login-form input {
      width: 100%;
      padding: 12px 12px 12px 40px;
      border: 1px solid #D1D5DB;
      border-radius: 8px;
      font-size: 14px;
      box-sizing: border-box;
    }

    .login-forgot {
      text-align: right;
    }

    .login-forgot a {
      color: #4C6E91;
      font-size: 14px;
      text-decoration: none;
    }

    .login-btn {
      background-color: #4C6E91;
      color: white;
      border: none;
      padding: 14px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.3s;
    }

    .login-btn:hover {
      background-color: #3E5C76;
    }

    @media (max-width: 768px) {
      .login-row {
        flex-direction: column;
        margin-top: 20px;
        width: 95%;
        gap: 0;
        min-height: auto;
      }
      .login-right {
        display: none;
      }
      .login-left {
        padding: 25px 20px;
        border-radius: 12px;
      }
      .login-brand-icon { width: 46px; height: 46px; }
      .login-brand-text { font-size: 32px; }
      .login-form { max-width: 100%; }
      .login-form input { padding: 14px 14px 14px 40px; font-size: 16px; }
      .login-btn { padding: 16px; font-size: 16px; }
    }
    @media (max-width: 400px) {
      .login-left { padding: 20px 15px; }
      .login-brand-icon { width: 40px; height: 40px; }
      .login-brand-text { font-size: 28px; }
    }
  ";
$content = ob_get_clean();
include __DIR__ . '/../layouts/public_layout.php';
?>