// Small app script to handle sidebar toggle, profile menu, clock and dark mode
document.addEventListener('DOMContentLoaded', function(){
  const mobileBtn = document.getElementById('mobileMenuBtn');
  const sidebar = document.getElementById('site-sidebar');
  const profileBtn = document.getElementById('profileBtn');
  const profileMenu = document.getElementById('profileMenu');
  const logoutLink = document.getElementById('logoutLink');
  const clockDisplay = document.getElementById('clockDisplay');
  const darkToggle = document.getElementById('darkModeToggle');

  if(mobileBtn && sidebar){
    mobileBtn.addEventListener('click', ()=>{
      sidebar.classList.toggle('open');
    });
  }

  if(profileBtn && profileMenu){
    profileBtn.addEventListener('click',(e)=>{
      e.stopPropagation(); profileMenu.classList.toggle('hidden');
    });
    document.addEventListener('click', ()=>{ profileMenu.classList.add('hidden'); });
  }

  if(logoutLink){
    logoutLink.addEventListener('click',(e)=>{
      e.preventDefault();
      showConfirm({
        title:'Logout?', message:'Are you sure you want to logout?', confirmText:'Logout', cancelText:'Cancel', type:'warning',
        onConfirm: ()=> { showSuccess('Goodbye!',800); setTimeout(()=> window.location.href='/comprog/web/logout.php',800); }
      });
    });
  }

  if(clockDisplay){
    function updateClock(){
      const d = new Date();
      clockDisplay.textContent = d.toLocaleString();
    }
    updateClock(); setInterval(updateClock,1000);
  }

  if(darkToggle){
    darkToggle.addEventListener('click', ()=>{
      document.documentElement.classList.toggle('dark');
      showInfo(document.documentElement.classList.contains('dark') ? 'Dark mode on' : 'Dark mode off', 1500);
    });
  }
});
// Small UI utilities: toast, spinner, dark mode, and camera helpers
(function () {
  window.app = {
    showToast: function (message, type = 'info', timeout = 3500) {
      var container = document.getElementById('toast-container');
      if (!container) return;
      var el = document.createElement('div');
      el.className = 'max-w-sm w-full bg-white/90 dark:bg-slate-800 border rounded-lg p-3 shadow-md flex items-start gap-3 ring-1 ring-slate-200 dark:ring-slate-700';
      var color = type === 'success' ? 'text-emerald-600' : type === 'error' ? 'text-rose-600' : 'text-slate-700';
      el.innerHTML = '<div class="' + color + ' text-xl">•</div><div class="flex-1"><div class="font-medium">' + message + '</div></div>';
      container.appendChild(el);
      setTimeout(function () { el.remove(); }, timeout);
    },

    showSpinner: function () {
      var s = document.getElementById('global-spinner');
      if (s) s.classList.remove('hidden');
    },
    hideSpinner: function () {
      var s = document.getElementById('global-spinner');
      if (s) s.classList.add('hidden');
    },

    initDarkMode: function () {
      var toggle = document.getElementById('darkModeToggle');
      var prefer = localStorage.getItem('darkMode');
      if (prefer === 'on' || (!prefer && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
      }
      if (toggle) toggle.addEventListener('click', function () {
        document.documentElement.classList.toggle('dark');
        localStorage.setItem('darkMode', document.documentElement.classList.contains('dark') ? 'on' : 'off');
      });
    },

    listCameras: async function () {
      if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return [];
      var devices = await navigator.mediaDevices.enumerateDevices();
      return devices.filter(function (d) { return d.kind === 'videoinput'; });
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    window.app.initDarkMode();
  });
})();

// Mini PJAX for Single Page Application Feel
document.addEventListener('DOMContentLoaded', () => {
    const mainContainer = document.getElementById('main-content');
    if (!mainContainer) return;

    window.app.navigate = async function(url, options = {}) {
        window.app.showSpinner();
        try {
            const res = await fetch(url, Object.assign({
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }, options));
            
            if (res.redirected) {
                url = res.url;
            }
            
            const html = await res.text();
            
            // Parse DOM
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Replace Main Content
            const newMain = doc.getElementById('main-content');
            if (newMain) {
                mainContainer.innerHTML = newMain.innerHTML;
            } else {
                window.location.href = url;
                return;
            }
            
            // Sync Sidebar Active states
            const newSidebar = doc.getElementById('site-sidebar');
            const oldSidebar = document.getElementById('site-sidebar');
            if (newSidebar && oldSidebar) {
                oldSidebar.innerHTML = newSidebar.innerHTML;
            }
            
            // Update URL
            if (options.method !== 'POST') {
                window.history.pushState(null, '', url);
            }
            
            bindPjaxEvents();
        } catch (e) {
            console.error('AJAX Nav Error', e);
            window.location.href = url;
        } finally {
            window.app.hideSpinner();
        }
    };

    function bindPjaxEvents() {
        document.querySelectorAll('a').forEach(a => {
            if (a.hasAttribute('data-pjax-bound')) return;
            a.setAttribute('data-pjax-bound', 'true');
            
        // Skip PJAX for pages that rely on inline scripts (e.g., scanner/map)
        if (a.hostname !== window.location.hostname || a.href.includes('export=csv') || a.hasAttribute('target') || a.href.includes('logout.php') || a.href.includes('scan.php') || a.href.includes('events.php')) return;
            
            a.addEventListener('click', e => {
                if (e.ctrlKey || e.metaKey || e.shiftKey) return;
                e.preventDefault();
                window.app.navigate(a.href);
            });
        });

        document.querySelectorAll('form').forEach(f => {
            if (f.hasAttribute('data-pjax-bound')) return;
            f.setAttribute('data-pjax-bound', 'true');
          const action = (f.action || window.location.href);
          if (f.id === 'loginForm' || f.id === 'scannerForm' || action.includes('scan.php') || action.includes('events.php')) return; 
            
            f.addEventListener('submit', e => {
                // Determine if exactly one submit button inside the form was clicked with a name
                const activeBtn = e.submitter;
                
                e.preventDefault();
                const method = f.method.toUpperCase();
                const url = new URL(f.action || window.location.href);
                const formData = new FormData(f);
                
                if (activeBtn && activeBtn.name) {
                    formData.append(activeBtn.name, activeBtn.value);
                }
                
                if (method === 'GET') {
                    const params = new URLSearchParams();
                    for (const [k, v] of formData.entries()) {
                        params.set(k, v);
                    }
                    url.search = params.toString();
                    window.app.navigate(url.toString());
                } else {
                    window.app.navigate(url.toString(), {
                        method: 'POST',
                        body: formData
                    });
                }
            });
        });
    }

    bindPjaxEvents();
    window.addEventListener('popstate', () => {
        window.app.navigate(window.location.href);
    });
});
