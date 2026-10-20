<?php
// 내가 선택한 썸네일 ZIP 다운로드 (사업 필터 동일 적용)
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';

const MAX_FILES = 1000;

$member = require_member_page();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    http_response_code(400);
    exit('잘못된 요청입니다.');
}
session_write_close();

$bs = int_list(isset($_POST['bs']) ? $_POST['bs'] : '');
$filter = $bs ? " AND i.bs_id IN (" . implode(',', $bs) . ")" : '';
$rows = q("SELECT i.img_id, i.file_name, i.bs_name, i.thumb
           FROM NAS_MEMBER_PICK p
           JOIN NAS_IMG i ON i.path_hash = p.path_hash AND i.db_state = 'Y'
           WHERE p.mem_id = ? AND i.thumb IS NOT NULL $filter
           ORDER BY i.bs_name, i.img_id
           LIMIT " . MAX_FILES,
    'i', array((int)$member['mem_id']));

if (!$rows) {
    echo '<!DOCTYPE html><meta charset="utf-8"><script>alert("다운로드할 썸네일이 없습니다.");history.back();</script>';
    exit;
}

// 파일/폴더 이름에 쓸 수 없는 문자 정리
function safe_name($s) {
    $s = preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]/u', '_', (string)$s);
    return trim($s, " .") === '' ? '_' : trim($s, " .");
}

$tmp = tempnam(sys_get_temp_dir(), 'fnjzip');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('ZIP 파일을 만들 수 없습니다.');
}
foreach ($rows as $r) {
    $base = pathinfo($r['file_name'], PATHINFO_FILENAME);
    $entry = safe_name($r['bs_name']) . '/' . $r['img_id'] . '_' . safe_name($base) . '.jpg';
    $zip->addFromString($entry, $r['thumb']);
}
$zip->close();
unset($rows);

$fname = 'FNJ_이미지_' . date('Ymd_His') . '.zip';
header('Content-Type: application/zip');
header('Content-Length: ' . filesize($tmp));
header("Content-Disposition: attachment; filename=\"FNJ_images_" . date('Ymd_His') . ".zip\"; filename*=UTF-8''" . rawurlencode($fname));
header('Cache-Control: no-store');
readfile($tmp);
unlink($tmp);
