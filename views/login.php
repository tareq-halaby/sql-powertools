  <div class="max-w-md mx-auto p-6">
    <h1 class="text-2xl font-semibold mb-4">PowerTools Login</h1>
    <div class="rounded-2xl border bg-white shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
      <h2 class="text-lg font-medium mb-3 flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3V12.75a3 3 0 00-3-3v-3A5.25 5.25 0 0012 1.5zm-3.75 8.25v-3a3.75 3.75 0 117.5 0v3h-7.5z" clip-rule="evenodd" /></svg> Admin Login</h2>
      <?php if (!empty($error)): ?>
      <div class="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/60 dark:text-red-200"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <form method="post" class="grid gap-3">
        <input type="hidden" name="action" value="login" />
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>" />
        <label class="grid gap-1">
          <span class="text-sm text-slate-600 dark:text-slate-300">Password</span>
          <input type="password" name="password" class="rounded-xl border px-3 py-2 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" autofocus />
        </label>
        <button class="px-4 py-2 rounded-2xl bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900">🔐 Login</button>
        <div class="text-xs text-slate-500 dark:text-slate-400">Tip: Set environment variable SAMPLER_PASSWORD to change the password.</div>
      </form>
    </div>
  </div>


