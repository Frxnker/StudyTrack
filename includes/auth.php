<?php
/**
 * Autenticación y sesiones.
 *
 *  - Sesión segura (cookie HttpOnly + SameSite, modo estricto, caducidad por inactividad)
 *  - Login con password_verify() y regeneración del id de sesión
 *  - Protección de páginas privadas: require_login() / require_guest()
 */

declare(strict_types=1);

require_once BASE_PATH . '/models/user.php';

/**
 * Hash ficticio: si el email no existe, comprobamos la contraseña contra él
 * para que la respuesta tarde lo mismo y no se pueda averiguar qué emails
 * están registrados midiendo el tiempo.
 */
const DUMMY_PASSWORD_HASH = '$2y$12$sdFKsLqvQcHEzw1Ghk7jVuZKVRYvV3Pu6Q.glUu0wPheKSmiTyI2y';

/* =============================================================================
 * Sesión
 * ========================================================================== */

/**
 * Inicia la sesión con opciones seguras y aplica la caducidad por inactividad.
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');   // Rechaza ids de sesión inventados
    ini_set('session.use_only_cookies', '1');  // Nunca el id en la URL
    ini_set('session.gc_maxlifetime', (string) SESSION_TIMEOUT);

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,          // Hasta cerrar el navegador
        'path'     => '/',
        'secure'   => $isHttps,   // Solo por HTTPS cuando esté disponible
        'httponly' => true,       // Inaccesible desde JavaScript
        'samesite' => 'Lax',      // No se envía en peticiones POST desde otras webs
    ]);
    session_start();

    if (isset($_SESSION['user_id'])) {
        $inactive = time() - (int) ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT;
        $otherBrowser = !hash_equals((string) ($_SESSION['fingerprint'] ?? ''), session_fingerprint());

        if ($inactive || $otherBrowser) {
            logout_user();
            session_start();
            session_regenerate_id(true);
            if ($inactive) {
                flash('info', 'Tu sesión ha caducado por inactividad. Vuelve a iniciar sesión.');
            }
        }
    }

    $_SESSION['last_activity'] = time();
}

/**
 * Huella del navegador: si alguien roba la cookie y la usa desde otro
 * navegador, la sesión deja de ser válida.
 */
function session_fingerprint(): string
{
    return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

/* =============================================================================
 * Login / logout
 * ========================================================================== */

/**
 * Comprueba email y contraseña. Devuelve el usuario si son correctos o null.
 */
function attempt_login(string $email, string $password): ?array
{
    $user = user_find_by_email($email);

    if ($user === null) {
        password_verify($password, DUMMY_PASSWORD_HASH); // Mismo tiempo de respuesta
        return null;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return null;
    }

    // Si PHP cambia el algoritmo o el coste por defecto, actualizamos el hash
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        user_update_password((int) $user['id'], $password);
    }

    return $user;
}

/**
 * Guarda al usuario en la sesión.
 */
function login_user(array $user): void
{
    // Nuevo id de sesión tras el login: evita la fijación de sesión
    session_regenerate_id(true);

    $_SESSION['user_id']       = (int) $user['id'];
    $_SESSION['fingerprint']   = session_fingerprint();
    $_SESSION['last_activity'] = time();

    user_touch_last_access((int) $user['id']);
}

/**
 * Cierra la sesión por completo (datos, cookie y archivo de sesión).
 */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

/* =============================================================================
 * Usuario actual y protección de páginas
 * ========================================================================== */

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

/**
 * Datos del usuario de la sesión (se consultan una sola vez por petición).
 */
function current_user(bool $refresh = false): ?array
{
    static $user = null;

    if (!is_logged_in()) {
        return null;
    }
    if ($user === null || $refresh) {
        $user = user_find(current_user_id());
    }

    return $user;
}

/**
 * Para páginas privadas: si no hay sesión, redirige al login.
 */
function require_login(): void
{
    if (is_logged_in() && current_user() !== null) {
        return;
    }

    // Sesión de un usuario que ya no existe (p. ej. cuenta eliminada)
    if (is_logged_in()) {
        logout_user();
        start_secure_session();
    }

    // Recordamos la página pedida para volver a ella después del login
    if (!is_post()) {
        $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $_SESSION['intended'] = current_page() . '.php' . ($query !== '' ? '?' . $query : '');
        if (current_page() !== 'dashboard') {
            flash('warning', 'Inicia sesión para continuar.');
        }
    }

    redirect('login.php');
}

/**
 * Para login y registro: si ya hay sesión, no tiene sentido mostrarlos.
 */
function require_guest(): void
{
    if (is_logged_in() && current_user() !== null) {
        redirect('dashboard.php');
    }
}

/**
 * Página a la que volver tras el login. Solo se aceptan páginas internas
 * (nombre.php) para evitar redirecciones a webs externas.
 */
function intended_url(string $default = 'dashboard.php'): string
{
    $url = (string) ($_SESSION['intended'] ?? '');
    unset($_SESSION['intended']);

    $isInternal = preg_match('/^[a-z_]+\.php(\?[\w=&%.\-+]*)?$/i', $url) === 1;
    $isAuthPage = preg_match('/^(login|register|logout|index)\.php/i', $url) === 1;

    return $isInternal && !$isAuthPage ? $url : $default;
}
