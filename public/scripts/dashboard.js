(function () {
  'use strict';

  var API_URL = '/dashboard/api/active-session';
  var activeSessionId = null;

  // ── Rest timer ──
  // Persisted to localStorage and tracked by an absolute end timestamp, so it
  // survives page navigation (e.g. switching to Workouts and back) and stays
  // accurate even while the tab was backgrounded.
  var TIMER_CIRC = 339.292; // 2π·54, matches the SVG ring radius
  var TIMER_KEY  = 'gt_rest_timer';
  var timer = { remaining: 0, total: 0, running: false, endAt: null, intervalId: null, sessionId: null };

  function formatTime(sec) {
    sec = Math.max(0, sec | 0);
    var m = Math.floor(sec / 60), s = sec % 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }

  // Recompute remaining from the absolute end time while running.
  function syncRemaining() {
    if (timer.running && timer.endAt) {
      timer.remaining = Math.max(0, Math.round((timer.endAt - Date.now()) / 1000));
    }
  }

  function persistTimer() {
    try {
      if (timer.total <= 0 && timer.remaining <= 0 && !timer.running) {
        localStorage.removeItem(TIMER_KEY);
        return;
      }
      localStorage.setItem(TIMER_KEY, JSON.stringify({
        total: timer.total, remaining: timer.remaining,
        running: timer.running, endAt: timer.endAt, sessionId: timer.sessionId
      }));
    } catch (_) {}
  }

  function restoreTimer(sessionId) {
    var raw = null;
    try { raw = localStorage.getItem(TIMER_KEY); } catch (_) {}
    if (!raw) return;
    var st;
    try { st = JSON.parse(raw); } catch (_) { return; }
    if (!st || st.sessionId !== sessionId) { // stale timer from another/old session
      try { localStorage.removeItem(TIMER_KEY); } catch (_) {}
      return;
    }
    timer.total     = st.total || 0;
    timer.sessionId = sessionId;
    if (st.running && st.endAt) {
      timer.running = true;
      timer.endAt   = st.endAt;
      syncRemaining();
      if (timer.remaining <= 0) { timer.running = false; timer.endAt = null; }
    } else {
      timer.running   = false;
      timer.endAt     = null;
      timer.remaining = st.remaining || 0;
    }
    ensureTimerInterval();
  }

  function updateTimerDom() {
    var txt = document.getElementById('timer-text');
    if (!txt) return; // timer not on screen (no active session)
    txt.textContent = formatTime(timer.remaining);
    var ring = document.getElementById('timer-ring');
    if (ring) {
      var frac = timer.total > 0 ? timer.remaining / timer.total : 0;
      ring.setAttribute('stroke-dashoffset', String(TIMER_CIRC * (1 - frac)));
    }
    var icon = document.getElementById('timer-toggle-icon');
    if (icon) icon.className = 'fa-solid ' + (timer.running ? 'fa-pause' : 'fa-play') + ' text-xs';
  }

  function timerTick() {
    if (timer.running) {
      syncRemaining();
      if (timer.remaining <= 0) { timer.running = false; timer.endAt = null; persistTimer(); }
    }
    updateTimerDom();
  }

  function ensureTimerInterval() {
    if (timer.intervalId == null) timer.intervalId = setInterval(timerTick, 1000);
  }

  function startTimer(seconds) {
    seconds = Math.max(0, seconds | 0);
    timer.sessionId = activeSessionId;
    timer.total     = seconds;
    timer.remaining = seconds;
    timer.running   = seconds > 0;
    timer.endAt     = timer.running ? Date.now() + seconds * 1000 : null;
    ensureTimerInterval();
    persistTimer();
    updateTimerDom();
  }

  function toggleTimer() {
    syncRemaining();
    if (timer.remaining <= 0) return;
    timer.running = !timer.running;
    timer.endAt   = timer.running ? Date.now() + timer.remaining * 1000 : null;
    ensureTimerInterval();
    persistTimer();
    updateTimerDom();
  }

  function adjustTimer(delta) {
    syncRemaining();
    timer.remaining = Math.max(0, timer.remaining + delta);
    if (timer.remaining > timer.total) timer.total = timer.remaining; // keep ring scale sane
    timer.endAt = timer.running ? Date.now() + timer.remaining * 1000 : null;
    persistTimer();
    updateTimerDom();
  }

  function resetTimer() {
    timer.running   = false;
    timer.remaining = 0;
    timer.total     = 0;
    timer.endAt     = null;
    timer.sessionId = null;
    try { localStorage.removeItem(TIMER_KEY); } catch (_) {}
  }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function elapsedMins(startedAt) {
    return Math.max(0, Math.floor((Date.now() - new Date(startedAt).getTime()) / 60000));
  }

  function progressPercent(exercises) {
    var total = 0, done = 0;
    for (var i = 0; i < exercises.length; i++) {
      var sets = exercises[i].sets || [];
      for (var j = 0; j < sets.length; j++) {
        total++;
        if (sets[j].is_completed) done++;
      }
    }
    return total === 0 ? 0 : Math.round((done / total) * 100);
  }

  function findCurrentIndex(exercises) {
    for (var i = 0; i < exercises.length; i++) {
      if (!exercises[i].is_completed) return i;
    }
    return -1; // all exercises finished
  }

  function exerciseListHtml(exercises) {
    if (!exercises.length) return '';
    var currentIdx = findCurrentIndex(exercises);
    var items = '';
    for (var i = 0; i < exercises.length; i++) {
      var ex = exercises[i];
      var sets = ex.sets || [];
      var doneSets = 0;
      for (var j = 0; j < sets.length; j++) if (sets[j].is_completed) doneSets++;
      var allDone = !!ex.is_completed;
      var isCurrent = i === currentIdx;

      var marker;
      if (allDone) {
        marker = '<span class="w-5 h-5 shrink-0 rounded-full bg-brand-green/15 text-brand-green flex items-center justify-center text-[10px]"><i class="fa-solid fa-check"></i></span>';
      } else if (isCurrent) {
        marker = '<span class="w-5 h-5 shrink-0 rounded-full border-2 border-brand-green text-brand-green flex items-center justify-center text-[10px] font-bold">' + (i + 1) + '</span>';
      } else {
        marker = '<span class="w-5 h-5 shrink-0 rounded-full border border-gray-600 text-gray-500 flex items-center justify-center text-[10px]">' + (i + 1) + '</span>';
      }

      var nameCls = isCurrent ? 'text-white font-semibold' : (allDone ? 'text-gray-500' : 'text-gray-300');
      var meta = sets.length ? (doneSets + '/' + sets.length + ' sets') : esc(ex.muscle_group || '');

      items += (
        '<li class="flex items-center gap-2.5">' +
          marker +
          '<span class="flex-1 min-w-0 truncate text-sm ' + nameCls + '">' + esc(ex.exercise_name_snapshot) + '</span>' +
          '<span class="text-gray-600 text-[11px] shrink-0">' + meta + '</span>' +
        '</li>'
      );
    }
    return (
      '<div class="bg-surface-raised border border-surface-border rounded-card p-5">' +
        '<p class="text-gray-400 text-xs uppercase tracking-widest mb-3">Exercises</p>' +
        '<ol class="flex flex-col gap-2.5">' + items + '</ol>' +
      '</div>'
    );
  }

  function previousLabel(s) {
    if (s.previous_weight == null) return '—';
    return s.previous_reps != null
      ? esc(s.previous_weight) + ' × ' + esc(s.previous_reps)
      : esc(s.previous_weight) + ' kg';
  }

  function statusToggle(s) {
    var rest = s.rest_sec != null ? s.rest_sec : '';
    if (s.is_completed) {
      return '<button type="button" data-action="toggle-set" data-set-id="' + esc(s.id) + '" data-completed="1" data-rest="' + esc(rest) + '" title="Mark as not done" ' +
        'class="w-6 h-6 mx-auto rounded-full bg-brand-green text-surface-base flex items-center justify-center hover:opacity-80 focus-visible:outline-none">' +
        '<i class="fa-solid fa-check text-[11px]"></i></button>';
    }
    return '<button type="button" data-action="toggle-set" data-set-id="' + esc(s.id) + '" data-completed="0" data-rest="' + esc(rest) + '" title="Mark as done" ' +
      'class="w-6 h-6 mx-auto block rounded-full border-2 border-gray-600 hover:border-brand-green focus-visible:outline-none"></button>';
  }

  function setsTableRowsHtml(sets) {
    var rows = '';

    if (!sets.length) {
      rows += '<tr><td colspan="5" class="px-3 py-3 text-center text-gray-500 text-xs">No sets yet</td></tr>';
    }

    for (var i = 0; i < sets.length; i++) {
      var s = sets[i];
      var rowBg = s.is_completed ? 'bg-brand-green/5' : '';
      rows += (
        '<tr class="' + rowBg + ' border-b border-surface-border last:border-0">' +
          '<td class="px-3 py-2 text-sm text-gray-300">' + esc(s.set_number) + '</td>' +
          '<td class="px-3 py-2 text-sm text-gray-500">' + previousLabel(s) + '</td>' +
          '<td class="px-3 py-2">' +
            '<input type="number" step="0.5" min="0" inputmode="decimal" placeholder="—" ' +
              'value="' + (s.weight_kg != null ? esc(s.weight_kg) : '') + '" ' +
              'data-set-id="' + esc(s.id) + '" data-field="weight" ' +
              'class="w-20 bg-surface-card border border-surface-border rounded px-2 py-1 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-brand-green" />' +
          '</td>' +
          '<td class="px-3 py-2">' +
            '<input type="number" min="0" inputmode="numeric" placeholder="—" ' +
              'value="' + (s.reps != null ? esc(s.reps) : '') + '" ' +
              'data-set-id="' + esc(s.id) + '" data-field="reps" ' +
              'class="w-16 bg-surface-card border border-surface-border rounded px-2 py-1 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-brand-green" />' +
          '</td>' +
          '<td class="px-3 py-2 text-center">' + statusToggle(s) + '</td>' +
        '</tr>'
      );
    }

    return rows;
  }

  function currentExerciseCardHtml(exercises, sessionId) {
    if (!exercises.length) {
      return '<div class="bg-surface-raised border border-surface-border rounded-card p-6"><p class="text-gray-500 text-sm">No exercises added yet.</p></div>';
    }

    var idx = findCurrentIndex(exercises);
    if (idx < 0) {
      return (
        '<div class="bg-surface-raised border-l-4 border-brand-green rounded-card p-8 text-center">' +
          '<i class="fa-solid fa-circle-check text-3xl text-brand-green mb-3 block"></i>' +
          '<p class="text-white font-bold">All exercises complete</p>' +
          '<p class="text-gray-500 text-sm mt-1">End the session whenever you\'re ready.</p>' +
        '</div>'
      );
    }
    var current = exercises[idx];

    return (
      '<div class="bg-surface-raised border-l-4 border-brand-green rounded-card p-6">' +
        '<div class="flex items-start justify-between gap-4 mb-5">' +
          '<div>' +
            '<h2 class="text-xl font-bold uppercase tracking-wider text-white">' + esc(current.exercise_name_snapshot) + '</h2>' +
            '<p class="text-gray-500 text-xs mt-1"><i class="fa-solid fa-circle-dot mr-1"></i>' + esc(current.muscle_group || 'General') + '</p>' +
          '</div>' +
          '<button class="shrink-0 bg-brand-green text-surface-base text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-full hover:opacity-90 focus-visible:outline-none" ' +
            'data-action="finish-exercise" data-exercise-id="' + esc(current.id) + '" data-session-id="' + esc(sessionId) + '">' +
            'Finish Exercise' +
          '</button>' +
        '</div>' +
        '<table class="w-full border-collapse">' +
          '<thead>' +
            '<tr class="border-b border-surface-border">' +
              '<th class="px-3 py-2 text-left text-xs text-gray-500 uppercase tracking-widest font-medium">Set</th>' +
              '<th class="px-3 py-2 text-left text-xs text-gray-500 uppercase tracking-widest font-medium">Previous</th>' +
              '<th class="px-3 py-2 text-left text-xs text-gray-500 uppercase tracking-widest font-medium">Weight (kg)</th>' +
              '<th class="px-3 py-2 text-left text-xs text-gray-500 uppercase tracking-widest font-medium">Reps</th>' +
              '<th class="px-3 py-2 text-center text-xs text-gray-500 uppercase tracking-widest font-medium">Status</th>' +
            '</tr>' +
          '</thead>' +
          '<tbody>' + setsTableRowsHtml(current.sets || []) + '</tbody>' +
        '</table>' +
        '<div class="mt-4">' +
          '<button type="button" data-action="add-set" data-exercise-id="' + esc(current.id) + '" ' +
            'data-set-number="' + ((current.sets || []).length + 1) + '" ' +
            'class="inline-flex items-center gap-1.5 text-brand-green text-xs font-bold uppercase tracking-widest border border-brand-green/30 rounded px-4 py-2 hover:bg-brand-green/10 focus-visible:outline-none">' +
            '<i class="fa-solid fa-plus text-[10px]"></i> Add set' +
          '</button>' +
        '</div>' +
      '</div>'
    );
  }

  function addExercisePickerHtml(catalogue, sessionId) {
    if (!catalogue || !catalogue.length) return '';
    var opts = '<option value="" data-name="">— select exercise —</option>';
    for (var i = 0; i < catalogue.length; i++) {
      var c = catalogue[i];
      opts += '<option value="' + esc(c.id) + '" data-name="' + esc(c.name) + '">' +
        esc(c.name) + ' — ' + esc(c.muscle_group) + '</option>';
    }
    return (
      '<div class="bg-surface-raised border border-surface-border rounded-card p-5">' +
        '<p class="text-gray-400 text-xs uppercase tracking-widest mb-3">Add Exercise</p>' +
        '<div class="flex flex-wrap items-end gap-3">' +
          '<select id="add-exercise-select" ' +
            'class="flex-1 min-w-48 bg-surface-card border border-surface-border rounded px-3 py-2 text-sm text-white focus:outline-none focus:border-brand-green appearance-none">' +
            opts +
          '</select>' +
          '<button type="button" data-action="add-exercise" data-session-id="' + esc(sessionId) + '" ' +
            'class="inline-flex items-center gap-1.5 bg-brand-green text-surface-base font-bold uppercase tracking-widest text-xs px-5 py-2 rounded hover:opacity-90 focus-visible:outline-none">' +
            '<i class="fa-solid fa-plus text-[10px]"></i> Add' +
          '</button>' +
        '</div>' +
      '</div>'
    );
  }

  function timerCardHtml() {
    var frac = timer.total > 0 ? timer.remaining / timer.total : 0;
    var offset = TIMER_CIRC * (1 - frac);
    return (
      '<div class="bg-surface-raised border border-surface-border rounded-card p-5">' +
        '<p class="text-gray-400 text-xs uppercase tracking-widest mb-3 text-center">Rest Timer</p>' +
        '<div class="flex justify-center">' +
          '<svg width="140" height="140" viewBox="0 0 140 140">' +
            '<circle cx="70" cy="70" r="54" fill="none" stroke="#1a1a1a" stroke-width="8"/>' +
            '<circle id="timer-ring" cx="70" cy="70" r="54" fill="none" stroke="#39ff14" stroke-width="8" stroke-dasharray="' + TIMER_CIRC + '" stroke-dashoffset="' + offset + '" stroke-linecap="round" transform="rotate(-90 70 70)"/>' +
            '<text id="timer-text" x="70" y="66" text-anchor="middle" fill="#ffffff" font-size="26" font-weight="700" font-family="system-ui,sans-serif">' + formatTime(timer.remaining) + '</text>' +
            '<text x="70" y="83" text-anchor="middle" fill="#555555" font-size="9" font-family="system-ui,sans-serif" letter-spacing="2">REMAINING</text>' +
          '</svg>' +
        '</div>' +
        '<div class="flex items-center justify-center gap-5 mt-3">' +
          '<button class="text-gray-500 hover:text-white w-8 h-8 flex items-center justify-center text-lg" data-action="timer-minus" title="-15s">−</button>' +
          '<button class="bg-surface-card border border-surface-border rounded-full w-10 h-10 flex items-center justify-center text-white hover:border-brand-green focus-visible:outline-none" data-action="timer-toggle" title="Play / pause">' +
            '<i id="timer-toggle-icon" class="fa-solid ' + (timer.running ? 'fa-pause' : 'fa-play') + ' text-xs"></i>' +
          '</button>' +
          '<button class="text-gray-500 hover:text-white w-8 h-8 flex items-center justify-center text-lg" data-action="timer-plus" title="+15s">+</button>' +
        '</div>' +
        '<div class="flex justify-center gap-2 mt-3">' +
          '<button class="px-3 py-1 text-xs rounded border border-surface-border text-gray-500 hover:border-brand-green hover:text-white" data-action="timer-preset" data-seconds="30">30s</button>' +
          '<button class="px-3 py-1 text-xs rounded border border-surface-border text-gray-500 hover:border-brand-green hover:text-white" data-action="timer-preset" data-seconds="60">60s</button>' +
          '<button class="px-3 py-1 text-xs rounded border border-surface-border text-gray-500 hover:border-brand-green hover:text-white" data-action="timer-preset" data-seconds="90">90s</button>' +
        '</div>' +
      '</div>'
    );
  }

  function sidebarHtml(session) {
    var exercises = session.exercises || [];
    var progress = progressPercent(exercises);

    return (
      '<div class="bg-surface-raised border border-surface-border rounded-card p-5">' +
        '<p class="text-gray-400 text-xs uppercase tracking-widest mb-2">Session Progress</p>' +
        '<p class="text-4xl font-bold text-white">' + progress + '%</p>' +
        '<div class="mt-3 bg-surface-card rounded-full h-1.5">' +
          '<div class="bg-brand-green h-1.5 rounded-full" style="width:' + progress + '%"></div>' +
        '</div>' +
      '</div>' +

      exerciseListHtml(exercises) +

      timerCardHtml() +

      '<button class="w-full border border-red-900 text-red-500 rounded-card py-3 text-sm font-bold uppercase tracking-widest hover:bg-red-950 hover:text-red-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-800" ' +
        'data-action="end-session" data-session-id="' + esc(session.id) + '">' +
        'End Session Early' +
      '</button>'
    );
  }

  function renderActive(session, catalogue) {
    activeSessionId = session.id;
    restoreTimer(session.id); // resume a rest timer left running before navigation
    var elapsed = elapsedMins(session.started_at);
    var planLabel = esc(session.plan_name || 'Free Session');
    var exercises = session.exercises || [];

    document.getElementById('dash-main').innerHTML = (
      '<section class="flex flex-col gap-6">' +
        '<div>' +
          '<span class="text-brand-green text-xs font-bold uppercase tracking-widest">Live Tracking</span>' +
          '<h1 class="text-3xl font-bold text-white mt-1 tracking-wide">Ongoing Session</h1>' +
          '<p class="text-gray-400 text-sm mt-1">' + planLabel + ' &bull; ' + elapsed + ' min' + (elapsed !== 1 ? 's' : '') + ' elapsed</p>' +
        '</div>' +
        currentExerciseCardHtml(exercises, session.id) +
        addExercisePickerHtml(catalogue, session.id) +
        '<div class="bg-surface-card border border-surface-border rounded-card aspect-video relative">' +
          '<span class="absolute bottom-4 left-4 text-gray-600 text-[10px] tracking-widest uppercase font-medium">Form Guide &bull; Animation</span>' +
        '</div>' +
      '</section>'
    );

    document.getElementById('dash-sidebar').innerHTML = sidebarHtml(session);
    document.getElementById('dash-sidebar').classList.remove('hidden');
    document.getElementById('dash-root').classList.remove('hidden');
  }

  function renderEmpty(hasError) {
    // Only clear the timer when there is genuinely no active session — a transient
    // API error shouldn't wipe a running rest timer.
    if (!hasError) resetTimer();
    var errorNote = hasError
      ? ' <span class="text-gray-600">— couldn\'t load session status</span>'
      : '';

    document.getElementById('dash-main').innerHTML = (
      '<section class="flex flex-col gap-5">' +
        '<div>' +
          '<span class="text-brand-green text-xs font-bold uppercase tracking-widest">Live Tracking</span>' +
          '<h1 class="text-3xl font-bold text-white mt-1 tracking-wide">No active session</h1>' +
          '<p class="text-gray-400 text-sm mt-1">Start a workout to begin tracking.' + errorNote + '</p>' +
        '</div>' +
        '<a href="/dashboard/session/start" ' +
          'class="inline-flex items-center gap-2 bg-brand-green text-surface-base font-bold uppercase tracking-widest text-sm px-6 py-3 rounded self-start hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green">' +
          '<i class="fa-solid fa-play text-xs"></i> Start a Session' +
        '</a>' +
      '</section>'
    );

    document.getElementById('dash-sidebar').classList.add('hidden');
    document.getElementById('dash-root').classList.remove('hidden');
  }

  function postAction(action, params) {
    var body = new URLSearchParams();
    Object.keys(params).forEach(function (k) { body.append(k, params[k]); });
    return fetch('/analytics/session/' + activeSessionId + '/' + action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    }).catch(function () {});
  }

  function handleClick(e) {
    var btn = e.target.closest('[data-action]');
    if (!btn) return;
    var action = btn.dataset.action;
    var sessionId = btn.dataset.sessionId;
    var exerciseId = btn.dataset.exerciseId;
    var seconds = btn.dataset.seconds;

    if (action === 'end-session') {
      if (!sessionId) return;
      btn.disabled = true;
      btn.textContent = 'Ending…';
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = '/analytics/session/' + sessionId + '/end';
      document.body.appendChild(form);
      form.submit();
    } else if (action === 'finish-exercise') {
      if (!exerciseId) return;
      btn.disabled = true;
      postAction('finish-exercise', { session_exercise_id: exerciseId }).then(refresh);
    } else if (action === 'add-exercise') {
      var sel = document.getElementById('add-exercise-select');
      if (!sel || !sel.value) return;
      var name = sel.options[sel.selectedIndex].dataset.name || '';
      btn.disabled = true;
      postAction('add-exercise', { exercise_id: sel.value, exercise_name: name }).then(refresh);
    } else if (action === 'add-set') {
      if (!exerciseId) return;
      btn.disabled = true;
      postAction('log-set', {
        session_exercise_id: exerciseId,
        set_number: btn.dataset.setNumber || '1'
      }).then(refresh);
    } else if (action === 'toggle-set') {
      var setId = btn.dataset.setId;
      if (!setId) return;
      var completed = btn.dataset.completed === '1';
      var rest = parseInt(btn.dataset.rest || '0', 10) || 0;
      var weightEl = document.querySelector('input[data-set-id="' + setId + '"][data-field="weight"]');
      var repsEl = document.querySelector('input[data-set-id="' + setId + '"][data-field="reps"]');
      postAction('save-set', {
        set_id: setId,
        weight_kg: weightEl ? weightEl.value : '',
        reps: repsEl ? repsEl.value : '',
        is_completed: completed ? '0' : '1'
      }).then(function () {
        // Marking a set done with a saved rest period kicks off the rest timer.
        if (!completed && rest > 0) startTimer(rest);
        refresh();
      });
    } else if (action === 'timer-toggle') {
      toggleTimer();
    } else if (action === 'timer-preset') {
      startTimer(Number(seconds) || 0);
    } else if (action === 'timer-minus') {
      adjustTimer(-15);
    } else if (action === 'timer-plus') {
      adjustTimer(15);
    }
  }

  async function refresh() {
    var data;
    try {
      var res = await fetch(API_URL);
      if (!res.ok) throw new Error('HTTP ' + res.status);
      data = await res.json();
    } catch (_) {
      data = { active: false, error: true };
    }

    if (data.active) {
      renderActive(data.session, data.catalogue || []);
    } else {
      renderEmpty(!!data.error);
    }
  }

  function init() {
    var root = document.getElementById('dash-root');
    root.addEventListener('click', handleClick);
    refresh();
  }

  document.addEventListener('DOMContentLoaded', init);
}());
