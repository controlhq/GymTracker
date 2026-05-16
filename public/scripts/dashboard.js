(function () {
  'use strict';

  var API_URL = '/dashboard/api/active-session';

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

  function setsTableRowsHtml(sets, exerciseId) {
    if (!sets.length) {
      return (
        '<tr><td colspan="5" class="px-3 py-3 text-center text-gray-500 text-xs">No sets logged yet</td></tr>' +
        '<tr><td colspan="5"><button class="w-full py-2 text-xs text-gray-500 hover:text-white tracking-widest uppercase" data-action="add-set" data-exercise-id="' + esc(exerciseId) + '">+ ADD SET</button></td></tr>'
      );
    }

    var firstPending = -1;
    for (var i = 0; i < sets.length; i++) {
      if (!sets[i].is_completed) { firstPending = i; break; }
    }

    var rows = '';
    for (var i = 0; i < sets.length; i++) {
      var s = sets[i];
      var isActive = i === firstPending;
      var rowBg = isActive ? 'bg-brand-green/10' : '';
      var statusCell;
      if (s.is_completed) {
        statusCell = '<span class="text-brand-green"><i class="fa-solid fa-check text-xs"></i></span>';
      } else if (isActive) {
        statusCell = '<span class="inline-block w-3 h-3 rounded-full border-2 border-brand-green"></span>';
      } else {
        statusCell = '<span class="inline-block w-3 h-3 rounded-full border border-gray-600"></span>';
      }
      rows += (
        '<tr class="' + rowBg + '">' +
          '<td class="px-3 py-2 text-sm text-gray-300">' + esc(s.set_number) + '</td>' +
          '<td class="px-3 py-2 text-sm text-gray-500">--</td>' +
          '<td class="px-3 py-2 text-sm ' + (s.weight_kg != null ? 'text-white' : 'text-gray-500') + '">' + (s.weight_kg != null ? esc(s.weight_kg) : '--') + '</td>' +
          '<td class="px-3 py-2 text-sm ' + (s.reps != null ? 'text-white' : 'text-gray-500') + '">' + (s.reps != null ? esc(s.reps) : '--') + '</td>' +
          '<td class="px-3 py-2 text-center">' + statusCell + '</td>' +
        '</tr>'
      );
    }
    rows += '<tr><td colspan="5"><button class="w-full py-2 text-xs text-gray-500 hover:text-white tracking-widest uppercase" data-action="add-set" data-exercise-id="' + esc(exerciseId) + '">+ ADD SET</button></td></tr>';
    return rows;
  }

  function currentExerciseCardHtml(exercises, sessionId) {
    var current = null;
    for (var i = 0; i < exercises.length; i++) {
      var sets = exercises[i].sets || [];
      for (var j = 0; j < sets.length; j++) {
        if (!sets[j].is_completed) { current = exercises[i]; break; }
      }
      if (current) break;
    }
    if (!current && exercises.length) current = exercises[0];

    if (!current) {
      return '<div class="bg-surface-raised border border-surface-border rounded-card p-6"><p class="text-gray-500 text-sm">No exercises added yet.</p></div>';
    }

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
          '<tbody>' + setsTableRowsHtml(current.sets || [], current.id) + '</tbody>' +
        '</table>' +
      '</div>'
    );
  }

  function sidebarHtml(session) {
    var exercises = session.exercises || [];
    var progress = progressPercent(exercises);

    var currentIdx = -1;
    for (var i = 0; i < exercises.length; i++) {
      var sets = exercises[i].sets || [];
      for (var j = 0; j < sets.length; j++) {
        if (!sets[j].is_completed) { currentIdx = i; break; }
      }
      if (currentIdx >= 0) break;
    }
    var upNext = currentIdx >= 0 ? (exercises[currentIdx + 1] || null) : (exercises[1] || null);

    var upNextHtml = upNext ? (
      '<div class="bg-surface-raised border border-surface-border rounded-card overflow-hidden">' +
        '<div class="bg-surface-card h-20 relative flex items-center justify-center">' +
          '<span class="absolute top-2 left-3 bg-brand-green text-surface-base text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-widest">Up Next</span>' +
          '<i class="fa-solid fa-dumbbell text-2xl text-gray-700"></i>' +
        '</div>' +
        '<div class="p-4 flex items-center justify-between">' +
          '<div>' +
            '<p class="text-white text-sm font-bold">' + esc(upNext.exercise_name_snapshot) + '</p>' +
            '<p class="text-gray-500 text-xs mt-0.5">' + esc(upNext.muscle_group || 'General') + '</p>' +
          '</div>' +
          '<i class="fa-solid fa-chevron-right text-gray-600 text-xs"></i>' +
        '</div>' +
      '</div>'
    ) : '';

    // SVG circle: r=54, circumference = 2πr ≈ 339.3, static at ~35% elapsed
    return (
      '<div class="bg-surface-raised border border-surface-border rounded-card p-5">' +
        '<p class="text-gray-400 text-xs uppercase tracking-widest mb-2">Session Progress</p>' +
        '<p class="text-4xl font-bold text-white">' + progress + '%</p>' +
        '<div class="mt-3 bg-surface-card rounded-full h-1.5">' +
          '<div class="bg-brand-green h-1.5 rounded-full" style="width:' + progress + '%"></div>' +
        '</div>' +
      '</div>' +

      '<div class="bg-surface-raised border border-surface-border rounded-card p-5">' +
        '<p class="text-gray-400 text-xs uppercase tracking-widest mb-3 text-center">Rest Timer</p>' +
        '<div class="flex justify-center">' +
          '<svg width="140" height="140" viewBox="0 0 140 140">' +
            '<circle cx="70" cy="70" r="54" fill="none" stroke="#1a1a1a" stroke-width="8"/>' +
            '<circle cx="70" cy="70" r="54" fill="none" stroke="#39ff14" stroke-width="8" stroke-dasharray="339.3" stroke-dashoffset="120" stroke-linecap="round" transform="rotate(-90 70 70)"/>' +
            '<text x="70" y="66" text-anchor="middle" fill="#ffffff" font-size="26" font-weight="700" font-family="system-ui,sans-serif">01:14</text>' +
            '<text x="70" y="83" text-anchor="middle" fill="#555555" font-size="9" font-family="system-ui,sans-serif" letter-spacing="2">REMAINING</text>' +
          '</svg>' +
        '</div>' +
        '<!-- TODO: wire real countdown -->' +
        '<div class="flex items-center justify-center gap-5 mt-3">' +
          '<button class="text-gray-500 hover:text-white w-8 h-8 flex items-center justify-center text-lg" data-action="timer-minus">−</button>' +
          '<button class="bg-surface-card border border-surface-border rounded-full w-10 h-10 flex items-center justify-center text-white hover:border-brand-green focus-visible:outline-none" data-action="timer-toggle">' +
            '<i class="fa-solid fa-play text-xs"></i>' +
          '</button>' +
          '<button class="text-gray-500 hover:text-white w-8 h-8 flex items-center justify-center text-lg" data-action="timer-plus">+</button>' +
        '</div>' +
        '<div class="flex justify-center gap-2 mt-3">' +
          '<button class="px-3 py-1 text-xs rounded border border-surface-border text-gray-500 hover:border-brand-green hover:text-white" data-action="timer-preset" data-seconds="30">30s</button>' +
          '<button class="px-3 py-1 text-xs rounded border border-brand-green text-brand-green" data-action="timer-preset" data-seconds="60">60s</button>' +
          '<button class="px-3 py-1 text-xs rounded border border-surface-border text-gray-500 hover:border-brand-green hover:text-white" data-action="timer-preset" data-seconds="90">90s</button>' +
        '</div>' +
      '</div>' +

      upNextHtml +

      '<button class="w-full border border-red-900 text-red-500 rounded-card py-3 text-sm font-bold uppercase tracking-widest hover:bg-red-950 hover:text-red-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-800" ' +
        'data-action="end-session" data-session-id="' + esc(session.id) + '">' +
        'End Session Early' +
      '</button>'
    );
  }

  function renderActive(session) {
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
        '<a href="/analytics/session/start" ' +
          'class="inline-flex items-center gap-2 bg-brand-green text-surface-base font-bold uppercase tracking-widest text-sm px-6 py-3 rounded self-start hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green">' +
          '<i class="fa-solid fa-play text-xs"></i> Start a Session' +
        '</a>' +
      '</section>'
    );

    document.getElementById('dash-sidebar').classList.add('hidden');
    document.getElementById('dash-root').classList.remove('hidden');
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
      console.log('finish exercise', exerciseId, 'in session', sessionId);
      // TODO: POST to /analytics/session/{sessionId}/finish-exercise
    } else if (action === 'add-set') {
      console.log('add set for exercise', exerciseId);
      // TODO: POST to /analytics/session/{sessionId}/log-set
    } else if (action === 'timer-toggle') {
      console.log('timer: play/pause');
      // TODO: startTimer(remainingSeconds)
    } else if (action === 'timer-preset') {
      console.log('timer preset:', seconds, 's');
      // TODO: startTimer(Number(seconds))
    } else if (action === 'timer-minus') {
      console.log('timer: -15s');
    } else if (action === 'timer-plus') {
      console.log('timer: +15s');
    }
  }

  async function init() {
    document.getElementById('dash-root').addEventListener('click', handleClick);

    var data;
    try {
      var res = await fetch(API_URL);
      if (!res.ok) throw new Error('HTTP ' + res.status);
      data = await res.json();
    } catch (_) {
      data = { active: false, error: true };
    }

    if (data.active) {
      renderActive(data.session);
    } else {
      renderEmpty(!!data.error);
    }
  }

  document.addEventListener('DOMContentLoaded', init);
}());
