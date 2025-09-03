{{-- resources/views/filament/pages/report/charts.blade.php --}}
<div class="space-y-4">
    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-gray-500">Periode</div>
            <div class="mt-1 text-lg font-semibold">{{ $label }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-gray-500">Total Order</div>
            <div class="mt-1 text-2xl font-bold">{{ number_format($kpi['orders'] ?? 0, 0, ',', '.') }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-gray-500">Pendapatan</div>
            <div class="mt-1 text-2xl font-bold">Rp {{ number_format($kpi['revenue'] ?? 0, 0, ',', '.') }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-gray-500">AOV • SKU</div>
            <div class="mt-1 text-2xl font-bold">
                Rp {{ number_format($kpi['aov'] ?? 0, 0, ',', '.') }} • {{ number_format($kpi['sku'] ?? 0, 0, ',', '.') }}
            </div>
        </div>
    </div>

    {{-- CHARTS --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-2 font-semibold">Pendapatan per Bulan</div>
            <div class="h-64">
                <canvas id="revChart"></canvas>
            </div>
            <div class="mt-2 text-xs text-gray-500">
                Total: Rp {{ number_format(($revenue['total'] ?? 0), 0, ',', '.') }}
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-2 font-semibold">Metode Pembayaran (Paid)</div>
            <div class="h-64">
                <canvas id="methodChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Payload JSON untuk grafik (tanpa @json) --}}
<script type="application/json" id="report-charts-data">
    {
        !!json_encode([
            'revLabels' => $revenue['labels'] ?? [],
            'revValues' => $revenue['values'] ?? [],
            'mLabels' => $methods['labels'] ?? [],
            'mValues' => $methods['values'] ?? [],
        ], JSON_UNESCAPED_UNICODE) !!
    }
</script>

<script>
    (function renderReportCharts() {
        const ensureChartJs = () => new Promise((resolve) => {
            if (window.Chart) return resolve();
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            s.onload = () => resolve();
            document.head.appendChild(s);
        });

        ensureChartJs().then(() => {
            // Ambil payload JSON
            const dataEl = document.getElementById('report-charts-data');
            let payload = {
                revLabels: [],
                revValues: [],
                mLabels: [],
                mValues: []
            };
            try {
                payload = JSON.parse(dataEl?.textContent || '{}');
            } catch (e) {}

            // Hancurkan chart lama saat re-render Livewire
            if (window._revChart) window._revChart.destroy();
            if (window._methodChart) window._methodChart.destroy();

            const rupiah = v => new Intl.NumberFormat('id-ID').format(v ?? 0);

            // Bar: Revenue per bulan
            const revCtx = document.getElementById('revChart').getContext('2d');
            window._revChart = new Chart(revCtx, {
                type: 'bar',
                data: {
                    labels: payload.revLabels,
                    datasets: [{
                        label: 'Pendapatan (Rp)',
                        data: payload.revValues,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: v => rupiah(v)
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => ' Rp ' + rupiah(ctx.parsed.y)
                            }
                        }
                    }
                }
            });

            // Doughnut: metode pembayaran
            const mCtx = document.getElementById('methodChart').getContext('2d');
            window._methodChart = new Chart(mCtx, {
                type: 'doughnut',
                data: {
                    labels: payload.mLabels,
                    datasets: [{
                        data: payload.mValues
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: ctx => `${ctx.label}: Rp ${rupiah(ctx.parsed)}`
                            }
                        }
                    }
                }
            });
        });
    })();
</script>
