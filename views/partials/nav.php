<nav class="fixed top-0 inset-x-0 z-40 backdrop-blur supports-[backdrop-filter]:bg-white/70 bg-white/95 dark:bg-slate-900/80 border-b dark:border-slate-700">
  <div class="max-w-5xl mx-auto px-6 h-14 flex items-center justify-between">
    <a href="./" class="text-lg font-semibold">SQL PowerTools</a>
    <div class="flex items-center gap-3">
      <?php $this->insert('partials/theme_toggle'); ?>
      <?php if (!empty($authed)): ?>
        <form method="post">
          <input type="hidden" name="action" value="logout" />
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['_csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
          <button class="px-3 py-1.5 rounded-xl border text-sm dark:border-slate-700" title="Logout">🚪 Logout</button>
        </form>
      <?php else: ?>
        <a href="./" class="px-3 py-1.5 rounded-xl border text-sm dark:border-slate-700" title="Login">🔐 Login</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<div class="h-14"></div>


