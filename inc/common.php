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
    $rows = q("SELECT mem_id, login_id, name, is_admin FROM NAS_MEMBER WHERE mem_id = ? AND db_state = 'Y'",
        'i', array($_SESSION['mem_id']));
    return $rows ? $rows[0] : null;
}

// 총괄 어드민 전용 페이지
function require_admin_page() {
    $m = require_member_page();
    if (empty($m['is_admin'])) {
        echo '<!DOCTYPE html><meta charset="utf-8"><script>'
            . 'alert("접근 권한이 없습니다.");location.replace("check.php");'
            . '</script>';
        exit;
    }
    return $m;
}

// 공통 <head>
function render_head($title) {
    ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?></title>
<link rel="icon" href="assets/fnj_logo_icon.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
<link rel="stylesheet" href="assets/app.css?v=<?= filemtime(__DIR__ . '/../assets/app.css') ?>">
    <?php
}

// 상단 바: 로고 + 탭 / (썸네일 크기) / 사용자
function render_topbar($member, $active, $withSize = true) {
    $tabs = array('check' => array('check.php', '이미지 확인'));
    if (!empty($member['is_admin'])) {
        $tabs['admin'] = array('admin.php', '계정 관리');
    }
    ?>
<header class="topbar">
    <div class="topbar-left">
        <a class="brand" href="check.php">
            <img src="assets/fnj_logo_icon.png" alt="FNJ">
            <span>FNJ 이미지 <b>Check</b></span>
        </a>
        <?php if (count($tabs) > 1): ?>
        <nav class="tabs">
            <?php foreach ($tabs as $key => $t): ?>
                <a href="<?= $t[0] ?>" class="tab<?= $key === $active ? ' on' : '' ?>"><?= h($t[1]) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
    </div>

    <?php if ($withSize): ?>
    <label class="size-ctrl" title="썸네일 크기">
        <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><rect x="5" y="5" width="14" height="14" rx="3" fill="none" stroke="currentColor" stroke-width="2"/></svg>
        <input type="range" id="sizeRange" min="80" max="320" step="10" value="160" aria-label="썸네일 크기">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="4" fill="none" stroke="currentColor" stroke-width="2"/></svg>
        <output id="sizeVal">160px</output>
    </label>
    <?php else: ?>
    <span></span>
    <?php endif; ?>

    <div class="user">
        <span class="user-name"><?= h($member['name'] !== '' ? $member['name'] : $member['login_id']) ?>님<?php if (!empty($member['is_admin'])): ?> <em class="role">관리자</em><?php endif; ?></span>
        <a class="btn btn-ghost btn-sm" href="logout.php">로그아웃</a>
    </div>
</header>
    <?php
}

// 다운로드 버튼
//   NAS 접근 가능: 원본(직접) + 썸네일
//   사내 원본 서버 설정됨: 원본(사내 서버로 연결, 새 탭) + 썸네일
//   그 외: 썸네일만
function render_download_buttons($size = '') {
    global $_origServer, $_origSecret;
    $icon = '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M12 4v11m0 0-4.5-4.5M12 15l4.5-4.5M5 19h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    $thumbBtn = '<button type="submit" name="type" value="thumb" class="btn btn-ghost dl-btn ' . $size . '" disabled title="미리보기용 썸네일(최대 320px)을 ZIP 으로 받습니다">썸네일 다운로드</button>';
    if (nas_available()) {
        ?>
<button type="submit" name="type" value="orig" class="btn btn-primary dl-btn <?= $size ?>" disabled title="NAS 원본 파일을 ZIP 으로 받습니다"><?= $icon ?> 원본 다운로드</button>
<?= $thumbBtn ?>
        <?php
    } elseif (!empty($_origServer) && !empty($_origSecret)) {
        ?>
<button type="submit" name="type" value="orig" formaction="orig_link.php" formtarget="_blank" class="btn btn-primary dl-btn <?= $size ?>" disabled title="사내 원본 서버에서 NAS 원본을 ZIP 으로 받습니다 (사내망 또는 VPN 접속 필요)"><?= $icon ?> 원본 다운로드 <small class="btn-note">사내망</small></button>
<?= $thumbBtn ?>
        <?php
    } else {
        ?>
<button type="submit" name="type" value="thumb" class="btn btn-primary dl-btn <?= $size ?>" disabled title="원본은 사내망 서버에서만 받을 수 있습니다"><?= $icon ?> 썸네일 다운로드</button>
        <?php
    }
}

// 공통 스크립트 (core.js) 와 전역 설정
function render_core_js() {
    ?>
<script>window.APP = { csrf: <?= json_encode(csrf_token()) ?> };</script>
<script src="assets/core.js?v=<?= filemtime(__DIR__ . '/../assets/core.js') ?>"></script>
    <?php
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

// DB 의 NAS 경로를 이 서버에서 읽을 수 있는 경로로 변환
function nas_path($folder, $file) {
    global $_nasFrom, $_nasTo;
    $p = rtrim($folder, '\\/') . '\\' . $file;
    $rest = null;
    if (isset($_nasFrom) && strpos($p, $_nasFrom) === 0) {
        $rest = substr($p, strlen($_nasFrom));
    }
    if (DIRECTORY_SEPARATOR === '/') {
        return $rest === null ? str_replace('\\', '/', $p) : $_nasTo . str_replace('\\', '/', $rest);
    }
    if ($rest === null) {
        return $p;
    }
    // Windows(SMB) 에서는 Synology 가 Windows 금지 문자를 사설 영역 문자로 바꿔 보여줌 (예: ? → U+F025)
    $parts = explode('\\', $rest);
    foreach ($parts as &$part) {
        $part = smb_name($part);
    }
    return $_nasTo . implode('\\', $parts);
}

// Samba catia/fruit 매핑: " * : < > ? | → U+F020~F027, 끝 공백/마침표 → U+F028/F029
function smb_name($name) {
    static $map = null;
    if ($map === null) {
        $map = array();
        foreach (array('"' => 0xF020, '*' => 0xF021, ':' => 0xF022, '<' => 0xF023,
                       '>' => 0xF024, '?' => 0xF025, '|' => 0xF027) as $ch => $cp) {
            $map[$ch] = mb_convert_encoding(pack('N', $cp), 'UTF-8', 'UTF-32BE');
        }
    }
    $name = strtr($name, $map);
    $last = substr($name, -1);
    if ($last === ' ' || $last === '.') {
        $name = substr($name, 0, -1) . mb_convert_encoding(pack('N', $last === ' ' ? 0xF028 : 0xF029), 'UTF-8', 'UTF-32BE');
    }
    return $name;
}

// 이 서버에서 NAS 원본에 접근 가능한지 (세션에 10분 캐시: 접근 불가 시 UNC 대기가 길 수 있음)
function nas_available() {
    global $_nasTo, $_nasProbe;
    if (empty($_nasTo) || empty($_nasProbe)) {
        return false;
    }
    if (isset($_SESSION['nas_ok']) && time() - $_SESSION['nas_ok'][1] < 600) {
        return $_SESSION['nas_ok'][0];
    }
    $probe = $_nasTo . $_nasProbe;
    $ok = @is_dir(DIRECTORY_SEPARATOR === '/' ? str_replace('\\', '/', $probe) : $probe);
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['nas_ok'] = array($ok, time());
    }
    return $ok;
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
