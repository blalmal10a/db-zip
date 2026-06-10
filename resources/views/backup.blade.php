@extends('db-zip::layouts.app')

@section('title', 'Database Backup')

@push('styles')
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.3.0/dist/index.global.min.js"></script>
@endpush

@section('content')
    <div class="bg-white p-6 rounded-xl shadow-xs max-w-2xl mx-auto border border-gray-100">
        <h2 class="m-0 mb-1 text-2xl font-semibold text-gray-900">Database backup</h2>
        <p class="text-gray-500 text-sm m-0 mb-6">Exports all tables as CSV files and packages them into a downloadable ZIP archive.</p>

        <button class="inline-flex items-center gap-2 px-6 py-2.5 bg-gray-100 hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed text-gray-900 border border-gray-300 rounded-lg text-sm font-semibold cursor-pointer transition-colors" id="start-backup">
            &#9654; Start backup
        </button>

        <div id="progress-wrap" class="hidden mt-5">
            <div class="bg-indigo-50 rounded-full h-2 overflow-hidden mb-1.5">
                <div class="h-full w-0 bg-indigo-500 rounded-full transition-[width] duration-300 ease-in-out" id="progress-bar"></div>
            </div>
            <div id="progress-label" class="text-xs text-gray-400 text-right">0 / 0</div>
        </div>

        <div id="status-log" class="mt-6 rounded-xl border border-gray-200 bg-gray-50 max-h-[380px] overflow-y-auto p-4 text-sm leading-relaxed text-gray-700">
            <span class="text-gray-400 italic">No backup started yet.</span>
        </div>

        <div class="hidden mt-5 p-3 px-4 rounded-lg border border-gray-200 bg-white items-center justify-between" id="download-row">
            <div class="text-sm text-gray-600 flex items-center gap-2">
                &#128230; <span id="download-filename-text"></span> <span class="text-xs font-bold px-2.5 py-0.5 rounded-full whitespace-nowrap ml-2.5 bg-green-50 text-green-700">New</span>
            </div>
            <a class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-indigo-500 hover:bg-indigo-600 text-white border-none rounded-lg text-sm font-semibold cursor-pointer no-underline transition-colors" id="download-link" href="#" download>
                &#8595; Download
            </a>
        </div>

        <div id="existing-backups-container" class="mt-6 hidden">
            <h3 class="text-base font-semibold mb-3 text-gray-700">Available Past Backups</h3>
            <div id="existing-backups-list" class="flex flex-col gap-2"></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
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
            row.className = 'flex items-center justify-between py-1 border-b border-gray-100 last:border-b-0';
            row.innerHTML = `<span class="text-gray-800">&#128196; ${name}</span>
                         <span class="badge text-xs font-bold px-2.5 py-0.5 rounded-full whitespace-nowrap ml-2.5 ${badgeClass}">${badgeText}</span>`;
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
            // Clears previous color utilities while maintaining base layout classes
            badge.className = `badge text-xs font-bold px-2.5 py-0.5 rounded-full whitespace-nowrap ml-2.5 ${cls}`;
            badge.textContent = text;
        }

        function setProgress(done, total) {
            const pct = total ? Math.round((done / total) * 100) : 0;
            progressBar.style.width = `${pct}%`;
            progressLbl.textContent = `${done} / ${total}`;
        }

        async function initializeBackupDashboard() {
            try {
                const response = await fetch(`/backup/tables?timestamp=${timestamp}`);
                existingList.innerHTML = '';
                const data = await response.json();
                if (data.files && data.files.length > 0) {
                    renderExistingFiles(data.files);
                }
            } catch (error) {
                console.error('Error fetching dashboard initial data:', error);
            }
        }

        function renderExistingFiles(files) {
            files.forEach(file => {
                let filePath = file.name;
                const timestamp = filePath.split('/').pop().replace('.zip', '');
                const dt = new Date(parseInt(timestamp));

                const fileName = `${toHyphenDateTime(dt)}.zip`;
                const webPath = filePath.replace('public/', '/');

                const row = document.createElement('div');
                row.className = 'flex items-center justify-between p-3 px-4 rounded-lg border border-gray-200 bg-white';

                row.innerHTML = `
                    <div class="text-sm text-gray-600 flex items-center gap-2">
                        &#128230; <span>${fileName}</span>
                    </div>
                    <div class="flex items-center">
                        <a class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-indigo-500 hover:bg-indigo-600 text-white border-none rounded-lg text-sm font-semibold cursor-pointer no-underline transition-colors" href="backup/download/${timestamp}" download="${fileName}">&#8595; Download</a>
                        <button class="delete-btn inline-flex items-center gap-2 px-4 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-900 border border-gray-300 rounded-lg text-sm font-semibold cursor-pointer transition-colors ml-2">Delete</button>
                    </div>
                `;

                row.querySelector('.delete-btn').onclick = async () => {
                    await deleteZipByFilename(timestamp);
                    setTimeout(() => {
                        initializeBackupDashboard();
                    }, 200);
                };
                existingList.appendChild(row);
            });

            existingContainer.style.className = 'mt-6 block';
            existingContainer.style.display = 'block';
        }

        async function startBackupProcess() {
            startBtn.disabled = true;
            log.innerHTML = '';
            downloadRow.classList.replace('flex', 'hidden');
            progressWrap.classList.replace('hidden', 'block');
            appendLog('<em>Fetching table list…</em>');

            try {
                const response = await fetch(`/backup/tables?timestamp=${timestamp}`);
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
                    appendTableRow(uid, tableName, 'Pending…', 'bg-gray-100 text-gray-500');
                    backupTableWithTimeout(tableName, 30000, timestamp, uid);
                }

                if (data.files) {
                    renderExistingFiles(data.files);
                }
            } catch (error) {
                appendLog(`<span class="text-red-700">Failed to fetch table list: ${error.message}</span>`);
                startBtn.disabled = false;
            }
        }

        async function backupTableWithTimeout(tableName, timeoutMs, ts, uid) {
            updateBadge(uid, 'Exporting…', 'bg-amber-50 text-amber-700');

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

            try {
                const response = await fetch(`/backup/export?table-name=${tableName}&timestamp=${ts}`, {
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
                updateBadge(uid, 'Done', 'bg-green-50 text-green-700');
            } catch (error) {
                clearTimeout(timeoutId);
                error.name === 'AbortError' ?
                    updateBadge(uid, 'Timed out', 'bg-orange-50 text-orange-700') :
                    updateBadge(uid, `Failed: ${error.message}`, 'bg-red-50 text-red-700');
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
                const response = await fetch(`/backup/zip?timestamp=${timestamp}`, {
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

                downloadLink.href = result.url;
                downloadLink.download = fileName;
                downloadFilenameText.textContent = fileName;
                downloadRow.classList.replace('hidden', 'flex');

                const rows = log.querySelectorAll('.log-row, div.flex');
                const success = [...rows].filter(r => r.querySelector('.text-green-700')).length;
                const failed = [...rows].filter(r => r.querySelector('.text-red-700, .text-orange-700')).length;

                appendLog(`<div class="mt-3 pt-2.5 border-t border-dashed border-gray-300 text-sm font-semibold flex gap-4">
                <span class="text-green-700">&#10003; ${success} exported</span>
                ${failed ? `<span class="text-red-700">&#10007; ${failed} failed</span>` : ''}
            </div>`);

                initializeBackupDashboard();
            } catch (error) {
                appendLog(`<span class="text-red-700">ZIP creation failed: ${error.message}</span>`);
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
            await fetch(`/backup/${encodeURIComponent(fileName)}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                        '{{ csrf_token() }}',
                },
            });
        }
    </script>
@endpush