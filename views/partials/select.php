<?php
/**
 * @var string $label
 * @var string $name
 * @var array $options // each: ['value' => string, 'label' => string]
 * @var string|null $value
 * @var bool|null $required
 * @var string|null $error
 * @var bool|null $allowCreateNew // if true, adds a "Create new…" option and paired input syncing
 * @var string|null $createNewPlaceholder
 */
$error = $error ?? null;
$id = 'sel_' . preg_replace('/[^a-z0-9_]+/i', '_', $name);
$createId = $id . '_create';
$hiddenId = $id . '_hidden';
$hasCreate = !empty($allowCreateNew);
$isCreateSelected = $hasCreate && $value && !in_array($value, array_map(fn($o)=>$o['value'], $options), true);
?>
<label class="grid gap-1">
  <span class="text-sm text-slate-600 dark:text-slate-300"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
  <select id="<?= $id ?>" class="rounded-xl border px-3 py-2 <?= $error ? 'border-red-400' : '' ?> dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" <?= !empty($required) ? 'required' : '' ?> <?= $hasCreate ? '' : 'name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"' ?> >
    <option value="">-- select --</option>
    <?php foreach ($options as $opt): $v = (string)$opt['value']; ?>
      <option value="<?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?>" <?= (string)$value === $v ? 'selected' : '' ?>><?= htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') ?></option>
    <?php endforeach; ?>
    <?php if ($hasCreate): ?>
      <option value="__create_new" <?= $isCreateSelected ? 'selected' : '' ?>>Create new…</option>
    <?php endif; ?>
  </select>
  <?php if ($hasCreate): ?>
    <input id="<?= $createId ?>" type="text" class="mt-2 rounded-xl border px-3 py-2 <?= $isCreateSelected ? '' : 'hidden' ?> dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100" placeholder="<?= htmlspecialchars($createNewPlaceholder ?? 'Enter name', ENT_QUOTES, 'UTF-8') ?>" value="<?= $isCreateSelected ? htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') : '' ?>" />
    <input id="<?= $hiddenId ?>" type="hidden" name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
    <script>
      (function(){
        const sel = document.getElementById('<?= $id ?>');
        const input = document.getElementById('<?= $createId ?>');
        const hidden = document.getElementById('<?= $hiddenId ?>');
        if (!sel || !input || !hidden) return;
        function syncHidden(){
          if (sel.value === '__create_new') {
            input.classList.remove('hidden');
            hidden.value = input.value.trim();
          } else {
            input.classList.add('hidden');
            hidden.value = sel.value;
          }
        }
        sel.addEventListener('change', syncHidden);
        input.addEventListener('input', syncHidden);
        syncHidden();
      })();
    </script>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <span class="text-xs text-red-600"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
  <?php endif; ?>
</label>


