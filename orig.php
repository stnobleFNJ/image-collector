<?php
// (사내 원본 서버) 서명된 링크를 검증하고 NAS 원본 ZIP 을 내려줌
//   ?t=토큰        : 안내 화면 (자동으로 다운로드 시작)
//   ?t=토큰&go=1   : ZIP 다운로드
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';
require __DIR__ . '/inc/pickzip.php';

$token = isset($_GET['t']) ? (string)$_GET['t'] : '';
$auth = orig_token_verify($token);
$error = '';
$member = null;

if (is_string($auth)) {
    $error = $auth;
} elseif (!nas_available()) {
    $error = '원본 서버에서 NAS 에 접근할 수 없습니다. 관리자에게 문의해 주세요.';
} else {
    $rows = q("SELECT mem_id, name, login_id FROM NAS_MEMBER WHERE mem_id = ? AND db_state = 'Y'", 'i', array($auth['m']));
    $member = $rows ? $rows[0] : null;
    if (!$member) {
        $error = '사용할 수 없는 계정입니다.';
    }
}
session_write_close();

if ($error === '' && isset($_GET['go'])) {
    send_pick_zip($member['mem_id'], $auth['b'], true);
}

$picks = $error === '' ? pick_rows($member['mem_id'], $auth['b'], true) : array();
$totalKb = array_sum(array_column($picks, 'size_kb'));
$bsCnt = count(array_unique(array_column($picks, 'bs_name')));
$goUrl = 'orig.php?t=' . rawurlencode($token) . '&go=1';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php render_head('원본 다운로드 · ' . APP_TITLE); ?>
</head>
<body class="login-body">

<main class="login-card orig-card">
    <img class="login-logo" src="assets/fnj_logo_icon.png" alt="FNJ">
    <h1 class="login-title">원본 <span>다운로드</span></h1>

    <?php if ($error !== ''): ?>
        <div class="alert"><?= h($error) ?></div>
        <button type="button" class="btn btn-ghost btn-block" onclick="window.close()">닫기</button>
    <?php elseif (!$picks): ?>
        <p class="login-sub">다운로드할 이미지가 없습니다.</p>
        <button type="button" class="btn btn-ghost btn-block" onclick="window.close()">닫기</button>
    <?php else: ?>
        <p class="login-sub"><?= h($member['name'] !== '' ? $member['name'] : $member['login_id']) ?>님이 선택한 NAS 원본을 ZIP 으로 묶고 있습니다.</p>
        <dl class="orig-sum">
            <div><dt>이미지</dt><dd><?= number_format(count($picks)) ?>장</dd></div>
            <div><dt>사업</dt><dd><?= number_format($bsCnt) ?>개</dd></div>
            <div><dt>예상 용량</dt><dd><?= $totalKb >= 1048576 ? number_format($totalKb / 1048576, 1) . 'GB' : number_format($totalKb / 1024, 1) . 'MB' ?></dd></div>
        </dl>
        <p class="orig-status" id="status"><span class="spinner"></span> 곧 다운로드가 시작됩니다…</p>
        <a class="btn btn-primary btn-block" href="<?= h($goUrl) ?>" id="goBtn">다운로드가 시작되지 않으면 클릭</a>
        <p class="muted orig-note">장수가 많으면 ZIP 을 만드는 데 시간이 걸립니다. 다운로드가 시작되면 이 창은 닫아도 됩니다.</p>
        <script>
        setTimeout(function () {
            location.href = document.getElementById('goBtn').href;
            document.getElementById('status').textContent = '다운로드를 요청했습니다. 브라우저의 다운로드 목록을 확인해 주세요.';
        }, 600);
        </script>
    <?php endif; ?>
</main>

</body>
</html>
