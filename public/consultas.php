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
<h1>Consultas</h1>
<p class="subtitulo">Las cinco consultas que pidió el departamento.</p>

<section class="tarjeta">
  <div class="pestanas" role="tablist">
    <button class="pestana activa" type="button" data-panel="general">1. Todos los equipos</button>
    <button class="pestana" type="button" data-panel="disponibles">2. Disponibles</button>
    <button class="pestana" type="button" data-panel="activos">3. Préstamos activos</button>
    <button class="pestana" type="button" data-panel="vencidos">4. Vencidos</button>
    <button class="pestana" type="button" data-panel="historial">5. Historial</button>
  </div>

  <div class="panel-pestana activo" id="general"><div class="tabla-scroll"><table>
    <thead><tr><th>Inventario</th><th>Descripción</th><th>Categoría</th><th>Condición</th><th>Estado</th></tr></thead>
    <tbody><?php foreach ($general as $r): ?>
      <tr><td><?= e($r['num_inventario']) ?></td><td><?= e($r['descripcion']) ?></td><td><?= e($r['categoria']) ?></td>
          <td><?= e($CONDICIONES[$r['condicion']]) ?></td><td><span class="estado <?= strtolower($r['estado']) ?>"><?= e($ESTADOS[$r['estado']]) ?></span></td></tr>
    <?php endforeach; ?></tbody></table></div></div>

  <div class="panel-pestana" id="disponibles"><div class="tabla-scroll"><table>
    <thead><tr><th>Inventario</th><th>Descripción</th><th>Categoría</th><th>Marca / modelo</th></tr></thead>
    <tbody><?php foreach ($disponibles as $r): ?>
      <tr><td><?= e($r['num_inventario']) ?></td><td><?= e($r['descripcion']) ?></td><td><?= e($r['categoria']) ?></td><td><?= e($r['marca_modelo']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$disponibles): ?><tr><td colspan="4" class="sin-resultados">No hay equipos disponibles.</td></tr><?php endif; ?></tbody></table></div></div>

  <div class="panel-pestana" id="activos"><div class="tabla-scroll"><table>
    <thead><tr><th>Equipo</th><th>Quién lo tiene</th><th>Departamento</th><th>Desde</th><th>Devolución prevista</th></tr></thead>
    <tbody><?php foreach ($activos as $r): ?>
      <tr><td><?= e($r['num_inventario'] . ' · ' . $r['descripcion']) ?></td><td><?= e($r['solicitante']) ?></td><td><?= e($r['departamento']) ?></td>
          <td><?= fecha_mx($r['fecha_prestamo']) ?></td><td><?= fecha_mx($r['fecha_devolucion_prevista']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$activos): ?><tr><td colspan="5" class="sin-resultados">No hay préstamos activos.</td></tr><?php endif; ?></tbody></table></div></div>

  <div class="panel-pestana" id="vencidos">
    <div class="aviso info">Préstamos activos cuya fecha de devolución prevista ya pasó.</div>
    <div class="tabla-scroll"><table>
    <thead><tr><th>Equipo</th><th>Quién lo tiene</th><th>Devolución prevista</th><th>Días de atraso</th></tr></thead>
    <tbody><?php foreach ($vencidos as $r): ?>
      <tr><td><?= e($r['num_inventario'] . ' · ' . $r['descripcion']) ?></td><td><?= e($r['solicitante']) ?></td>
          <td><?= fecha_mx($r['fecha_devolucion_prevista']) ?></td><td><span class="estado vencido"><?= (int) $r['dias_atraso'] ?> <?= $r['dias_atraso'] == 1 ? 'día' : 'días' ?></span></td></tr>
    <?php endforeach; ?>
    <?php if (!$vencidos): ?><tr><td colspan="4" class="sin-resultados">No hay préstamos vencidos.</td></tr><?php endif; ?></tbody></table></div></div>

  <div class="panel-pestana" id="historial">
    <form class="barra-tabla" method="get" action="consultas.php#historial">
      <div class="campo" style="margin:0;flex:1;min-width:180px">
        <label for="buscar_por">Buscar historial por</label>
        <select id="buscar_por" name="buscar_por" onchange="this.form.id.value=''; this.form.submit()">
          <option value="solicitante" <?= $buscar_por === 'solicitante' ? 'selected' : '' ?>>Solicitante</option>
          <option value="equipo" <?= $buscar_por === 'equipo' ? 'selected' : '' ?>>Equipo</option>
        </select>
      </div>
      <div class="campo" style="margin:0;flex:2;min-width:220px">
        <label for="id_historial"><?= $buscar_por === 'equipo' ? 'Equipo' : 'Solicitante' ?></label>
        <select id="id_historial" name="id">
          <option value="">Selecciona…</option>
          <?php foreach ($opciones as $o): ?>
            <option value="<?= $o['id'] ?>" <?= ($_GET['id'] ?? '') == $o['id'] ? 'selected' : '' ?>><?= e($o['texto']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="align-self:end"><button class="boton" type="submit">Buscar</button></div>
    </form>
    <div class="tabla-scroll"><table>
      <thead><tr><th>Equipo</th><th>Solicitante</th><th>Prestado</th><th>Prevista</th><th>Devuelto</th><th>Condición</th></tr></thead>
      <tbody><?php foreach ($historial as $r): ?>
        <tr><td><?= e($r['num_inventario'] . ' · ' . $r['descripcion']) ?></td><td><?= e($r['solicitante']) ?></td>
            <td><?= fecha_mx($r['fecha_prestamo']) ?></td><td><?= fecha_mx($r['fecha_devolucion_prevista']) ?></td>
            <td><?= fecha_mx($r['fecha_devolucion_real']) ?></td>
            <td><?= $r['fecha_devolucion_real'] ? e($CONDICIONES[$r['condicion_devolucion']]) : '<span class="estado prestado">Activo</span>' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$historial): ?><tr><td colspan="6" class="sin-resultados"><?= empty($_GET['id']) ? 'Elige una persona o un equipo.' : 'Sin préstamos registrados.' ?></td></tr><?php endif; ?></tbody></table></div>
  </div>
</section>
<?php pie();
