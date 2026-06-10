@extends('db-zip::layouts.app')

@section('title', 'Restore Database Backup')

@push('styles')
<style>
    h2 {
        margin: 0 0 6px;
        font-size: 1.4rem;
        font-weight: 700;
    }

    .subtitle {
        color: #666;
        font-size: 0.875rem;
        margin-bottom: 24px;
    }

    .upload-area {
        border: 2px dashed #c5cae9;
        border-radius: 10px;
        padding: 28px;
        text-align: center;
        cursor: pointer;
        transition: border-color 0.2s, background 0.2s;
        background: #fafbff;
        margin-bottom: 20px;
    }

    .upload-area:hover,
    .upload-area.dragover {
        border-color: #5c6bc0;
        background: #f0f2ff;
    }

    .upload-area input[type="file"] {
        display: none;
    }

    .upload-icon {
        font-size: 2rem;
        margin-bottom: 8px;
    }

    .upload-label {
        font-size: 0.95rem;
        color: #555;
    }

    .upload-label span {
        color: #5c6bc0;
        font-weight: 600;
        cursor: pointer;
    }

    #file-name {
        margin-top: 8px;
        font-size: 0.82rem;
        color: #5c6bc0;
        font-weight: 500;
        min-height: 18px;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 11px 26px;
        background: #5c6bc0;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s, opacity 0.2s;
    }

    .btn:hover {
        background: #3f51b5;
    }

    .btn:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    #progress-wrap {
        display: none;
        margin: 20px 0 0;
    }

    #progress-wrap.visible {
        display: block;
    }

    .progress-track {
        background: #e8eaf6;
        border-radius: 999px;
        height: 8px;
        overflow: hidden;
        margin-bottom: 6px;
    }

    .progress-bar {
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, #5c6bc0, #7986cb);
        border-radius: 999px;
        transition: width 0.3s ease;
    }

    #progress-label {
        font-size: 0.8rem;
        color: #888;
        text-align: right;
    }

    #status-log {
        margin-top: 24px;
        border-radius: 10px;
        border: 1px solid #e0e0e0;
        background: #fafafa;
        max-height: 500px;
        overflow-y: auto;
        padding: 14px 16px;
        font-size: 0.85rem;
        line-height: 1.7;
    }

    .table-group {
        margin: 4px 0;
        border: 1px solid #e8e8e8;
        border-radius: 6px;
        overflow: hidden;
    }

    .table-group-header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        background: #f5f5f5;
        cursor: pointer;
        user-select: none;
        font-weight: 600;
        font-size: 0.9rem;
        transition: background 0.15s;
    }

    .table-group-header:hover {
        background: #eeeeee;
    }

    .table-group-header .toggle-icon {
        font-size: 0.7rem;
        transition: transform 0.2s;
        color: #888;
    }

    .table-group-header .toggle-icon.collapsed {
        transform: rotate(-90deg);
    }

    .table-group-body {
        padding: 0;
    }

    .table-group-body.hidden {
        display: none;
    }

    .chunk-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        padding: 5px 12px 5px 28px;
        border-top: 1px solid #f0f0f0;
        font-size: 0.82rem;
    }

    .chunk-row:first-child {
        border-top: none;
    }

    .log-name {
        color: #333;
    }

    .badge {
        font-size: 0.75rem;
        font-weight: 700;
        padding: 2px 9px;
        border-radius: 999px;
        white-space: nowrap;
        margin-left: 10px;
        flex-shrink: 0;
    }

    .badge-pending {
        background: #e8eaf6;
        color: #5c6bc0;
    }

    .badge-uploading {
        background: #fff8e1;
        color: #f57f17;
    }

    .badge-success {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .badge-error {
        background: #ffebee;
        color: #c62828;
    }

    .badge-skipped {
        background: #f5f5f5;
        color: #757575;
    }

    .log-placeholder {
        color: #aaa;
        font-style: italic;
    }

    .summary-line {
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px dashed #ccc;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .text-success {
        color: #2e7d32;
    }

    .text-error {
        color: #c62828;
    }

    .nginx-banner {
        display: none;
        margin-bottom: 16px;
        padding: 12px 16px;
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 8px;
        color: #856404;
        font-size: 0.85rem;
    }

    .nginx-banner.visible {
        display: block;
    }
</style>
@endpush

@section('content')
<div class="card">
    <h2>Restore Database Backup</h2>
    <p class="subtitle">Upload a ZIP archive containing <code>table.json</code> and CSV files per table.</p>

    <div class="nginx-banner" id="nginx-banner">
        Server rejected the request. File may be too large (server limit). Try a smaller backup or increase <code>client_max_body_size</code>.
    </div>

    <div class="upload-area" id="drop-zone">
        <input type="file" id="backup-zip" accept=".zip">
        <div class="upload-icon">&#128230;</div>
        <div class="upload-label">Drag &amp; drop your ZIP here, or <span onclick="document.getElementById('backup-zip').click()">browse</span></div>
        <div id="file-name"></div>
    </div>

    <button class="btn" id="start-restore" disabled>
        &#9654; Start Restore
    </button>

    <div id="progress-wrap">
        <div class="progress-track">
            <div class="progress-bar" id="progress-bar"></div>
        </div>
        <div id="progress-label">0 / 0</div>
    </div>

    <div id="status-log"><span class="log-placeholder">No restore started yet.</span></div>
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
        nginxBanner.classList.remove('visible');

        const zipFile = fileInput.files[0];

        appendLog('<em>Reading ZIP archive…</em>');
        let zip;
        try {
            zip = await new JSZip().loadAsync(zipFile);
        } catch (err) {
            appendLog(`<span class="text-error">Could not read ZIP: ${err.message}</span>`);
            startBtn.disabled = false;
            return;
        }

        const schemaMap = await buildSchemaMap(zip);

        const csvFiles = Object.values(zip.files).filter(f =>
            !f.dir && f.name.toLowerCase().endsWith('.csv')
        );

        if (!csvFiles.length) {
            appendLog("<span class='text-error'>No CSV files found in the ZIP.</span>");
            startBtn.disabled = false;
            return;
        }

        appendLog(`Found <strong>${csvFiles.length}</strong> CSV file(s).${schemaMap ? ` Schema loaded for <strong>${Object.keys(schemaMap).length}</strong> table(s).` : ' No <code>table.json</code> found — tables must already exist.'}<br>`);

        const groups = groupCsvFiles(csvFiles);

        progressWrap.classList.add('visible');
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
                const rowEl = buildChunkRow(chunkId, cleanName, 'Uploading…', 'badge-uploading');
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
            `<div class="summary-line">
                ✅ <span class="text-success">${successCount} succeeded</span>
                ${errorCount ? `&nbsp; ❌ <span class="text-error">${errorCount} failed</span>` : ''}
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
        div.className = 'table-group';
        div.id = groupId;

        const header = document.createElement('div');
        header.className = 'table-group-header';
        header.innerHTML = `<span class="toggle-icon collapsed">▼</span> 📦 ${tableName} <span class="group-progress">0 / ${chunkCount}</span>`;

        const body = document.createElement('div');
        body.className = 'table-group-body hidden';
        body.id = `${groupId}-body`;

        header.addEventListener('click', () => {
            const isHidden = body.classList.toggle('hidden');
            header.querySelector('.toggle-icon').classList.toggle('collapsed', isHidden);
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
        row.className = 'chunk-row';
        row.innerHTML = `<span class="log-name">📄 ${name}</span>
                         <span class="badge ${badgeClass}">${badgeText}</span>`;
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
            appendLog(`<span class="text-error">Could not parse table.json: ${err.message}</span>`);
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
                    nginxBanner.classList.add('visible');
                }
                throw new Error(`Server returned HTML — possible nginx limit`);
            }

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.error || `Server error ${response.status}`);
            }

            updateBadge(rowId, '✓ Success', 'badge-success');
            return true;
        } catch (err) {
            updateBadge(rowId, `✗ ${err.message}`, 'badge-error');
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
        badge.className = `badge ${cls}`;
        badge.textContent = text;
    }

    function setProgress(done, total) {
        const pct = total ? Math.round((done / total) * 100) : 0;
        progressBar.style.width = `${pct}%`;
        progressLbl.textContent = `${done} / ${total}`;
    }
</script>
@endpush