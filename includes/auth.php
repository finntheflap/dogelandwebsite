<?php
/* ============================================================================
   AUTHME — password hash + verify (SHA256 / BCRYPT)
   ========================================================================== */

/* --- AuthMe hashing --- */
function authme_make_hash($password){
  global $CFG;
  if(strtoupper($CFG['authme_hash'])==='BCRYPT') return password_hash($password, PASSWORD_BCRYPT);
  $salt = bin2hex(random_bytes(8)); // 16 hex
  return '$SHA$'.$salt.'$'.hash('sha256', hash('sha256',$password).$salt);
}
function authme_verify($password,$stored){
  if(!$stored) return false;
  if(strpos($stored,'$SHA$')===0){
    $p=explode('$',$stored); if(count($p)<4) return false;
    return hash_equals($p[3], hash('sha256', hash('sha256',$password).$p[2]));
  }
  if(preg_match('/^\$2[aby]\$/',$stored)) return password_verify($password,$stored);
  return hash_equals((string)$stored, hash('sha256',$password)); // fallback
}

/* ============================================================================
   REMEMBER-ME — selector/validator cookie pattern (Symfony/Laravel style).
   Cookie: dgl_rmb=<selector>:<validator>
   DB: web_remember_tokens(selector, username, sha256(validator), expires, ...)
   ============================================================================ */

const RMB_COOKIE   = 'dgl_rmb';
const RMB_LIFETIME = 60 * 86400; // 60 ngày

/* Cấp token mới, lưu DB, set cookie. Gọi sau khi login thành công + user tick "ghi nhớ". */
function remember_issue($username){
  try {
    $selector  = bin2hex(random_bytes(8));   // 16 hex chars
    $validator = bin2hex(random_bytes(32));  // 64 hex chars
    $hash      = hash('sha256', $validator);
    $now       = ms();
    $exp       = $now + RMB_LIFETIME * 1000;
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua        = mb_substr_safe($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    db()->prepare("INSERT INTO web_remember_tokens(selector,username,validator_hash,expires,created,ip,ua)
                   VALUES(?,?,?,?,?,?,?)")
        ->execute([$selector,$username,$hash,$exp,$now,$ip,$ua]);

    setcookie(RMB_COOKIE, $selector.':'.$validator, [
      'expires'  => time() + RMB_LIFETIME,
      'path'     => '/',
      'secure'   => !empty($_SERVER['HTTPS']),
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
  } catch (Exception $e) { /* ignore — không chặn login flow */ }
}

/* Kiểm tra cookie. Trả username nếu hợp lệ, '' nếu không. Tự dọn token hết hạn. */
function remember_check(){
  if (empty($_COOKIE[RMB_COOKIE])) return '';
  $raw = (string)$_COOKIE[RMB_COOKIE];
  if (!str_contains($raw, ':')) return '';
  [$selector, $validator] = explode(':', $raw, 2);
  if (strlen($selector) !== 16 || strlen($validator) !== 64) return '';

  try {
    $now = ms();
    // Lazy cleanup tokens hết hạn (1% requests để tránh GC mỗi lần)
    if (random_int(1, 100) === 1) {
      db()->prepare("DELETE FROM web_remember_tokens WHERE expires<?")->execute([$now]);
    }
    $st = db()->prepare("SELECT username,validator_hash,expires FROM web_remember_tokens WHERE selector=? LIMIT 1");
    $st->execute([$selector]);
    $row = $st->fetch();
    if (!$row) { remember_clear_cookie(); return ''; }
    if ((int)$row['expires'] < $now) {
      db()->prepare("DELETE FROM web_remember_tokens WHERE selector=?")->execute([$selector]);
      remember_clear_cookie();
      return '';
    }
    if (!hash_equals((string)$row['validator_hash'], hash('sha256', $validator))) {
      // Mismatch → có thể là replay/steal → revoke hết token của user để safe
      db()->prepare("DELETE FROM web_remember_tokens WHERE username=?")->execute([$row['username']]);
      remember_clear_cookie();
      return '';
    }
    // Hợp lệ → update last_used (light touch)
    db()->prepare("UPDATE web_remember_tokens SET last_used=? WHERE selector=?")->execute([$now,$selector]);
    return (string)$row['username'];
  } catch (Exception $e) { return ''; }
}

/* Xoá cookie + DB row hiện tại (logout / manual revoke). */
function remember_revoke(){
  if (!empty($_COOKIE[RMB_COOKIE])) {
    $raw = (string)$_COOKIE[RMB_COOKIE];
    if (str_contains($raw, ':')) {
      [$selector] = explode(':', $raw, 2);
      try { db()->prepare("DELETE FROM web_remember_tokens WHERE selector=?")->execute([$selector]); } catch (Exception $e) {}
    }
  }
  remember_clear_cookie();
}

function remember_clear_cookie(){
  setcookie(RMB_COOKIE, '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  unset($_COOKIE[RMB_COOKIE]);
}
