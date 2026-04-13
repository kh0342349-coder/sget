<?php
date_default_timezone_set('America/Bogota');
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Transporte - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --bg-color: #f8fafc;
            --text-color: #1e293b;
            --border-color: #e2e8f0;
            --success-bg: #f0fdf4;
            --success-text: #166534;
            --success-border: #bbf7d0;
            --error-bg: #fef2f2;
            --error-text: #dc2626;
            --error-border: #fee2e2;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: var(--text-color);
        }

        .login-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .icon-container {
            background-color: var(--primary-color);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 20px;
        }

        .icon-container svg { fill: white; width: 30px; }

        h2 { margin: 0 0 8px; font-weight: 600; font-size: 1.5rem; }
        .subtitle { color: #64748b; margin-bottom: 30px; font-size: 0.9rem; }

        .form-group { text-align: left; margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.85rem; }

        input[type="number"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn-login {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 14px;
            width: 100%;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 10px;
        }

        .btn-login:hover { background-color: #1d4ed8; }

        /* Estilos de Mensajes */
        .msg {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            text-align: left;
        }
        .error-msg {
            background-color: var(--error-bg);
            color: var(--error-text);
            border: 1px solid var(--error-border);
        }
        .success-msg {
            background-color: var(--success-bg);
            color: var(--success-text);
            border: 1px solid var(--success-border);
        }

        .footer-links { margin-top: 25px; font-size: 0.85rem; }
        .footer-links a { color: var(--primary-color); text-decoration: none; font-weight: 600; }
        .footer-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="icon-container">
            <svg viewBox="0 0 24 24"><path d="M18 11h-2V6c0-1.1-.9-2-2-2H6c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2 0 1.1.9 2 2 2s2-.9 2-2h4c0 1.1.9 2 2 2s2-.9 2-2c1.1 0 2-.9 2-2v-5c0-1.1-.9-2-2-2zm-12 0V6h8v5H6zm1 8c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm10 0c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1z"/></svg>
        </div>

        <h2>Sistema de Transporte</h2>
        <p class="subtitle">Inicia sesión para continuar</p>

        <?php if(isset($_SESSION['success_msg'])): ?>
            <div class="msg success-msg">
                <strong>¡Hecho!</strong> <?= htmlspecialchars($_SESSION['success_msg']) ?>
            </div>
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>

        <?php if(isset($_SESSION['msg'])): ?>
            <div class="msg error-msg">
                <strong>Error:</strong> <?= htmlspecialchars($_SESSION['msg']) ?>
            </div>
            <?php unset($_SESSION['msg']); ?>
        <?php endif; ?>

        <form action="validar.php" method="POST">
            <div class="form-group">
                <label for="doc">Número de Documento</label>
                <input type="number" id="doc" name="doc" placeholder="Ingresa tu número de documento" required>
            </div>
            
            <div class="form-group">
                <label for="pass">Contraseña</label>
                <input type="password" id="pass" name="pass" placeholder="Ingresa tu contraseña" required>
            </div>

            <button type="submit" class="btn-login">Iniciar Sesión</button>
        </form>

        <div class="footer-links">
            <span>¿No tienes una cuenta?</span> <a href="registrar.php">Regístrate</a>
        </div>
    </div>

</body>
</html>