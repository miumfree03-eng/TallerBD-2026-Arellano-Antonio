<?php
require_once __DIR__ . '/../app/layout.php';
requiere_sesion();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requiere_admin();
    verificar_csrf();
    try {
        if (($_POST['accion'] ?? '') === 'guardar') {
            $id = (int) ($_POST['id_solicitante'] ?? 0);
            $datos = [trim($_POST['num_institucional']), trim($_POST['nombre']), trim($_POST['apellidos']),
                      (int) $_POST['id_departamento']];
            if ($id === 0) {
                consulta('INSERT INTO solicitante (num_institucional, nombre, apellidos, id_departamento)
                          VALUES (?, ?, ?, ?)', $datos);
                avisar('exito', 'Solicitante registrado.');
            } else {
                consulta('UPDATE solicitante SET num_institucional = ?, nombre = ?, apellidos = ?, id_departamento = ?
                          WHERE id_solicitante = ?', [...$datos, $id]);
                avisar('exito', 'Solicitante actualizado.');
            }
        } elseif (($_POST['accion'] ?? '') === 'eliminar') {
            // Si ya pidió equipo, ON DELETE RESTRICT lo impide para no perder su historial
            consulta('DELETE FROM solicitante WHERE id_solicitante = ?', [(int) $_POST['id_solicitante']]);
            avisar('exito', 'Solicitante eliminado.');
        } elseif (($_POST['accion'] ?? '') === 'desactivar') {
            consulta('UPDATE solicitante SET activo = NOT activo WHERE id_solicitante = ?', [(int) $_POST['id_solicitante']]);
            avisar('exito', 'Estado del solicitante actualizado.');
        }
    } catch (PDOException $ex) {
        $codigo = $ex->errorInfo[1] ?? 0;
        avisar('error', [
            1062 => 'Ya existe un solicitante con ese número institucional.',
            1451 => 'Este solicitante tiene préstamos registrados y no se puede borrar; puedes desactivarlo.',
        ][$codigo] ?? 'No se pudo guardar: ' . $ex->getMessage());
    }
    redirigir('solicitantes.php');
}

$departamentos = consulta('SELECT id_departamento, nombre FROM departamento ORDER BY nombre')->fetchAll();
$editar = isset($_GET['editar'])
    ? consulta('SELECT * FROM solicitante WHERE id_solicitante = ?', [(int) $_GET['editar']])->fetch()
    : null;
// JOIN con departamento y subconsulta para contar préstamos activos de cada persona
$solicitantes = consulta(
    'SELECT s.id_solicitante, s.num_institucional, CONCAT(s.nombre, " ", s.apellidos) AS nombre, s.activo,
            d.nombre AS departamento,
            (SELECT COUNT(*) FROM prestamo p
              WHERE p.id_solicitante = s.id_solicitante AND p.fecha_devolucion_real IS NULL) AS activos
     FROM solicitante s JOIN departamento d ON d.id_departamento = s.id_departamento
     ORDER BY s.apellidos, s.nombre')->fetchAll();

encabezado('Solicitantes');
?>
<div class="row g-4">
  <?php if (es_admin()): ?>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><?= $editar ? 'Editar solicitante' : 'Nuevo solicitante' ?></div>
      <div class="card-body">
        <form method="post" class="needs-validation" novalidate>
          <?= campo_csrf() ?>
          <input type="hidden" name="accion" value="guardar">
          <input type="hidden" name="id_solicitante" value="<?= (int) ($editar['id_solicitante'] ?? 0) ?>">
          <div class="mb-3">
            <label for="num_institucional" class="form-label">No. de empleado o control</label>
            <input id="num_institucional" name="num_institucional" class="form-control" required pattern="[A-Za-z0-9]{4,20}"
                   value="<?= e($editar['num_institucional'] ?? '') ?>">
            <div class="invalid-feedback">Solo letras y números (4 a 20)</div>
          </div>
          <div class="mb-3">
            <label for="nombre" class="form-label">Nombre(s)</label>
            <input id="nombre" name="nombre" class="form-control" required value="<?= e($editar['nombre'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label for="apellidos" class="form-label">Apellidos</label>
            <input id="apellidos" name="apellidos" class="form-control" required value="<?= e($editar['apellidos'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label for="id_departamento" class="form-label">Departamento</label>
            <select id="id_departamento" name="id_departamento" class="form-select" required>
              <option value="">Seleccionar</option>
              <?php foreach ($departamentos as $d): ?>
                <option value="<?= $d['id_departamento'] ?>" <?= ($editar['id_departamento'] ?? 0) == $d['id_departamento'] ? 'selected' : '' ?>><?= e($d['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar</button>
          <?php if ($editar): ?><a class="btn btn-outline-secondary" href="solicitantes.php">Cancelar</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="<?= es_admin() ? 'col-lg-8' : 'col-12' ?>">
    <div class="card">
      <div class="card-header">Registrados</div>
      <div class="card-body pb-0">
        <input type="search" class="form-control mb-3" placeholder="Buscar..." data-filtro="#tabla-solicitantes">
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0" id="tabla-solicitantes">
          <thead><tr><th>Número</th><th>Nombre</th><th>Departamento</th><th class="text-center">Activos</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($solicitantes as $s): ?>
            <tr data-fila>
              <td><?= e($s['num_institucional']) ?></td>
              <td><?= e($s['nombre']) ?><?= $s['activo'] ? '' : ' <span class="badge text-bg-secondary">Inactivo</span>' ?></td>
              <td><?= e($s['departamento']) ?></td>
              <td class="text-center"><?= (int) $s['activos'] ?></td>
              <td class="text-nowrap tabla-acciones">
                <a class="btn btn-sm btn-outline-secondary" href="consultas.php?buscar_por=solicitante&id=<?= $s['id_solicitante'] ?>#historial" title="Historial"><i class="bi bi-clock-history"></i></a>
                <?php if (es_admin()): ?>
                  <a class="btn btn-sm btn-outline-primary" href="solicitantes.php?editar=<?= $s['id_solicitante'] ?>" title="Editar"><i class="bi bi-pencil"></i></a>
                  <form method="post"><?= campo_csrf() ?><input type="hidden" name="accion" value="desactivar"><input type="hidden" name="id_solicitante" value="<?= $s['id_solicitante'] ?>">
                    <button class="btn btn-sm btn-outline-warning" type="submit" title="<?= $s['activo'] ? 'Desactivar' : 'Activar' ?>"><i class="bi <?= $s['activo'] ? 'bi-person-dash' : 'bi-person-check' ?>"></i></button></form>
                  <form method="post" onsubmit="return confirm('¿Eliminar a <?= e($s['nombre']) ?>?');"><?= campo_csrf() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id_solicitante" value="<?= $s['id_solicitante'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Eliminar"><i class="bi bi-trash"></i></button></form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$solicitantes): ?><tr><td colspan="5" class="text-center text-body-secondary py-3">Sin solicitantes.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php pie();
