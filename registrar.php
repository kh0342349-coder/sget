<?php
date_default_timezone_set('America/Bogota');
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Transporte - Registro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --bg-color: #f8fafc;
            --text-color: #1e293b;
            --border-color: #e2e8f0;
            --error-color: #ef4444;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            color: var(--text-color);
        }

        .register-container {
            background-color: #ffffff;
            padding: 30px 40px;
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        h2 { margin: 0 0 8px; font-weight: 600; font-size: 1.5rem; }
        .subtitle { color: #64748b; margin-bottom: 20px; font-size: 0.9rem; }
        .form-group { text-align: left; margin-bottom: 12px; }
        label { display: block; margin-bottom: 4px; font-weight: 600; font-size: 0.8rem; }

        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            box-sizing: border-box;
            transition: all 0.2s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn-register {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px;
            width: 100%;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 10px;
        }

        .alert-error {
            background-color: #fee2e2;
            color: var(--error-color);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.85rem;
            border: 1px solid #fecaca;
            text-align: left;
        }

        .footer-links { margin-top: 15px; font-size: 0.85rem; }
        .footer-links a { color: var(--primary-color); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>

    <div class="register-container">
        <h2>Crear Cuenta</h2>
        <p class="subtitle">Únete al Sistema de Transporte</p>

        <?php if(isset($_SESSION['msg'])): ?>
            <div class="alert-error">
                <strong>Error:</strong> <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?>
            </div>
        <?php endif; ?>

        <form action="nuevo_usuario.php" method="POST">
            <div class="form-group">
                <label for="nombre">Nombre Completo</label>
                <input type="text" id="nombre" name="nombre" placeholder="Juan Pérez" required>
            </div>

            <div class="form-group" style="display: flex; gap: 10px;">
                <div style="flex: 1;">
                    <label for="tipo_doc">Tipo</label>
                    <select id="tipo_doc" name="tipo_doc" required>
                        <option value="CC">CC</option>
                        <option value="TI">TI</option>
                        <option value="CE">CE</option>
                    </select>
                </div>
                <div style="flex: 2;">
                    <label for="doc">N° Documento</label>
                    <input type="number" id="doc" name="doc" placeholder="123456" required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" placeholder="ejemplo@correo.com" required>
            </div>
            
            <div class="form-group">
                <label for="pass">Contraseña</label>
                <input type="password" id="pass" name="pass" placeholder="Mínimo 6 caracteres" required>
            </div>

            <div class="form-group">
                <label for="pass2">Confirmar Contraseña</label>
                <input type="password" id="pass2" name="pass2" placeholder="Repite tu contraseña" required>
            </div>

            <button type="submit" class="btn-register">Registrarse ahora</button>
        </form>

        <div class="footer-links">
            <span>¿Ya tienes cuenta?</span> <a href="index.php">Inicia sesión</a>
        </div>
    </div>

</body>
</html>