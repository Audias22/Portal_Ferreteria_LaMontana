<?php
declare(strict_types=1);
require_once __DIR__ . '/error_handler.php';
install_error_handling(__DIR__ . '/../logs/php_errors.log', 'Ferretería La Montaña');
require_once __DIR__ . '/../config.php';
date_default_timezone_set(PORTAL_TIMEZONE);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PORTALSESSID');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => PORTAL_BASE . '/']);
    session_start();
}

const ROLES = ['admin' => 'Administrador', 'empleado' => 'Ventas', 'bodega' => 'Bodega'];

/**
 * IP bloqueadas desde SecureWatch. La lista se consulta al SIEM como máximo cada 30 segundos
 * y se guarda en logs/siem_bloqueos.json. Si el SIEM no responde se usa la última lista conocida.
 */
function siem_blocked_ips(): array
{
    $cache = __DIR__ . '/../logs/siem_bloqueos.json';
    $data = is_file($cache) ? (json_decode((string)@file_get_contents($cache), true) ?: []) : [];
    if (time() - (int)($data['fetched'] ?? 0) < 30 || !function_exists('curl_init')) return (array)($data['ips'] ?? []);
    $data['fetched'] = time();   // aunque falle, no se reintenta en cada petición
    $ch = curl_init(str_replace('events.php', 'blocklist.php', SIEM_ENDPOINT) . '?source=' . rawurlencode(SIEM_SOURCE));
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['X-API-Key: ' . SIEM_API_KEY],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 1, CURLOPT_TIMEOUT => 2,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = $code === 200 ? json_decode((string)$resp, true) : null;
    if (is_array($json['ips'] ?? null)) $data['ips'] = array_values(array_filter($json['ips'], 'is_string'));
    @file_put_contents($cache, json_encode($data), LOCK_EX);
    return (array)($data['ips'] ?? []);
}

/** Corta la petición si la IP del visitante fue bloqueada en SecureWatch. */
function siem_block_gate(): void
{
    if (PHP_SAPI === 'cli') return;
    $ip = client_ip();
    if (!in_array($ip, siem_blocked_ips(), true)) return;
    // Se avisa al SIEM como máximo una vez cada 5 minutos por IP, para no inundarlo.
    $mark = __DIR__ . '/../logs/bloqueo_' . preg_replace('/[^0-9a-f.:]/i', '_', $ip) . '.txt';
    if (!is_file($mark) || time() - filemtime($mark) > 300) {
        @touch($mark);
        siem('BLOCKED_ACCESS', 'Acceso rechazado: IP bloqueada por SecureWatch', 'DENIED',
            ['method' => $_SERVER['REQUEST_METHOD'] ?? 'GET', 'endpoint' => mb_substr($_SERVER['REQUEST_URI'] ?? '', 0, 500)]);
    }
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Acceso bloqueado</title></head>'
        . '<body style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;margin:80px auto;padding:0 16px;color:#222">'
        . '<h1 style="font-size:22px">Acceso bloqueado</h1><p>El acceso desde su dirección (' . e($ip) . ') fue bloqueado por seguridad.</p>'
        . '<p>Si cree que es un error, comuníquese con el administrador del sistema.</p></body></html>';
    exit;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('mysql:host=' . PORTAL_DB_HOST . ';port=' . PORTAL_DB_PORT . ';dbname=' . PORTAL_DB_NAME . ';charset=utf8mb4',
        PORTAL_DB_USER, PORTAL_DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    return $pdo;
}

function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string { return PORTAL_BASE . '/' . ltrim($path, '/'); }
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
function user(): ?array { return $_SESSION['portal_user'] ?? null; }
function money($n): string { return 'Q ' . number_format((float)$n, 2); }
/** Clase CSS según la categoría (para el color del recuadro del producto). */
function cat_class(string $cat): string
{
    $c = strtolower(strtr($cat, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u']));
    return preg_replace('/[^a-z]/', '', $c) ?: 'otros';
}
function redirect(string $path): never { header('Location: ' . url($path)); exit; }

function csrf(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Solicitud inválida. Recargue la página.');
    }
}

/** Mensajes de confirmación que se muestran una sola vez después de redirigir. */
function flash(string $type, string $text): void { $_SESSION['flash'][] = [$type, $text]; }
function flashes(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as [$type, $text]) $out .= '<div class="msg ' . e($type) . '">' . e($text) . '</div>';
    unset($_SESSION['flash']);
    return $out;
}

/** Paginación sencilla: devuelve [página actual, total de páginas, offset]. */
function paginate(int $total, int $perPage): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($pages, max(1, (int)($_GET['p'] ?? 1)));
    return [$page, $pages, ($page - 1) * $perPage];
}
function pager(int $page, int $pages): string
{
    if ($pages <= 1) return '';
    $q = $_GET;
    $html = '<nav class="pager" aria-label="Páginas">';
    for ($i = 1; $i <= $pages; $i++) {
        $q['p'] = $i;
        $html .= $i === $page
            ? '<span class="current">' . $i . '</span>'
            : '<a href="?' . e(http_build_query($q)) . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}

/**
 * Envía un evento a SecureWatch. Nunca detiene el portal: si el SIEM no responde,
 * el evento se guarda en logs/siem_pendientes.log para no perder la evidencia.
 */
function siem(string $type, string $description, string $result = 'INFO', array $extra = []): void
{
    $u = user();
    $meta = ['user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200)];
    if (isset($extra['metadata']) && is_array($extra['metadata'])) { $meta = array_merge($meta, $extra['metadata']); unset($extra['metadata']); }
    $event = array_merge([
        'event_id'    => 'portal-' . date('YmdHis') . '-' . bin2hex(random_bytes(6)),
        'source'      => SIEM_SOURCE,
        'event_type'  => $type,
        'username'    => $u['usuario'] ?? 'anonymous',
        'ip'          => client_ip(),
        'result'      => $result,
        'description' => mb_substr($description, 0, 250),
        'timestamp'   => date(DATE_ATOM),
        'metadata'    => $meta,
    ], $extra);
    $json = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ok = false;
    if (function_exists('curl_init')) {
        $ch = curl_init(SIEM_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-API-Key: ' . SIEM_API_KEY],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => 2,
        ]);
        $resp = curl_exec($ch);
        $ok = $resp !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);
    }
    if (!$ok) {
        @file_put_contents(__DIR__ . '/../logs/siem_pendientes.log', $json . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

/** Registra la solicitud HTTP con los datos que envió el usuario (para detectar XSS, SQLi, traversal, escaneos). */
function siem_request(string $description, string $requestData = ''): void
{
    siem('HTTP_REQUEST', $description, 'INFO', [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'endpoint' => mb_substr($_SERVER['REQUEST_URI'] ?? '', 0, 500),
        'request_data' => mb_substr($requestData, 0, 4000),
    ]);
}

function require_login(): array
{
    $u = user();
    if (!$u) redirect('login.php');
    // Cierre de sesión por inactividad (30 minutos).
    if (time() - (int)($_SESSION['last_activity'] ?? time()) > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('info', 'La sesión se cerró por inactividad.');
        redirect('login.php');
    }
    $_SESSION['last_activity'] = time();
    return $u;
}

/** Exige uno de los roles indicados; si no, registra ACCESS_DENIED y muestra 403. */
function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['rol'], $roles, true)) {
        siem(str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') ? 'ACCESS_DENIED_ADMIN' : 'ACCESS_DENIED',
            'Acceso denegado a ' . ($_SERVER['REQUEST_URI'] ?? '') . ' (rol ' . $u['rol'] . ')', 'DENIED',
            ['method' => $_SERVER['REQUEST_METHOD'] ?? 'GET', 'endpoint' => mb_substr($_SERVER['REQUEST_URI'] ?? '', 0, 500)]);
        http_response_code(403);
        $title = 'Acceso denegado';
        require __DIR__ . '/header.php';
        echo '<div class="box"><h2>Acceso denegado</h2><p>Su usuario no tiene permiso para esta sección. El intento quedó registrado.</p><p><a href="' . url('inicio.php') . '">Volver al panel</a></p></div>';
        require __DIR__ . '/footer.php';
        exit;
    }
    return $u;
}
function has_role(string ...$roles): bool { $u = user(); return $u && in_array($u['rol'], $roles, true); }

/** Política de contraseñas del portal. Devuelve un mensaje de error o null si es válida. */
function password_problem(string $new): ?string
{
    if (strlen($new) < 10) return 'La contraseña debe tener al menos 10 caracteres.';
    if (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) return 'La contraseña debe combinar letras y números.';
    return null;
}

/** Correo saliente del laboratorio: no hay servidor de correo, se guarda en logs/correo_saliente.log. */
function send_mail_lab(string $to, string $subject, string $body): void
{
    $msg = ['fecha' => date('Y-m-d H:i:s'), 'para' => $to, 'asunto' => $subject, 'cuerpo' => $body];
    @file_put_contents(__DIR__ . '/../logs/correo_saliente.log', json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

siem_block_gate();
