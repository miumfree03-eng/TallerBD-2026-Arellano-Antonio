<?php
require_once __DIR__ . '/../app/layout.php';
requiere_sesion();

// Conteo de equipos por estado (SELECT con GROUP BY)
$por_estado = consulta('SELECT estado, COUNT(*) AS total FROM equipo GROUP BY estado')->fetchAll(PDO::FETCH_KEY_PAIR);
$vencidos = consulta('SELECT COUNT(*) FROM prestamo
                      WHERE fecha_devolucion_real IS NULL AND fecha_devolucion_prevista < CURRENT_DATE')->fetchColumn();

// Prestamos activos mas proximos a vencer (JOIN de 4 tablas)
$proximos = consulta(
    'SELECT e.num_inventario, e.descripcion, CONCAT(s.nombre, " ", s.apellidos) AS solicitante,
            d.nombre AS departamento, p.fecha_devolucion_prevista,
            p.fecha_devolucion_prevista < CURRENT_DATE AS vencido
     FROM prestamo p
     JOIN equipo e       ON e.id_equipo = p.id_equipo
     JOIN solicitante s  ON s.id_solicitante = p.id_solicitante
     JOIN departamento d ON d.id_departamento = s.id_departamento
     WHERE p.fecha_devolucion_real IS NULL
     ORDER BY p.fecha_devolucion_prevista
     LIMIT 5')->fetchAll();

$tarjetas = [
    ['Disponibles', $por_estado['DISPONIBLE'] ?? 0, 'success', 'bi-check-circle', 'consultas.php#disponibles'],
    ['Prestados', $por_estado['PRESTADO'] ?? 0, 'primary', 'bi-box-arrow-up-right', 'consultas.php#activos'],
    ['En mantenimiento', $por_estado['MANTENIMIENTO'] ?? 0, 'warning', 'bi-tools', 'equipos.php'],
    ['Vencidos', $vencidos, 'danger', 'bi-exclamation-triangle', 'consultas.php#vencidos'],
];

encabezado('Inicio');
?>
<div class="row g-3 mb-4">
  <?php foreach ($tarjetas as [$texto, $numero, $color, $icono, $enlace]): ?>
    <div class="col-6 col-xl-3">
      <a href="<?= $enlace ?>" class="card text-decoration-none shadow-sm border-0 h-100">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <div class="text-body-secondary small"><?= $texto ?></div>
            <div class="fs-3 fw-semibold"><?= (int) $numero ?></div>
          </div>
          <i class="bi <?= $icono ?> fs-2 text-<?= $color ?>"></i>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Préstamos por vencer</span>
    <?php if (es_admin()): ?><a href="prestamos.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Nuevo préstamo</a><?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr><th>Equipo</th><th>Solicitante</th><th>Departamento</th><th>Devolución</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($proximos as $p): ?>
        <tr>
          <td><strong><?= e($p['num_inventario']) ?></strong> <?= e($p['descripcion']) ?></td>
          <td><?= e($p['solicitante']) ?></td>
          <td><?= e($p['departamento']) ?></td>
          <td><?= fecha_mx($p['fecha_devolucion_prevista']) ?></td>
          <td><?= $p['vencido'] ? '<span class="badge text-bg-danger">Vencido</span>' : '<span class="badge text-bg-primary">Activo</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$proximos): ?><tr><td colspan="5" class="text-center text-body-secondary py-3">No hay préstamos activos.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php pie();
