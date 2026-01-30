<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>APEX MONITORING | 24H RUNTIME</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .glow {
            box-shadow: 0 0 15px rgba(234, 179, 8, 0.3);
        }

        .pulse-red {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.4;
            }

            100% {
                opacity: 1;
            }
        }
    </style>
</head>

<body class="bg-[#0b1120] text-slate-200 font-mono" x-data="healthMonitor()" x-init="init()">
    <div class="min-h-screen flex flex-col p-8">
        <div class="flex justify-between items-start mb-12">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <span class="bg-yellow-500 text-black px-2 py-0.5 font-black text-sm">PSTORE</span>
                    <span class="text-xs text-slate-500 tracking-widest uppercase">Apex Infrastructure v1.0</span>
                </div>
                <h1 class="text-3xl font-bold tracking-tighter">RUNTIME <span class="text-yellow-500">MONITOR</span>
                </h1>
            </div>
            <div class="text-right">
                <div class="text-xs text-slate-500 mb-1 italic uppercase">Live Server Time</div>
                <div class="text-xl font-bold font-mono text-yellow-500" x-text="serverTime">00:00:00</div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-slate-900/50 border border-slate-800 p-6 rounded-sm glow">
                <div class="flex justify-between items-center mb-6">
                    <span class="text-[10px] tracking-widest text-slate-500 uppercase">System API</span>
                    <div class="h-2 w-2 rounded-full bg-green-500 pulse-red"></div>
                </div>
                <div class="text-3xl font-bold mb-1 uppercase" x-text="health.status">---</div>
                <div class="text-[10px] text-slate-500">Latency: <span class="text-green-400">12ms</span></div>
            </div>

            <div class="bg-slate-900/50 border border-slate-800 p-6 rounded-sm">
                <div class="flex justify-between items-center mb-6">
                    <span class="text-[10px] tracking-widest text-slate-500 uppercase">Database Instance</span>
                    <i class="fas fa-database text-slate-700 text-xs"></i>
                </div>
                <div class="text-xl font-bold mb-1 truncate" x-text="health.database">---</div>
                <div class="text-[10px] text-slate-500">Engine: <span class="text-blue-400">PostgreSQL</span></div>
            </div>

            <div class="bg-slate-900/50 border border-slate-800 p-6 rounded-sm">
                <div class="flex justify-between items-center mb-6">
                    <span class="text-[10px] tracking-widest text-slate-500 uppercase">Memory Usage</span>
                    <i class="fas fa-microchip text-slate-700 text-xs"></i>
                </div>
                <div class="text-xl font-bold mb-1" x-text="health.memory_usage">0 MB</div>
                <div class="w-full bg-slate-800 h-1 mt-2">
                    <div class="bg-yellow-500 h-1" style="width: 45%"></div>
                </div>
            </div>

            <div class="bg-slate-900/50 border border-slate-800 p-6 rounded-sm">
                <div class="flex justify-between items-center mb-6">
                    <span class="text-[10px] tracking-widest text-slate-500 uppercase">Uptime Ratio</span>
                    <i class="fas fa-clock text-slate-700 text-xs"></i>
                </div>
                <div class="text-3xl font-bold mb-1" x-text="health.uptime">---</div>
                <div class="text-[10px] text-slate-400">Target: <span class="text-yellow-600">99.99%</span></div>
            </div>
        </div>

        <div class="mt-8 bg-black/40 border border-slate-800 p-4 rounded-sm font-mono text-[11px] overflow-hidden">
            <div class="text-slate-600 mb-2 flex items-center gap-2">
                <span class="inline-block w-2 h-2 bg-yellow-500"></span>
                LIVE SYSTEM LOGS [srv1247904]
            </div>
            <div class="space-y-1 h-32">
                <p class="text-green-500/80">[INFO] <span x-text="serverTime"></span> - Request GET /api/v1/products -
                    200 OK</p>
                <p class="text-slate-500">[DEBUG] <span x-text="serverTime"></span> - Cache driver (redis) connected.
                </p>
                <p class="text-yellow-500/80">[WARN] <span x-text="serverTime"></span> - High traffic detected on branch
                    JKT01.</p>
                <p class="text-blue-500/80">[JOBS] <span x-text="serverTime"></span> - Processing transaction sync...
                </p>
            </div>
        </div>

        <div class="mt-auto pt-8 flex border-t border-slate-800/50 justify-between items-center">
            <div class="flex gap-6">
                <a href="/pulse"
                    class="text-[10px] text-slate-500 hover:text-yellow-500 transition tracking-widest uppercase underline underline-offset-4">Internal
                    Pulse</a>
                <a href="https://stokps.com"
                    class="text-[10px] text-slate-500 hover:text-yellow-500 transition tracking-widest uppercase underline underline-offset-4">Terminal
                    POS</a>
            </div>
            <div class="text-[10px] text-slate-600 tracking-tighter uppercase">
                &copy; 2026 Fabian Solutions x PStore - Jakarta
            </div>
        </div>
    </div>

    <script>
        function healthMonitor() {
            return {
                serverTime: '--:--:--',
                health: { status: 'loading', database: '---', memory_usage: '0 MB', uptime: '---' },
                init() {
                    this.updateData();
                    setInterval(() => this.updateData(), 3000); // Update tiap 3 detik
                },
                updateData() {
                    fetch('/api/health-check')
                        .then(res => res.json())
                        .then(data => {
                            this.health = data;
                            this.serverTime = data.server_time.split(' ')[1];
                        });
                }
            }
        }
    </script>
</body>

</html>