<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>APEX | COMMAND CENTER</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap');

        body {
            font-family: 'JetBrains Mono', monospace;
        }

        .scanline {
            background: linear-gradient(to bottom, rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%), linear-gradient(to right, rgba(255, 0, 0, 0.06), rgba(0, 255, 0, 0.02), rgba(0, 0, 255, 0.06));
            background-size: 100% 2px, 3px 100%;
            pointer-events: none;
        }

        .stat-card {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(51, 65, 85, 0.5);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 2px;
            height: 100%;
            background: #eab308;
        }
    </style>
</head>

<body class="bg-[#020617] text-slate-300 overflow-x-hidden" x-data="commandCenter()" x-init="init()">
    <div class="scanline fixed inset-0 z-50 opacity-10"></div>

    <div class="min-h-screen p-6 flex flex-col gap-6 relative z-10">
        <header class="flex justify-between items-center border-b border-slate-800 pb-4">
            <div class="flex items-center gap-4">
                <div class="bg-yellow-500 text-black px-3 py-1 font-black skew-x-[-12deg]">PSTORE</div>
                <div class="h-4 w-[1px] bg-slate-700"></div>
                <div class="text-xs tracking-[0.3em] font-bold text-slate-500 uppercase">Apex Node: srv1247904</div>
            </div>
            <div class="flex gap-8 text-right">
                <div>
                    <div class="text-[10px] text-slate-500 uppercase">Global Time (UTC+7)</div>
                    <div class="text-xl font-bold text-yellow-500" x-text="data.server_time">00:00:00</div>
                </div>
                <div>
                    <div class="text-[10px] text-slate-500 uppercase">System Date</div>
                    <div class="text-sm font-bold" x-text="data.server_date">30 JAN 2026</div>
                </div>
            </div>
        </header>

        <main class="grid grid-cols-1 lg:grid-cols-4 gap-4">
            <div class="stat-card p-4">
                <div class="text-[10px] text-slate-500 mb-2 uppercase tracking-widest">Network Status</div>
                <div class="flex items-end gap-2">
                    <span class="text-3xl font-black text-white uppercase" x-text="data.status">---</span>
                    <span class="text-green-500 text-[10px] mb-1">● 12ms</span>
                </div>
            </div>
            <div class="stat-card p-4">
                <div class="text-[10px] text-slate-500 mb-2 uppercase tracking-widest">DB Cluster</div>
                <div class="text-xl font-bold text-white uppercase" x-text="data.database">---</div>
                <div class="text-[10px] text-blue-500 mt-1">PostgreSQL v16.x</div>
            </div>
            <div class="stat-card p-4">
                <div class="text-[10px] text-slate-500 mb-2 uppercase tracking-widest">Resources</div>
                <div class="text-xl font-bold text-white" x-text="data.memory_usage">0 MB</div>
                <div class="w-full bg-slate-800 h-1 mt-2">
                    <div class="bg-yellow-500 h-1 transition-all" :style="`width: 40%` text-center"></div>
                </div>
            </div>
            <div class="stat-card p-4 border-r-4 border-yellow-500">
                <div class="text-[10px] text-slate-500 mb-2 uppercase tracking-widest">Runtime Stability</div>
                <div class="text-3xl font-bold text-white" x-text="data.uptime">---</div>
            </div>
        </main>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 flex-1">
            <section
                class="lg:col-span-2 bg-slate-900/30 border border-slate-800 rounded-sm overflow-hidden flex flex-col">
                <div class="bg-slate-800/50 p-3 border-b border-slate-700 flex justify-between items-center">
                    <span class="text-xs font-bold flex items-center gap-2">
                        <i class="fas fa-users text-yellow-500"></i> LIVE PERSONNEL ACTIVITY
                    </span>
                    <span class="text-[9px] bg-yellow-500/10 text-yellow-500 px-2 py-0.5 rounded">AUTO REFRESH</span>
                </div>
                <div class="flex-1 overflow-y-auto">
                    <table class="w-full text-left text-[11px]">
                        <thead class="bg-slate-900/80 sticky top-0 text-slate-500 uppercase italic">
                            <tr>
                                <th class="p-3">Operator</th>
                                <th class="p-3">Branch Location</th>
                                <th class="p-3">Timezone</th>
                                <th class="p-3 text-right">Last Seen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/50">
                            <template x-for="user in data.active_personnel" :key="user.username">
                                <tr class="hover:bg-yellow-500/5 transition">
                                    <td class="p-3">
                                        <div class="font-bold text-slate-200" x-text="user.name"></div>
                                        <div class="text-[9px] text-slate-500" x-text="`ID: ${user.username}`"></div>
                                    </td>
                                    <td class="p-3">
                                        <span class="flex items-center gap-1">
                                            <i class="fas fa-map-marker-alt text-[9px] text-red-500"></i>
                                            <span x-text="user.branch"></span>
                                        </span>
                                    </td>
                                    <td class="p-3 text-slate-500" x-text="user.tz"></td>
                                    <td class="p-3 text-right text-yellow-500 font-bold" x-text="user.last_seen"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bg-black/60 border border-slate-800 rounded-sm flex flex-col">
                <div
                    class="p-3 border-b border-slate-800 text-[10px] font-bold text-slate-500 flex items-center justify-between">
                    <span>SYSTEM_LOG_STREAM</span>
                    <i class="fas fa-terminal"></i>
                </div>
                <div class="p-4 text-[10px] font-mono space-y-2 overflow-hidden flex-1">
                    <p class="text-green-500 italic">>> Initializing Apex Protocol...</p>
                    <p class="text-slate-500">[AUTH] <span x-text="data.server_time"></span> - Session validated for
                        super_admin</p>
                    <p class="text-blue-500">[SYNC] Branch JKT_CENTRAL synchronized successfully</p>
                    <p class="text-yellow-500/70">[WARN] Cache buffer reaching 70% threshold</p>
                    <p class="text-slate-400">>> Listening on port 443...</p>
                </div>
            </section>
        </div>

        <footer class="flex justify-between items-center text-[9px] text-slate-600 border-t border-slate-800 pt-4">
            <div>&copy; 2026 FABIAN SOLUTIONS X PSTORE GROUP | SECURE ACCESS ONLY</div>
            <div class="flex gap-4">
                <a href="/pulse" class="hover:text-yellow-500 transition underline">INTERNAL PULSE</a>
                <span class="text-slate-800">|</span>
                <a href="https://stokps.com" class="hover:text-yellow-500 transition underline">TERMINAL POS</a>
            </div>
        </footer>
    </div>

    <script>
        function commandCenter() {
            return {
                data: {
                    status: '...',
                    database: '...',
                    memory_usage: '0 MB',
                    uptime: '...',
                    server_time: '00:00:00',
                    server_date: '---',
                    active_personnel: []
                },
                init() {
                    this.fetchData();
                    setInterval(() => this.fetchData(), 5000);
                },
                fetchData() {
                    fetch('/api/health-check')
                        .then(res => res.json())
                        .then(res => this.data = res)
                        .catch(err => console.error("Signal Lost", err));
                }
            }
        }
    </script>
</body>

</html>