/* =====================================================================
   Sistema de préstamos de equipo · Comportamiento de la interfaz
   - Menú para celular
   - Marcar la página activa en el menú
   - Filtros de búsqueda en tablas
   - Pestañas de la página de consultas
   - Validación de formularios antes de enviarlos
   Los formularios se envían a PHP; la validación de aquí solo ayuda
   al usuario, porque PHP y MariaDB vuelven a validar todo.
   ===================================================================== */

document.addEventListener("DOMContentLoaded", function () {
  activarMenuMovil();
  marcarPaginaActiva();
  activarFiltrosDeTablas();
  activarPestanas();
  activarValidaciones();
  ponerFechaMinimaHoy();
});

/* ---------- Menú hamburguesa ---------- */
function activarMenuMovil() {
  const boton = document.querySelector(".boton-menu");
  const navegacion = document.querySelector(".navegacion");
  if (!boton || !navegacion) return;
  boton.addEventListener("click", function () {
    const abierto = navegacion.classList.toggle("abierta");
    boton.setAttribute("aria-expanded", abierto);
  });
}

/* ---------- Página activa en el menú ---------- */
function marcarPaginaActiva() {
  const actual = location.pathname.split("/").pop() || "index.html";
  document.querySelectorAll(".menu a").forEach(function (enlace) {
    if (enlace.getAttribute("href") === actual) enlace.classList.add("activo");
  });
}

/* ---------- Filtros de tablas ----------
   <input data-filtro="#tabla">        busca texto en todas las columnas
   <select data-filtro-estado="#tabla"> filtra por el atributo data-estado de cada fila */
function activarFiltrosDeTablas() {
  document.querySelectorAll("[data-filtro], [data-filtro-estado]").forEach(function (control) {
    control.addEventListener("input", function () {
      const selector = control.dataset.filtro || control.dataset.filtroEstado;
      filtrarTabla(document.querySelector(selector));
    });
  });
}

function filtrarTabla(tabla) {
  if (!tabla) return;
  const id = "#" + tabla.id;
  const texto = (document.querySelector('[data-filtro="' + id + '"]') || {}).value || "";
  const estado = (document.querySelector('[data-filtro-estado="' + id + '"]') || {}).value || "";
  let visibles = 0;

  tabla.querySelectorAll("tbody tr:not(.sin-resultados-fila)").forEach(function (fila) {
    const coincideTexto = fila.textContent.toLowerCase().includes(texto.toLowerCase());
    const coincideEstado = !estado || fila.dataset.estado === estado;
    fila.hidden = !(coincideTexto && coincideEstado);
    if (!fila.hidden) visibles++;
  });

  const vacia = tabla.querySelector(".sin-resultados-fila");
  if (vacia) vacia.hidden = visibles > 0;
}

/* ---------- Pestañas ---------- */
function activarPestanas() {
  document.querySelectorAll(".pestana").forEach(function (pestana) {
    pestana.addEventListener("click", function () { mostrarPestana(pestana.dataset.panel); });
  });
  // Un enlace como consultas.html#vencidos abre directamente esa pestaña
  const desdeEnlace = location.hash.slice(1);
  if (desdeEnlace && document.getElementById(desdeEnlace)) mostrarPestana(desdeEnlace);
}

function mostrarPestana(idPanel) {
  document.querySelectorAll(".pestana").forEach(p => p.classList.toggle("activa", p.dataset.panel === idPanel));
  document.querySelectorAll(".panel-pestana").forEach(p => p.classList.toggle("activo", p.id === idPanel));
}

/* ---------- Validaciones ----------
   Usa la validación del navegador (required, pattern, min) y muestra
   un mensaje en español debajo de cada campo con error. */
function activarValidaciones() {
  document.querySelectorAll("form[data-validar]").forEach(function (formulario) {
    formulario.addEventListener("submit", function (evento) {
      limpiarErrores(formulario);
      let valido = true;

      formulario.querySelectorAll("input, select, textarea").forEach(function (campo) {
        if (!campo.checkValidity()) {
          valido = false;
          mostrarError(campo, mensajePara(campo));
        }
      });

      // Regla del negocio: la devolución prevista no puede ser antes de hoy
      // (si el navegador ya marcó el campo por el atributo min, solo se cambia el texto del mensaje)
      const prevista = formulario.querySelector('[name="fecha_devolucion_prevista"]');
      if (prevista && prevista.closest(".con-error")) prevista.closest(".campo").querySelector(".mensaje-error").remove();
      if (prevista && prevista.value && prevista.value < hoy()) {
        valido = false;
        mostrarError(prevista, "La devolución prevista no puede ser anterior a hoy.");
      }

      // Si hay errores no se envía; si todo está bien, el formulario llega a PHP, que valida otra vez
      if (!valido) {
        evento.preventDefault();
        const aviso = formulario.querySelector(".aviso-formulario");
        if (aviso) {
          aviso.className = "aviso aviso-formulario error";
          aviso.textContent = "Revisa los campos marcados en rojo.";
          aviso.hidden = false;
        }
      }
    });
  });
}

function mensajePara(campo) {
  if (campo.validity.valueMissing) return "Este campo es obligatorio.";
  if (campo.validity.patternMismatch) return campo.dataset.mensaje || "El formato no es válido.";
  if (campo.validity.tooShort) return "Escribe al menos " + campo.minLength + " caracteres.";
  if (campo.validity.rangeUnderflow) return "El valor es menor al permitido.";
  return "Revisa este campo.";
}

function mostrarError(campo, texto) {
  const contenedor = campo.closest(".campo");
  if (!contenedor) return;
  contenedor.classList.add("con-error");
  const mensaje = document.createElement("div");
  mensaje.className = "mensaje-error";
  mensaje.textContent = texto;
  contenedor.appendChild(mensaje);
}

function limpiarErrores(formulario) {
  formulario.querySelectorAll(".con-error").forEach(c => c.classList.remove("con-error"));
  formulario.querySelectorAll(".mensaje-error").forEach(m => m.remove());
}

/* ---------- Fechas ---------- */
function hoy() {
  const d = new Date();
  return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
}

function ponerFechaMinimaHoy() {
  document.querySelectorAll('input[type="date"][data-min-hoy]').forEach(c => c.min = hoy());
}
