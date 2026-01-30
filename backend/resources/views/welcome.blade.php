<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PStore Management - APEX API</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-slate-900 text-white font-sans">
    <div class="min-h-screen flex flex-col items-center justify-center p-6">
        <div class="mb-8 text-center">
            <div class="bg-yellow-500 text-black px-4 py-2 rounded-lg font-bold text-2xl inline-block mb-4">
                PStore
            </div>
            <h1 class="text-4xl font-extrabold tracking-tight">APEX <span class="text-yellow-500">SYSTEM</span> API</h1>
            <p class="text-slate-400 mt-2">Production Environment - srv1247904</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 w-full max-w-4xl">
            <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-xl">
                <div class="flex justify-between items-center mb-4">
                    <i class="fas fa-server text-yellow-500 text-2xl"></i>
                    <span class="bg-green-500/10 text-green-500 text-xs px-2 py-1 rounded">Online</span>
                </div>
                <h3 class="text-slate-400 text-sm">Server Status</h3>
                <p class="text-2xl font-bold">Stable</p>
            </div>

            <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-xl">
                <div class="flex justify-between items-center mb-4">
                    <i class="fas fa-database text-yellow-500 text-2xl"></i>
                    <span class="text-slate-400 text-xs font-mono">PostgreSQL</span>
                </div>
                <h3 class="text-slate-400 text-sm">Database Connect</h3>
                <p class="text-2xl font-bold italic">Connected</p>
            </div>

            <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-xl">
                <div class="flex justify-between items-center mb-4">
                    <i class="fas fa-link text-yellow-500 text-2xl"></i>
                    <span class="text-slate-400 text-xs">v1.0.0</span>
                </div>
                <h3 class="text-slate-400 text-sm">API Endpoint</h3>
                <p class="text-2xl font-bold">Active</p>
            </div>
        </div>

        <div class="mt-10 flex gap-4">
            <a href="/pulse"
                class="bg-slate-700 hover:bg-slate-600 px-6 py-3 rounded-xl transition font-medium flex items-center gap-2">
                <i class="fas fa-chart-line text-yellow-500"></i> Laravel Pulse
            </a>
            <a href="https://stokps.com" target="_blank"
                class="bg-yellow-500 hover:bg-yellow-400 text-black px-6 py-3 rounded-xl transition font-bold flex items-center gap-2">
                <i class="fas fa-external-link-alt"></i> Buka POS
            </a>
        </div>

        <footer class="mt-20 text-slate-500 text-sm">
            &copy; 2026 Fabian Solutions x PSTORE
        </footer>
    </div>
</body>

</html>