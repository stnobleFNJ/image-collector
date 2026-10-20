<?php
// 공통: 세션, DB, 인증, 출력 도우미
if (!defined('APP_INIT')) { http_response_code(403); exit; }

require __DIR__ . '/../db_config.php';

const APP_TITLE   = 'FNJ 이미지 Check';
const SIGNUP_MAIL = 'stnoble@fnj.or.kr';  // 회원가입 요청 수신 메일

header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Asia/Seoul');

session_name('FNJIMG');
session_set_cookie_params(array(
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
));
session_start();

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function db() {
    static $conn = null;
    if ($conn === null) {
        global $_dbHost, $_dbUser, $_dbPasswd, $_dbName;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn = new mysqli($_dbHost, $_dbUser, $_dbPasswd, $_dbName);
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

// prepared statement 실행 후 결과 배열 반환 (SELECT 가 아니면 affected_rows)
function q($sql, $types = '', $params = array()) {
    $st = db()->prepare($sql);
    if ($types !== '') {
        $st->bind_param($types, ...$params);
    }
    $st->execute();
    $res = $st->get_result();
    if ($res === false) {
        $n = $st->affected_rows;
        $st->close();
        return $n;
    }
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_ok() {
    $t = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN']
        : (isset($_POST['csrf']) ? $_POST['csrf'] : '');
    return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
}

function current_member() {
    if (empty($_SESSION['mem_id'])) {
        return null;
    }
    $rows = q("SELECT mem_id, login_id, name FROM NAS_MEMBER WHERE mem_id = ? AND db_state = 'Y'",
        'i', array($_SESSION['mem_id']));
    return $rows ? $rows[0] : null;
}

// 페이지용: 회원이 아니면 경고 후 로그인 페이지로
function require_member_page() {
    $m = current_member();
    if ($m) {
        return $m;
    }
    $_SESSION = array();
    echo '<!DOCTYPE html><meta charset="utf-8"><script>'
        . 'alert("불법적인 접속입니다.");location.replace("login.php");'
        . '</script>';
    exit;
}

// API 용: 회원이 아니면 401 JSON
function require_member_api() {
    $m = current_member();
    if (!$m) {
        json_out(array('ok' => false, 'error' => '불법적인 접속입니다.', 'login' => true), 401);
    }
    return $m;
}

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 현재 적재분(db_state='Y') 사업 목록
function business_list() {
    return q("SELECT b.bs_id, b.bs_name, b.client, b.bs_years, COUNT(i.img_id) AS cnt
              FROM NAS_BS b
              JOIN NAS_IMG i ON i.bs_id = b.bs_id AND i.db_state = 'Y' AND i.thumb IS NOT NULL
              WHERE b.db_state = 'Y'
              GROUP BY b.bs_id
              ORDER BY cnt DESC, b.bs_name");
}

// 정수 id 목록 정리
function int_list($v, $max = 500) {
    if (!is_array($v)) {
        $v = $v === null || $v === '' ? array() : explode(',', (string)$v);
    }
    $out = array();
    foreach ($v as $x) {
        $x = (int)$x;
        if ($x > 0) {
            $out[$x] = $x;
        }
        if (count($out) >= $max) {
            break;
        }
    }
    return array_values($out);
}
