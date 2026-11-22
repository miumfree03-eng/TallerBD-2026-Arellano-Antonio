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
<h1>Devoluciones</h1>
<p class="subtitulo">Registra que un equipo regresó y en qué condición.</p>

<div class="dos-columnas">
  <?php if (es_admin()): ?>
  <section class="tarjeta">
    <h2>Registrar devolución</h2>
    <form method="post" data-validar novalidate>
      <?= campo_csrf() ?>
      <div class="aviso aviso-formulario" hidden></div>
      <div class="campo">
        <label for="id_prestamo">Préstamo activo</label>
        <select id="id_prestamo" name="id_prestamo" required>
          <option value="">Selecciona el préstamo…</option>
          <?php foreach ($activos as $p): ?>
            <option value="<?= $p['id_prestamo'] ?>"><?= e($p['num_inventario'] . ' · ' . $p['descripcion'] . ' — ' . $p['solicitante'] . ($p['vencido'] ? ' (vencido)' : '')) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="condicion_devolucion">Condición en que regresó</label>
        <select id="condicion_devolucion" name="condicion_devolucion" required>
          <option value="">Selecciona…</option>
          <option value="BUENA">Buena: vuelve a estar disponible</option>
          <option value="REGULAR">Regular: disponible, con observaciones</option>
          <option value="DANADA">Dañada: se envía a mantenimiento</option>
        </select>
      </div>
      <div class="campo">
        <label for="obs_devolucion">Observaciones</label>
        <textarea id="obs_devolucion" name="observaciones" rows="3" maxlength="200" placeholder="Ej.: regresó sin cable HDMI"></textarea>
      </div>
      <div class="botones"><button class="boton" type="submit">Registrar devolución</button></div>
    </form>
  </section>
  <?php endif; ?>

  <section class="tarjeta">
    <h2>Últimas devoluciones</h2>
    <div class="tabla-scroll">
      <table>
        <thead><tr><th>Equipo</th><th>Solicitante</th><th>Devuelto</th><th>Condición</th><th>Recibió</th></tr></thead>
        <tbody>
        <?php foreach ($ultimas as $d): ?>
          <tr>
            <td><?= e($d['num_inventario'] . ' · ' . $d['descripcion']) ?></td>
            <td><?= e($d['solicitante']) ?></td>
            <td><?= fecha_mx($d['fecha_devolucion_real']) ?></td>
            <td><?= $d['condicion_devolucion'] === 'DANADA' ? '<span class="estado mantenimiento">Dañada</span>' : e($CONDICIONES[$d['condicion_devolucion']]) ?></td>
            <td><?= e($d['recibio']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$ultimas): ?><tr><td colspan="5" class="sin-resultados">Todavía no hay devoluciones.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php pie();
