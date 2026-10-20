(function () {
    'use strict';

    var $ = function (id) { return document.getElementById(id); };
    var chipsEl = $('bsChips'), grid = $('grid'), pickGrid = $('pickGrid'), sentinel = $('sentinel');
    var LS_BS = 'fnjimg.bs', LS_SIZE = 'fnjimg.size';

    var state = {
        bs: new Set(),      // 선택한 bs_id
        offset: 0,
        more: false,
        loading: false,
        reqSeq: 0,          // 사업 변경 시 이전 응답 무시용
        picks: new Map()    // img_id -> item (현재 필터 기준)
    };

    function store(key, val) { try { localStorage.setItem(key, val); } catch (e) {} }
    function load(key) { try { return localStorage.getItem(key); } catch (e) { return null; } }

    function toast(msg) {
        var t = $('toast');
        t.textContent = msg;
        t.classList.add('show');
        clearTimeout(toast.timer);
        toast.timer = setTimeout(function () { t.classList.remove('show'); }, 2200);
    }

    function api(params, post) {
        var opt = { headers: { 'X-CSRF-Token': APP.csrf }, credentials: 'same-origin' };
        var url = 'api.php';
        if (post) {
            opt.method = 'POST';
            opt.body = new URLSearchParams(params);
        } else {
            url += '?' + new URLSearchParams(params).toString();
        }
        return fetch(url, opt).then(function (r) {
            return r.json().catch(function () { return { ok: false, error: '서버 응답 오류' }; }).then(function (d) {
                if (d.login) {
                    alert(d.error || '불법적인 접속입니다.');
                    location.replace('login.php');
                    throw new Error('login');
                }
                if (!d.ok) { throw new Error(d.error || '요청 실패'); }
                return d;
            });
        });
    }

    function bsParam() { return Array.from(state.bs).join(','); }

    /* ---------- 썸네일 크기 ---------- */
    var range = $('sizeRange');
    function applySize(v) {
        document.documentElement.style.setProperty('--thumb', v + 'px');
        $('sizeVal').textContent = v + 'px';
    }
    range.value = load(LS_SIZE) || range.value;
    applySize(range.value);
    range.addEventListener('input', function () { applySize(range.value); store(LS_SIZE, range.value); });

    /* ---------- 사업 선택 ---------- */
    var chips = Array.from(chipsEl.querySelectorAll('.chip'));

    function syncBsUi() {
        chips.forEach(function (c) { c.classList.toggle('on', state.bs.has(+c.dataset.id)); });
        $('bsSelCount').textContent = state.bs.size + '개 선택';
        var names = chips.filter(function (c) { return state.bs.has(+c.dataset.id); })
            .map(function (c) { return c.dataset.name; });
        $('gridTitle').textContent = names.length ? names.slice(0, 3).join(', ') + (names.length > 3 ? ' 외 ' + (names.length - 3) + '개' : '') : '썸네일';
        $('pickScope').textContent = names.length ? '선택한 사업 ' + names.length + '개' : '전체 사업';
        $('dlBs').value = bsParam();
        // bs_id 는 재적재 시 바뀌므로 이름으로 저장
        store(LS_BS, JSON.stringify(names));
    }

    function onBsChanged() {
        syncBsUi();
        loadGrid(true);
        loadPicks();
    }

    chipsEl.addEventListener('click', function (e) {
        var c = e.target.closest('.chip');
        if (!c) { return; }
        var id = +c.dataset.id;
        if (state.bs.has(id)) { state.bs.delete(id); } else { state.bs.add(id); }
        onBsChanged();
    });

    $('bsClear').addEventListener('click', function () {
        if (!state.bs.size) { return; }
        state.bs.clear();
        onBsChanged();
    });

    $('bsToggleMore').addEventListener('click', function () {
        var collapsed = chipsEl.classList.toggle('collapsed');
        this.textContent = collapsed ? '펼치기' : '접기';
    });

    $('bsFilter').addEventListener('input', function () {
        var kw = this.value.trim().toLowerCase();
        chips.forEach(function (c) { c.hidden = kw !== '' && c.dataset.name.toLowerCase().indexOf(kw) < 0; });
        if (kw) { chipsEl.classList.remove('collapsed'); $('bsToggleMore').textContent = '접기'; }
    });

    /* ---------- 썸네일 그리드 ---------- */
    function makeTile(it, small) {
        var el = document.createElement('div');
        el.className = small ? 'ptile' : 'tile';
        el.dataset.id = it.id;
        el.title = it.name + (it.bs ? '\n' + it.bs + (it.yr ? ' · ' + it.yr : '') : '');
        var img = document.createElement('img');
        img.loading = 'lazy';
        img.decoding = 'async';
        img.alt = '';
        img.src = 'thumb.php?id=' + it.id;
        el.appendChild(img);
        if (small) {
            var x = document.createElement('button');
            x.type = 'button';
            x.className = 'ptile-x';
            x.setAttribute('aria-label', '선택 해제');
            x.textContent = '×';
            el.appendChild(x);
        } else {
            el.insertAdjacentHTML('beforeend',
                '<span class="check" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></span>' +
                '<span class="tile-name"></span>');
            el.querySelector('.tile-name').textContent = it.name;
            if (it.picked) { el.classList.add('picked'); }
        }
        return el;
    }

    function loadGrid(reset) {
        if (reset) {
            state.reqSeq++;
            state.offset = 0;
            state.more = false;
            state.loading = false;
            grid.innerHTML = '';
            $('gridInfo').textContent = '';
        }
        $('gridEmpty').hidden = state.bs.size > 0;
        if (!state.bs.size || state.loading) { sentinel.classList.remove('on'); return; }

        var seq = state.reqSeq;
        state.loading = true;
        sentinel.classList.add('on');
        api({ act: 'items', bs: bsParam(), offset: state.offset }).then(function (d) {
            if (seq !== state.reqSeq) { return; }
            var frag = document.createDocumentFragment();
            d.items.forEach(function (it) { frag.appendChild(makeTile(it)); });
            grid.appendChild(frag);
            state.offset += d.items.length;
            state.more = d.more;
            if (d.total !== null) { state.total = d.total; }
            $('gridInfo').textContent = state.offset.toLocaleString() + ' / ' + (state.total || 0).toLocaleString() + '장';
        }).catch(function (e) {
            if (e.message !== 'login') { toast(e.message); }
        }).then(function () {
            if (seq !== state.reqSeq) { return; }
            state.loading = false;
            sentinel.classList.toggle('on', state.more);
            if (state.more && isNearBottom()) { loadGrid(false); }
        });
    }

    function isNearBottom() {
        return sentinel.getBoundingClientRect().top < window.innerHeight + 600;
    }

    new IntersectionObserver(function (entries) {
        if (entries[0].isIntersecting && state.more && !state.loading) { loadGrid(false); }
    }, { rootMargin: '600px 0px' }).observe(sentinel);

    // 크기를 줄이면 화면에 빈 공간이 생길 수 있어 추가 로드 확인
    range.addEventListener('change', function () { if (state.more && !state.loading && isNearBottom()) { loadGrid(false); } });

    /* ---------- 선택 (실시간 반영) ---------- */
    function renderPickCount() {
        var n = state.picks.size;
        $('pickCount').textContent = n.toLocaleString();
        $('pickEmpty').hidden = n > 0;
        $('dlBtn').disabled = n === 0;
        $('pickClear').disabled = n === 0;
    }

    function addPickTile(it, prepend) {
        if (pickGrid.querySelector('.ptile[data-id="' + it.id + '"]')) { return; }
        var el = makeTile(it, true);
        if (prepend) { pickGrid.prepend(el); } else { pickGrid.appendChild(el); }
    }

    function setPicked(it, on) {
        var tile = grid.querySelector('.tile[data-id="' + it.id + '"]');
        if (tile) { tile.classList.toggle('picked', on); }
        if (on) {
            state.picks.set(it.id, it);
            addPickTile(it, true);
        } else {
            state.picks.delete(it.id);
            var p = pickGrid.querySelector('.ptile[data-id="' + it.id + '"]');
            if (p) { p.remove(); }
        }
        renderPickCount();
    }

    function togglePick(it, on) {
        setPicked(it, on);  // 먼저 화면에 반영
        api({ act: 'toggle', id: it.id, on: on ? 1 : 0 }, true).catch(function (e) {
            setPicked(it, !on);
            if (e.message !== 'login') { toast('저장 실패: ' + e.message); }
        });
    }

    grid.addEventListener('click', function (e) {
        var tile = e.target.closest('.tile');
        if (!tile) { return; }
        var id = +tile.dataset.id;
        togglePick({
            id: id,
            name: tile.querySelector('.tile-name').textContent,
            bs: (tile.title.split('\n')[1] || '').split(' · ')[0]
        }, !tile.classList.contains('picked'));
    });

    pickGrid.addEventListener('click', function (e) {
        var t = e.target.closest('.ptile');
        if (!t) { return; }
        var it = state.picks.get(+t.dataset.id);
        if (it) { togglePick(it, false); }
    });

    var pickSeq = 0;
    function loadPicks() {
        var seq = ++pickSeq;
        api({ act: 'picks', bs: bsParam() }).then(function (d) {
            if (seq !== pickSeq) { return; }
            state.picks.clear();
            pickGrid.innerHTML = '';
            d.items.forEach(function (it) { state.picks.set(it.id, it); addPickTile(it, false); });
            renderPickCount();
        }).catch(function (e) { if (e.message !== 'login') { toast(e.message); } });
    }

    $('pickClear').addEventListener('click', function () {
        var n = state.picks.size;
        if (!n || !confirm('현재 표시된 선택 ' + n + '개를 모두 해제할까요?')) { return; }
        api({ act: 'clear', bs: bsParam() }, true).then(function () {
            state.picks.forEach(function (it) { setPicked(it, false); });
            toast('선택을 비웠습니다.');
        }).catch(function (e) { if (e.message !== 'login') { toast(e.message); } });
    });

    $('dlForm').addEventListener('submit', function () {
        $('dlBs').value = bsParam();
        if (state.picks.size > 1000) { toast('최대 1,000장까지 다운로드됩니다.'); }
        else { toast('ZIP 파일을 만드는 중입니다…'); }
    });

    /* ---------- 시작 ---------- */
    var saved = [];
    try { saved = JSON.parse(load(LS_BS) || '[]'); } catch (e) {}
    chips.forEach(function (c) { if (saved.indexOf(c.dataset.name) >= 0) { state.bs.add(+c.dataset.id); } });
    onBsChanged();
})();
