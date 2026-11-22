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
<h1>Equipos</h1>
<p class="subtitulo">Cada equipo físico se registra por separado con su número de inventario.</p>

<div class="dos-columnas">
  <?php if (es_admin()): ?>
  <section class="tarjeta">
    <h2><?= $editar ? 'Editar equipo ' . e($editar['num_inventario']) : 'Registrar equipo' ?></h2>
    <form method="post" data-validar novalidate>
      <?= campo_csrf() ?>
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="id_equipo" value="<?= (int) ($editar['id_equipo'] ?? 0) ?>">
      <div class="aviso aviso-formulario" hidden></div>
      <div class="campo">
        <label for="num_inventario">Número de inventario</label>
        <input id="num_inventario" name="num_inventario" type="text" required pattern="INV-[0-9]{3,6}"
               data-mensaje="Usa el formato INV-001." value="<?= e($editar['num_inventario'] ?? '') ?>" placeholder="INV-001">
      </div>
      <div class="campo">
        <label for="descripcion">Descripción</label>
        <input id="descripcion" name="descripcion" type="text" required minlength="3" value="<?= e($editar['descripcion'] ?? '') ?>">
      </div>
      <div class="campo">
        <label for="id_categoria">Categoría</label>
        <select id="id_categoria" name="id_categoria" required>
          <option value="">Selecciona…</option>
          <?php foreach ($categorias as $c): ?>
            <option value="<?= $c['id_categoria'] ?>" <?= ($editar['id_categoria'] ?? 0) == $c['id_categoria'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fila-campos">
        <div class="campo"><label for="marca">Marca</label><input id="marca" name="marca" type="text" value="<?= e($editar['marca'] ?? '') ?>"></div>
        <div class="campo"><label for="modelo">Modelo</label><input id="modelo" name="modelo" type="text" value="<?= e($editar['modelo'] ?? '') ?>"></div>
      </div>
      <div class="fila-campos">
        <div class="campo">
          <label for="condicion">Condición</label>
          <select id="condicion" name="condicion" required>
            <?php foreach ($CONDICIONES as $valor => $texto): ?>
              <option value="<?= $valor ?>" <?= ($editar['condicion'] ?? 'BUENA') === $valor ? 'selected' : '' ?>><?= $texto ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="estado">Estado</label>
          <select id="estado" name="estado" required>
            <?php foreach ($ESTADOS as $valor => $texto):
                if ($valor === 'PRESTADO' && ($editar['estado'] ?? '') !== 'PRESTADO') continue; ?>
              <option value="<?= $valor ?>" <?= ($editar['estado'] ?? 'DISPONIBLE') === $valor ? 'selected' : '' ?>><?= $texto ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="campo">
        <label for="motivo">Motivo (si se envía a mantenimiento)</label>
        <input id="motivo" name="motivo" type="text" maxlength="255" placeholder="Ej.: no enciende la pantalla">
      </div>
      <div class="botones">
        <button class="boton" type="submit">Guardar</button>
        <?php if ($editar): ?><a class="boton secundario" href="equipos.php">Cancelar</a><?php endif; ?>
      </div>
    </form>
  </section>
  <?php endif; ?>

  <section class="tarjeta">
    <h2>Equipos registrados</h2>
    <div class="barra-tabla">
      <input type="search" placeholder="Buscar por inventario, descripción o marca…" data-filtro="#tabla-equipos" aria-label="Buscar equipos">
      <select data-filtro-estado="#tabla-equipos" aria-label="Filtrar por estado">
        <option value="">Todos los estados</option>
        <?php foreach ($ESTADOS as $valor => $texto): ?><option value="<?= $valor ?>"><?= $texto ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="tabla-scroll">
      <table id="tabla-equipos">
        <thead><tr><th>Inventario</th><th>Descripción</th><th>Categoría</th><th>Condición</th><th>Estado</th><?php if (es_admin()): ?><th>Acciones</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($equipos as $eq): ?>
          <tr data-estado="<?= e($eq['estado']) ?>">
            <td><?= e($eq['num_inventario']) ?></td>
            <td><?= e($eq['descripcion']) ?><br><small><?= e(trim($eq['marca'] . ' ' . $eq['modelo'])) ?></small></td>
            <td><?= e($eq['categoria']) ?></td>
            <td><?= e($CONDICIONES[$eq['condicion']]) ?></td>
            <td><span class="estado <?= strtolower($eq['estado']) ?>"><?= e($ESTADOS[$eq['estado']]) ?></span></td>
            <?php if (es_admin()): ?>
            <td class="botones">
              <a class="boton chico secundario" href="equipos.php?editar=<?= $eq['id_equipo'] ?>">Editar</a>
              <form method="post" onsubmit="return confirm('¿Eliminar este equipo?');">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id_equipo" value="<?= $eq['id_equipo'] ?>">
                <button class="boton chico peligro" type="submit">Eliminar</button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
          <tr class="sin-resultados-fila" <?= $equipos ? 'hidden' : '' ?>><td colspan="6" class="sin-resultados">No hay equipos que coincidan.</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php pie();
