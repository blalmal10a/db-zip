@extends('db-zip::layouts.app')

@section('title', 'Restore Database Backup')

@push('styles')
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.3.0/dist/index.global.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush

@section('content')
<div class="bg-white p-6 rounded-xl shadow-xs max-w-2xl mx-auto border border-gray-100"
     x-data="restoreDashboard()">
     
    <h2 class="m-0 mb-1.5 text-2xl font-bold text-gray-900">Restore Database Backup</h2>
    <p class="text-gray-500 text-sm mb-6">Upload a ZIP archive containing <code>table.json</code> and CSV files per table.</p>

    <div class="mb-4 p-3 px-4 bg-amber-50 border border-amber-300 rounded-lg text-amber-800 text-sm" 
         x-show="nginxBanner" x-cloak>
        Server rejected the request. File may be too large (server limit). Try a smaller backup or increase <code>client_max_body_size</code>.
    </div>

    <div class="border-2 border-dashed border-indigo-100 rounded-xl p-7 text-center cursor-pointer transition-colors bg-slate-50/50 hover:bg-indigo-50/40 hover:border-indigo-400 group"
         :class="{ 'border-indigo-500 bg-indigo-50/40': isDragging }"
         @dragover.prevent="isDragging = true"
         @dragleave.prevent="isDragging = false"
         @drop.prevent="isDragging = false; handleDrop($event)">
         
        <input type="file" id="backup-zip" accept=".zip" class="hidden" x-ref="fileInput" @change="handleFileSelect($event)">
        <div class="text-3xl mb-2">&#128230;</div>
        <div class="text-sm text-gray-600">
            Drag &amp; drop your ZIP here, or <span class="text-indigo-500 font-semibold cursor-pointer" @click="$refs.fileInput.click()">browse</span>
        </div>
        <div class="mt-2 text-xs text-indigo-500 font-medium min-h-[18px]" x-text="fileName ? `📎 ${fileName}` : ''"></div>
    </div>

    <button class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-500 hover:bg-indigo-600 disabled:opacity-55 disabled:cursor-not-allowed text-white border-none rounded-lg text-sm font-semibold cursor-pointer transition-colors mt-5" 
            :disabled="!fileReady || isWorking"
            @click="startRestoreProcess()">
        &#9654; Start Restore
    </button>

    <div class="mt-5" x-show="progress.visible" x-cloak>
        <div class="bg-indigo-50 rounded-full h-2 overflow-hidden mb-1.5">
            <div class="h-full bg-gradient-to-r from-indigo-500 to-indigo-400 rounded-full transition-[width] duration-300 ease-in-out" 
                 :style="`width: ${progress.percentage}%`"></div>
        </div>
        <div class="text-xs text-gray-400 text-right" x-text="`${progress.done} / ${progress.total}`">0 / 0</div>
    </div>

    <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 max-h-[500px] overflow-y-auto p-4 text-sm leading-relaxed text-gray-700"
         x-ref="logContainer">
        <template x-if="logs.length === 0">
            <span class="text-gray-400 italic">No restore started yet.</span>
        </template>
        <template x-for="log in logs" :key="log.id">
            <div>
                <template x-if="log.type === 'html'">
                    <div x-html="log.content"></div>
                </template>

                <template x-if="log.type === 'group'">
                    <div class="my-1 border border-gray-200 rounded-md overflow-hidden">
                        <div class="flex items-center gap-2 p-2 px-3 bg-gray-100 hover:bg-gray-200 cursor-pointer select-none font-semibold text-sm transition-colors"
                             @click="log.expanded = !log.expanded">
                            <span class="text-[0.7rem] text-gray-400 transition-transform duration-200"
                                  :class="{ '-rotate-90': !log.expanded }">▼</span> 
                            📦 <span x-text="log.tableName"></span> 
                            <span class="text-xs font-normal text-gray-500 ml-auto" x-text="`${log.doneCount} / ${log.chunkCount}`"></span>
                        </div>
                        <div class="bg-white" x-show="log.expanded" x-cloak>
                            <template x-for="chunk in log.chunks" :key="chunk.id">
                                <div class="flex items-baseline justify-between p-1.5 pr-3 pl-7 border-t border-gray-100 text-xs">
                                    <span class="text-gray-700">📄 <span x-text="chunk.name"></span></span>
                                    <span class="text-xs font-bold px-2 py-0.5 rounded-full whitespace-nowrap ml-2.5 shrink-0"
                                          :class="chunk.badgeClass" x-text="chunk.badgeText"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" integrity="sha384-+mbV2IY1Zk/X1p/nWllGySJSUN8uMs+gUAN10Or95UBH0fpj6GfKgPmgC5EXieXG" crossorigin="anonymous"></script>
<script>
    function restoreDashboard() {
        return {
            isDragging: false,
            fileReady: false,
            isWorking: false,
            fileName: '',
            selectedFile: null,
            nginxBanner: false,
            logs: [],
            progress: { visible: false, done: 0, total: 0, percentage: 0 },
            csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',

            handleFileSelect(e) {
                const file = e.target.files[0];
                this.stageFile(file);
            },

            handleDrop(e) {
                const file = e.dataTransfer.files[0];
                if (file && file.name.endsWith('.zip')) {
                    this.stageFile(file);
                    // Update underlying file input element reference safely
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    this.$refs.fileInput.files = dt.files;
                }
            },

            stageFile(file) {
                if (!file) return;
                this.fileName = file.name;
                this.selectedFile = file;
                this.fileReady = true;
            },

            appendHtmlLog(html) {
                this.logs.push({ id: Math.random().toString(36).substring(2, 11), type: 'html', content: html });
                this.scrollToBottom();
            },

            scrollToBottom() {
                this.$nextTick(() => {
                    const el = this.$refs.logContainer;
                    el.scrollTop = el.scrollHeight;
                });
            },

            updateProgress(done, total) {
                this.progress.done = done;
                this.progress.total = total;
                this.progress.percentage = total ? Math.round((done / total) * 100) : 0;
            },

            extractTableName(filename) {
                return filename.replace(/_\d{3}\.csv$/i, '').replace(/\.csv$/i, '');
            },

            async buildSchemaMap(zip) {
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
                        if (match) map[match[1]] = sql;
                    }
                    return map;
                } catch (err) {
                    this.appendHtmlLog(`<span class="text-red-700">Could not parse table.json: ${err.message}</span>`);
                    return null;
                }
            },

            groupCsvFiles(csvFiles) {
                const map = {};
                csvFiles.forEach(entry => {
                    const cleanName = entry.name.split('/').pop();
                    const tableName = this.extractTableName(cleanName);
                    if (!map[tableName]) map[tableName] = [];
                    map[tableName].push(entry);
                });
                return Object.entries(map).map(([tableName, files]) => ({ tableName, files }));
            },

            async startRestoreProcess() {
                if (!this.selectedFile) return;

                this.isWorking = true;
                this.logs = [];
                this.nginxBanner = false;
                this.appendHtmlLog('<em>Reading ZIP archive…</em>');

                let zip;
                try {
                    zip = await new JSZip().loadAsync(this.selectedFile);
                } catch (err) {
                    this.appendHtmlLog(`<span class="text-red-700">Could not read ZIP: ${err.message}</span>`);
                    this.isWorking = false;
                    return;
                }

                const schemaMap = await this.buildSchemaMap(zip);
                const csvFiles = Object.values(zip.files).filter(f => !f.dir && f.name.toLowerCase().endsWith('.csv'));

                if (!csvFiles.length) {
                    this.appendHtmlLog("<span class='text-red-700'>No CSV files found in the ZIP.</span>");
                    this.isWorking = false;
                    return;
                }

                this.appendHtmlLog(`Found <strong>${csvFiles.length}</strong> CSV file(s).${schemaMap ? ` Schema loaded for <strong>${Object.keys(schemaMap).length}</strong> table(s).` : ' No <code>table.json</code> found — tables must already exist.'}<br>`);

                const groups = this.groupCsvFiles(csvFiles);
                this.progress.visible = true;
                this.updateProgress(0, groups.length);

                let successCount = 0, errorCount = 0;

                for (let gi = 0; gi < groups.length; gi++) {
                    const group = groups[gi];
                    
                    const groupLog = {
                        id: `group-${gi}`,
                        type: 'group',
                        tableName: group.tableName,
                        chunkCount: group.files.length,
                        doneCount: 0,
                        expanded: false,
                        chunks: []
                    };
                    this.logs.push(groupLog);

                    let firstChunk = true;

                    for (let ci = 0; ci < group.files.length; ci++) {
                        if (ci > 0) await new Promise(r => setTimeout(r, 500));

                        const zipEntry = group.files[ci];
                        const cleanName = zipEntry.name.split('/').pop();

                        const chunkState = {
                            id: `${groupLog.id}-chunk-${ci}`,
                            name: cleanName,
                            badgeText: 'Uploading…',
                            badgeClass: 'bg-amber-50 text-amber-700'
                        };
                        groupLog.chunks.push(chunkState);
                        this.scrollToBottom();

                        const blob = await zipEntry.async('blob');
                        const fileObject = new File([blob], cleanName, { type: 'text/csv' });
                        const tableSQL = firstChunk ? (schemaMap?.[group.tableName] ?? null) : '__append__';
                        firstChunk = false;

                        const ok = await this.uploadAndRestoreFile(fileObject, tableSQL, chunkState);
                        ok ? successCount++ : errorCount++;
                        groupLog.doneCount++;
                    }

                    if (errorCount) break;
                    this.updateProgress(gi + 1, groups.length);
                }

                this.appendHtmlLog(`<div class="mt-3 pt-2.5 border-t border-dashed border-gray-300 text-sm font-semibold">
                    ✅ <span class="text-green-700">${successCount} succeeded</span>
                    ${errorCount ? `&nbsp; ❌ <span class="text-red-700">${errorCount} failed</span>` : ''}
                </div>`);

                this.isWorking = false;
            },

            async uploadAndRestoreFile(file, tableSQL, chunkState) {
                const formData = new FormData();
                formData.append('file', file);
                if (tableSQL && tableSQL !== '__append__') formData.append('table_sql', tableSQL);
                if (tableSQL === '__append__') formData.append('append', '1');

                try {
                    const response = await fetch('/backup/restore', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': this.csrfToken },
                        body: formData,
                    });

                    const contentType = response.headers.get('content-type') || '';
                    if (contentType.includes('text/html') || contentType.includes('text/plain')) {
                        const text = await response.text();
                        if (text.includes('nginx') || text.includes('413') || text.includes('Request Entity Too Large')) {
                            this.nginxBanner = true;
                        }
                        throw new Error(`Server returned HTML — possible nginx limit`);
                    }

                    const result = await response.json();
                    if (!response.ok) throw new Error(result.error || `Server error ${response.status}`);

                    chunkState.badgeText = '✓ Success';
                    chunkState.badgeClass = 'bg-green-50 text-green-700';
                    return true;
                } catch (err) {
                    chunkState.badgeText = `✗ ${err.message}`;
                    chunkState.badgeClass = 'bg-red-50 text-red-700';
                    return false;
                }
            }
        }
    }
</script>
@endpush