<?php
/**
 * @var string $id Unique DOM id
 * @var string $title Modal title
 * @var string $body HTML content for modal body
 * @var string|null $confirmLabel
 * @var string|null $cancelLabel
 */
$confirmLabel = $confirmLabel ?? 'Confirm';
$cancelLabel = $cancelLabel ?? 'Cancel';
?>
<div id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>" class="fixed inset-0 hidden z-50" role="dialog" aria-modal="true">
  <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl dark:bg-slate-900 dark:text-slate-100 border dark:border-slate-700">
      <div class="px-5 py-4 border-b dark:border-slate-700 flex items-center justify-between">
        <h3 class="text-lg font-semibold flex items-center gap-2">
          <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-slate-100 dark:bg-slate-800">💡</span>
          <?= $title ?>
        </h3>
        <button type="button" data-modal-cancel class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close">✕</button>
      </div>
      <div class="px-5 py-4 max-h-[70vh] overflow-auto">
        <?= $body ?>
      </div>
      <div class="px-5 py-4 flex justify-end gap-2 border-t dark:border-slate-700">
        <button type="button" data-modal-cancel class="px-4 py-2 rounded-2xl border dark:border-slate-700"><?= htmlspecialchars($cancelLabel, ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" data-modal-confirm class="px-4 py-2 rounded-2xl bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900"><?= htmlspecialchars($confirmLabel, ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>


