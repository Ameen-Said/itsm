<?php
class Auth {
    private static ?Auth $instance = null;
    private PDO $db;
    private ?array $user = null;
    private array $permissions = [];

    public static function getInstance(): Auth {
        if (self::$instance === null) self::$instance = new Auth();
        return self::$instance;
    }

    private function __construct() {
        $this->db = Database::getInstance();
        $this->startSecureSession();
        if (!empty($_SESSION['user_id'])) {
            $this->loadUser((int)$_SESSION['user_id']);
        }
    }

    private function startSecureSession(): void {
        if (session_status() !== PHP_SESSION_NONE) return;
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        session_name('ITSM_SESS');
        session_start();

        if (!empty($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > SESSION_TIMEOUT) {
            $this->destroySession();
            return;
        }
        $_SESSION['last_activity'] = time();

        if (empty($_SESSION['regenerated_at']) || (time() - (int)$_SESSION['regenerated_at']) > 600) {
            session_regenerate_id(true);
            $_SESSION['regenerated_at'] = time();
        }
    }

    private function destroySession(): void {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        session_unset();
        session_destroy();
    }

    public function login(string $identifier, string $password): array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE (username=? OR email=?) LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->logAudit(null, 'login_failed', 'auth');
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }
        if ($user['status'] === 'suspended') return ['success' => false, 'message' => 'Account suspended.'];
        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            $min = ceil((strtotime($user['locked_until']) - time()) / 60);
            return ['success' => false, 'message' => "Account locked. Try again in {$min} min."];
        }
        if (!password_verify($password, $user['password_hash'])) {
            $attempts = (int)$user['login_attempts'] + 1;
            $lock = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
            $this->db->prepare("UPDATE users SET login_attempts=?, locked_until=? WHERE id=?")->execute([$attempts, $lock, $user['id']]);
            $this->logAudit(null, 'login_failed', 'auth');
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }
        if ($user['status'] !== 'active') return ['success' => false, 'message' => 'Account not active.'];

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['last_activity'] = time();
        $_SESSION['regenerated_at'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $this->db->prepare("UPDATE users SET last_login=NOW(), login_attempts=0, locked_until=NULL WHERE id=?")->execute([$user['id']]);
        $this->loadUser((int)$user['id']);
        $this->logAudit((int)$user['id'], 'login', 'auth', (int)$user['id']);
        return ['success' => true];
    }

    private function loadUser(int $userId): void {
        $stmt = $this->db->prepare(
            "SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id=r.id WHERE u.id=? AND u.status='active' LIMIT 1"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) { $this->destroySession(); $this->user = null; return; }
        $this->user = $user;
        $this->loadPermissions();
    }

    private function loadPermissions(): void {
        $stmt = $this->db->prepare(
            "SELECT p.module, p.action FROM permissions p JOIN role_permissions rp ON p.id=rp.permission_id WHERE rp.role_id=?"
        );
        $stmt->execute([$this->user['role_id']]);
        $this->permissions = [];
        foreach ($stmt->fetchAll() as $row) {
            $this->permissions[$row['module']][$row['action']] = true;
        }
    }

    public function logout(): void {
        if (!empty($_SESSION['user_id'])) $this->logAudit((int)$_SESSION['user_id'], 'logout', 'auth', (int)$_SESSION['user_id']);
        $this->destroySession();
        $this->user = null;
        $this->permissions = [];
    }

    public function isLoggedIn(): bool { return $this->user !== null; }
    public function getUser(): ?array  { return $this->user; }
    public function getUserId(): ?int  { return $this->user ? (int)$this->user['id'] : null; }
    public function isAdmin(): bool    { return $this->user && (int)$this->user['role_id'] === 1; }

    public function can(string $module, string $action = 'view'): bool {
        return isset($this->permissions[$module][$action]);
    }

    public function requireLogin(): void {
        if (!$this->isLoggedIn()) { header('Location: ' . APP_URL . '/login.php'); exit; }
    }

    public function requirePermission(string $module, string $action = 'view'): void {
        $this->requireLogin();
        if (!$this->can($module, $action)) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
            }
            http_response_code(403);
            include APP_ROOT . '/includes/403.php';
            exit;
        }
    }

    public function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
    public function verifyCsrfToken(?string $token): bool {
        if (empty($token) || empty($_SESSION['csrf_token'])) return false;
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    public function csrfField(): string {
        return '<input type="hidden" name="csrf_token" value="' . h($this->generateCsrfToken()) . '">';
    }
    public function requireCsrf(): void {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['csrf_token'] ?? null;
        if (!$this->verifyCsrfToken($token)) jsonResponse(['success' => false, 'message' => 'CSRF token invalid. Please refresh.'], 403);
    }

    public function getTheme(): string { return $this->user['theme'] ?? ($_SESSION['theme'] ?? 'light'); }
    public function getLang(): string  { return $this->user['language'] ?? ($_SESSION['lang'] ?? 'en'); }

    public function updateTheme(string $theme): void {
        $theme = in_array($theme, ['light','dark']) ? $theme : 'light';
        if (!$this->getUserId()) return;
        $this->db->prepare("UPDATE users SET theme=? WHERE id=?")->execute([$theme, $this->getUserId()]);
        if ($this->user) $this->user['theme'] = $theme;
        $_SESSION['theme'] = $theme;
    }
    public function updateLang(string $lang): void {
        $lang = in_array($lang, ['en','ar']) ? $lang : 'en';
        if (!$this->getUserId()) return;
        $this->db->prepare("UPDATE users SET language=? WHERE id=?")->execute([$lang, $this->getUserId()]);
        if ($this->user) $this->user['language'] = $lang;
        $_SESSION['lang'] = $lang;
    }

    public function logAudit(?int $userId, string $action, string $module, ?int $recordId = null, array $data = []): void {
        try {
            $this->db->prepare(
                "INSERT INTO audit_logs (user_id,action,module,record_id,new_values,ip_address,user_agent) VALUES (?,?,?,?,?,?,?)"
            )->execute([
                $userId, $action, $module, $recordId,
                $data ? json_encode($data) : null,
                $_SERVER['REMOTE_ADDR'] ?? 'cli',
                defined('CRON_MODE') ? 'cron' : substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ]);
        } catch (Throwable $e) { error_log('[Audit] ' . $e->getMessage()); }
    }
}
