<?php
// views/components/dashboard_alerts.php
// Se asume que $exito y $error son las variables que contienen los mensajes.
?>

<?php if (isset($exito) && $exito): ?>
    <div class="alert alert-success" id="alert-success">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        <span><?php echo htmlspecialchars((string)$exito, ENT_QUOTES, 'UTF-8'); ?></span>
        <button onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; margin-left:auto; color:#33485C; font-size:16px;">×</button>
    </div>
<?php endif; ?>

<?php if (isset($error) && $error): ?>
    <div class="alert alert-error" id="alert-error">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
        <span><?php echo htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8'); ?></span>
        <button onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; margin-left:auto; color:#7A4F4F; font-size:16px;">×</button>
    </div>
<?php endif; ?>

<style>
    .alert {
        padding: 12px 16px;
        border-radius: 3px;
        margin-bottom: 20px;
        font-weight: 500;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideDown 0.3s ease-out;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .alert-success { background: #F4F6F8; color: #33485C; border: 1px solid #D6E1EA; border-left: 4px solid #4C6E91; }
    .alert-error { background: #F7F2F2; color: #7A4F4F; border: 1px solid #E8D8D8; border-left: 4px solid #9A6A6A; }
    
    @keyframes slideDown {
        from { transform: translateY(-10px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    /* Auto-dismiss after 5 seconds */
    .alert-success { animation: slideDown 0.3s ease-out, fadeOut 0.5s ease-in 4.5s forwards; }
    .alert-error { animation: slideDown 0.3s ease-out, fadeOut 0.5s ease-in 4.5s forwards; }
    
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; pointer-events: none; }
    }
</style>
