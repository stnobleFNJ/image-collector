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

// NAS 원본 경로 (DB 에는 \\192.168.0.6\공유폴더\... 형식으로 저장됨)
// 사내 Windows PC 에서 실행: 그대로 / Synology NAS 에서 실행: $_nasTo = '/volume1/'
// cafe24 처럼 NAS 에 접근할 수 없는 서버에서는 원본 다운로드 버튼이 자동으로 숨겨집니다.
$_nasFrom  = '\\\\192.168.0.6\\';
$_nasTo    = '\\\\192.168.0.6\\';
$_nasProbe = 'photo';  // 접근 가능 여부 확인용 공유 폴더

// 사내 원본 다운로드 서버 (cafe24 처럼 NAS 에 접근 못 하는 서버에서 원본 버튼이 이 서버로 연결)
// 두 서버의 $_origSecret 은 반드시 같아야 합니다.
$_origServer = "http://192.168.0.123:8000";
$_origSecret = "임의의_긴_문자열로_변경";
