<?php /** @var string $title */ /** @var string $body */ ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($title ?? 'SQL PowerTools', ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        };
    </script>
    <style>
        @media (prefers-color-scheme: dark) {
            body {
                background: #0b1015;
                color: #e5e7eb;
            }
        }
    </style>
</head>

<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-900 dark:text-slate-100 transition-all duration-300" x-cloak>
    <?php $this->insert('partials/nav', ['authed' => $authed ?? !empty($_SESSION['sampler_authed'])]); ?>
    <div class="max-w-5xl mx-auto p-6">

        <?php if ($error): ?>
        <div
            class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-red-700 dark:border-red-800 dark:bg-red-950/60 dark:text-red-200">
            <div class="font-semibold">Error</div>
            <div class="text-sm"><?= e($error) ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty(getenv('READ_ONLY')) && filter_var(getenv('READ_ONLY'), FILTER_VALIDATE_BOOLEAN)): ?>
        <div
            class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-200">
            <div class="font-semibold">Read-only mode</div>
            <div class="text-sm">Cloning actions are disabled on this environment.</div>
        </div>
        <?php endif; ?>


        <?= $body ?>

        <footer class="mt-8 text-xs text-slate-500 flex justify-between items-center">
            <span>© <?= date('Y') ?> SQL PowerTools</span>
            <span>
                Developed by
                <a href="https://tareq.im" target="_blank" rel="noopener" class="underline hover:text-slate-700 dark:hover:text-slate-300">Tareq Halaby</a>
            </span>
        </footer>

    </div>
</body>

</html>
