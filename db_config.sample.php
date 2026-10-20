<?php
// db_config.php 로 복사해서 사용하세요.
if (!defined('APP_INIT')) { http_response_code(403); exit; }

$_dbHost   = "아이디.cafe24.com";  // 접속 오류 시 "localhost" 로 변경
$_dbName   = "DB명";
$_dbUser   = "아이디";
$_dbPasswd = "비밀번호";

// 썸네일 테이블 / 컬럼명 (컬럼은 비워두면 자동 감지)
$_imgTable  = "NAS_IMG";
$_imgIdCol  = "img_id";
$_imgSrcCol = "thumb";      // URL/경로 문자열 또는 이미지 BLOB
$_imgCapCol = "file_name";  // 썸네일 아래 표시할 컬럼
