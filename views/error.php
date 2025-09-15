<?php /** @var string $title */ /** @var string $heading */ /** @var string $clientIp */ /** @var string $allowedIps */ /** @var string $retryUrl */ ?>

<div class="max-w-xl w-full bg-white rounded-2xl border border-slate-200 shadow-sm p-6 dark:bg-slate-900 dark:border-slate-700">
    <div class="flex items-center gap-3 mb-3">
        <span class="text-2xl">🚫</span>
        <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100">
            <?= htmlspecialchars($heading ?? 'Access restricted', ENT_QUOTES, 'UTF-8') ?>
        </h1>
    </div>
    <p class="text-sm text-slate-600 dark:text-slate-300 mb-4">This tool is locked to a specific allowlist of IP addresses for safety.</p>
    <div class="text-sm grid gap-2 mb-4">
        <div class="flex justify-between">
            <span class="text-slate-500">Your IP</span>
            <span class="font-mono text-slate-800 dark:text-slate-100"><?= htmlspecialchars($clientIp, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php if (!empty($allowedIps)): ?>
        <div class="flex justify-between">
            <span class="text-slate-500">Allowed IPs</span>
            <span class="font-mono text-slate-800 dark:text-slate-100"><?= htmlspecialchars($allowedIps, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endif; ?>
    </div>
    <div class="text-xs text-slate-500 mb-4">
        Update the <code>.env</code> (or environment) to include your IP in <code>ALLOW_IPS</code>.
        If behind a proxy/CDN, configure trusted proxy IPs and pass the real client IP.
    </div>
    <details class="text-xs text-slate-500 mb-3">
        <summary class="cursor-pointer">How to allow my IP?</summary>
        <div class="mt-2">
            <pre class="bg-slate-100 rounded p-2 overflow-auto dark:bg-slate-800">ALLOW_IPS=<?= htmlspecialchars($clientIp, ENT_QUOTES, 'UTF-8') ?></pre>
            <div class="mt-2">Multiple IPs: <code>ALLOW_IPS=1.2.3.4, 5.6.7.8</code></div>
        </div>
    </details>
    <a href="<?= htmlspecialchars($retryUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border text-sm dark:border-slate-700">Retry</a>
</div>


