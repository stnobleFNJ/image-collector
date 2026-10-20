<?php
// 내가 선택한 이미지 ZIP 다운로드 (사업 필터 동일 적용)
//   type=thumb : DB 썸네일
//   type=orig  : NAS 원본 (NAS 에 접근 가능한 서버에서만)
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';
require __DIR__ . '/inc/pickzip.php';

$member = require_member_page();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    http_response_code(400);
    exit('잘못된 요청입니다.');
}
$orig = isset($_POST['type']) && $_POST['type'] === 'orig';
if ($orig && !nas_available()) {
    zip_fail('이 서버에서는 NAS 원본에 접근할 수 없습니다. 썸네일 다운로드를 이용해 주세요.');
}
session_write_close();

send_pick_zip($member['mem_id'], int_list(isset($_POST['bs']) ? $_POST['bs'] : ''), $orig);
