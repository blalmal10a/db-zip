@extends('db-zip::layouts.app')

@section('title', 'Restore Database Backup')

@push('styles')
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.3.0/dist/index.global.min.js"></script>
@endpush

@section('content')
<div class="bg-white p-6 rounded-xl shadow-xs max-w-2xl mx-auto border border-gray-100">
    <h2 class="m-0 mb-1.5 text-2xl font-bold text-gray-900">Restore Database Backup</h2>
    <p class="text-gray-500 text-sm mb-6">Upload a ZIP archive containing <code>table.json</code> and CSV files per table.</p>

    <div class="hidden mb-4 p-3 px-4 bg-amber-50 border border-amber-300 rounded-lg text-amber-800 text-sm" id="nginx-banner">
        Server rejected the request. File may be too large (server limit). Try a smaller backup or increase <code>client_max_body_size</code>.
    </div>

    <div class="border-2 border-dashed border-indigo-100 rounded-xl p-7 text-center cursor-pointer transition-colors bg-slate-50/50 hover:bg-indigo-50/40 hover:border-indigo-400 group [&.dragover]:border-indigo-500 [&.dragover]:bg-indigo-50/40" id="drop-zone">
        <input type="file" id="backup-zip" accept=".zip" class="hidden">
        <div class="text-3xl mb-2">&#128230;</div>
        <div class="text-sm text-gray-600">Drag &amp; drop your ZIP here, or <span class="text-indigo-500 font-semibold cursor-pointer" onclick="document.getElementById('backup-zip').click()">browse</span></div>
        <div id="file-name" class="mt-2 text-xs text-indigo-500 font-medium min-h-[18px]"></div>
    </div>

    <button class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-500 hover:bg-indigo-600 disabled:opacity-55 disabled:cursor-not-allowed text-white border-none rounded-lg text-sm font-semibold cursor-pointer transition-colors mt-5" id="start-restore" disabled>
        &#9654; Start Restore
    </button>

    <div id="progress-wrap" class="hidden mt-5">
        <div class="bg-indigo-50 rounded-full h-2 overflow-hidden mb-1.5">
            <div class="h-full w-0 bg-gradient-to-r from-indigo-500 to-indigo-400 rounded-full transition-[width] duration-300 ease-in-out" id="progress-bar"></div>
        </div>
        <div id="progress-label" class="text-xs text-gray-400 text-right">0 / 0</div>
    </div>

    <div id="status-log" class="mt-6 rounded-xl border border-gray-200 bg-gray-50 max-h-[500px] overflow-y-auto p-4 text-sm leading-relaxed text-gray-700">
        <span class="text-gray-400 italic">No restore started yet.</span>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" integrity="sha384-+mbV2IY1Zk/X1p/nWllGySJSUN8uMs+gUAN10Or95UBH0fpj6GfKgPmgC5EXieXG" crossorigin="anonymous"></script>
<script>
    const fileInput = document.getElementById('backup-zip');
    const dropZone = document.getElementById('drop-zone');
    const fileNameEl = document.getElementById('file-name');
    const startBtn = document.getElementById('start-restore');
    const log = document.getElementById('status-log');
    const progressWrap = document.getElementById('progress-wrap');
    const progressBar = document.getElementById('progress-bar');
    const progressLbl = document.getElementById('progress-label');
    const nginxBanner = document.getElementById('nginx-banner');

    fileInput.addEventListener('change', () => onFileSelected(fileInput.files[0]));

    dropZone.addEventListener('dragover', e => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        const f = e.dataTransfer.files[0];
        if (f && f.name.endsWith('.zip')) onFileSelected(f);
    });

    function onFileSelected(file) {
        if (!file) return;
        fileNameEl.textContent = `📎 ${file.name}`;
        startBtn.disabled = false;
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;
    }

    startBtn.addEventListener('click', startRestoreProcess);

    function extractTableName(filename) {
        return filename.replace(/_\d{3}\.csv$/i, '').replace(/\.csv$/i, '');
    }

    async function startRestoreProcess() {
        if (!fileInput.files.length) return;

        startBtn.disabled = true;
        log.innerHTML = '';
        nginxBanner.classList.replace('block', 'hidden');

        const zipFile = fileInput.files[0];

        appendLog('<em>Reading ZIP archive…</em>');
        let zip;
        try {
            zip = await new JSZip().loadAsync(zipFile);
        } catch (err) {
            appendLog(`<span class="text-red-700">Could not read ZIP: ${err.message}</span>`);
            startBtn.disabled = false;
            return;
        }

        const schemaMap = await buildSchemaMap(zip);

        const csvFiles = Object.values(zip.files).filter(f =>
            !f.dir && f.name.toLowerCase().endsWith('.csv')
        );

        if (!csvFiles.length) {
            appendLog("<span class='text-red-700'>No CSV files found in the ZIP.</span>");
            startBtn.disabled = false;
            return;
        }

        appendLog(`Found <strong>${csvFiles.length}</strong> CSV file(s).${schemaMap ? ` Schema loaded for <strong>${Object.keys(schemaMap).length}</strong> table(s).` : ' No <code>table.json</code> found — tables must already exist.'}<br>`);

        const groups = groupCsvFiles(csvFiles);

        progressWrap.classList.replace('hidden', 'block');
        setProgress(0, groups.length);

        let successCount = 0,
            errorCount = 0;

        for (let gi = 0; gi < groups.length; gi++) {
            const group = groups[gi];
            const groupId = `group-${gi}`;

            const groupEl = buildGroupElement(groupId, group.tableName, group.files.length);
            log.appendChild(groupEl);

            let firstChunk = true;
            let groupDone = 0;

            for (let ci = 0; ci < group.files.length; ci++) {
                if (ci > 0) {
                    await new Promise(r => setTimeout(r, 500));
                }

                const zipEntry = group.files[ci];
                const cleanName = zipEntry.name.split('/').pop();
                const chunkId = `${groupId}-chunk-${ci}`;
                const rowEl = buildChunkRow(chunkId, cleanName, 'Uploading…', 'bg-amber-50 text-amber-700');
                const body = groupEl.querySelector('.table-group-body');
                body.appendChild(rowEl);

                const blob = await zipEntry.async('blob');
                const fileObject = new File([blob], cleanName, {
                    type: 'text/csv'
                });

                const tableSQL = firstChunk ? (schemaMap?.[group.tableName] ?? null) : '__append__';
                firstChunk = false;

                const ok = await uploadAndRestoreFile(fileObject, tableSQL, chunkId);
                ok ? successCount++ : errorCount++;
                groupDone++;

                updateGroupProgress(groupId, groupDone, group.files.length);
            }
            if (errorCount) {
                break;
            }
            setProgress(gi + 1, groups.length);
        }

        appendLog(
            `<div class="mt-3 pt-2.5 border-t border-dashed border-gray-300 text-sm font-semibold">
                ✅ <span class="text-green-700">${successCount} succeeded</span>
                ${errorCount ? `&nbsp; ❌ <span class="text-red-700">${errorCount} failed</span>` : ''}
             </div>`
        );

        startBtn.disabled = false;
    }

    function groupCsvFiles(csvFiles) {
        const map = {};
        csvFiles.forEach(entry => {
            const cleanName = entry.name.split('/').pop();
            const tableName = extractTableName(cleanName);
            if (!map[tableName]) map[tableName] = [];
            map[tableName].push(entry);
        });
        return Object.entries(map).map(([tableName, files]) => ({
            tableName,
            files
        }));
    }

    function buildGroupElement(groupId, tableName, chunkCount) {
        const div = document.createElement('div');
        div.className = 'my-1 border border-gray-200 rounded-md overflow-hidden';
        div.id = groupId;

        const header = document.createElement('div');
        header.className = 'flex items-center gap-2 p-2 px-3 bg-gray-100 hover:bg-gray-200 cursor-pointer select-none font-semibold text-sm transition-colors';
        header.innerHTML = `<span class="toggle-icon text-[0.7rem] text-gray-400 transition-transform duration-200 -rotate-90">▼</span> 📦 ${tableName} <span class="group-progress">0 / ${chunkCount}</span>`;

        const body = document.createElement('div');
        body.className = 'table-group-body hidden';
        body.id = `${groupId}-body`;

        header.addEventListener('click', () => {
            const isHidden = body.classList.toggle('hidden');
            header.querySelector('.toggle-icon').classList.toggle('-rotate-90', isHidden);
        });

        div.appendChild(header);
        div.appendChild(body);
        return div;
    }

    function updateGroupProgress(groupId, done, total) {
        const header = document.getElementById(groupId)?.querySelector('.group-progress');
        if (header) {
            header.textContent = `${done} / ${total}`;
        }
    }

    function buildChunkRow(id, name, badgeText, badgeClass) {
        const row = document.createElement('div');
        row.id = id;
        row.className = 'flex items-baseline justify-between p-1.5 pr-3 pl-7 border-t border-gray-100 first:border-t-0 text-xs';
        row.innerHTML = `<span class="text-gray-700">📄 ${name}</span>
                         <span class="badge text-xs font-bold px-2 py-0.5 rounded-full whitespace-nowrap ml-2.5 shrink-0 ${badgeClass}">${badgeText}</span>`;
        return row;
    }

    async function buildSchemaMap(zip) {
        const jsonEntry = Object.values(zip.files).find(f =>
            !f.dir && f.name.split('/').pop().toLowerCase() === 'table.json'
        );
        if (!jsonEntry) return null;

        try {
            const raw = await jsonEntry.async('string');
            const stmts = JSON.parse(raw);
            const map = {};

            for (const sql of stmts) {
                const match = sql.match(/CREATE\s+TABLE\s+`?(\w+)`?/i);
                if (match) {
                    map[match[1]] = sql;
                }
            }
            return map;
        } catch (err) {
            appendLog(`<span class="text-red-700">Could not parse table.json: ${err.message}</span>`);
            return null;
        }
    }

    async function uploadAndRestoreFile(file, tableSQL, rowId) {
        const formData = new FormData();
        formData.append('file', file);
        if (tableSQL && tableSQL !== '__append__') {
            formData.append('table_sql', tableSQL);
        }
        if (tableSQL === '__append__') {
            formData.append('append', '1');
        }

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
            const response = await fetch('/backup/restore', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf
                },
                body: formData,
            });

            const contentType = response.headers.get('content-type') || '';

            if (contentType.includes('text/html') || contentType.includes('text/plain')) {
                const text = await response.text();
                if (text.includes('nginx') || text.includes('413') || text.includes('Request Entity Too Large')) {
                    nginxBanner.classList.replace('hidden', 'block');
                }
                throw new Error(`Server returned HTML — possible nginx limit`);
            }

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.error || `Server error ${response.status}`);
            }

            updateBadge(rowId, '✓ Success', 'bg-green-50 text-green-700');
            return true;
        } catch (err) {
            updateBadge(rowId, `✗ ${err.message}`, 'bg-red-50 text-red-700');
            return false;
        }
    }

    function appendLog(html) {
        const el = document.createElement('div');
        el.innerHTML = html;
        log.appendChild(el);
        log.scrollTop = log.scrollHeight;
    }

    function updateBadge(rowId, text, cls) {
        const row = document.getElementById(rowId);
        if (!row) return;
        const badge = row.querySelector('.badge');
        if (!badge) return;
        badge.className = `badge text-xs font-bold px-2 py-0.5 rounded-full whitespace-nowrap ml-2.5 shrink-0 ${cls}`;
        badge.textContent = text;
    }

    function setProgress(done, total) {
        const pct = total ? Math.round((done / total) * 100) : 0;
        progressBar.style.width = `${pct}%`;
        progressLbl.textContent = `${done} / ${total}`;
    }
</script>
@endpush