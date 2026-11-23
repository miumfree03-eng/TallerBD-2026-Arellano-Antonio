// Validacion de formularios (patron de Bootstrap), filtros de tablas y fecha minima

document.addEventListener("DOMContentLoaded", function () {

  // Validacion de Bootstrap: si un campo no es valido se marca en rojo y no se envia
  document.querySelectorAll("form.needs-validation").forEach(function (form) {
    form.addEventListener("submit", function (event) {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add("was-validated");
    });
  });

  // La fecha de devolucion no puede ser antes de hoy
  const hoy = new Date().toISOString().slice(0, 10);
  document.querySelectorAll('input[type="date"].min-hoy').forEach(function (campo) {
    campo.min = hoy;
  });

  // Filtro de tablas: <input data-filtro="#tabla"> y <select data-filtro-estado="#tabla">
  document.querySelectorAll("[data-filtro], [data-filtro-estado]").forEach(function (control) {
    control.addEventListener("input", function () {
      const tabla = document.querySelector(control.dataset.filtro || control.dataset.filtroEstado);
      const texto = (document.querySelector('[data-filtro="#' + tabla.id + '"]') || {}).value || "";
      const estado = (document.querySelector('[data-filtro-estado="#' + tabla.id + '"]') || {}).value || "";
      tabla.querySelectorAll("tbody tr[data-fila]").forEach(function (fila) {
        const coincide = fila.textContent.toLowerCase().includes(texto.toLowerCase());
        fila.hidden = !(coincide && (!estado || fila.dataset.estado === estado));
      });
    });
  });

  // Abrir la pestana indicada en la URL (ej. consultas.php#vencidos)
  if (location.hash) {
    const boton = document.querySelector('[data-bs-target="' + location.hash + '"]');
    if (boton) bootstrap.Tab.getOrCreateInstance(boton).show();
  }
});
