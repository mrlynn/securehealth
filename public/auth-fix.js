// Direct login script - include this in any page with a script tag to debug authentication
(function() {
  // Log authentication status
  console.log('Auth Fix script loaded');
  
  // Inactivity auto-logout configuration (milliseconds)
  const WARNING_BEFORE_LOGOUT_MS = 1 * 60 * 1000; // 1 minute warning window
  const INACTIVITY_LOGOUT_MS = 5 * 60 * 1000; // 5 minutes total inactivity
  const WARNING_AT_MS = INACTIVITY_LOGOUT_MS - WARNING_BEFORE_LOGOUT_MS; // 4 minutes
  
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
            '<p id="inactivityCountdown" style="margin:0;color:#6c757d">Signing out in 1:00</p>' +
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
    t.textContent = 'Inactivity: 5:00';
    document.body.appendChild(t);
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
    } catch (e) {
      console.warn('Keepalive ping failed', e);
    }
    hideWarning();
    recordActivity();
  }
  
  function forceLogout() {
    try { localStorage.removeItem('securehealth_user'); } catch (_) {}
    // Navigate to Symfony logout path (server will invalidate session and redirect to target)
    window.location.href = '/api/logout';
  }
  
  function recordActivity() {
    lastActivityAt = Date.now();
    hideWarning();
    scheduleTimers();
  }
  
  // Monitor user activity to reset timers
  ['mousemove', 'keydown', 'click', 'touchstart'].forEach(evt => {
    window.addEventListener(evt, recordActivity, { passive: true });
  });
  // Do not treat focus/scroll as activity (can be triggered by scripts/UI)
  document.addEventListener('visibilitychange', () => {
    // If user returns to tab and interacts, timers will reset via events
  });
  
  // Initialize timers and UI on load
  ensureTicker();
  scheduleTimers();
  startCountdownInterval();
  
  function checkAuth() {
    const storedUser = localStorage.getItem('securehealth_user');
    console.log('Current securehealth_user:', storedUser);
    
    if (!storedUser) {
      console.log('No user in localStorage');
      return;
    }
    
    try {
      const user = JSON.parse(storedUser);
      console.log('Parsed user:', user);
      
      // Fix user object if needed
      let fixed = false;
      
      if (!user.email && user.username) {
        user.email = user.username;
        fixed = true;
        console.log('Added missing email from username');
      }
      
      if (!user.username && user.email) {
        user.username = user.email;
        fixed = true;
        console.log('Added missing username from email');
      }
      
      if (!user.roles) {
        user.roles = ['ROLE_USER'];
        fixed = true;
        console.log('Added missing roles');
      } else if (!Array.isArray(user.roles)) {
        user.roles = [user.roles];
        fixed = true;
        console.log('Converted roles to array');
      }
      
      // If any fixes were applied, update localStorage
      if (fixed) {
        console.log('Fixing user object in localStorage');
        localStorage.setItem('securehealth_user', JSON.stringify(user));
        console.log('Fixed user saved:', JSON.stringify(user));
      }
      
    } catch (e) {
      console.error('Error parsing stored user:', e);
    }
  }
  
  // Check and fix auth on load
  checkAuth();
  
  // Expose global function
  window.fixAuth = checkAuth;
  
  console.log('Auth fix complete - call window.fixAuth() to run again');
})();