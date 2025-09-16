<?php /** @var int $step */ /** @var ?string $error */ /** @var array $databases */ /** @var array $report */ /** @var array $post */ /** @var array $rowEstimates */ /** @var string $mermaid */ ?>

<div class="grid gap-6">

    <?php $this->insert('partials/card', [
        'number' => '1',
        'title' => 'Connect to MySQL',
        'content' => (function() use ($post) { ob_start(); ?>
    <div class="text-xs text-slate-500 dark:text-slate-400 mb-2 flex items-center gap-2">
        <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-slate-100 dark:bg-slate-800">💡</span>
        <span>Step 1: Enter your MySQL connection (host, port, user, password).</span>
    </div>
    <form method="post" class="grid gap-4" id="connectForm">
        <input type="hidden" name="step" value="2" />
        <div class="grid sm:grid-cols-2 gap-4">
            <?php $this->insert('partials/input', [
                'label' => 'Host',
                'name' => 'db_host',
                'value' => $post['db_host'] ?? ($defaults['db_host'] ?? 'localhost'),
                'required' => true,
            ]); ?>
            <?php $this->insert('partials/input', [
                'label' => 'Port',
                'name' => 'db_port',
                'value' => $post['db_port'] ?? ($defaults['db_port'] ?? '3306'),
                'required' => true,
            ]); ?>
            <?php $this->insert('partials/input', [
                'label' => 'User',
                'name' => 'db_user',
                'value' => $post['db_user'] ?? ($defaults['db_user'] ?? ''),
                'required' => true,
            ]); ?>
            <?php $this->insert('partials/input', [
                'label' => 'Password',
                'name' => 'db_pass',
                'type' => 'password',
                'value' => $post['db_pass'] ?? '',
            ]); ?>
        </div>
        <div class="hidden"></div>
    </form>
    <?php return ob_get_clean(); })()
      ,
        'footer' => (function(){ ob_start(); ?>
          <button  type="submit" form="connectForm" formmethod="post" formaction="" class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-sm font-medium bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 disabled:opacity-50">
            🔌 Connect &amp; Choose DB
          </button>
        <?php return ob_get_clean(); })()
      ]); ?>

    <?php if ($step >= 2): ?>
    <?php $this->insert('partials/card', [
        'number' => '2',
        'title' => 'Choose Source Database & Sampling Settings',
        'content' => (function() use ($post, $databases) { ob_start(); ?>
    <div class="text-xs text-slate-500 dark:text-slate-400 mb-2 flex items-center gap-2">
        <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-slate-100 dark:bg-slate-800">💡</span>
        <span>Step 2: Pick the source database, set how many rows per table, and choose or create a target
            database.</span>
    </div>
    <form method="post" class="grid gap-4" id="step2Form">
        <input type="hidden" name="step" value="3" />
        <input type="hidden" name="_csrf"
            value="<?= htmlspecialchars($_SESSION['_csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
        <input type="hidden" name="db_host" value="<?= e($post['db_host'] ?? 'localhost') ?>" />
        <input type="hidden" name="db_port" value="<?= e($post['db_port'] ?? '3306') ?>" />
        <input type="hidden" name="db_user" value="<?= e($post['db_user'] ?? '') ?>" />
        

        <div class="grid sm:grid-cols-3 gap-4">
            <?php $this->insert('partials/select', [
                'label' => 'Source database',
                'name' => 'source_db',
                'options' => array_map(fn($d) => ['value' => $d, 'label' => $d], $databases),
                'value' => $post['source_db'] ?? '',
                'required' => true,
            ]); ?>
            <?php $this->insert('partials/input', [
                'label' => 'Rows per table (max)',
                'name' => 'row_limit',
                'type' => 'number',
                'value' => $post['row_limit'] ?? '50',
            ]); ?>
            <label class="grid gap-1">
                <span class="text-sm text-slate-600 dark:text-slate-300">All rows</span>
                <div class="flex items-center gap-2">
                    <input id="chk_all_rows" name="all_rows" value="1" type="checkbox" class="h-4 w-4 accent-emerald-500" <?= !empty($post['all_rows']) ? 'checked' : '' ?> />
                    <span class="text-xs text-slate-500 dark:text-slate-400">Omit LIMIT and copy every row</span>
                </div>
            </label>
            <label class="grid gap-1">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-600 dark:text-slate-300">Target database</span>
                    <button type="button" id="openCreateDbInline" class="text-[11px] px-2 py-0.5 rounded-lg border dark:border-slate-700">➕ New</button>
                </div>
                <?php $this->insert('partials/select', [
                    'label' => '',
                    'name' => 'target_db',
                    'options' => array_map(fn($d) => ['value' => $d, 'label' => $d], $databases),
                    'value' => $post['target_db'] ?? '',
                ]); ?>
                <div class="text-[11px] text-slate-500 dark:text-slate-400" id="targetPreview">&nbsp;</div>
            </label>
        </div>
        <div class="hidden"></div>
    </form>
    <?php
          $modalBody = (function(){ ob_start(); ?>
    <label class="grid gap-1">
        <?php $this->insert('partials/input', [
                'label' => 'New database name',
                'name' => 'new_db_name',
                'id' => 'newDbName',
                'placeholder' => 'e.g. mydb_sample',
                'required' => false,
            ]); ?>
    </label>
    <?php return ob_get_clean(); })();
          $this->insert('partials/modal', [
            'id' => 'createTargetDbModal',
            'title' => 'Create Target Database',
            'body' => $modalBody,
            'confirmLabel' => 'Use Name',
            'cancelLabel' => 'Cancel',
          ]);
        ?>
    <script>
        (function() {
            const openBtn = document.getElementById('openCreateDb');
            const openBtnInline = document.getElementById('openCreateDbInline');
            const modal = document.getElementById('createTargetDbModal');
            const cancelBtns = modal ? Array.from(modal.querySelectorAll('[data-modal-cancel]')) : [];
            const confirmBtns = modal ? Array.from(modal.querySelectorAll('[data-modal-confirm]')) : [];
            const input = document.getElementById('newDbName');
            const targetSelect = document.getElementById('sel_target_db');
            const sourceSelect = document.getElementById('sel_source_db');
            const loadBtn = document.getElementById('btnLoadTables');
            const targetPreview = document.getElementById('targetPreview');

            function open() {
                if (modal) {
                    modal.classList.remove('hidden');
                    setTimeout(() => input?.focus(), 0);
                }
            }

            function close() {
                modal?.classList.add('hidden');
            }

            function validate() {
                const hasSource = !!(sourceSelect && sourceSelect.value);
                const hasTarget = !!(targetSelect && targetSelect.value);
                if (loadBtn) loadBtn.disabled = !hasSource;
                // Also reflect target selection state for other controls
                const cloneBtn = document.getElementById('btnCreateSample');
                const targetStructBtn = document.getElementById('btnDownloadTargetStructure');
                if (cloneBtn) cloneBtn.disabled = !hasTarget || cloneBtn.disabled; // remains disabled until action step enables
                if (targetStructBtn) targetStructBtn.disabled = !hasTarget;
            }

            function confirm() {
                const name = (input?.value || '').trim();
                if (!name) {
                    input?.focus();
                    return;
                }
                if (targetSelect) {
                    let opt = Array.from(targetSelect.options).find(o => o.value === name);
                    if (!opt) {
                        opt = document.createElement('option');
                        opt.value = name;
                        opt.textContent = name;
                        targetSelect.appendChild(opt);
                    }
                    targetSelect.value = name;
                }
                close();
                renderPreview();
            }
            openBtn?.addEventListener('click', open);
            openBtnInline?.addEventListener('click', open);
            cancelBtns.forEach(btn => btn.addEventListener('click', close));
            confirmBtns.forEach(btn => btn.addEventListener('click', confirm));
            modal?.addEventListener('click', (e) => {
                if (e.target === modal) close();
            });
            sourceSelect?.addEventListener('change', validate);
            targetSelect?.addEventListener('change', validate);
            function renderPreview(){
                if (!targetPreview) return;
                const src = sourceSelect?.value || '';
                const tgt = targetSelect?.value || '';
                const willUse = (tgt || (src ? src + '_sample' : ''));
                targetPreview.textContent = willUse ? ('Will use: ' + willUse) : '';
            }
            sourceSelect?.addEventListener('change', renderPreview);
            targetSelect?.addEventListener('change', renderPreview);
            validate();
            renderPreview();
        })();
    </script>
    <?php return ob_get_clean(); })()
      ,
        'footer' => (function(){ ob_start(); ?>
          <div class="flex gap-3">
            <button type="submit" form="step2Form" class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-sm font-medium bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900" id="btnLoadTables">📥 Load Tables</button>
            <button type="button" class="px-4 py-2 rounded-2xl text-sm border dark:border-slate-700" id="openCreateDb">➕ Create database</button>
          </div>
        <?php return ob_get_clean(); })()
      ]); ?>
    <?php endif; ?>

    <?php if ($step >= 3): ?>
    <?php
    $sourceDB = $post['source_db'] ?? '';
    try {
        $pdoDB = pdo_connect($sourceDB);
        $tables = get_tables($pdoDB, $sourceDB);
    } catch (Throwable $ex) {
        $tables = [];
    }
    ?>
    <?php $this->insert('partials/card', [
        'number' => '3',
        'title' => 'Select Action',
        'content' => (function() use ($post, $tables, $rowEstimates, $mermaid, $diagramEnabled, $dbSummary, $sensitiveColsByTable) { ob_start(); ?>
    <div class="text-xs text-slate-500 dark:text-slate-400 mb-2 flex items-center gap-2">
        <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-slate-100 dark:bg-slate-800">💡</span>
        <span>
            <b>Step 3:</b> Select the action you want to perform on your database.
            <span class="ml-2 text-emerald-600 dark:text-emerald-400 font-semibold" title="Hover each action card to see a brief description.">Hover for details.</span>
        </span>
    </div>
    <div class="grid gap-3" id="actionControls">
        <span class="text-sm text-slate-600 dark:text-slate-300 font-medium">Choose an action below:</span>
        <div class="grid sm:grid-cols-4 gap-3">
            <button type="button" id="cardClone" data-action="clone"
                class="group text-left rounded-2xl border dark:border-slate-700 p-4 hover:border-slate-400 dark:hover:border-slate-500 transition-shadow hover:shadow-lg focus:ring-2 focus:ring-emerald-400"
                title="Clone selected tables and a limited number of rows from the source to the target database.">
                <div class="font-medium mb-1 flex items-center gap-2">
                    <span class="text-lg">🧪</span> Clone sample
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    Pick tables and copy limited rows into target DB.
                </div>
                <div class="text-[11px] text-emerald-600 dark:text-emerald-400 opacity-0 group-hover:opacity-100 transition-opacity" title="Ideal for preparing smaller datasets for testing or development without copying full production volumes.">
                    Great for dev/test data.
                </div>
            </button>
            <button type="button" id="cardExport" data-action="export-structure"
                class="group text-left rounded-2xl border dark:border-slate-700 p-4 hover:border-slate-400 dark:hover:border-slate-500 transition-shadow hover:shadow-lg focus:ring-2 focus:ring-emerald-400"
                title="Export the SQL schema (CREATE TABLE, etc) for the source or target database.">
                <div class="font-medium mb-1 flex items-center gap-2">
                    <span class="text-lg">📦</span> Export structure
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    Download SQL schema for source/target (selected tables if any).
                </div>
                <div class="text-[11px] text-emerald-600 dark:text-emerald-400 opacity-0 group-hover:opacity-100 transition-opacity" title="Exports only DDL (CREATE TABLE, indexes, routines, triggers). No rows are included.">
                    No data, just structure.
                </div>
            </button>
            <button type="button" id="cardBackup" data-action="backup-full"
                class="group text-left rounded-2xl border dark:border-slate-700 p-4 hover:border-slate-400 dark:hover:border-slate-500 transition-shadow hover:shadow-lg focus:ring-2 focus:ring-emerald-400"
                title="Download a full SQL dump of the source database. Optionally compress with gzip.">
                <div class="font-medium mb-1 flex items-center gap-2">
                    <span class="text-lg">💾</span> Full backup (source)
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    Stream a complete dump of the source DB (gzip optional).
                </div>
                <div class="text-[11px] text-emerald-600 dark:text-emerald-400 opacity-0 group-hover:opacity-100 transition-opacity" title="Create a full backup before migrations or risky operations. Gzip reduces size and speeds transfers.">
                    Recommended before major changes.
                </div>
            </button>
            <button type="button" id="cardDiagram" data-action="diagram"
                class="group text-left rounded-2xl border dark:border-slate-700 p-4 hover:border-slate-400 dark:hover:border-slate-500 transition-shadow hover:shadow-lg focus:ring-2 focus:ring-emerald-400 <?= empty($diagramEnabled) ? 'opacity-50 cursor-not-allowed' : '' ?>"
                <?= empty($diagramEnabled) ? 'disabled' : '' ?>
                title="Visualize your database structure and relationships as a Mermaid ER diagram.">
                <div class="font-medium mb-1 flex items-center gap-2">
                    <span class="text-lg">📊</span> Database diagram
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    View Mermaid ER diagram of relationships.
                </div>
                <div class="text-[11px] text-emerald-600 dark:text-emerald-400 opacity-0 group-hover:opacity-100 transition-opacity" title="Copy-ready Mermaid code to share or embed in docs and diagrams.">
                    Great for documentation &amp; planning.
                </div>
            </button>
        </div>
    </div>
    <form method="post" class="grid gap-4" id="tablesForm">
        <input type="hidden" name="step" value="4" />
        <input type="hidden" name="_csrf"
            value="<?= htmlspecialchars($_SESSION['_csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
        <?php foreach (['db_host','db_port','db_user','source_db','row_limit','all_rows','target_db'] as $k): ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e($post[$k] ?? '') ?>" />
        <?php endforeach; ?>

        <div id="progressBanner" class="hidden rounded-xl border border-slate-200 dark:border-slate-700 bg-white/70 dark:bg-slate-900/60 px-3 py-2 text-xs text-slate-700 dark:text-slate-300 flex items-center gap-2 mt-7">
            <span class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-slate-400 border-t-transparent"></span>
            <span id="progressText">Working…</span>
        </div>

        <div id="tablesToolbar"
            class="flex items-center justify-between mt-6 bg-slate-50 dark:bg-slate-900/50 rounded-xl px-3 py-1.5">
            <div class="text-sm text-slate-600 dark:text-slate-300">Checked tables will be processed. If you leave all
                unchecked, the tool will process <b>all tables</b>.</div>
            <div class="flex items-center gap-2">
                <?php $this->insert('partials/button', [
                    'label' => 'Select all',
                    'variant' => 'secondary',
                    'type' => 'button',
                    'id' => 'selectAll',
                ]); ?>
                <?php $this->insert('partials/button', [
                    'label' => 'Clear',
                    'variant' => 'secondary',
                    'type' => 'button',
                    'id' => 'clearAll',
                ]); ?>
            </div>
        </div>

        <div id="tablesPicker" class="grid sm:grid-cols-3 gap-2 max-h-80 overflow-auto border rounded-xl p-3 mt-0.5">
            <?php $maskOverrides = (array)($post['mask_cols'] ?? []); foreach ($tables as $t): $est = $rowEstimates[$t] ?? null; $sens = (int)count($sensitiveColsByTable[$t] ?? []); $chips = array_values(array_unique((array)($maskOverrides[$t] ?? []))); ?>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="tables[]" value="<?= e($t) ?>" class="h-4 w-4" />
                <span>
                    <?= e($t) ?>
                    <?php if ($sens > 0): ?>
                    <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-[10px]" title="<?= $sens ?> sensitive column<?= $sens>1?'s':'' ?> detected">
                        🔒 <?= $sens ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($chips)): ?>
                    <span class="mt-0.5 block">
                        <?php foreach ($chips as $ch): ?>
                        <span class="mr-1 inline-flex items-center rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 text-[10px]" title="Masked column"><?= e($ch) ?></span>
                        <?php endforeach; ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($est !== null): ?>
                    <span class="text-slate-500">(≈ <?= number_format((int) $est) ?>)</span>
                    <?php endif; ?>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
        <div id="tablesHint" class="text-xs text-slate-500 dark:text-slate-400">
            <strong>(≈)</strong> Counts are approximate (from
            information_schema).
        </div>
        <?php $maskOverrides = (array)($post['mask_cols'] ?? []); ?>
        <div id="maskingPanel" class="mt-2 text-xs bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 rounded-xl p-3">
            <?php if (!empty($maskOverrides)): ?>
            <div class="flex items-start justify-between gap-2">
                <div>
                    <div class="text-slate-700 dark:text-slate-300 mb-1 font-medium">Masking summary (overrides)</div>
                    <ul class="space-y-1">
                        <?php foreach ($maskOverrides as $tbl => $cols): $cols = array_values(array_unique((array)$cols)); ?>
                        <li class="text-slate-600 dark:text-slate-400">
                            <span class="font-mono text-slate-800 dark:text-slate-200"><?= e($tbl) ?></span>:
                            <?php if (!empty($cols)): ?>
                                <span class="text-slate-700 dark:text-slate-300"><?= e(implode(', ', array_map('strval', $cols))) ?></span>
                            <?php else: ?>
                                <span class="italic text-slate-500">no columns (unmasked)</span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="flex items-center gap-2">
                    <label class="inline-flex items-center gap-1 text-[11px] text-slate-700 dark:text-slate-300 cursor-pointer" title="When enabled, detected or selected sensitive columns are overwritten with masked values in clones and sanitized backups.">
                        <input type="checkbox" name="mask_sensitive" value="1" class="h-4 w-4 accent-emerald-500" />
                        <span>Mask password-like columns</span>
                    </label>
                    <button type="button" id="openMasking" class="px-3 py-1.5 rounded-xl border text-[11px] dark:border-slate-700">Masking options</button>
                    <span class="text-[11px] text-slate-500" id="maskSelectedLabel">(0 selected)</span>
                    <div class="relative">
                        <button type="button" id="openMaskingHelp" class="px-2 py-1 rounded-lg border text-[11px] dark:border-slate-700" aria-haspopup="true" aria-expanded="false">?</button>
                        <div id="maskingHelpPopover" class="hidden absolute right-0 mt-1 w-72 z-10 rounded-lg border bg-white dark:bg-slate-900 dark:text-slate-100 text-[11px] p-3 shadow-lg dark:border-slate-700">
                            <div class="font-semibold mb-1">About masking & deterministic</div>
                            <div class="text-slate-600 dark:text-slate-300">
                                Masking overwrites sensitive fields (passwords, tokens, secrets) in cloned/sanitized data.
                                Deterministic (ORDER BY PK) sorts by primary key before LIMIT so samples are reproducible.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <?php 
            $selectedTables = array_values(array_map('strval', (array)($post['tables'] ?? [])));
            $autoTables = array_filter($sensitiveColsByTable, fn($v) => !empty($v));
            $totalSensitiveTables = count($autoTables);
            if (!empty($selectedTables)) {
                $autoCount = 0;
                foreach ($selectedTables as $tSel) { if (!empty($sensitiveColsByTable[$tSel] ?? [])) { $autoCount++; } }
            } else {
                $autoCount = $totalSensitiveTables;
            }
            $sensitiveTableKeys = array_keys($autoTables);
            ?>
            <div class="flex items-center justify-between gap-3 text-slate-600 dark:text-slate-400">
                <div>
                    Auto-detected masking will apply to 
                    <span id="autoMaskCount" class="font-semibold text-slate-800 dark:text-slate-200" data-total="<?= (int)$totalSensitiveTables ?>" data-table-map='<?= json_encode(array_fill_keys($sensitiveTableKeys, 1), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>'><?= (int)$autoCount ?></span> 
                    <span id="autoMaskPlural">table<?= $autoCount===1?'':'s' ?></span>.
                    <span class="ml-1 text-slate-500">Use “Masking options” to override.</span>
                </div>
                <div class="flex items-center gap-2">
                    <label class="inline-flex items-center gap-1 text-[11px] text-slate-700 dark:text-slate-300 cursor-pointer" title="When enabled, detected or selected sensitive columns are overwritten with masked values in clones and sanitized backups.">
                        <input type="checkbox" name="mask_sensitive" value="1" class="h-4 w-4 accent-emerald-500" />
                        <span>Mask password-like columns</span>
                    </label>
                    <button type="button" id="openMasking" class="px-3 py-1.5 rounded-xl border text-[11px] dark:border-slate-700">Masking options</button>
                    <span class="text-[11px] text-slate-500" id="maskSelectedLabel">(0 selected)</span>
                    <div class="relative">
                        <button type="button" id="openMaskingHelp2" class="px-2 py-1 rounded-lg border text-[11px] dark:border-slate-700" aria-haspopup="true" aria-expanded="false">?</button>
                        <div id="maskingHelpPopover2" class="hidden absolute right-0 mt-1 w-72 z-10 rounded-lg border bg-white dark:bg-slate-900 dark:text-slate-100 text-[11px] p-3 shadow-lg dark:border-slate-700">
                            <div class="font-semibold mb-1">About masking & deterministic</div>
                            <div class="text-slate-600 dark:text-slate-300">
                                Masking overwrites sensitive fields (passwords, tokens, secrets) in cloned/sanitized data.
                                Deterministic (ORDER BY PK) sorts by primary key before LIMIT so samples are reproducible.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div id="deterministicPanel" class="text-xs bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 rounded-xl p-3">
            <div class="flex items-center justify-between gap-3 text-slate-600 dark:text-slate-400">
                <div>
                    <div class="text-slate-700 dark:text-slate-300 mb-1 font-medium font-bold">Deterministic sampling</div>
                    <div class="text-slate-600 dark:text-slate-400">Order rows by primary key before LIMIT to make samples reproducible.</div>
                </div>
                <div class="flex items-center gap-3">
                    <label class="inline-flex items-center gap-1 text-[11px] text-slate-700 dark:text-slate-300 cursor-pointer">
                        <input type="checkbox" name="order_by_pk" value="1" class="h-4 w-4 accent-emerald-500" />
                        <span>Enable</span>
                    </label>
                    <div class="relative">
                        <button type="button" id="openDetHelp" class="px-2 py-1 rounded-lg border text-[11px] dark:border-slate-700" aria-haspopup="true" aria-expanded="false">?</button>
                        <div id="detHelpPopover" class="hidden absolute right-0 mt-1 w-72 z-10 rounded-lg border bg-white dark:bg-slate-900 dark:text-slate-100 text-[11px] p-3 shadow-lg dark:border-slate-700">
                            <div class="font-semibold mb-1">Deterministic sampling</div>
                            <div class="text-slate-600 dark:text-slate-300">
                                When enabled, rows are ordered by the primary key before applying LIMIT.
                                This makes clones reproducible across runs. Disable for maximum raw speed.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="targetMissingNotice"
            class="my-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 dark:bg-amber-900/20 dark:border-amber-800 hidden">
            ⚠ Select a target database to enable cloning and target-structure export.
        </div>

        <div id="actionCtas" class="hidden"></div>
    </form>
    <?php
          $maskBody = (function() use ($tables, $sensitiveColsByTable, $post) { ob_start(); ?>
    <div class="text-xs text-slate-600 dark:text-slate-300 mb-2">Select columns to mask per table. Leave unchecked to keep original values.</div>
    <div class="flex items-center justify-between mb-2 text-xs">
      <div class="text-slate-600 dark:text-slate-400">Click to toggle per-table selections</div>
      <div class="flex items-center gap-2">
        <button type="button" id="maskSelectAll" class="px-2 py-1 rounded-lg border dark:border-slate-700">Select all</button>
        <button type="button" id="maskClearAll" class="px-2 py-1 rounded-lg border dark:border-slate-700">Clear</button>
      </div>
    </div>
    <div class="max-h-72 overflow-auto pr-1" id="maskingList">
      <?php $selectedTables = array_values(array_map('strval', (array)($post['tables'] ?? []))); $anyMaskedTables = false; foreach ($tables as $t): if (!empty($selectedTables) && !in_array($t, $selectedTables, true)) continue; $sensCols = $sensitiveColsByTable[$t] ?? []; if (empty($sensCols)) continue; $anyMaskedTables = true; ?>
      <div class="mb-3 mask-table-block" data-table="<?= e($t) ?>">
        <div class="text-sm font-medium text-slate-700 dark:text-slate-200 mb-1"><?= e($t) ?></div>
        <div class="grid sm:grid-cols-2 gap-2">
          <?php foreach ($sensCols as $col): ?>
          <label class="inline-flex items-center gap-2 text-xs">
            <input type="checkbox" name="mask_cols[<?= e($t) ?>][]" value="<?= e($col) ?>" class="h-4 w-4 accent-emerald-500" />
            <span class="font-mono"><?= e($col) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; if (!$anyMaskedTables): ?>
        <div class="text-xs text-slate-500">No sensitive columns detected in this database.</div>
      <?php endif; ?>
    </div>
    <script>
      (function(){
        const root = document.getElementById('maskingModal');
        const list = root?.querySelector('#maskingList');
        const selAll = root?.querySelector('#maskSelectAll');
        const clrAll = root?.querySelector('#maskClearAll');
        selAll?.addEventListener('click', ()=>{
          list?.querySelectorAll('input[type=checkbox][name^="mask_cols["]').forEach(cb => cb.checked = true);
        });
        clrAll?.addEventListener('click', ()=>{
          list?.querySelectorAll('input[type=checkbox][name^="mask_cols["]').forEach(cb => cb.checked = false);
        });
      })();
    </script>
    <?php return ob_get_clean(); })();
          $this->insert('partials/modal', [
            'id' => 'maskingModal',
            'title' => 'Masking Options',
            'body' => $maskBody,
            'confirmLabel' => 'Done',
            'cancelLabel' => 'Cancel',
          ]);
        ?>
    <script>
        (function(){
            const btn = document.getElementById('openMasking');
            const btn2 = document.getElementById('openMaskingHelp');
            const btn2b = document.getElementById('openMaskingHelp2');
            const detBtn = document.getElementById('openDetHelp');
            const rowLimitInput = document.querySelector('#step2Form input[name="row_limit"]');
            const allRowsChk = document.getElementById('chk_all_rows');
            const modal = document.getElementById('maskingModal');
            const maskChk = document.querySelector('input[name="mask_sensitive"]');
            const cancelBtns = modal ? Array.from(modal.querySelectorAll('[data-modal-cancel]')) : [];
            const confirmBtns = modal ? Array.from(modal.querySelectorAll('[data-modal-confirm]')) : [];
            function syncAllRowsState(){
                if (!rowLimitInput || !allRowsChk) return;
                const on = !!allRowsChk.checked;
                rowLimitInput.disabled = on;
                rowLimitInput.classList.toggle('opacity-50', on);
            }
            allRowsChk?.addEventListener('change', syncAllRowsState);
            syncAllRowsState();
            function open(){
                if (!modal) return;
                // Filter table blocks by current selection
                const selected = Array.from(document.querySelectorAll('#tablesForm input[name="tables[]"]:checked')).map(el => el.value);
                const blocks = modal.querySelectorAll('.mask-table-block');
                if (selected.length === 0) {
                    blocks.forEach(b => b.classList.remove('hidden'));
                } else {
                    blocks.forEach(b => {
                        const t = b.getAttribute('data-table');
                        b.classList.toggle('hidden', !selected.includes(t));
                    });
                }
                // If masking is enabled and nothing is selected yet, pre-select all visible checkboxes
                if (maskChk && maskChk.checked) {
                    const anyChecked = modal.querySelector('input[type="checkbox"][name^="mask_cols["]:checked');
                    if (!anyChecked) {
                        modal.querySelectorAll('input[type="checkbox"][name^="mask_cols["]').forEach(cb => cb.checked = !cb.closest('.mask-table-block')?.classList.contains('hidden'));
                    }
                }
                modal.classList.remove('hidden');
            }
            function close(){ modal?.classList.add('hidden'); }
            function clearSelections(){
                const boxes = modal?.querySelectorAll('input[type=checkbox][name^="mask_cols["]');
                boxes && boxes.forEach(cb => { cb.checked = false; });
            }
            btn?.addEventListener('click', open);
            // When enabling mask checkbox, pre-select all in the modal for convenience
            maskChk?.addEventListener('change', () => {
                if (!modal) return;
                if (maskChk.checked) {
                    modal.querySelectorAll('input[type="checkbox"][name^="mask_cols["]').forEach(cb => cb.checked = true);
                }
            });
            // Live update auto-masking count based on selected tables
            (function(){
                const countEl = document.getElementById('autoMaskCount');
                const pluralEl = document.getElementById('autoMaskPlural');
                if (!countEl) return;
                const tableMap = (()=>{ try { return JSON.parse(countEl.getAttribute('data-table-map')||'{}'); } catch(e){ return {}; } })();
                function recompute(){
                    const selected = Array.from(document.querySelectorAll('#tablesForm input[name="tables[]"]:checked')).map(el => el.value);
                    let n = 0;
                    if (selected.length === 0) { n = parseInt(countEl.getAttribute('data-total')||'0', 10) || 0; }
                    else { selected.forEach(t => { if (Object.prototype.hasOwnProperty.call(tableMap, t)) n++; }); }
                    countEl.textContent = String(n);
                    if (pluralEl) pluralEl.textContent = n === 1 ? 'table' : 'tables';
                }
                document.getElementById('tablesForm')?.addEventListener('change', recompute);
                recompute();
            })();
            // Track how many columns are selected in masking overrides
            (function(){
                const labelEls = Array.from(document.querySelectorAll('#maskSelectedLabel'));
                function computeCount(){
                    // hidden inputs reflect last confirmed selections
                    const hidden = Array.from(document.querySelectorAll('input[type="hidden"][name^="mask_cols["]'));
                    return hidden.length;
                }
                function render(){
                    const n = computeCount();
                    labelEls.forEach(el => el.textContent = `(${n} selected)`);
                }
                // When user clicks Done in modal, new hidden inputs will be posted on next render.
                // For immediate feedback, update when modal checkboxes change.
                modal?.addEventListener('change', (e)=>{
                    const target = e.target;
                    if (target && target.matches('input[type="checkbox"][name^="mask_cols["]')) {
                        // Count currently checked in modal as a preview
                        const current = modal.querySelectorAll('input[type="checkbox"][name^="mask_cols["]:checked').length;
                        labelEls.forEach(el => el.textContent = `(${current} selected)`);
                    }
                });
                render();
            })();
            function togglePopover(btnEl, popover){
                const expanded = btnEl.getAttribute('aria-expanded') === 'true';
                btnEl.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                popover.classList.toggle('hidden', expanded);
                function onDoc(e){ if (!popover.contains(e.target) && e.target !== btnEl){ popover.classList.add('hidden'); btnEl.setAttribute('aria-expanded','false'); document.removeEventListener('click', onDoc); } }
                if (!expanded) setTimeout(()=>document.addEventListener('click', onDoc),0);
            }
            const pop = document.getElementById('maskingHelpPopover');
            const pop2 = document.getElementById('maskingHelpPopover2');
            const detPop = document.getElementById('detHelpPopover');
            btn2?.addEventListener('click', () => togglePopover(btn2, pop));
            btn2b?.addEventListener('click', () => togglePopover(btn2b, pop2));
            detBtn?.addEventListener('click', () => togglePopover(detBtn, detPop));
            cancelBtns.forEach(btn => btn.addEventListener('click', () => { clearSelections(); close(); }));
            confirmBtns.forEach(btn => btn.addEventListener('click', close));
            modal?.addEventListener('click', (e)=>{ if (e.target === modal) close(); });
            // Reset removed by design; users can reopen Masking Options to adjust
        })();
    </script>
    <div class="flex flex-wrap items-center gap-3 mt-4" id="exportCtas">
        <form method="post" id="exportSourceForm">
            <?php foreach (['db_host','db_port','db_user','source_db','target_db'] as $k): ?>
            <input type="hidden" name="<?= e($k) ?>" value="<?= e($post[$k] ?? '') ?>" />
            <?php endforeach; ?>
            <?php foreach (($post['mask_cols'] ?? []) as $tbl => $cols): foreach ((array)$cols as $col): ?>
            <input type="hidden" name="mask_cols[<?= e($tbl) ?>][]" value="<?= e($col) ?>" />
            <?php endforeach; endforeach; ?>
            <?php foreach (($post['tables'] ?? []) as $t): ?>
            <input type="hidden" name="tables[]" value="<?= e($t) ?>" />
            <?php endforeach; ?>
            <input type="hidden" name="_csrf"
                value="<?= htmlspecialchars($_SESSION['_csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
            <input type="hidden" name="action" value="download_sql_structure" />
            <input type="hidden" name="from" value="source" />
            
        </form>
        <form method="post" id="exportTargetForm">
            <?php foreach (['db_host','db_port','db_user','source_db','target_db'] as $k): ?>
            <input type="hidden" name="<?= e($k) ?>" value="<?= e($post[$k] ?? '') ?>" />
            <?php endforeach; ?>
            <?php foreach (($post['mask_cols'] ?? []) as $tbl => $cols): foreach ((array)$cols as $col): ?>
            <input type="hidden" name="mask_cols[<?= e($tbl) ?>][]" value="<?= e($col) ?>" />
            <?php endforeach; endforeach; ?>
            <?php foreach (($post['tables'] ?? []) as $t): ?>
            <input type="hidden" name="tables[]" value="<?= e($t) ?>" />
            <?php endforeach; ?>
            <input type="hidden" name="_csrf"
                value="<?= htmlspecialchars($_SESSION['_csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
            <input type="hidden" name="action" value="download_sql_structure" />
            <input type="hidden" name="from" value="target" />
            
        </form>
    </div>
    <div class="mt-3 hidden" id="backupPanel">
        <?php if (!empty($dbSummary)): ?>
        <div
            class="mb-4 p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
            <div class="flex items-center gap-2 mb-3">
                <span class="text-lg animate-pulse">📊</span>
                <h3 class="font-semibold text-slate-800 dark:text-slate-200 tracking-tight">Database Summary</h3>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <div class="text-center">
                    <div class="text-2xl font-extrabold text-slate-800 dark:text-slate-200">
                        <?= number_format($dbSummary['table_count']) ?></div>
                    <div class="text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wide">Tables</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-extrabold text-slate-800 dark:text-slate-200">
                        <?= number_format($dbSummary['total_rows']) ?></div>
                    <div class="text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wide">Total Rows</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-extrabold text-slate-800 dark:text-slate-200">
                        <?= number_format($dbSummary['total_size_mb'], 1) ?>MB</div>
                    <div class="text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wide">Total Size</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-semibold text-emerald-700 dark:text-emerald-400">
                        <?= e($dbSummary['estimated_time']) ?></div>
                    <div class="text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wide">Est. Time</div>
                </div>
            </div>

            <?php if ($dbSummary['total_size_mb'] > 0): ?>
            <div
                class="mb-4 p-3 rounded-lg bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700">
                <div class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 flex items-center gap-2">
                    <span
                        class="inline-flex h-5 w-5 items-center justify-center rounded bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400">📐</span>
                    Size Breakdown
                </div>
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-600 dark:text-slate-400">Data:</span>
                        <span
                            class="text-slate-700 dark:text-slate-300"><?= number_format($dbSummary['data_size_mb'], 1) ?>MB</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600 dark:text-slate-400">Indexes:</span>
                        <span
                            class="text-slate-700 dark:text-slate-300"><?= number_format($dbSummary['index_size_mb'], 1) ?>MB</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($dbSummary['largest_tables'])): ?>
            <div class="mb-4">
                <div class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 flex items-center gap-2">
                    <span
                        class="inline-flex h-5 w-5 items-center justify-center rounded bg-slate-200 dark:bg-slate-800">📦</span>
                    Largest Tables
                    <span class="ml-2 text-xs text-slate-400 dark:text-slate-500">(Top
                        <?= count($dbSummary['largest_tables']) ?>)</span>
                </div>
                <div class="overflow-x-auto">
                    <table
                        class="min-w-full text-xs border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900/60 shadow-sm">
                        <thead>
                            <tr class="bg-slate-100 dark:bg-slate-800">
                                <th class="px-3 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Table
                                </th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-700 dark:text-slate-200">Rows
                                </th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-700 dark:text-slate-200">Size
                                    (MB)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dbSummary['largest_tables'] as $table): ?>
                            <tr
                                class="border-t border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition group">
                                <td
                                    class="px-3 py-2 text-slate-700 dark:text-slate-200 font-mono group-hover:underline">
                                    <?= e($table['TABLE_NAME']) ?></td>
                                <td class="px-3 py-2 text-right text-slate-600 dark:text-slate-400">
                                    <?= number_format($table['TABLE_ROWS']) ?></td>
                                <td class="px-3 py-2 text-right text-slate-600 dark:text-slate-400">
                                    <?= number_format($table['size_mb'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="border-t border-slate-200 dark:border-slate-700 pt-3">
                <div class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2 flex items-center gap-2">
                    <span
                        class="inline-flex h-5 w-5 items-center justify-center rounded bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">💡</span>
                    Recommendations
                </div>
                <ul class="text-xs text-slate-600 dark:text-slate-400 space-y-1 pl-1">
                    <?php foreach ($dbSummary['recommendations'] as $rec): ?>
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 text-emerald-500">•</span>
                        <span><?= e($rec) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <div class="text-xs text-slate-500 dark:text-slate-400 mb-2 flex items-center gap-2">
            <span
                class="inline-flex h-5 w-5 items-center justify-center rounded bg-slate-100 dark:bg-slate-800">💡</span>
            <span>
                Download a complete dump of the selected source database.
                <span class="inline-block ml-1 text-emerald-600 dark:text-emerald-400 font-semibold">Enable gzip for
                    large databases.</span>
            </span>
        </div>
        <form method="post" id="fullBackupForm"
            class="flex flex-wrap items-center gap-3 bg-slate-50 dark:bg-slate-900/40 p-3 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
            <?php foreach (['db_host','db_port','db_user','db_pass','source_db'] as $k): ?>
            <input type="hidden" name="<?= e($k) ?>" value="<?= e($post[$k] ?? '') ?>" />
            <?php endforeach; ?>
            <?php foreach (($post['mask_cols'] ?? []) as $tbl => $cols): foreach ((array)$cols as $col): ?>
            <input type="hidden" name="mask_cols[<?= e($tbl) ?>][]" value="<?= e($col) ?>" />
            <?php endforeach; endforeach; ?>
            <input type="hidden" name="mask_sensitive" value="<?= e($post['mask_sensitive'] ?? '') ?>" />
            <input type="hidden" name="_csrf"
                value="<?= htmlspecialchars($_SESSION['_csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
            <input type="hidden" name="action" value="download_full_backup" />
            <label
                class="inline-flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300 mr-3 cursor-pointer hover:text-emerald-600 dark:hover:text-emerald-400 transition">
                <input type="checkbox" name="gzip" value="1" class="h-4 w-4 accent-emerald-500" />
                <span>Gzip compress</span>
            </label>
            
        </form>
    </div>
    <div id="diagramPanel" class="mt-3 hidden">
        <div class="text-xs text-slate-500 dark:text-slate-400 mb-2 flex items-center gap-2">
            <span
                class="inline-flex h-5 w-5 items-center justify-center rounded bg-slate-100 dark:bg-slate-800">📊</span>
            <span>Mermaid ER Diagram</span>
        </div>
        <div class="flex items-center justify-between mb-2">
            <div class="text-sm font-medium">Diagram code</div>
        </div>
        <textarea id="diagramTextInline"
            class="w-full h-64 rounded-xl border px-3 py-2 font-mono text-xs bg-slate-50 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
            readonly><?= htmlspecialchars($mermaid, ENT_QUOTES, 'UTF-8') ?></textarea>
        
        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Paste into a viewer (e.g., <a class="inline-block ml-1 text-emerald-600 dark:text-emerald-400 font-semibold" href="https://mermaid.live" target="_blank">mermaid.live</a>) to visualize.
        </div>        
    </div>
    <script>
        (function() {
            let selected = 'clone';
            const cardClone = document.getElementById('cardClone');
            const cardExport = document.getElementById('cardExport');
            const cardBackup = document.getElementById('cardBackup');
            const cardDiagram = document.getElementById('cardDiagram');
            const tablesPicker = document.getElementById('tablesPicker');
            const actionCtas = document.getElementById('actionCtas');
            const exportCtas = document.getElementById('exportCtas');
            const backupPanel = document.getElementById('backupPanel');
            const diagramPanel = document.getElementById('diagramPanel');

            function apply() {
                const v = selected;
                // card styling
                [cardClone, cardExport, cardBackup, cardDiagram].forEach(c => c?.classList.remove('ring-2',
                    'ring-slate-400', 'dark:ring-slate-500'));
                const active = v === 'clone' ? cardClone : v === 'export-structure' ? cardExport : v === 'backup-full' ?
                    cardBackup : cardDiagram;
                active?.classList.add('ring-2', 'ring-slate-400', 'dark:ring-slate-500');
                tablesPicker?.classList.toggle('hidden', v !== 'clone' && v !== 'export-structure');
                document.getElementById('tablesHint')?.classList.toggle('hidden', v !== 'clone' && v !==
                    'export-structure');
                document.getElementById('tablesToolbar')?.classList.toggle('hidden', v !== 'clone' && v !==
                    'export-structure');
                actionCtas?.classList.toggle('hidden', v !== 'clone');
                exportCtas?.classList.toggle('hidden', v !== 'export-structure');
                backupPanel?.classList.toggle('hidden', v !== 'backup-full');
                diagramPanel?.classList.toggle('hidden', v !== 'diagram');
                // Hide masking panel for Export Structure and Diagram
                document.getElementById('maskingPanel')?.classList.toggle('hidden', v === 'export-structure' || v === 'diagram');
                document.getElementById('deterministicPanel')?.classList.toggle('hidden', v !== 'clone');
                // Footer buttons visibility
                document.getElementById('footerCloneBtn')?.classList.toggle('hidden', v !== 'clone');
                document.getElementById('footerExportSourceBtn')?.classList.toggle('hidden', v !== 'export-structure');
                document.getElementById('footerExportTargetBtn')?.classList.toggle('hidden', v !== 'export-structure');
                document.getElementById('footerFullBackupBtn')?.classList.toggle('hidden', v !== 'backup-full');
                document.getElementById('footerDiagramBtn')?.classList.toggle('hidden', v !== 'diagram');
                updateTargetNotice();
            }
            const targetSelect2 = document.getElementById('sel_target_db');
            const targetNotice = document.getElementById('targetMissingNotice');
            function updateTargetNotice() {
                const hasTarget = !!(targetSelect2 && targetSelect2.value);
                const inActionsNeedingTarget = selected === 'clone' || selected === 'export-structure';
                const shouldShow = inActionsNeedingTarget && !hasTarget;
                targetNotice?.classList.toggle('hidden', !shouldShow);
            }
            [cardClone, cardExport, cardBackup, cardDiagram].forEach(btn => btn?.addEventListener('click', () => {
                if (btn.hasAttribute('disabled')) return;
                selected = btn.dataset.action;
                apply();
            }));
            targetSelect2?.addEventListener('change', updateTargetNotice);
            // copy inline diagram (footer button)
            (function() {
                function attachCopyHandler(){
                    const ta = document.getElementById('diagramTextInline') || document.getElementById('diagramText');
                    const btn = document.getElementById('copyDiagramInline');
                    const status = document.getElementById('copyStatusInline');
                    if (!btn) { setTimeout(attachCopyHandler, 200); return; }
                    const originalText = btn.textContent;
                    function setBtnTemp(text, ms){ btn.textContent = text; setTimeout(()=>{ btn.textContent = originalText; }, ms); }
                    ta?.addEventListener('focus', () => { ta.select(); });
                    btn.addEventListener('click', async (e) => {
                        e.preventDefault();
                        const textToCopy = ta?.value || '';
                        const fallbackCopy = () => {
                            const tmp = document.createElement('textarea');
                            tmp.value = textToCopy; tmp.style.position='fixed'; tmp.style.opacity='0';
                            document.body.appendChild(tmp); tmp.select();
                            try { document.execCommand('copy'); setBtnTemp('✔ Copied to clipboard', 1500); status?.classList.add('hidden'); }
                            catch(err){ setBtnTemp('Press Ctrl+C to copy', 2500); }
                            document.body.removeChild(tmp);
                        };
                        try {
                            if (navigator.clipboard && window.isSecureContext !== false) {
                                await navigator.clipboard.writeText(textToCopy);
                                setBtnTemp('✔ Copied to clipboard', 1500);
                                status?.classList.add('hidden');
                            } else {
                                fallbackCopy();
                            }
                        } catch (err) {
                            fallbackCopy();
                        }
                    }, { passive: true });
                }
                attachCopyHandler();
            })();
            apply();
        })();
    </script>
    <script>
        (function(){
            const banner = document.getElementById('progressBanner');
            const text = document.getElementById('progressText');
            let pending = false;
            function show(msg){ if (!banner) return; banner.classList.remove('hidden'); if (text) text.textContent = msg || 'Working…'; pending = true; }
            function hide(){ if (!banner) return; banner.classList.add('hidden'); pending = false; }
            function attachProgress(form, msg, isDownload){
                form?.addEventListener('submit', () => {
                    show(msg);
                    if (isDownload) {
                        // Auto-hide after a grace period; downloads don't trigger navigation
                        setTimeout(() => { if (pending) hide(); }, 8000);
                    }
                });
            }
            attachProgress(document.getElementById('tablesForm'), 'Cloning sample…', false);
            // Attach to backup forms
            document.querySelectorAll('form').forEach(f => {
                const action = f.querySelector('input[name="action"]')?.value || '';
                if (action === 'download_full_backup') attachProgress(f, 'Preparing full backup…', true);
                if (action === 'download_sql_structure') attachProgress(f, 'Exporting structure…', true);
            });
            // Hide when user returns focus to the page or visibility changes back
            window.addEventListener('focus', () => { if (pending) hide(); });
            document.addEventListener('visibilitychange', () => { if (!document.hidden && pending) hide(); });
            // Allow manual dismiss on click
            banner?.addEventListener('click', hide);
        })();
    </script>
    <script>
        (function(){
            // Persist row_limit, mask_sensitive, order_by_pk
            const form = document.getElementById('tablesForm');
            const rowLimit = form?.querySelector('input[name="row_limit"]');
            const maskChk = document.querySelector('input[name="mask_sensitive"]');
            const detChk = document.querySelector('input[name="order_by_pk"]');

            // Load
            try {
                const s = JSON.parse(localStorage.getItem('spt_prefs') || '{}');
                if (rowLimit && s.row_limit) rowLimit.value = s.row_limit;
                if (maskChk && typeof s.mask_sensitive === 'boolean') maskChk.checked = s.mask_sensitive;
                if (detChk && typeof s.order_by_pk === 'boolean') detChk.checked = s.order_by_pk;
            } catch(e) {}

            function save() {
                const s = {
                    row_limit: rowLimit ? rowLimit.value : undefined,
                    mask_sensitive: maskChk ? !!maskChk.checked : undefined,
                    order_by_pk: detChk ? !!detChk.checked : undefined,
                };
                localStorage.setItem('spt_prefs', JSON.stringify(s));
            }
            rowLimit?.addEventListener('change', save);
            maskChk?.addEventListener('change', save);
            detChk?.addEventListener('change', save);
        })();
    </script>
    <?php
          $diagramBody = (function() use ($mermaid) { ob_start(); ?>
    <div class="flex items-center justify-between mb-2">
        <div class="text-sm font-medium">Mermaid ER Diagram</div>
        <button type="button" id="copyDiagram"
            class="px-3 py-1.5 rounded-xl border text-sm dark:border-slate-700">Copy</button>
    </div>
    <textarea id="diagramText"
        class="w-full h-64 rounded-xl border px-3 py-2 font-mono text-xs dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100"
        readonly><?= htmlspecialchars($mermaid, ENT_QUOTES, 'UTF-8') ?></textarea>
    <div id="copyStatus" class="text-xs text-emerald-700 dark:text-emerald-400 mt-2 hidden">✔ Copied to clipboard</div>
    <div class="text-xs text-slate-500 mt-1">Paste into a viewer (e.g., <a class="text-emerald-700 dark:text-emerald-400" href="https://mermaid.live" target="_blank">mermaid.live</a>) to visualize.</div>
    <script>
        (function() {
            const ta = document.getElementById('diagramText');
            const btn = document.getElementById('copyDiagram');
            const status = document.getElementById('copyStatus');
            ta?.addEventListener('focus', () => {
                ta.select();
            });
            btn?.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(ta.value);
                    status?.classList.remove('hidden');
                    setTimeout(() => status?.classList.add('hidden'), 1500);
                } catch (e) {
                    ta.select();
                    status && (status.textContent = 'Press Ctrl+C to copy');
                    status?.classList.remove('hidden');
                    setTimeout(() => status?.classList.add('hidden'), 2500);
                }
            });
        })();
    </script>
    <?php return ob_get_clean(); })();
          $this->insert('partials/modal', [
            'id' => 'diagramModal',
            'title' => 'Database Diagram (Mermaid)',
            'body' => $diagramBody,
            'confirmLabel' => 'Close',
            'cancelLabel' => 'Cancel',
          ]);
        ?>
    <script>
        (function() {
            const btn = document.getElementById('openDiagram');
            const modal = document.getElementById('diagramModal');
            const cancelBtns = modal ? Array.from(modal.querySelectorAll('[data-modal-cancel]')) : [];
            const confirmBtns = modal ? Array.from(modal.querySelectorAll('[data-modal-confirm]')) : [];

            function open() {
                modal?.classList.remove('hidden');
            }

            function close() {
                modal?.classList.add('hidden');
            }
            btn?.addEventListener('click', open);
            cancelBtns.forEach(btn => btn.addEventListener('click', close));
            confirmBtns.forEach(btn => btn.addEventListener('click', close));
            modal?.addEventListener('click', (e) => {
                if (e.target === modal) close();
            });
        })();
    </script>
    <?php return ob_get_clean(); })(),
        'footer' => (function() use ($post) { ob_start(); ?>
          <span id="footerCloneBtn">
            <button type="submit" form="tablesForm" class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl text-sm font-medium bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 disabled:opacity-50" id="btnCreateSample">🤖 Clone Sample</button>
          </span>
          <span id="footerExportSourceBtn">
            <button type="submit" form="exportSourceForm" class="px-4 py-2 rounded-2xl text-sm border dark:border-slate-700">🟢 Download Source Structure</button>
          </span>
          <span id="footerExportTargetBtn">
            <button type="submit" form="exportTargetForm" class="px-4 py-2 rounded-2xl text-sm border dark:border-slate-700" id="btnDownloadTargetStructure" <?= empty($post['target_db'] ?? '') ? 'disabled' : '' ?>>🟠 Download Target Structure</button>
          </span>
          <span id="footerFullBackupBtn">
            <button type="submit" form="fullBackupForm" class="px-4 py-2 rounded-2xl text-sm border dark:border-slate-700">💾 Full Backup</button>
          </span>
          <span id="footerDiagramBtn" class="inline-flex items-center gap-2">
            <button type="button" id="copyDiagramInline" class="px-3 py-1.5 rounded-2xl text-sm border dark:border-slate-700">⿻ Copy Diagram</button>
            <span id="copyStatusInline" class="text-xs text-emerald-700 dark:text-emerald-400 hidden">✔ Copied to clipboard</span>
          </span>
        <?php return ob_get_clean(); })()
      ]); ?>

    <script>
        const formEl = document.getElementById('tablesForm');
        const btnCreate = document.getElementById('btnCreateSample');
        const targetSelect = document.getElementById('sel_target_db');

        function validateTables() {
            const any = Array.from(formEl?.querySelectorAll('input[type=checkbox][name="tables[]"]') || []).some(cb => cb.checked);
            const hasTarget = !!(targetSelect && targetSelect.value);
            if (btnCreate) btnCreate.disabled = !(any && hasTarget);
        }
        document.getElementById('selectAll')?.addEventListener('click', () => {
            document.querySelectorAll('#tablesForm input[type=checkbox][name="tables[]"]').forEach(cb => cb.checked = true);
            validateTables();
        });
        document.getElementById('clearAll')?.addEventListener('click', () => {
            document.querySelectorAll('#tablesForm input[type=checkbox][name="tables[]"]').forEach(cb => cb.checked = false);
            validateTables();
        });
        document.querySelectorAll('#tablesForm input[type=checkbox][name="tables[]"]').forEach(cb => cb.addEventListener('change',
            validateTables));
        targetSelect?.addEventListener('change', validateTables);
        validateTables();
    </script>
    <?php endif; ?>

    <?php if ($step === 4): ?>
    <?php
    $sourceDB = e($post['source_db'] ?? '');
    $targetDB = e($post['target_db'] ?: $sourceDB . '_sample');
    $rowLimit = (int) ($post['row_limit'] ?? 100);
    ?>
    <?php $this->insert('partials/card', [
        'number' => '4',
        'title' => 'Done — Copy Report',
        'content' => (function() use ($post, $report, $sourceDB, $targetDB, $rowLimit, $rowEstimates) { ob_start(); ?>
    <div class="text-xs text-slate-500 dark:text-slate-400 mb-2 flex items-center gap-2">
        <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-slate-100 dark:bg-slate-800">💡</span>
        <span>Step 4: Review the copy report. You can download the sample or structure-only SQL.</span>
    </div>
    <div class="mb-4 text-sm text-slate-700">
        <div><strong>Source:</strong> <?= $sourceDB ?></div>
        <div><strong>Target:</strong> <?= $targetDB ?></div>
        <div><strong>Rows per table:</strong> <?= e((string) $rowLimit) ?></div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm border rounded-xl overflow-hidden dark:border-slate-700">
            <thead class="bg-slate-100 dark:bg-slate-800">
                <tr>
                    <th class="text-left px-3 py-2 border dark:border-slate-700">Table</th>
                    <th class="text-left px-3 py-2 border dark:border-slate-700">Created</th>
                    <th class="text-left px-3 py-2 border dark:border-slate-700">Rows Copied</th>
                    <th class="text-left px-3 py-2 border dark:border-slate-700">Status</th>
                </tr>
            </thead>
            <tbody class="dark:divide-slate-700">
                <?php foreach ($report as $r): ?>
                <tr>
                    <td class="px-3 py-2 border font-mono text-xs dark:border-slate-700"><?= e($r['table']) ?></td>
                    <td class="px-3 py-2 border dark:border-slate-700">
                        <?= $r['created'] ? '<span class="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-400">✔</span>' : '—' ?>
                    </td>
                    <td class="px-3 py-2 border dark:border-slate-700">
                        <?php $total = $rowEstimates[$r['table']] ?? null; ?>
                        <?= e((string) $r['copied']) ?><?php if ($total !== null): ?> / <?= number_format((int) $total) ?>
                        total<?php endif; ?>
                    </td>
                    <td class="px-3 py-2 border dark:border-slate-700">
                        <?php if ($r['error']): ?>
                        <span class="text-red-700 dark:text-red-400">Error: <?= e($r['error']) ?></span>
                        <?php else: ?>
                        <span class="text-emerald-700 dark:text-emerald-400">OK</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-4 text-slate-600 text-sm">
        <p>Tip: If your schema has strong foreign-key dependencies, consider running twice with an order that respects
            dependencies, or add ordering/filters per table (e.g., by primary key). For deterministic sampling, replace
            the simple <code>LIMIT</code> with <code>ORDER BY id LIMIT X</code> where applicable.</p>
    </div>
    <form method="post" class="mt-4">
        <?php foreach (['db_host','db_port','db_user','db_pass','source_db','target_db'] as $k): ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e($post[$k] ?? '') ?>" />
        <?php endforeach; ?>
        <input type="hidden" name="_csrf"
            value="<?= htmlspecialchars($_SESSION['_csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
        <input type="hidden" name="action" value="download_sql" />
        <?php $this->insert('partials/button', [
            'label' => 'Download SQL',
            'variant' => 'secondary',
            'type' => 'submit',
            'icon' => '📥',
            'disabled' => !empty(getenv('READ_ONLY')) && filter_var(getenv('READ_ONLY'), FILTER_VALIDATE_BOOLEAN),
        ]); ?>
    </form>
    <form method="post" class="mt-2">
        <?php foreach (['db_host','db_port','db_user','db_pass','source_db','target_db'] as $k): ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e($post[$k] ?? '') ?>" />
        <?php endforeach; ?>
        <?php foreach (($post['tables'] ?? []) as $t): ?>
        <input type="hidden" name="tables[]" value="<?= e($t) ?>" />
        <?php endforeach; ?>
        <input type="hidden" name="_csrf"
            value="<?= htmlspecialchars($_SESSION['_csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
        <input type="hidden" name="action" value="download_sql_structure" />
        <?php $this->insert('partials/button', [
            'label' => 'Download Structure Only',
            'variant' => 'secondary',
            'type' => 'submit',
            'icon' => '📥',
        ]); ?>
    </form>
    <form method="post" class="mt-2">
        <?php foreach (['db_host','db_port','db_user','db_pass','source_db'] as $k): ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e($post[$k] ?? '') ?>" />
        <?php endforeach; ?>
        <input type="hidden" name="_csrf"
            value="<?= htmlspecialchars($_SESSION['_csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
        <input type="hidden" name="action" value="download_full_backup" />
        <label class="inline-flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300 mr-3">
            <input type="checkbox" name="gzip" value="1" class="h-4 w-4" />
            <span>Gzip compress</span>
        </label>
        <?php $this->insert('partials/button', [
            'label' => 'Full Backup (source DB)',
            'variant' => 'secondary',
            'type' => 'submit',
            'icon' => '💾',
        ]); ?>
    </form>
    <?php return ob_get_clean(); })()
      ]); ?>
    <?php endif; ?>

</div>
