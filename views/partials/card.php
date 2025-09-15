<?php
/**
 * @var string $number Step number badge text
 * @var string $title  Section title
 * @var string $content HTML content for the card body (already escaped as needed)
 */
?>
<section class="rounded-2xl border bg-white shadow-sm p-0 dark:bg-slate-800 dark:border-slate-700 shadow-2xl transition-shadow overflow-hidden">
  <header class="px-5 pt-5 pb-3 border-b dark:border-slate-700 bg-white/60 dark:bg-slate-800/60 backdrop-blur">
  <h2 class="text-lg font-semibold flex items-center gap-2">
    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-white text-sm dark:bg-slate-100 dark:text-slate-900"><?= $number ?></span>
    <?= $title ?>
  </h2>
  </header>
  <div class="p-5">
    <?= $content ?>
  </div>
  <?php if (!empty($footer)): ?>
  <footer class="px-5 pt-3 pb-5 border-t dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/40">
    <div class="flex flex-wrap items-center gap-2">
      <?= $footer ?>
    </div>
  </footer>
  <?php endif; ?>
</section>


