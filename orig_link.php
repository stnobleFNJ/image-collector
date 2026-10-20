<?php
// (cafe24 등 NAS 에 접근 못 하는 서버) 원본 다운로드: 서명된 링크로 사내 원본 서버에 넘김
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';
require __DIR__ . '/inc/pickzip.php';

$member = require_member_page();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    http_response_code(400);
    exit('잘못된 요청입니다.');
}
if (empty($_origServer) || empty($_origSecret)) {
    zip_fail('사내 원본 서버가 설정되어 있지 않습니다.');
}

$bs = int_list(isset($_POST['bs']) ? $_POST['bs'] : '');
$token = orig_token($member['mem_id'], $bs);
header('Location: ' . rtrim($_origServer, '/') . '/orig.php?t=' . rawurlencode($token));
