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
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión · Préstamos de equipo</title>
  <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
  <main class="pagina-login">
    <section class="tarjeta caja-login">
      <h1>Préstamos de equipo</h1>
      <p class="subtitulo">Departamento de Soporte y Servicios Tecnológicos</p>
      <?php if ($error): ?><div class="aviso error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" data-validar novalidate>
        <?= campo_csrf() ?>
        <div class="aviso aviso-formulario" hidden></div>
        <div class="campo">
          <label for="usuario">Usuario</label>
          <input id="usuario" name="usuario" type="text" required autocomplete="username">
        </div>
        <div class="campo">
          <label for="password">Contraseña</label>
          <input id="password" name="password" type="password" required autocomplete="current-password">
        </div>
        <div class="botones"><button class="boton" type="submit">Entrar</button></div>
      </form>
    </section>
  </main>
  <script src="js/app.js"></script>
</body>
</html>
