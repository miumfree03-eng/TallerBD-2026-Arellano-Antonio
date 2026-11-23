<?php
require_once __DIR__ . '/../app/layout.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    // SELECT parametrizado: el usuario escrito en el formulario nunca se pega dentro del SQL
    $u = consulta('SELECT id_usuario, usuario, nombre_completo, password_hash, rol
                   FROM usuario WHERE usuario = ? AND activo = TRUE', [trim($_POST['usuario'] ?? '')])->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);
        unset($u['password_hash']);
        $_SESSION['usuario'] = $u;
        redirigir('index.php');
    }
    $error = 'Usuario o contraseña incorrectos.';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Iniciar sesión | Control de Préstamos</title>
  <link rel="stylesheet" href="vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/estilos.css">
</head>
<body class="d-flex align-items-center py-4 bg-body-tertiary min-vh-100">
  <main class="login-caja w-100 m-auto px-3">
    <form method="post" class="needs-validation" novalidate>
      <?= campo_csrf() ?>
      <div class="text-center mb-4">
        <i class="bi bi-laptop fs-1 text-primary"></i>
        <h1 class="h4 mt-2 mb-1">Control de Préstamos</h1>
        <p class="text-body-secondary small mb-0">Soporte y Servicios Tecnológicos</p>
      </div>

      <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>

      <div class="form-floating mb-2">
        <input type="text" class="form-control" id="usuario" name="usuario" placeholder="usuario" required autocomplete="username">
        <label for="usuario">Usuario</label>
      </div>
      <div class="form-floating mb-3">
        <input type="password" class="form-control" id="password" name="password" placeholder="contraseña" required autocomplete="current-password">
        <label for="password">Contraseña</label>
      </div>
      <button class="btn btn-primary w-100 py-2" type="submit">Entrar</button>
      <p class="mt-4 mb-0 text-center text-body-secondary small">TecNM Campus Colima &middot; <?= date('Y') ?></p>
    </form>
  </main>
  <script src="vendor/bootstrap/bootstrap.bundle.min.js"></script>
  <script src="js/app.js"></script>
</body>
</html>
