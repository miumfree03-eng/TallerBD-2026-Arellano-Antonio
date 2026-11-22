<?php
require_once __DIR__ . '/../app/layout.php';
requiere_sesion();

// Conteo de equipos por estado (SELECT con GROUP BY)
$por_estado = consulta('SELECT estado, COUNT(*) AS total FROM equipo GROUP BY estado')->fetchAll(PDO::FETCH_KEY_PAIR);
$vencidos = consulta('SELECT COUNT(*) FROM prestamo
                      WHERE fecha_devolucion_real IS NULL AND fecha_devolucion_prevista < CURRENT_DATE')->fetchColumn();

// Préstamos activos más próximos a vencer (JOIN de 4 tablas)
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

encabezado('Inicio');
?>
<h1>Panel de control</h1>
<p class="subtitulo">Situación actual de los equipos del departamento.</p>

<section class="resumen">
  <div class="indicador verde"><div class="numero"><?= (int) ($por_estado['DISPONIBLE'] ?? 0) ?></div><div class="etiqueta">Disponibles</div></div>
  <div class="indicador"><div class="numero"><?= (int) ($por_estado['PRESTADO'] ?? 0) ?></div><div class="etiqueta">Prestados</div></div>
  <div class="indicador ambar"><div class="numero"><?= (int) ($por_estado['MANTENIMIENTO'] ?? 0) ?></div><div class="etiqueta">En mantenimiento</div></div>
  <div class="indicador rojo"><div class="numero"><?= (int) $vencidos ?></div><div class="etiqueta">Préstamos vencidos</div></div>
</section>

<section class="tarjeta">
  <h2>¿Qué quieres hacer?</h2>
  <div class="accesos">
    <a class="acceso" href="prestamos.php"><strong>Registrar préstamo</strong>Entregar un equipo disponible a un solicitante.</a>
    <a class="acceso" href="devoluciones.php"><strong>Registrar devolución</strong>Recibir un equipo y anotar su condición.</a>
    <a class="acceso" href="equipos.php"><strong>Administrar equipos</strong>Alta, edición, mantenimiento y baja.</a>
    <a class="acceso" href="solicitantes.php"><strong>Solicitantes</strong>Personas que pueden pedir equipo.</a>
    <a class="acceso" href="consultas.php"><strong>Consultas</strong>Disponibles, activos, vencidos e historial.</a>
    <a class="acceso" href="consultas.php#vencidos"><strong>Ver vencidos</strong>Préstamos cuya fecha de devolución ya pasó.</a>
  </div>
</section>

<section class="tarjeta">
  <h2>Préstamos que vencen pronto</h2>
  <div class="tabla-scroll">
    <table>
      <thead><tr><th>Equipo</th><th>Solicitante</th><th>Departamento</th><th>Devolución prevista</th><th>Estado</th></tr></thead>
      <tbody>
      <?php foreach ($proximos as $p): ?>
        <tr>
          <td><?= e($p['num_inventario'] . ' · ' . $p['descripcion']) ?></td>
          <td><?= e($p['solicitante']) ?></td>
          <td><?= e($p['departamento']) ?></td>
          <td><?= fecha_mx($p['fecha_devolucion_prevista']) ?></td>
          <td><?= $p['vencido'] ? '<span class="estado vencido">Vencido</span>' : '<span class="estado prestado">Activo</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$proximos): ?><tr><td colspan="5" class="sin-resultados">No hay préstamos activos.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php pie();
