<?php
require_once __DIR__ . '/../app/layout.php';
$u = requiere_sesion();

// ------------------------------------------------------------------ registrar devolución (TRANSACCIÓN)
// UPDATE del préstamo (fecha real, quién recibió, condición) + UPDATE del equipo:
// vuelve a DISPONIBLE, o pasa a MANTENIMIENTO si regresó dañado (y se abre su registro de mantenimiento).
// El préstamo no se borra: queda en el historial (Situación 4).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requiere_admin();
    verificar_csrf();
    $id_prestamo = (int) $_POST['id_prestamo'];
    $condicion = $_POST['condicion_devolucion'] ?? '';
    $pdo = db();
    try {
        if (!in_array($condicion, ['BUENA', 'REGULAR', 'DANADA'], true)) {
            throw new RuntimeException('Selecciona la condición en que regresó el equipo.');
        }
        $pdo->beginTransaction();
        $p = consulta('SELECT id_equipo FROM prestamo WHERE id_prestamo = ? AND fecha_devolucion_real IS NULL FOR UPDATE',
                      [$id_prestamo])->fetch();
        if (!$p) {
            throw new RuntimeException('Ese préstamo ya fue devuelto o no existe.');
        }
        consulta('UPDATE prestamo SET fecha_devolucion_real = NOW(), id_usuario_recibe = ?, condicion_devolucion = ?,
                  observaciones = LEFT(CONCAT_WS(" | ", observaciones, ?), 255) WHERE id_prestamo = ?',
                 [$u['id_usuario'], $condicion, trim($_POST['observaciones'] ?? '') ?: null, $id_prestamo]);
        $nuevo_estado = $condicion === 'DANADA' ? 'MANTENIMIENTO' : 'DISPONIBLE';
        consulta('UPDATE equipo SET estado = ?, condicion = ? WHERE id_equipo = ?', [$nuevo_estado, $condicion, $p['id_equipo']]);
        if ($nuevo_estado === 'MANTENIMIENTO') {
            consulta('INSERT INTO mantenimiento (id_equipo, motivo, id_usuario) VALUES (?, ?, ?)',
                     [$p['id_equipo'], 'Devuelto dañado en el préstamo #' . $id_prestamo, $u['id_usuario']]);
        }
        $pdo->commit();
        avisar('exito', $nuevo_estado === 'MANTENIMIENTO'
            ? 'Devolución registrada. El equipo regresó dañado y pasó a mantenimiento.'
            : 'Devolución registrada. El equipo está disponible otra vez.');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        avisar('error', $ex instanceof RuntimeException ? $ex->getMessage() : 'No se pudo registrar: ' . $ex->getMessage());
    }
    redirigir('devoluciones.php');
}

$activos = consulta(
    'SELECT p.id_prestamo, e.num_inventario, e.descripcion, CONCAT(s.nombre, " ", s.apellidos) AS solicitante,
            p.fecha_devolucion_prevista < CURRENT_DATE AS vencido
     FROM prestamo p
     JOIN equipo e      ON e.id_equipo = p.id_equipo
     JOIN solicitante s ON s.id_solicitante = p.id_solicitante
     WHERE p.fecha_devolucion_real IS NULL
     ORDER BY e.num_inventario')->fetchAll();
$ultimas = consulta(
    'SELECT e.num_inventario, e.descripcion, CONCAT(s.nombre, " ", s.apellidos) AS solicitante,
            p.fecha_devolucion_real, p.condicion_devolucion, ur.usuario AS recibio
     FROM prestamo p
     JOIN equipo e      ON e.id_equipo = p.id_equipo
     JOIN solicitante s ON s.id_solicitante = p.id_solicitante
     JOIN usuario ur    ON ur.id_usuario = p.id_usuario_recibe
     WHERE p.fecha_devolucion_real IS NOT NULL
     ORDER BY p.fecha_devolucion_real DESC
     LIMIT 10')->fetchAll();

encabezado('Devoluciones');
?>
<div class="row g-4">
  <?php if (es_admin()): ?>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Registrar devolución</div>
      <div class="card-body">
        <form method="post" class="needs-validation" novalidate>
          <?= campo_csrf() ?>
          <div class="mb-3">
            <label for="id_prestamo" class="form-label">Préstamo</label>
            <select id="id_prestamo" name="id_prestamo" class="form-select" required>
              <option value="">Seleccionar</option>
              <?php foreach ($activos as $p): ?>
                <option value="<?= $p['id_prestamo'] ?>"><?= e($p['num_inventario'] . ' - ' . $p['solicitante'] . ($p['vencido'] ? ' (vencido)' : '')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="condicion_devolucion" class="form-label">Condición</label>
            <select id="condicion_devolucion" name="condicion_devolucion" class="form-select" required>
              <option value="">Seleccionar</option>
              <option value="BUENA">Buena</option>
              <option value="REGULAR">Regular</option>
              <option value="DANADA">Dañada (pasa a mantenimiento)</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="obs_devolucion" class="form-label">Observaciones</label>
            <textarea id="obs_devolucion" name="observaciones" class="form-control" rows="3" maxlength="200"></textarea>
          </div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Registrar</button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="<?= es_admin() ? 'col-lg-8' : 'col-12' ?>">
    <div class="card">
      <div class="card-header">Últimas devoluciones</div>
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead><tr><th>Equipo</th><th>Solicitante</th><th>Fecha</th><th>Condición</th><th>Recibió</th></tr></thead>
          <tbody>
          <?php foreach ($ultimas as $d): ?>
            <tr>
              <td><strong><?= e($d['num_inventario']) ?></strong> <?= e($d['descripcion']) ?></td>
              <td><?= e($d['solicitante']) ?></td>
              <td><?= fecha_mx($d['fecha_devolucion_real']) ?></td>
              <td><?= $d['condicion_devolucion'] === 'DANADA' ? '<span class="badge text-bg-warning">Dañada</span>' : e($CONDICIONES[$d['condicion_devolucion']]) ?></td>
              <td><?= e($d['recibio']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$ultimas): ?><tr><td colspan="5" class="text-center text-body-secondary py-3">Sin devoluciones.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php pie();
