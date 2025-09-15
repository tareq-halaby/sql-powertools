<?php
/**
 * @var string $label
 * @var string $name
 * @var string|null $value
 * @var string $type
 * @var string|null $placeholder
 * @var bool|null $required
 * @var string|null $help
 * @var string|null $error
 */
$error = $error ?? null;
$type = $type ?? 'text';
$id = 'fld_' . preg_replace('/[^a-z0-9_]+/i', '_', $name);
?>
<label class="grid gap-1">
  <span class="text-sm text-slate-600 dark:text-slate-300"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
  <?php if ($type === 'password'): ?>
  <div class="flex items-stretch">
    <input
      id="<?= $id ?>"
      class="w-full rounded-l-xl border border-r-0 px-3 py-2 <?= $error ? 'border-red-400' : '' ?> dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
      name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
      value="<?= htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8') ?>"
      type="password"
      <?= !empty($placeholder) ? 'placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
      <?= !empty($required) ? 'required' : '' ?>
    />
    <button type="button" id="toggle_<?= $id ?>" class="px-3 py-2 rounded-r-xl border <?= $error ? 'border-red-400' : 'border-slate-300 dark:border-slate-700' ?> bg-white dark:bg-slate-900 text-slate-600 hover:text-slate-800 dark:text-slate-300 dark:hover:text-slate-100" aria-label="Toggle password visibility">👁️</button>
  </div>
  <script>
    (function(){
      const input = document.getElementById('<?= $id ?>');
      const btn = document.getElementById('toggle_<?= $id ?>');
      btn?.addEventListener('click', function() {
        if (!input) return;
        const isPwd = input.type === 'password';
        input.type = isPwd ? 'text' : 'password';
        btn.textContent = isPwd ? '🙈' : '👁️';
      });
    })();
  </script>
  <?php else: ?>
  <input
    id="<?= $id ?>"
    class="rounded-xl border px-3 py-2 <?= $error ? 'border-red-400' : '' ?> dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
    name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
    value="<?= htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8') ?>"
    type="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
    <?= !empty($placeholder) ? 'placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
    <?= !empty($required) ? 'required' : '' ?>
  />
  <?php endif; ?>
  <?php if (!empty($help)): ?>
    <span class="text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($help, ENT_QUOTES, 'UTF-8') ?></span>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <span class="text-xs text-red-600 dark:text-red-400"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
  <?php endif; ?>
</label>


