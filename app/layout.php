<?php
// Plantilla comun de todas las paginas (basada en el ejemplo "Dashboard" de Bootstrap 5).

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Escapa texto antes de mostrarlo en HTML (evita XSS)
function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function fecha_mx(?string $fecha): string
{
    return $fecha ? date('d/m/Y', strtotime($fecha)) : '-';
}

// Mensaje que se muestra una vez despues de redirigir (Post/Redirect/Get)
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

// Color de Bootstrap para cada estado del equipo
function badge_estado(string $estado): string
{
    global $ESTADOS;
    $color = ['DISPONIBLE' => 'success', 'PRESTADO' => 'primary', 'MANTENIMIENTO' => 'warning', 'BAJA' => 'secondary'][$estado] ?? 'light';
    return '<span class="badge text-bg-' . $color . '">' . e($ESTADOS[$estado] ?? $estado) . '</span>';
}

function encabezado(string $titulo): void
{
    $u = usuario_actual();
    $aviso = $_SESSION['aviso'] ?? null;
    unset($_SESSION['aviso']);
    $actual = basename($_SERVER['SCRIPT_NAME']);
    $menu = [
        'index.php'        => ['bi-house', 'Inicio'],
        'equipos.php'      => ['bi-laptop', 'Equipos'],
        'solicitantes.php' => ['bi-people', 'Solicitantes'],
        'prestamos.php'    => ['bi-box-arrow-up-right', 'Préstamos'],
        'devoluciones.php' => ['bi-box-arrow-in-down-left', 'Devoluciones'],
        'consultas.php'    => ['bi-search', 'Consultas'],
    ];
    ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> | Control de Préstamos</title>
  <link rel="stylesheet" href="vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
<header class="navbar sticky-top bg-dark flex-md-nowrap p-0 shadow" data-bs-theme="dark">
  <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3 fs-6 text-white" href="index.php">Control de Préstamos</a>
  <span class="navbar-text text-white-50 small d-none d-md-inline ms-auto me-3">
    <?= e($u['nombre_completo'] ?? '') ?> (<?= e(strtolower($u['rol'] ?? '')) ?>)
  </span>
  <button class="navbar-toggler d-md-none border-0 me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuLateral" aria-label="Menú">
    <span class="navbar-toggler-icon"></span>
  </button>
</header>

<div class="container-fluid">
  <div class="row">
    <div class="sidebar border-end col-md-3 col-lg-2 p-0 bg-body-tertiary">
      <div class="offcanvas-md offcanvas-end bg-body-tertiary" tabindex="-1" id="menuLateral">
        <div class="offcanvas-header">
          <h5 class="offcanvas-title">Control de Préstamos</h5>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#menuLateral" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body d-md-flex flex-column p-0 pt-lg-3 overflow-y-auto">
          <ul class="nav flex-column">
            <?php foreach ($menu as $archivo => [$icono, $texto]): ?>
              <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 <?= $actual === $archivo ? 'active' : '' ?>" href="<?= $archivo ?>">
                  <i class="bi <?= $icono ?>"></i> <?= $texto ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
          <hr class="my-3">
          <ul class="nav flex-column mb-auto">
            <li class="nav-item">
              <a class="nav-link d-flex align-items-center gap-2" href="logout.php"><i class="bi bi-door-closed"></i> Cerrar sesión</a>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pb-4">
      <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h3"><?= e($titulo) ?></h1>
      </div>
      <?php if ($aviso): ?>
        <div class="alert alert-<?= $aviso['tipo'] === 'exito' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
          <?= e($aviso['texto']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
      <?php endif; ?>
<?php
}

function pie(): void
{
    ?>
    </main>
  </div>
</div>
<script src="vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="js/app.js"></script>
</body>
</html>
<?php
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(token_csrf()) . '">';
}
