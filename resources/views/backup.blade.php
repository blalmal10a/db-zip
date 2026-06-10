@extends('db-zip::layouts.app')

@section('title', 'Database Backup')

@push('styles')
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.3.0/dist/index.global.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush

@section('content')
    <div class="bg-white p-6 rounded-xl shadow-xs max-w-2xl mx-auto border border-gray-100"
         x-data="backupDashboard()"
         x-init="initDashboard()">
         
        <h2 class="m-0 mb-1 text-2xl font-semibold text-gray-900">Database backup</h2>
        <p class="text-gray-500 text-sm m-0 mb-6">Exports all tables as CSV files and packages them into a downloadable ZIP archive.</p>

        <button class="inline-flex items-center gap-2 px-6 py-2.5 bg-gray-100 hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed text-gray-900 border border-gray-300 rounded-lg text-sm font-semibold cursor-pointer transition-colors" 
                :disabled="isWorking"
                @click="startBackupProcess()">
            &#9654; Start backup
        </button>

        <div class="mt-5" x-show="progress.visible" x-cloak>
            <div class="bg-indigo-50 rounded-full h-2 overflow-hidden mb-1.5">
                <div class="h-full bg-indigo-500 rounded-full transition-[width] duration-300 ease-in-out" 
                     :style="`width: ${progress.percentage}%`"></div>
            </div>
            <div class="text-xs text-gray-400 text-right" x-text="`${progress.done} / ${progress.total}`">0 / 0</div>
        </div>

        <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 max-h-[380px] overflow-y-auto p-4 text-sm leading-relaxed text-gray-700"
             x-ref="logContainer">
            <template x-if="logs.length === 0">
                <span class="text-gray-400 italic">No backup started yet.</span>
            </template>
            <template x-for="log in logs" :key="log.id">
                <div>
                    <template x-if="log.type === 'table'">
                        <div class="flex items-center justify-between py-1 border-b border-gray-100 last:border-b-0">
                            <span class="text-gray-800">&#128196; <span x-text="log.name"></span></span>
                            <span class="text-xs font-bold px-2.5 py-0.5 rounded-full whitespace-nowrap ml-2.5" 
                                  :class="log.badgeClass" 
                                  x-text="log.badgeText"></span>
                        </div>
                    </template>
                    <template x-if="log.type === 'html'">
                        <div x-html="log.content"></div>
                    </template>
                </div>
            </template>
        </div>

        <div class="mt-5 p-3 px-4 rounded-lg border border-gray-200 bg-white items-center justify-between flex" 
             x-show="download.visible" x-cloak>
            <div class="text-sm text-gray-600 flex items-center gap-2">
                &#128230; <span x-text="download.filename"></span> <span class="text-xs font-bold px-2.5 py-0.5 rounded-full whitespace-nowrap ml-2.5 bg-green-50 text-green-700">New</span>
            </div>
            <a class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-indigo-500 hover:bg-indigo-600 text-white border-none rounded-lg text-sm font-semibold cursor-pointer no-underline transition-colors" 
               :href="download.url" 
               :download="download.filename">
                &#8595; Download
            </a>
        </div>

        <div class="mt-6" x-show="existingFiles.length > 0" x-cloak>
            <h3 class="text-base font-semibold mb-3 text-gray-700">Available Past Backups</h3>
            <div class="flex flex-col gap-2">
                <template x-for="file in existingFiles" :key="file.timestamp">
                    <div class="flex items-center justify-between p-3 px-4 rounded-lg border border-gray-200 bg-white">
                        <div class="text-sm text-gray-600 flex items-center gap-2">
                            &#128230; <span x-text="file.displayUrl"></span>
                        </div>
                        <div class="flex items-center">
                            <a class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-indigo-500 hover:bg-indigo-600 text-white border-none rounded-lg text-sm font-semibold cursor-pointer no-underline transition-colors" 
                               :href="`backup/download/${file.timestamp}`" 
                               :download="file.displayUrl">&#8595; Download</a>
                            <button class="inline-flex items-center gap-2 px-4 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-900 border border-gray-300 rounded-lg text-sm font-semibold cursor-pointer transition-colors ml-2"
                                    @click="deleteZipByFilename(file.timestamp)">Delete</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function backupDashboard() {
            return {
                timestamp: Date.now(),
                isWorking: false,
                logs: [],
                existingFiles: [],
                progress: { visible: false, done: 0, total: 0, percentage: 0 },
                download: { visible: false, filename: '', url: '#' },
                csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',

                async initDashboard() {
                    await this.fetchExistingBackups();
                },

                async fetchExistingBackups() {
                    try {
                        const response = await fetch(`/backup/tables?timestamp=${this.timestamp}`);
                        const data = await response.json();
                        if (data.files) {
                            this.existingFiles = data.files.map(file => {
                                const ts = file.name.split('/').pop().replace('.zip', '');
                                return {
                                    timestamp: ts,
                                    displayUrl: `${this.toHyphenDateTime(new Date(parseInt(ts)))}.zip`
                                };
                            });
                        }
                    } catch (error) {
                        console.error('Error fetching dashboard initial data:', error);
                    }
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

                async startBackupProcess() {
                    this.isWorking = true;
                    this.logs = [];
                    this.download.visible = false;
                    this.progress.visible = true;
                    this.appendHtmlLog('<em>Fetching table list…</em>');

                    try {
                        const response = await fetch(`/backup/tables?timestamp=${this.timestamp}`);
                        const data = await response.json();
                        const tablesArray = data.tables ? data.tables : data;

                        this.logs = [];
                        this.updateProgress(0, tablesArray.length);
                        this.appendHtmlLog(`Found <strong>${tablesArray.length}</strong> table(s). Starting export…<br>`);

                        let countDoneTable = 0;
                        
                        // Map tables out to track reactive components
                        const exportPromises = tablesArray.map(async (item) => {
                            const tableName = item.name || item;
                            const tableLog = {
                                id: 'log-' + Math.random().toString(36).substring(2, 11),
                                type: 'table',
                                name: tableName,
                                badgeText: 'Pending…',
                                badgeClass: 'bg-gray-100 text-gray-500'
                            };
                            this.logs.push(tableLog);

                            await this.backupTableWithTimeout(tableName, 30000, tableLog);
                            
                            countDoneTable++;
                            this.updateProgress(countDoneTable, tablesArray.length);
                        });

                        await Promise.all(exportPromises);
                        await this.zipFolder();
                    } catch (error) {
                        this.appendHtmlLog(`<span class="text-red-700">Failed to fetch table list: ${error.message}</span>`);
                    } finally {
                        this.isWorking = false;
                        await this.fetchExistingBackups();
                    }
                },

                async backupTableWithTimeout(tableName, timeoutMs, logRef) {
                    logRef.badgeText = 'Exporting…';
                    logRef.badgeClass = 'bg-amber-50 text-amber-700';

                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

                    try {
                        const response = await fetch(`/backup/export?table-name=${tableName}&timestamp=${this.timestamp}`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': this.csrfToken,
                                'Content-Type': 'application/json'
                            },
                            signal: controller.signal
                        });

                        clearTimeout(timeoutId);
                        if (!response.ok) throw new Error(`Server error ${response.status}`);
                        await response.json();
                        
                        logRef.badgeText = 'Done';
                        logRef.badgeClass = 'bg-green-50 text-green-700';
                    } catch (error) {
                        clearTimeout(timeoutId);
                        if (error.name === 'AbortError') {
                            logRef.badgeText = 'Timed out';
                            logRef.badgeClass = 'bg-orange-50 text-orange-700';
                        } else {
                            logRef.badgeText = `Failed: ${error.message}`;
                            logRef.badgeClass = 'bg-red-50 text-red-700';
                        }
                    }
                    this.scrollToBottom();
                },

                async zipFolder() {
                    try {
                        const response = await fetch(`/backup/zip?timestamp=${this.timestamp}`, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Content-Type': 'application/json' }
                        });
                        const result = await response.json();

                        const d = new Date(this.timestamp);
                        const localeStr = d.toLocaleString('en-GB', {
                            year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false
                        });
                        const [date, time] = localeStr.split(', ');
                        const generatedFileName = `${date.split('/').reverse().join('-')}-${time.replace(':', '_')}.zip`;

                        this.download.url = result.url;
                        this.download.filename = generatedFileName;
                        this.download.visible = true;

                        const success = this.logs.filter(l => l.badgeClass?.includes('text-green-700')).length;
                        const failed = this.logs.filter(l => l.badgeClass?.includes('text-red-700') || l.badgeClass?.includes('text-orange-700')).length;

                        this.appendHtmlLog(`<div class="mt-3 pt-2.5 border-t border-dashed border-gray-300 text-sm font-semibold flex gap-4">
                            <span class="text-green-700">&#10003; ${success} exported</span>
                            ${failed ? `<span class="text-red-700">&#10007; ${failed} failed</span>` : ''}
                        </div>`);
                    } catch (error) {
                        this.appendHtmlLog(`<span class="text-red-700">ZIP creation failed: ${error.message}</span>`);
                    }
                },

                async deleteZipByFilename(ts) {
                    try {
                        await fetch(`/backup/${encodeURIComponent(ts)}`, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': this.csrfToken },
                        });
                        await this.fetchExistingBackups();
                    } catch (error) {
                        console.error('Failed to delete zip file:', error);
                    }
                },

                toHyphenDateTime(date = new Date()) {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    const hours = String(date.getHours()).padStart(2, '0');
                    const minutes = String(date.getMinutes()).padStart(2, '0');
                    const seconds = String(date.getSeconds()).padStart(2, '0');
                    return `${year}-${month}-${day} ${hours}-${minutes}-${seconds}`;
                }
            }
        }
    </script>
@endpush