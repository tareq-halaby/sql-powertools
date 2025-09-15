<?php
/**
 * @var string $label
 * @var string|null $variant // primary|secondary
 * @var string|null $type // submit|button|reset
 * @var bool|null $disabled
 * @var string|null $icon // optional emoji
 * @var string|null $id // optional id attribute
 */
$variant = $variant ?? 'primary';
$type = $type ?? 'button';
$classes = ($variant === 'secondary')
  ? 'inline-flex items-center gap-2 px-4 py-2 rounded-2xl border text-sm font-medium dark:border-slate-700'
  : 'inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-sm font-medium bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900';
?>
<button <?= !empty($id) ? 'id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"' : '' ?> type="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" class="<?= $classes ?> disabled:opacity-50 flex items-center gap-2" <?= !empty($disabled) ? 'disabled' : '' ?>>
  <?php if (!empty($icon)): ?>
    <span><?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?></span>
  <?php endif; ?>
  <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
</button>


