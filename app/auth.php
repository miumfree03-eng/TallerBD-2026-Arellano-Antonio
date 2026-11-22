<?php
// Sesión del encargado y control de acceso por rol (Situación 5 del documento).
// ADMIN: registra y modifica. CONSULTA: solo puede ver.

session_start();

function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

// Obliga a iniciar sesión antes de ver cualquier página interna
function requiere_sesion(): array
{
    $u = usuario_actual();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}

// Solo los ADMIN pueden ejecutar operaciones que modifican datos
function requiere_admin(): void
{
    $u = requiere_sesion();
    if ($u['rol'] !== 'ADMIN') {
        http_response_code(403);
        exit('Tu usuario es de solo consulta: no puede modificar información.');
    }
}

function es_admin(): bool
{
    return (usuario_actual()['rol'] ?? '') === 'ADMIN';
}

// Protección CSRF: cada formulario lleva un token que solo conoce la sesión
function token_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function verificar_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Solicitud no válida. Recarga la página e inténtalo de nuevo.');
    }
}
