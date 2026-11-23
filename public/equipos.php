<?php
require_once __DIR__ . '/../app/layout.php';
$u = requiere_sesion();

// ------------------------------------------------------------------ operaciones (solo ADMIN)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requiere_admin();
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'guardar') {
            $id = (int) ($_POST['id_equipo'] ?? 0);
            $datos = [
                trim($_POST['num_inventario']), trim($_POST['descripcion']), (int) $_POST['id_categoria'],
                trim($_POST['marca']) ?: null, trim($_POST['modelo']) ?: null, $_POST['condicion'],
            ];
            if ($id === 0) {
                // INSERT: todo equipo nuevo entra como DISPONIBLE
                consulta('INSERT INTO equipo (num_inventario, descripcion, id_categoria, marca, modelo, condicion, estado)
                          VALUES (?, ?, ?, ?, ?, ?, ?)', [...$datos, $_POST['estado']]);
                avisar('exito', 'Equipo registrado.');
            } else {
                cambiar_estado_y_datos($id, $datos, $_POST['estado'], $u);
                avisar('exito', 'Equipo actualizado.');
            }
        } elseif ($accion === 'eliminar') {
            // DELETE: MariaDB lo impide (ON DELETE RESTRICT) si el equipo ya tiene préstamos o mantenimientos
            consulta('DELETE FROM equipo WHERE id_equipo = ?', [(int) $_POST['id_equipo']]);
            avisar('exito', 'Equipo eliminado.');
        }
    } catch (PDOException $ex) {
        $codigo = $ex->errorInfo[1] ?? 0;
        $mensajes = [
            1062 => 'Ya existe un equipo con ese número de inventario.',
            1451 => 'Este equipo tiene historial de préstamos o mantenimiento y no se puede borrar. Cámbialo a estado "Baja".',
        ];
        avisar('error', $mensajes[$codigo] ?? 'No se pudo guardar: ' . $ex->getMessage());
    }
    redirigir('equipos.php');
}

// UPDATE de datos y cambio de estado en una transacción: si pasa a MANTENIMIENTO se abre un registro
// en la tabla mantenimiento, y si sale de MANTENIMIENTO se cierra (Situación 3).
function cambiar_estado_y_datos(int $id, array $datos, string $nuevo, array $u): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $actual = consulta('SELECT estado FROM equipo WHERE id_equipo = ? FOR UPDATE', [$id])->fetchColumn();
        if ($actual === 'PRESTADO' && $nuevo !== 'PRESTADO') {
            throw new RuntimeException('El equipo está prestado: primero registra su devolución.');
        }
        if ($nuevo === 'PRESTADO' && $actual !== 'PRESTADO') {
            throw new RuntimeException('Un equipo solo pasa a "Prestado" desde la página de préstamos.');
        }
        consulta('UPDATE equipo SET num_inventario = ?, descripcion = ?, id_categoria = ?, marca = ?, modelo = ?,
                  condicion = ?, estado = ? WHERE id_equipo = ?', [...$datos, $nuevo, $id]);
        if ($nuevo === 'MANTENIMIENTO' && $actual !== 'MANTENIMIENTO') {
            consulta('INSERT INTO mantenimiento (id_equipo, motivo, id_usuario) VALUES (?, ?, ?)',
                     [$id, trim($_POST['motivo'] ?? '') ?: 'Sin motivo especificado', $u['id_usuario']]);
        }
        if ($actual === 'MANTENIMIENTO' && $nuevo !== 'MANTENIMIENTO') {
            consulta('UPDATE mantenimiento SET fecha_fin = NOW() WHERE id_equipo = ? AND fecha_fin IS NULL', [$id]);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        if ($ex instanceof RuntimeException) {
            avisar('error', $ex->getMessage());
            redirigir('equipos.php');
        }
        throw $ex;
    }
}

// ------------------------------------------------------------------ datos para la vista
$categorias = consulta('SELECT id_categoria, nombre FROM categoria ORDER BY nombre')->fetchAll();
$editar = isset($_GET['editar'])
    ? consulta('SELECT * FROM equipo WHERE id_equipo = ?', [(int) $_GET['editar']])->fetch()
    : null;
// SELECT con JOIN a categoria para mostrar el nombre en lugar del id
$equipos = consulta('SELECT e.id_equipo, e.num_inventario, e.descripcion, c.nombre AS categoria, e.marca, e.modelo,
                            e.condicion, e.estado
                     FROM equipo e JOIN categoria c ON c.id_categoria = e.id_categoria
                     ORDER BY e.num_inventario')->fetchAll();

encabezado('Equipos');
?>
<div class="row g-4">
  <?php if (es_admin()): ?>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><?= $editar ? 'Editar ' . e($editar['num_inventario']) : 'Nuevo equipo' ?></div>
      <div class="card-body">
        <form method="post" class="needs-validation" novalidate>
          <?= campo_csrf() ?>
          <input type="hidden" name="accion" value="guardar">
          <input type="hidden" name="id_equipo" value="<?= (int) ($editar['id_equipo'] ?? 0) ?>">
          <div class="mb-3">
            <label for="num_inventario" class="form-label">No. de inventario</label>
            <input id="num_inventario" name="num_inventario" class="form-control" required pattern="INV-[0-9]{3,6}"
                   value="<?= e($editar['num_inventario'] ?? '') ?>" placeholder="INV-001">
            <div class="invalid-feedback">Formato INV-001</div>
          </div>
          <div class="mb-3">
            <label for="descripcion" class="form-label">Descripción</label>
            <input id="descripcion" name="descripcion" class="form-control" required minlength="3" value="<?= e($editar['descripcion'] ?? '') ?>">
            <div class="invalid-feedback">Campo obligatorio</div>
          </div>
          <div class="mb-3">
            <label for="id_categoria" class="form-label">Categoría</label>
            <select id="id_categoria" name="id_categoria" class="form-select" required>
              <option value="">Seleccionar</option>
              <?php foreach ($categorias as $c): ?>
                <option value="<?= $c['id_categoria'] ?>" <?= ($editar['id_categoria'] ?? 0) == $c['id_categoria'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2 mb-3">
            <div class="col"><label for="marca" class="form-label">Marca</label><input id="marca" name="marca" class="form-control" value="<?= e($editar['marca'] ?? '') ?>"></div>
            <div class="col"><label for="modelo" class="form-label">Modelo</label><input id="modelo" name="modelo" class="form-control" value="<?= e($editar['modelo'] ?? '') ?>"></div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col">
              <label for="condicion" class="form-label">Condición</label>
              <select id="condicion" name="condicion" class="form-select" required>
                <?php foreach ($CONDICIONES as $valor => $texto): ?>
                  <option value="<?= $valor ?>" <?= ($editar['condicion'] ?? 'BUENA') === $valor ? 'selected' : '' ?>><?= $texto ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col">
              <label for="estado" class="form-label">Estado</label>
              <select id="estado" name="estado" class="form-select" required>
                <?php foreach ($ESTADOS as $valor => $texto):
                    if ($valor === 'PRESTADO' && ($editar['estado'] ?? '') !== 'PRESTADO') continue; ?>
                  <option value="<?= $valor ?>" <?= ($editar['estado'] ?? 'DISPONIBLE') === $valor ? 'selected' : '' ?>><?= $texto ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label for="motivo" class="form-label">Motivo de mantenimiento</label>
            <input id="motivo" name="motivo" class="form-control" maxlength="255" placeholder="Opcional">
          </div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar</button>
          <?php if ($editar): ?><a class="btn btn-outline-secondary" href="equipos.php">Cancelar</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="<?= es_admin() ? 'col-lg-8' : 'col-12' ?>">
    <div class="card">
      <div class="card-header">Inventario</div>
      <div class="card-body pb-0">
        <div class="row g-2 mb-3">
          <div class="col-sm-8"><input type="search" class="form-control" placeholder="Buscar..." data-filtro="#tabla-equipos"></div>
          <div class="col-sm-4">
            <select class="form-select" data-filtro-estado="#tabla-equipos">
              <option value="">Todos</option>
              <?php foreach ($ESTADOS as $valor => $texto): ?><option value="<?= $valor ?>"><?= $texto ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0" id="tabla-equipos">
          <thead><tr><th>Inventario</th><th>Descripción</th><th>Categoría</th><th>Condición</th><th>Estado</th><?php if (es_admin()): ?><th></th><?php endif; ?></tr></thead>
          <tbody>
          <?php foreach ($equipos as $eq): ?>
            <tr data-fila data-estado="<?= e($eq['estado']) ?>">
              <td><?= e($eq['num_inventario']) ?></td>
              <td><?= e($eq['descripcion']) ?><div class="small text-body-secondary"><?= e(trim($eq['marca'] . ' ' . $eq['modelo'])) ?></div></td>
              <td><?= e($eq['categoria']) ?></td>
              <td><?= e($CONDICIONES[$eq['condicion']]) ?></td>
              <td><?= badge_estado($eq['estado']) ?></td>
              <?php if (es_admin()): ?>
              <td class="text-nowrap tabla-acciones">
                <a class="btn btn-sm btn-outline-primary" href="equipos.php?editar=<?= $eq['id_equipo'] ?>" title="Editar"><i class="bi bi-pencil"></i></a>
                <form method="post" onsubmit="return confirm('¿Eliminar el equipo <?= e($eq['num_inventario']) ?>?');">
                  <?= campo_csrf() ?>
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id_equipo" value="<?= $eq['id_equipo'] ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit" title="Eliminar"><i class="bi bi-trash"></i></button>
                </form>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          <?php if (!$equipos): ?><tr><td colspan="6" class="text-center text-body-secondary py-3">Sin equipos registrados.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php pie();
