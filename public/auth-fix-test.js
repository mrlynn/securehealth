// Test version of auth-fix.js with shorter timeouts for testing
(function() {
  // Log authentication status
  console.log('Auth Fix script loaded (TEST VERSION)');
  
  // TEST: Shorter timeouts for testing (30 seconds warning, 60 seconds total)
  const WARNING_BEFORE_LOGOUT_MS = 10 * 1000; // 10 seconds warning window
  const INACTIVITY_LOGOUT_MS = 30 * 1000; // 30 seconds total inactivity
  const WARNING_AT_MS = INACTIVITY_LOGOUT_MS - WARNING_BEFORE_LOGOUT_MS; // 20 seconds
  
  let warningTimerId = null;
  let logoutTimerId = null;
  let lastActivityAt = Date.now();
  
  function createOrGetModal() {
    let modal = document.getElementById('inactivityModal');
    if (modal) return modal;
    const wrapper = document.createElement('div');
    wrapper.innerHTML = (
      '<div id="inactivityModal" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);z-index:1050">' +
        '<div style="background:#fff;border-radius:8px;max-width:420px;width:90%;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">' +
          '<div style="padding:16px 20px;border-bottom:1px solid #eee">' +
            '<h3 style="margin:0;font-size:18px">Are you still there?</h3>' +
          '</div>' +
          '<div style="padding:16px 20px">' +
            '<p style="margin:0 0 8px">You will be signed out due to inactivity.</p>' +
            '<p id="inactivityCountdown" style="margin:0;color:#6c757d">Signing out in 0:10</p>' +
          '</div>' +
          '<div style="padding:12px 20px;border-top:1px solid #eee;display:flex;gap:8px;justify-content:flex-end">' +
            '<button id="staySignedInBtn" style="padding:8px 12px;border:0;border-radius:6px;background:#0d6efd;color:#fff;cursor:pointer">Stay signed in</button>' +
            '<button id="signOutNowBtn" style="padding:8px 12px;border:1px solid #dee2e6;border-radius:6px;background:#fff;color:#212529;cursor:pointer">Sign out now</button>' +
          '</div>' +
        '</div>' +
      '</div>'
    );
    document.body.appendChild(wrapper.firstChild);
    modal = document.getElementById('inactivityModal');
    document.getElementById('staySignedInBtn').addEventListener('click', staySignedIn);
    document.getElementById('signOutNowBtn').addEventListener('click', forceLogout);
    return modal;
  }
  
  function showWarning() {
    const modal = createOrGetModal();
    updateCountdown(Math.ceil((INACTIVITY_LOGOUT_MS - (Date.now() - lastActivityAt)) / 1000));
    modal.style.display = 'flex';
    console.log('Warning modal shown');
  }
  
  function hideWarning() {
    const modal = document.getElementById('inactivityModal');
    if (modal) modal.style.display = 'none';
  }
  
  function updateCountdown(seconds) {
    const el = document.getElementById('inactivityCountdown');
    if (!el) return;
    const m = Math.max(0, Math.floor(seconds / 60));
    const s = Math.max(0, seconds % 60);
    el.textContent = `Signing out in ${m}:${String(s).padStart(2, '0')}`;
  }
  
  function scheduleTimers() {
    clearTimeout(warningTimerId);
    clearTimeout(logoutTimerId);
    const now = Date.now();
    const inactiveFor = now - lastActivityAt;
    const toWarning = Math.max(0, WARNING_AT_MS - inactiveFor);
    const toLogout = Math.max(0, INACTIVITY_LOGOUT_MS - inactiveFor);
    
    console.log(`Scheduling timers: warning in ${toWarning}ms, logout in ${toLogout}ms`);
    
    warningTimerId = setTimeout(() => {
      showWarning();
      startCountdownInterval();
    }, toWarning);
    logoutTimerId = setTimeout(forceLogout, toLogout);
  }
  
  let countdownIntervalId = null;
  function startCountdownInterval() {
    clearInterval(countdownIntervalId);
    countdownIntervalId = setInterval(() => {
      const secondsLeft = Math.ceil((INACTIVITY_LOGOUT_MS - (Date.now() - lastActivityAt)) / 1000);
      updateCountdown(secondsLeft);
      updateTicker(secondsLeft);
      if (secondsLeft <= 0) {
        clearInterval(countdownIntervalId);
      }
    }, 1000);
  }

  function ensureTicker() {
    let t = document.getElementById('inactivityTicker');
    if (t) return t;
    t = document.createElement('div');
    t.id = 'inactivityTicker';
    t.style.position = 'fixed';
    t.style.right = '12px';
    t.style.bottom = '12px';
    t.style.padding = '6px 10px';
    t.style.background = 'rgba(13,110,253,0.92)';
    t.style.color = '#fff';
    t.style.borderRadius = '999px';
    t.style.fontSize = '12px';
    t.style.lineHeight = '1';
    t.style.zIndex = '1051';
    t.style.boxShadow = '0 4px 12px rgba(0,0,0,.15)';
    t.textContent = 'Inactivity: 0:30';
    document.body.appendChild(t);
    console.log('Timer ticker created');
    return t;
  }

  function updateTicker(seconds) {
    const t = ensureTicker();
    const m = Math.max(0, Math.floor(seconds / 60));
    const s = Math.max(0, seconds % 60);
    t.textContent = `Inactivity: ${m}:${String(s).padStart(2, '0')}`;
  }
  
  async function staySignedIn() {
    try {
      // Ping a lightweight public endpoint to keep session fresh
      await fetch('/api/health', { credentials: 'include' });
      console.log('Keepalive ping successful');
    } catch (e) {
      console.warn('Keepalive ping failed', e);
    }
    hideWarning();
    recordActivity();
  }
  
  function forceLogout() {
    console.log('Force logout triggered');
    try { localStorage.removeItem('securehealth_user'); } catch (_) {}
    // Navigate to Symfony logout path (server will invalidate session and redirect to target)
    window.location.href = '/api/logout';
  }
  
  function recordActivity() {
    lastActivityAt = Date.now();
    hideWarning();
    scheduleTimers();
    console.log('Activity recorded, timers reset');
  }
  
  // Monitor user activity to reset timers
  ['mousemove', 'keydown', 'click', 'touchstart'].forEach(evt => {
    window.addEventListener(evt, recordActivity, { passive: true });
  });
  
  // Initialize timers and UI on load
  console.log('Initializing timer system...');
  ensureTicker();
  scheduleTimers();
  startCountdownInterval();
  
  console.log('Timer system initialized successfully');
})();
