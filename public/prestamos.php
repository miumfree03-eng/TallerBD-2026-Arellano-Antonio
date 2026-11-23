<?php
require_once __DIR__ . '/../app/layout.php';
$u = requiere_sesion();

// ------------------------------------------------------------------ registrar préstamo (TRANSACCIÓN)
// Situación 1 (préstamo duplicado) y Situación 2 (registro incompleto):
//   1. SELECT ... FOR UPDATE bloquea la fila del equipo; si otro encargado intenta prestarlo al mismo
//      tiempo, su transacción espera y después ve que ya no está DISPONIBLE.
//   2. INSERT del préstamo + UPDATE del estado del equipo: se confirman juntos (COMMIT) o no se
//      guarda nada (ROLLBACK). Además, el índice UNIQUE de equipo_activo es una segunda barrera.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requiere_admin();
    verificar_csrf();
    $id_equipo = (int) $_POST['id_equipo'];
    $prevista = $_POST['fecha_devolucion_prevista'] ?? '';
    $pdo = db();
    try {
        if ($prevista < date('Y-m-d')) {
            throw new RuntimeException('La devolución prevista no puede ser anterior a hoy.');
        }
        $pdo->beginTransaction();
        $estado = consulta('SELECT estado FROM equipo WHERE id_equipo = ? FOR UPDATE', [$id_equipo])->fetchColumn();
        if ($estado !== 'DISPONIBLE') {
            throw new RuntimeException($estado === 'MANTENIMIENTO'
                ? 'El equipo está en mantenimiento y no se puede prestar.'      // Situación 3
                : 'El equipo ya no está disponible (otro encargado pudo haberlo prestado).');
        }
        consulta('INSERT INTO prestamo (id_equipo, id_solicitante, id_usuario_entrega, fecha_devolucion_prevista, observaciones)
                  VALUES (?, ?, ?, ?, ?)',
                 [$id_equipo, (int) $_POST['id_solicitante'], $u['id_usuario'], $prevista, trim($_POST['observaciones'] ?? '') ?: null]);
        consulta("UPDATE equipo SET estado = 'PRESTADO' WHERE id_equipo = ?", [$id_equipo]);
        $pdo->commit();
        avisar('exito', 'Préstamo registrado.');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();       // no queda ni el préstamo ni el cambio de estado a medias
        }
        $msg = $ex instanceof RuntimeException ? $ex->getMessage()
             : (($ex->errorInfo[1] ?? 0) === 1062 ? 'Ese equipo ya tiene un préstamo activo.' : 'No se pudo registrar: ' . $ex->getMessage());
        avisar('error', $msg);
    }
    redirigir('prestamos.php');
}

// ------------------------------------------------------------------ datos para la vista
$disponibles = consulta("SELECT id_equipo, num_inventario, descripcion FROM equipo
                         WHERE estado = 'DISPONIBLE' ORDER BY num_inventario")->fetchAll();
$solicitantes = consulta('SELECT id_solicitante, num_institucional, CONCAT(nombre, " ", apellidos) AS nombre
                          FROM solicitante WHERE activo = TRUE ORDER BY apellidos, nombre')->fetchAll();
$activos = consulta(
    'SELECT p.id_prestamo, e.num_inventario, e.descripcion, CONCAT(s.nombre, " ", s.apellidos) AS solicitante,
            p.fecha_prestamo, p.fecha_devolucion_prevista, p.fecha_devolucion_prevista < CURRENT_DATE AS vencido,
            uu.usuario AS entrego
     FROM prestamo p
     JOIN equipo e      ON e.id_equipo = p.id_equipo
     JOIN solicitante s ON s.id_solicitante = p.id_solicitante
     JOIN usuario uu    ON uu.id_usuario = p.id_usuario_entrega
     WHERE p.fecha_devolucion_real IS NULL
     ORDER BY p.fecha_devolucion_prevista')->fetchAll();

encabezado('Préstamos');
?>
<div class="row g-4">
  <?php if (es_admin()): ?>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Registrar préstamo</div>
      <div class="card-body">
        <form method="post" class="needs-validation" novalidate>
          <?= campo_csrf() ?>
          <div class="mb-3">
            <label for="id_equipo" class="form-label">Equipo</label>
            <select id="id_equipo" name="id_equipo" class="form-select" required>
              <option value="">Seleccionar</option>
              <?php foreach ($disponibles as $eq): ?>
                <option value="<?= $eq['id_equipo'] ?>"><?= e($eq['num_inventario'] . ' - ' . $eq['descripcion']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Solo se listan equipos disponibles.</div>
          </div>
          <div class="mb-3">
            <label for="id_solicitante" class="form-label">Solicitante</label>
            <select id="id_solicitante" name="id_solicitante" class="form-select" required>
              <option value="">Seleccionar</option>
              <?php foreach ($solicitantes as $s): ?>
                <option value="<?= $s['id_solicitante'] ?>"><?= e($s['num_institucional'] . ' - ' . $s['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="fecha_devolucion_prevista" class="form-label">Fecha de devolución</label>
            <input id="fecha_devolucion_prevista" name="fecha_devolucion_prevista" type="date" class="form-control min-hoy" required>
            <div class="invalid-feedback">Elige una fecha de hoy en adelante</div>
          </div>
          <div class="mb-3">
            <label for="observaciones" class="form-label">Observaciones</label>
            <textarea id="observaciones" name="observaciones" class="form-control" rows="2" maxlength="255"></textarea>
          </div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Registrar</button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="<?= es_admin() ? 'col-lg-8' : 'col-12' ?>">
    <div class="card">
      <div class="card-header">Préstamos activos</div>
      <div class="card-body pb-0">
        <input type="search" class="form-control mb-3" placeholder="Buscar..." data-filtro="#tabla-activos">
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0" id="tabla-activos">
          <thead><tr><th>Equipo</th><th>Solicitante</th><th>Prestado</th><th>Devolución</th><th>Entregó</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($activos as $p): ?>
            <tr data-fila>
              <td><strong><?= e($p['num_inventario']) ?></strong> <?= e($p['descripcion']) ?></td>
              <td><?= e($p['solicitante']) ?></td>
              <td><?= fecha_mx($p['fecha_prestamo']) ?></td>
              <td><?= fecha_mx($p['fecha_devolucion_prevista']) ?></td>
              <td><?= e($p['entrego']) ?></td>
              <td><?= $p['vencido'] ? '<span class="badge text-bg-danger">Vencido</span>' : '<span class="badge text-bg-primary">Activo</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$activos): ?><tr><td colspan="6" class="text-center text-body-secondary py-3">No hay préstamos activos.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php pie();
