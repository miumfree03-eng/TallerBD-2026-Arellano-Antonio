<?php
require_once __DIR__ . '/../app/layout.php';
requiere_sesion();

// Las 5 consultas que pidió el departamento, siempre con nombres comprensibles (JOIN) y no solo ids.

// 1. Listado general de equipos y su situación
$general = consulta('SELECT e.num_inventario, e.descripcion, c.nombre AS categoria, e.condicion, e.estado
                     FROM equipo e JOIN categoria c ON c.id_categoria = e.id_categoria
                     ORDER BY e.num_inventario')->fetchAll();

// 2. Equipos disponibles
$disponibles = consulta("SELECT e.num_inventario, e.descripcion, c.nombre AS categoria, CONCAT_WS(' · ', e.marca, e.modelo) AS marca_modelo
                         FROM equipo e JOIN categoria c ON c.id_categoria = e.id_categoria
                         WHERE e.estado = 'DISPONIBLE' ORDER BY e.num_inventario")->fetchAll();

// 3. Préstamos activos: quién tiene cada equipo
$activos = consulta('SELECT e.num_inventario, e.descripcion, CONCAT(s.nombre, " ", s.apellidos) AS solicitante,
                            d.nombre AS departamento, p.fecha_prestamo, p.fecha_devolucion_prevista
                     FROM prestamo p
                     JOIN equipo e       ON e.id_equipo = p.id_equipo
                     JOIN solicitante s  ON s.id_solicitante = p.id_solicitante
                     JOIN departamento d ON d.id_departamento = s.id_departamento
                     WHERE p.fecha_devolucion_real IS NULL
                     ORDER BY p.fecha_devolucion_prevista')->fetchAll();

// 4. Préstamos vencidos y días de atraso
$vencidos = consulta('SELECT e.num_inventario, e.descripcion, CONCAT(s.nombre, " ", s.apellidos) AS solicitante,
                             p.fecha_devolucion_prevista, DATEDIFF(CURRENT_DATE, p.fecha_devolucion_prevista) AS dias_atraso
                      FROM prestamo p
                      JOIN equipo e      ON e.id_equipo = p.id_equipo
                      JOIN solicitante s ON s.id_solicitante = p.id_solicitante
                      WHERE p.fecha_devolucion_real IS NULL AND p.fecha_devolucion_prevista < CURRENT_DATE
                      ORDER BY dias_atraso DESC')->fetchAll();

// 5. Historial de una persona o de un equipo (consulta parametrizada)
$buscar_por = ($_GET['buscar_por'] ?? 'solicitante') === 'equipo' ? 'equipo' : 'solicitante';
$opciones = $buscar_por === 'equipo'
    ? consulta('SELECT id_equipo AS id, CONCAT(num_inventario, " · ", descripcion) AS texto FROM equipo ORDER BY num_inventario')->fetchAll()
    : consulta('SELECT id_solicitante AS id, CONCAT(num_institucional, " · ", nombre, " ", apellidos) AS texto FROM solicitante ORDER BY apellidos')->fetchAll();
$historial = [];
if (!empty($_GET['id'])) {
    $columna = $buscar_por === 'equipo' ? 'p.id_equipo' : 'p.id_solicitante';   // la columna viene de una lista fija, no del usuario
    $historial = consulta("SELECT e.num_inventario, e.descripcion, CONCAT(s.nombre, ' ', s.apellidos) AS solicitante,
                                  p.fecha_prestamo, p.fecha_devolucion_prevista, p.fecha_devolucion_real, p.condicion_devolucion
                           FROM prestamo p
                           JOIN equipo e      ON e.id_equipo = p.id_equipo
                           JOIN solicitante s ON s.id_solicitante = p.id_solicitante
                           WHERE $columna = ?
                           ORDER BY p.fecha_prestamo DESC", [(int) $_GET['id']])->fetchAll();
}

encabezado('Consultas');
?>
<ul class="nav nav-tabs" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#general" type="button">Equipos</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#disponibles" type="button">Disponibles</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#activos" type="button">Préstamos activos</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#vencidos" type="button">Vencidos <?php if ($vencidos): ?><span class="badge text-bg-danger"><?= count($vencidos) ?></span><?php endif; ?></button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#historial" type="button">Historial</button></li>
</ul>

<div class="tab-content border border-top-0 rounded-bottom bg-body p-3">
  <div class="tab-pane fade show active" id="general">
    <div class="table-responsive"><table class="table table-sm table-striped mb-0">
      <thead><tr><th>Inventario</th><th>Descripción</th><th>Categoría</th><th>Condición</th><th>Estado</th></tr></thead>
      <tbody><?php foreach ($general as $r): ?>
        <tr><td><?= e($r['num_inventario']) ?></td><td><?= e($r['descripcion']) ?></td><td><?= e($r['categoria']) ?></td>
            <td><?= e($CONDICIONES[$r['condicion']]) ?></td><td><?= badge_estado($r['estado']) ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
  </div>

  <div class="tab-pane fade" id="disponibles">
    <div class="table-responsive"><table class="table table-sm table-striped mb-0">
      <thead><tr><th>Inventario</th><th>Descripción</th><th>Categoría</th><th>Marca / modelo</th></tr></thead>
      <tbody><?php foreach ($disponibles as $r): ?>
        <tr><td><?= e($r['num_inventario']) ?></td><td><?= e($r['descripcion']) ?></td><td><?= e($r['categoria']) ?></td><td><?= e($r['marca_modelo']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$disponibles): ?><tr><td colspan="4" class="text-center text-body-secondary">No hay equipos disponibles.</td></tr><?php endif; ?></tbody></table></div>
  </div>

  <div class="tab-pane fade" id="activos">
    <div class="table-responsive"><table class="table table-sm table-striped mb-0">
      <thead><tr><th>Equipo</th><th>Lo tiene</th><th>Departamento</th><th>Desde</th><th>Devolución</th></tr></thead>
      <tbody><?php foreach ($activos as $r): ?>
        <tr><td><?= e($r['num_inventario'] . ' ' . $r['descripcion']) ?></td><td><?= e($r['solicitante']) ?></td><td><?= e($r['departamento']) ?></td>
            <td><?= fecha_mx($r['fecha_prestamo']) ?></td><td><?= fecha_mx($r['fecha_devolucion_prevista']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$activos): ?><tr><td colspan="5" class="text-center text-body-secondary">No hay préstamos activos.</td></tr><?php endif; ?></tbody></table></div>
  </div>

  <div class="tab-pane fade" id="vencidos">
    <div class="table-responsive"><table class="table table-sm table-striped mb-0">
      <thead><tr><th>Equipo</th><th>Lo tiene</th><th>Debía regresar</th><th>Atraso</th></tr></thead>
      <tbody><?php foreach ($vencidos as $r): ?>
        <tr><td><?= e($r['num_inventario'] . ' ' . $r['descripcion']) ?></td><td><?= e($r['solicitante']) ?></td>
            <td><?= fecha_mx($r['fecha_devolucion_prevista']) ?></td>
            <td><span class="badge text-bg-danger"><?= (int) $r['dias_atraso'] ?> <?= $r['dias_atraso'] == 1 ? 'día' : 'días' ?></span></td></tr>
      <?php endforeach; ?>
      <?php if (!$vencidos): ?><tr><td colspan="4" class="text-center text-body-secondary">No hay préstamos vencidos.</td></tr><?php endif; ?></tbody></table></div>
  </div>

  <div class="tab-pane fade" id="historial">
    <form class="row g-2 align-items-end mb-3" method="get" action="consultas.php#historial">
      <div class="col-sm-3">
        <label for="buscar_por" class="form-label">Buscar por</label>
        <select id="buscar_por" name="buscar_por" class="form-select" onchange="this.form.id.value=''; this.form.submit()">
          <option value="solicitante" <?= $buscar_por === 'solicitante' ? 'selected' : '' ?>>Solicitante</option>
          <option value="equipo" <?= $buscar_por === 'equipo' ? 'selected' : '' ?>>Equipo</option>
        </select>
      </div>
      <div class="col-sm-6">
        <label for="id_historial" class="form-label"><?= $buscar_por === 'equipo' ? 'Equipo' : 'Solicitante' ?></label>
        <select id="id_historial" name="id" class="form-select">
          <option value="">Seleccionar</option>
          <?php foreach ($opciones as $o): ?>
            <option value="<?= $o['id'] ?>" <?= ($_GET['id'] ?? '') == $o['id'] ? 'selected' : '' ?>><?= e($o['texto']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-3"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Buscar</button></div>
    </form>
    <div class="table-responsive"><table class="table table-sm table-striped mb-0">
      <thead><tr><th>Equipo</th><th>Solicitante</th><th>Prestado</th><th>Prevista</th><th>Devuelto</th><th>Condición</th></tr></thead>
      <tbody><?php foreach ($historial as $r): ?>
        <tr><td><?= e($r['num_inventario'] . ' ' . $r['descripcion']) ?></td><td><?= e($r['solicitante']) ?></td>
            <td><?= fecha_mx($r['fecha_prestamo']) ?></td><td><?= fecha_mx($r['fecha_devolucion_prevista']) ?></td>
            <td><?= fecha_mx($r['fecha_devolucion_real']) ?></td>
            <td><?= $r['fecha_devolucion_real'] ? e($CONDICIONES[$r['condicion_devolucion']]) : '<span class="badge text-bg-primary">Activo</span>' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$historial): ?><tr><td colspan="6" class="text-center text-body-secondary"><?= empty($_GET['id']) ? 'Selecciona una persona o un equipo.' : 'Sin préstamos registrados.' ?></td></tr><?php endif; ?></tbody></table></div>
  </div>
</div>
<?php pie();
