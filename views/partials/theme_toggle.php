<?php
/** Simple theme toggle button with System/Light/Dark cycling **/
?>
<button id="themeToggle" type="button" class="px-3 py-1.5 rounded-xl border text-sm dark:border-slate-700 flex items-center" aria-label="Toggle theme">
  <span id="themeIcon" aria-hidden="true"></span>
</button>
<script>
  (function(){
    const btn = document.getElementById('themeToggle');
    const icon = document.getElementById('themeIcon');
    const modes = ['system','light','dark'];
    function current(){ return localStorage.getItem('theme') || 'system'; }
    function apply(mode){
      let m = mode;
      if (mode === 'system') m = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      document.documentElement.classList.toggle('dark', m === 'dark');
      btn.setAttribute('title', 'Theme: ' + (mode.charAt(0).toUpperCase()+mode.slice(1)));
      if (mode === 'system') { icon.innerHTML = '⚙️'; }
      else if (mode === 'light') { icon.innerHTML = '🔆'; }
      else { icon.innerHTML = '🌛'; }
    }
    function cycle(){
      const idx = modes.indexOf(current());
      const next = modes[(idx + 1) % modes.length];
      localStorage.setItem('theme', next);
      apply(next);
    }
    btn?.addEventListener('click', cycle);
    // react to system changes when in system mode
    try {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if (current()==='system') apply('system'); });
    } catch(e) {}
    apply(current());
  })();
  </script>


