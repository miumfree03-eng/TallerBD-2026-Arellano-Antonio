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
<h1>Solicitantes</h1>
<p class="subtitulo">Personas de la institución que pueden pedir equipo prestado.</p>

<div class="dos-columnas">
  <?php if (es_admin()): ?>
  <section class="tarjeta">
    <h2><?= $editar ? 'Editar solicitante' : 'Registrar solicitante' ?></h2>
    <form method="post" data-validar novalidate>
      <?= campo_csrf() ?>
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="id_solicitante" value="<?= (int) ($editar['id_solicitante'] ?? 0) ?>">
      <div class="aviso aviso-formulario" hidden></div>
      <div class="campo">
        <label for="num_institucional">Número institucional o de empleado</label>
        <input id="num_institucional" name="num_institucional" type="text" required pattern="[A-Za-z0-9]{4,20}"
               data-mensaje="Solo letras y números, de 4 a 20 caracteres." value="<?= e($editar['num_institucional'] ?? '') ?>">
      </div>
      <div class="fila-campos">
        <div class="campo"><label for="nombre">Nombre(s)</label><input id="nombre" name="nombre" type="text" required value="<?= e($editar['nombre'] ?? '') ?>"></div>
        <div class="campo"><label for="apellidos">Apellidos</label><input id="apellidos" name="apellidos" type="text" required value="<?= e($editar['apellidos'] ?? '') ?>"></div>
      </div>
      <div class="campo">
        <label for="id_departamento">Departamento o área</label>
        <select id="id_departamento" name="id_departamento" required>
          <option value="">Selecciona…</option>
          <?php foreach ($departamentos as $d): ?>
            <option value="<?= $d['id_departamento'] ?>" <?= ($editar['id_departamento'] ?? 0) == $d['id_departamento'] ? 'selected' : '' ?>><?= e($d['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="botones">
        <button class="boton" type="submit">Guardar</button>
        <?php if ($editar): ?><a class="boton secundario" href="solicitantes.php">Cancelar</a><?php endif; ?>
      </div>
    </form>
  </section>
  <?php endif; ?>

  <section class="tarjeta">
    <h2>Solicitantes registrados</h2>
    <div class="barra-tabla">
      <input type="search" placeholder="Buscar por nombre, número o departamento…" data-filtro="#tabla-solicitantes" aria-label="Buscar solicitantes">
    </div>
    <div class="tabla-scroll">
      <table id="tabla-solicitantes">
        <thead><tr><th>Número</th><th>Nombre</th><th>Departamento</th><th>Préstamos activos</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($solicitantes as $s): ?>
          <tr>
            <td><?= e($s['num_institucional']) ?></td>
            <td><?= e($s['nombre']) ?><?= $s['activo'] ? '' : ' <span class="estado baja">Inactivo</span>' ?></td>
            <td><?= e($s['departamento']) ?></td>
            <td><?= (int) $s['activos'] ?></td>
            <td class="botones">
              <a class="boton chico secundario" href="consultas.php?buscar_por=solicitante&id=<?= $s['id_solicitante'] ?>#historial">Historial</a>
              <?php if (es_admin()): ?>
                <a class="boton chico secundario" href="solicitantes.php?editar=<?= $s['id_solicitante'] ?>">Editar</a>
                <form method="post"><?= campo_csrf() ?><input type="hidden" name="accion" value="desactivar"><input type="hidden" name="id_solicitante" value="<?= $s['id_solicitante'] ?>"><button class="boton chico secundario" type="submit"><?= $s['activo'] ? 'Desactivar' : 'Activar' ?></button></form>
                <form method="post" onsubmit="return confirm('¿Eliminar este solicitante?');"><?= campo_csrf() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id_solicitante" value="<?= $s['id_solicitante'] ?>"><button class="boton chico peligro" type="submit">Eliminar</button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
          <tr class="sin-resultados-fila" <?= $solicitantes ? 'hidden' : '' ?>><td colspan="5" class="sin-resultados">No hay solicitantes que coincidan.</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php pie();
