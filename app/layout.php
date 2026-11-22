<?php
// Encabezado, menú y pie comunes a todas las páginas, más utilidades de vista.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Escapa texto antes de mostrarlo en HTML (evita XSS)
function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function fecha_mx(?string $fecha): string
{
    return $fecha ? date('d/m/Y', strtotime($fecha)) : '—';
}

// Mensajes de una sola vez entre redirecciones (patrón Post/Redirect/Get)
function avisar(string $tipo, string $texto): void
{
    $_SESSION['aviso'] = ['tipo' => $tipo, 'texto' => $texto];
}

function redirigir(string $pagina): void
{
    header("Location: $pagina");
    exit;
}

$ESTADOS = ['DISPONIBLE' => 'Disponible', 'PRESTADO' => 'Prestado', 'MANTENIMIENTO' => 'Mantenimiento', 'BAJA' => 'Baja'];
$CONDICIONES = ['BUENA' => 'Buena', 'REGULAR' => 'Regular', 'DANADA' => 'Dañada'];

function encabezado(string $titulo): void
{
    $u = usuario_actual();
    $aviso = $_SESSION['aviso'] ?? null;
    unset($_SESSION['aviso']);
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($titulo) ?> · Préstamos de equipo</title>
  <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
  <header class="encabezado">
    <div class="contenedor">
      <a class="marca" href="index.php">Préstamos de equipo<small>Soporte y Servicios Tecnológicos</small></a>
      <button class="boton-menu" type="button" aria-label="Abrir menú" aria-expanded="false">☰</button>
      <nav class="navegacion">
        <ul class="menu">
          <li><a href="index.php">Inicio</a></li>
          <li><a href="equipos.php">Equipos</a></li>
          <li><a href="solicitantes.php">Solicitantes</a></li>
          <li><a href="prestamos.php">Préstamos</a></li>
          <li><a href="devoluciones.php">Devoluciones</a></li>
          <li><a href="consultas.php">Consultas</a></li>
        </ul>
      </nav>
      <?php if ($u): ?>
        <div class="usuario-sesion oculto-movil"><?= e($u['nombre_completo']) ?> (<?= e($u['rol']) ?>) · <a href="logout.php">Salir</a></div>
      <?php endif; ?>
    </div>
  </header>
  <main class="contenedor">
    <?php if ($aviso): ?>
      <div class="aviso <?= e($aviso['tipo']) ?>"><?= e($aviso['texto']) ?></div>
    <?php endif; ?>
<?php
}

function pie(): void
{
    ?>
  </main>
  <footer class="pie">Taller de Base de Datos · TecNM Campus Colima</footer>
  <script src="js/app.js"></script>
</body>
</html>
<?php
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(token_csrf()) . '">';
}
