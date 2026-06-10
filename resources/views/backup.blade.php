@extends('db-zip::layouts.app')

@section('title', 'Database Backup')

@push('styles')
    <style>
        h2 {
            margin: 0 0 4px;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .subtitle {
            color: #666;
            font-size: 0.875rem;
            margin: 0 0 24px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            background: #f5f5f5;
            color: #1a1a1a;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn:hover {
            background: #ebebeb;
        }

        .btn:disabled {
            opacity: .5;
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
            background: #5c6bc0;
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
            max-height: 380px;
            overflow-y: auto;
            padding: 14px 16px;
            font-size: 0.85rem;
            line-height: 1.7;
        }

        .log-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .log-row:last-child {
            border-bottom: none;
        }

        .log-name {
            color: #333;
        }

        .badge {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 999px;
            white-space: nowrap;
            margin-left: 10px;
        }

        .badge-pending {
            background: #f0f0f0;
            color: #888;
        }

        .badge-processing {
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

        .badge-timeout {
            background: #fff3e0;
            color: #e65100;
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
            display: flex;
            gap: 16px;
        }

        .text-success {
            color: #2e7d32;
        }

        .text-error {
            color: #c62828;
        }

        .download-row {
            display: none;
            margin-top: 20px;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            background: #fff;
            align-items: center;
            justify-content: space-between;
        }

        .download-row.visible {
            display: flex;
        }

        .download-filename {
            font-size: 0.85rem;
            color: #555;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .download-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            background: #5c6bc0;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
        }

        .download-btn:hover {
            background: #3f51b5;
        }

        #existing-backups-container {
            margin-top: 24px;
            display: none;
        }

        #existing-backups-container h3 {
            font-size: 1rem;
            margin-bottom: 12px;
            color: #444;
        }
    </style>
@endpush

@section('content')
    <div class="card">
        <h2>Database backup</h2>
        <p class="subtitle">Exports all tables as CSV files and packages them into a downloadable ZIP archive.</p>

        <button class="btn" id="start-backup">&#9654; Start backup</button>

        <div id="progress-wrap">
            <div class="progress-track">
                <div class="progress-bar" id="progress-bar"></div>
            </div>
            <div id="progress-label">0 / 0</div>
        </div>

        <div id="status-log"><span class="log-placeholder">No backup started yet.</span></div>

        <div class="download-row" id="download-row">
            <div class="download-filename">
                &#128230; <span id="download-filename-text"></span> <span class="badge badge-success">New</span>
            </div>
            <a class="download-btn" id="download-link" href="#" download>&#8595; Download</a>
        </div>

        <div id="existing-backups-container">
            <h3>Available Past Backups</h3>
            <div id="existing-backups-list"></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const dbZipRoute = @json($route ?? config('db-zip.route'));

        let totalTables = 0;
        let countDoneTable = 0;
        const timestamp = Date.now();

        const startBtn = document.getElementById('start-backup');
        const log = document.getElementById('status-log');
        const progressWrap = document.getElementById('progress-wrap');
        const progressBar = document.getElementById('progress-bar');
        const progressLbl = document.getElementById('progress-label');
        const downloadRow = document.getElementById('download-row');
        const downloadLink = document.getElementById('download-link');
        const downloadFilenameText = document.getElementById('download-filename-text');

        const existingContainer = document.getElementById('existing-backups-container');
        const existingList = document.getElementById('existing-backups-list');

        window.addEventListener('DOMContentLoaded', initializeBackupDashboard);
        startBtn.addEventListener('click', startBackupProcess);

        function generateRandomId() {
            return 'log-' + Math.random().toString(36).substring(2, 11);
        }

        function appendTableRow(id, name, badgeText, badgeClass) {
            const row = document.createElement('div');
            row.id = id;
            row.className = 'log-row';
            row.innerHTML = `<span class="log-name">&#128196; ${name}</span>
                         <span class="badge ${badgeClass}">${badgeText}</span>`;
            log.appendChild(row);
            log.scrollTop = log.scrollHeight;
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

        async function initializeBackupDashboard() {
            try {
                const response = await fetch(`${dbZipRoute.backup_tables}?timestamp=${timestamp}`);
                const data = await response.json();

                if (data.files && data.files.length > 0) {
                    renderExistingFiles(data.files);
                }
            } catch (error) {
                console.error('Error fetching dashboard initial data:', error);
            }
        }

        function renderExistingFiles(files) {
            existingList.innerHTML = '';

            files.forEach(file => {
            let filePath = file.name
                const ts = filePath.split('/').pop().replace('.zip', '');
                const dt = new Date(parseInt(ts));

                const fileName = `${toHyphenDateTime(dt)}.zip`;

                const row = document.createElement('div');
            row.className = 'download-row visible';
            row.style.marginTop = '8px';

            row.innerHTML = `
                <div class="download-filename">
                    &#128230; <span>${fileName}</span>
                </div>
                <div>
                    <a class="download-btn" href="${dbZipRoute.backup_download.replace('{fileName}', ts)}" download="${fileName}">&#8595; Download</a>
                    <button class="btn delete-btn" style="margin-left: 8px;">Delete</button>
                </div>
            `;

            row.querySelector('.delete-btn').onclick = async () => {
                await deleteZipByFilename(ts);
                initializeBackupDashboard();
            };
            existingList.appendChild(row);
            });

            existingContainer.style.display = 'block';
        }

        async function startBackupProcess() {
            startBtn.disabled = true;
            log.innerHTML = '';
            downloadRow.classList.remove('visible');
            progressWrap.classList.add('visible');
            appendLog('<em>Fetching table list…</em>');

            try {
                const response = await fetch(`${dbZipRoute.backup_tables}?timestamp=${timestamp}`);
                const data = await response.json();

                const tablesArray = data.tables ? data.tables : data;
                totalTables = tablesArray.length;
                countDoneTable = 0;

                log.innerHTML = '';
                setProgress(0, totalTables);
                appendLog(`Found <strong>${totalTables}</strong> table(s). Starting export…<br>`);

                for (const item of tablesArray) {
                    const tableName = item.name || item;
                    const uid = generateRandomId();
                    appendTableRow(uid, tableName, 'Pending…', 'badge-pending');
                    backupTableWithTimeout(tableName, 30000, timestamp, uid);
                }

                if (data.files) {
                    renderExistingFiles(data.files);
                }
            } catch (error) {
                appendLog(`<span class="text-error">Failed to fetch table list: ${error.message}</span>`);
                startBtn.disabled = false;
            }
        }

        async function backupTableWithTimeout(tableName, timeoutMs, ts, uid) {
            updateBadge(uid, 'Exporting…', 'badge-processing');

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

            try {
                const response = await fetch(`${dbZipRoute.backup_export}?table-name=${tableName}&timestamp=${ts}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                            '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    },
                    signal: controller.signal
                });

                clearTimeout(timeoutId);
                if (!response.ok) throw new Error(`Server error ${response.status}`);
                await response.json();
                updateBadge(uid, 'Done', 'badge-success');
            } catch (error) {
                clearTimeout(timeoutId);
                error.name === 'AbortError' ?
                    updateBadge(uid, 'Timed out', 'badge-timeout') :
                    updateBadge(uid, `Failed: ${error.message}`, 'badge-error');
            } finally {
                countDoneTable++;
                setProgress(countDoneTable, totalTables);
                if (countDoneTable === totalTables) {
                    await zipFolder();
                    startBtn.disabled = false;
                }
            }
        }

        async function zipFolder() {
            try {
                const response = await fetch(`${dbZipRoute.backup_zip}?timestamp=${timestamp}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                            '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                });

                const result = await response.json();

                const d = new Date(timestamp);
                const localeStr = d.toLocaleString('en-GB', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                });
                const [date, time] = localeStr.split(', ');
                const fileName = `${date.split('/').reverse().join('-')}-${time.replace(':', '_')}.zip`;

                downloadLink.href = dbZipRoute.backup_download.replace('{fileName}', timestamp);
                downloadLink.download = fileName;
                downloadFilenameText.textContent = fileName;
                downloadRow.classList.add('visible');

                const rows = log.querySelectorAll('.log-row');
                const success = [...rows].filter(r => r.querySelector('.badge-success')).length;
                const failed = [...rows].filter(r => r.querySelector('.badge-error, .badge-timeout')).length;

                appendLog(`<div class="summary-line">
                <span class="text-success">&#10003; ${success} exported</span>
                ${failed ? `<span class="text-error">&#10007; ${failed} failed</span>` : ''}
            </div>`);

                initializeBackupDashboard();
            } catch (error) {
                appendLog(`<span class="text-error">ZIP creation failed: ${error.message}</span>`);
            }
        }

        function toHyphenDateTime(date = new Date()) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            return `${year}-${month}-${day} ${hours}-${minutes}-${seconds}`;
        }

        async function deleteZipByFilename(fileName) {
            await fetch(dbZipRoute.backup_delete.replace('{fileName}', encodeURIComponent(fileName)), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                        '{{ csrf_token() }}',
                },
            });
        }
    </script>
@endpush
