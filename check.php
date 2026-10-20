<?php
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';

$member = require_member_page();
$businesses = business_list();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(APP_TITLE) ?></title>
<link rel="icon" href="assets/fnj_logo_icon.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css">
<link rel="stylesheet" href="assets/app.css">
</head>
<body class="app-body">

<header class="topbar">
    <a class="brand" href="check.php">
        <img src="assets/fnj_logo_icon.png" alt="FNJ">
        <span>FNJ 이미지 <b>Check</b></span>
    </a>

    <label class="size-ctrl" title="썸네일 크기">
        <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><rect x="5" y="5" width="14" height="14" rx="3" fill="none" stroke="currentColor" stroke-width="2"/></svg>
        <input type="range" id="sizeRange" min="80" max="320" step="10" value="160" aria-label="썸네일 크기">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="4" fill="none" stroke="currentColor" stroke-width="2"/></svg>
        <output id="sizeVal">160px</output>
    </label>

    <div class="user">
        <span class="user-name"><?= h($member['name'] !== '' ? $member['name'] : $member['login_id']) ?>님</span>
        <a class="btn btn-ghost btn-sm" href="logout.php">로그아웃</a>
    </div>
</header>

<section class="bsbar">
    <div class="bsbar-head">
        <h2>사업 <small id="bsSelCount">0개 선택</small></h2>
        <input type="search" id="bsFilter" class="bs-filter" placeholder="사업명 검색">
        <button type="button" class="btn btn-ghost btn-sm" id="bsClear">선택 해제</button>
        <button type="button" class="btn btn-ghost btn-sm" id="bsToggleMore">펼치기</button>
    </div>
    <div class="chips collapsed" id="bsChips">
        <?php foreach ($businesses as $b): ?>
            <button type="button" class="chip" data-id="<?= (int)$b['bs_id'] ?>" data-name="<?= h($b['bs_name']) ?>"
                    title="<?= h(trim($b['client'] . ' · ' . $b['bs_years'], ' ·')) ?>">
                <?= h($b['bs_name']) ?><span class="chip-cnt"><?= number_format($b['cnt']) ?></span>
            </button>
        <?php endforeach; ?>
    </div>
</section>

<div class="layout">
    <main class="main">
        <div class="main-head">
            <h2 id="gridTitle">썸네일</h2>
            <span class="muted" id="gridInfo"></span>
        </div>
        <div class="empty" id="gridEmpty">
            <img src="assets/fnj_logo_icon.png" alt="">
            <p>위에서 사업을 선택하면 썸네일이 표시됩니다.<br><small>여러 사업을 함께 선택할 수 있습니다.</small></p>
        </div>
        <div class="grid" id="grid"></div>
        <div class="sentinel" id="sentinel"><span class="spinner"></span></div>
    </main>

    <aside class="side">
        <div class="side-card">
            <div class="side-head">
                <h2>내 선택 <span class="badge" id="pickCount">0</span></h2>
                <span class="muted side-scope" id="pickScope">전체 사업</span>
            </div>
            <div class="side-grid" id="pickGrid"></div>
            <div class="side-empty" id="pickEmpty">썸네일을 클릭하면 여기에 모입니다.</div>
            <form method="post" action="download.php" class="side-actions" id="dlForm">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="bs" id="dlBs" value="">
                <button type="submit" class="btn btn-primary" id="dlBtn" disabled>
                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M12 4v11m0 0-4.5-4.5M12 15l4.5-4.5M5 19h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    한번에 다운로드
                </button>
                <button type="button" class="btn btn-ghost" id="pickClear" disabled>선택 비우기</button>
            </form>
        </div>
    </aside>
</div>

<div class="toast" id="toast"></div>

<script>
window.APP = { csrf: <?= json_encode(csrf_token()) ?> };
</script>
<script src="assets/check.js"></script>
</body>
</html>
