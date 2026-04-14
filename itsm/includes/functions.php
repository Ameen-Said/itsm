<?php
// IT Manager Pro — Helper Functions v3 (fully fixed)

// ── i18n ────────────────────────────────────────────────
$_ITSM_LANG = [];
function loadLang(string $lang = 'en'): void {
    global $_ITSM_LANG;
    $f = APP_ROOT . '/lang/' . ($lang === 'ar' ? 'ar' : 'en') . '.php';
    $_ITSM_LANG = file_exists($f) ? require $f : [];
}
function __(string $key, array $rep = []): string {
    global $_ITSM_LANG;
    $s = $_ITSM_LANG[$key] ?? $key;
    foreach ($rep as $i => $v) $s = str_replace('{' . $i . '}', $v, $s);
    return $s;
}

// ── Output helpers ───────────────────────────────────────
function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function j(mixed $v): string { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); }

// ── HTTP helpers ─────────────────────────────────────────
function redirect(string $url): never { header('Location: ' . $url); exit; }
function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function flash(string $type, string $msg): void {
    if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['flash'][] = ['type' => $type, 'message' => $msg];
}
function getFlash(): array { $m = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $m; }

// ── Settings (DB-backed, refreshable cache) ─────────────
function getSetting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows = Database::getInstance()->query("SELECT `key`,`value` FROM `settings`")->fetchAll();
            $cache = array_column($rows, 'value', 'key');
        } catch (Throwable $e) { $cache = []; }
    }
    return (string)($cache[$key] ?? $default);
}
function setSetting(string $key, string $value): void {
    Database::getInstance()
        ->prepare("INSERT INTO settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=?")
        ->execute([$key, $value, $value]);
}

// ── Pagination ───────────────────────────────────────────
function paginate(PDO $db, string $sql, array $params, int $page = 1, int $per = PAGINATION_LIMIT): array {
    $countSql = preg_replace('/\s+ORDER\s+BY\s+.+$/is', '', $sql);
    $countSql = "SELECT COUNT(*) FROM ($countSql) AS _c";
    try { $cs = $db->prepare($countSql); $cs->execute($params); $total = (int)$cs->fetchColumn(); }
    catch (Throwable $e) { $total = 0; }
    $pages  = max(1, (int)ceil($total / $per));
    $page   = max(1, min($page, $pages));
    $offset = ($page - 1) * $per;
    $stmt   = $db->prepare($sql . " LIMIT $per OFFSET $offset");
    $stmt->execute($params);
    return ['records'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'per_page'=>$per,'total_pages'=>$pages,'has_prev'=>$page>1,'has_next'=>$page<$pages];
}
function renderPagination(array $p, string $base): string {
    if ($p['total_pages'] <= 1) return '';
    $sep = str_contains($base, '?') ? '&' : '?';
    $h = '<nav><ul class="pagination pagination-sm mb-0">';
    $h .= '<li class="page-item'.(!$p['has_prev']?' disabled':'').'"><a class="page-link" href="'.h($base.$sep.'page='.($p['page']-1)).'"><i class="bi bi-chevron-left"></i></a></li>';
    for ($i = max(1,$p['page']-2); $i <= min($p['total_pages'],$p['page']+2); $i++) {
        $a = $i===$p['page']?' active':'';
        $h .= "<li class=\"page-item$a\"><a class=\"page-link\" href=\"".h($base.$sep.'page='.$i)."\">$i</a></li>";
    }
    $h .= '<li class="page-item'.(!$p['has_next']?' disabled':'').'"><a class="page-link" href="'.h($base.$sep.'page='.($p['page']+1)).'"><i class="bi bi-chevron-right"></i></a></li>';
    $h .= '</ul></nav>';
    return $h;
}

// ── Encryption ───────────────────────────────────────────
function encryptData(string $data, string $key): array {
    $iv = random_bytes(16);
    $enc = openssl_encrypt($data, 'AES-256-CBC', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
    return ['cipher' => base64_encode($enc), 'iv' => base64_encode($iv)];
}
function decryptData(string $cipher, string $iv, string $key): string|false {
    return openssl_decrypt(base64_decode($cipher), 'AES-256-CBC', hash('sha256', $key, true), OPENSSL_RAW_DATA, base64_decode($iv));
}
function getVaultKey(array $user, ?string $master = null): string {
    $base = !empty($master) ? $master : ENCRYPTION_KEY;
    $salt = $user['vault_salt'] ?? '';
    return hash_hmac('sha256', $user['id'] . $salt, $base);
}

// ── File upload ──────────────────────────────────────────
function handleUpload(array $file, string $sub = '', array $exts = []): array {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK)
        return ['success'=>false,'message'=>'Upload error: '.$file['error']];
    if ($file['size'] > MAX_FILE_SIZE) return ['success'=>false,'message'=>'File too large (max 20MB).'];
    $ext = strtolower(pathinfo($file['name']??'', PATHINFO_EXTENSION));
    $allowed = $exts ?: ALLOWED_EXTENSIONS;
    if (!in_array($ext, $allowed, true)) return ['success'=>false,'message'=>"File type .$ext not allowed."];
    $dir = UPLOAD_DIR . ($sub ? trim($sub,'/').'/' : '');
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) return ['success'=>false,'message'=>'Cannot create upload directory.'];
    $name = uniqid('',true).'_'.bin2hex(random_bytes(4)).'.'.$ext;
    if (!move_uploaded_file($file['tmp_name'], $dir.$name)) return ['success'=>false,'message'=>'Failed to save file.'];
    return ['success'=>true,'filename'=>$name,'original'=>$file['name'],'size'=>$file['size'],'mime'=>mime_content_type($dir.$name)?:($file['type']??'')];
}

// ── ID generators ────────────────────────────────────────
function generateAssetCode(): string { return 'AST-'.strtoupper(substr(uniqid(),7)).'-'.rand(100,999); }
function generateBarcode(): string { return str_pad((string)rand(100000000,999999999),12,'0',STR_PAD_LEFT); }
function generatePONumber(): string { return 'PO-'.date('Ym').'-'.str_pad((string)rand(1,9999),4,'0',STR_PAD_LEFT); }

// ── Date helpers ─────────────────────────────────────────
function sanitizeDate(?string $v): ?string {
    if (!$v) return null;
    $ts = strtotime($v);
    return $ts !== false ? date('Y-m-d', $ts) : null;
}
function daysUntil(?string $d): ?int {
    if (!$d || $d==='0000-00-00') return null;
    $ts = strtotime($d);
    return $ts !== false ? (int)ceil(($ts - time()) / 86400) : null;
}
function expiryClass(?string $d): string {
    $days = daysUntil($d);
    if ($days === null) return '';
    if ($days < 0)   return 'text-danger fw-bold';
    if ($days <= 30) return 'text-warning fw-bold';
    if ($days <= 90) return 'text-info';
    return '';
}
function formatDate(?string $d, ?string $fmt = null): string {
    if (!$d || in_array($d,['0000-00-00','0000-00-00 00:00:00'])) return '—';
    $ts = strtotime($d);
    return $ts !== false ? date($fmt ?? (getSetting('date_format','d M Y')), $ts) : '—';
}
function formatMoney(float $amt, ?string $cur = null): string {
    $c = $cur ?? getSetting('currency', 'USD');
    return $c . ' ' . number_format($amt, 2);
}
function formatBytes(int $b): string {
    if ($b>=1073741824) return number_format($b/1073741824,2).' GB';
    if ($b>=1048576)    return number_format($b/1048576,2).' MB';
    if ($b>=1024)       return number_format($b/1024,2).' KB';
    return $b.' B';
}

// ── Status badges ────────────────────────────────────────
function assetStatusBadge(string $s): string {
    $m=['available'=>'success','assigned'=>'primary','maintenance'=>'warning','retired'=>'secondary'];
    return '<span class="badge bg-'.($m[$s]??'secondary').'">'.h(ucfirst($s)).'</span>';
}
function userStatusBadge(string $s): string {
    $m=['active'=>'success','inactive'=>'warning','suspended'=>'danger'];
    return '<span class="badge bg-'.($m[$s]??'secondary').'">'.h(ucfirst($s)).'</span>';
}
function licenseStatusBadge(string $s): string {
    $m=['active'=>'success','expired'=>'danger','cancelled'=>'secondary'];
    return '<span class="badge bg-'.($m[$s]??'secondary').'">'.h(ucfirst($s)).'</span>';
}

// ── Global search ────────────────────────────────────────
function globalSearch(PDO $db, string $q, int $limit = 6): array {
    $like = '%'.$q.'%'; $r = [];
    $r['assets'] = $db->prepare("SELECT id, asset_code as code, name, status, 'asset' as type FROM assets WHERE name LIKE ? OR asset_code LIKE ? OR serial_number LIKE ? LIMIT $limit")->execute([$like,$like,$like]) ? [] : [];
    $s = $db->prepare("SELECT id, asset_code as code, name, status FROM assets WHERE name LIKE ? OR asset_code LIKE ? OR serial_number LIKE ? LIMIT $limit");
    $s->execute([$like,$like,$like]); $r['assets'] = $s->fetchAll();
    $s = $db->prepare("SELECT id, employee_id as code, full_name as name, status FROM users WHERE full_name LIKE ? OR email LIKE ? OR employee_id LIKE ? LIMIT $limit");
    $s->execute([$like,$like,$like]); $r['users'] = $s->fetchAll();
    $s = $db->prepare("SELECT id, '' as code, software_name as name, status FROM licenses WHERE software_name LIKE ? LIMIT $limit");
    $s->execute([$like]); $r['licenses'] = $s->fetchAll();
    $s = $db->prepare("SELECT id, '' as code, title as name, '' as status FROM documents WHERE title LIKE ? OR description LIKE ? LIMIT $limit");
    $s->execute([$like,$like]); $r['documents'] = $s->fetchAll();
    return $r;
}

// ── Sync asset status ─────────────────────────────────────
function syncAssetStatus(PDO $db, int $id): void {
    $a = $db->prepare("SELECT assigned_to, status FROM assets WHERE id=?");
    $a->execute([$id]); $a = $a->fetch();
    if (!$a || in_array($a['status'],['maintenance','retired'])) return;
    $correct = $a['assigned_to'] ? 'assigned' : 'available';
    if ($a['status'] !== $correct) $db->prepare("UPDATE assets SET status=? WHERE id=?")->execute([$correct,$id]);
}

// ── Create notification ───────────────────────────────────
function createNotification(PDO $db, ?int $uid, string $title, string $msg, string $type='info', string $link=''): void {
    try { $db->prepare("INSERT INTO notifications (user_id,title,message,type,link) VALUES (?,?,?,?,?)")->execute([$uid,$title,$msg,$type,$link]); }
    catch (Throwable $e) { error_log('[Notif] '.$e->getMessage()); }
}

// ── License seat count ────────────────────────────────────
function getLicenseSeatsUsed(PDO $db, int $licId): int {
    $s = $db->prepare("SELECT COUNT(*) FROM license_assignments WHERE license_id=?");
    $s->execute([$licId]); return (int)$s->fetchColumn();
}
