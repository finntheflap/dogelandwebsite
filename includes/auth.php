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
