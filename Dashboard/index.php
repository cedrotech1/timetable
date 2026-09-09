<?php
session_start();
include 'connection.php';
/* --------------------------------------------------------------
   1. JSON API – called with ?ajax=1
   -------------------------------------------------------------- */
$isAjax = isset($_GET['ajax']);

if ($isAjax) {
    header('Content-Type: application/json');

    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'DB connection failed']);
        exit;
    }

    $date = $_GET['date'] ?? date('Y-m-d');
    $response = ['success' => false, 'hourlyData' => array_fill(0, 24, 0)];

    try {
        $stmt = $conn->prepare("
            SELECT HOUR(created_at) AS hour, COUNT(*) AS count
            FROM timetable 
            WHERE DATE(created_at) = ?
            GROUP BY HOUR(created_at)
            ORDER BY hour
        ");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $response['hourlyData'][(int)$row['hour']] = (int)$row['count'];
        }
        $response['success'] = true;
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        http_response_code(500);
    }

    echo json_encode($response);
    exit;
}
?>
<?php if (!$isAjax): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Hourly Classes Dashboard</title>
    <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="assets/img/icon1.png" rel="icon">
  <link href="assets/img/icon1.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="assets/css/style.css" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .chart-container { position: relative; height: 420px; }
        .loading { opacity: 0.5; pointer-events: none; }
        .hour-label { font-family: 'Courier New', monospace; font-weight: bold; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen">
<?php include 'includes/header.php'; ?>
<?php include 'includes/menu.php'; ?>

<!-- main content -->
  <main id="main" class="main">
<div class="">

    <!-- Header Card -->
    <div class="bg-white rounded-2xl p-6 mb-6">
        <h1 class="text-3xl font-bold text-slate-800 text-center mb-2">
            Hourly Classes Distribution
        </h1>
        <p class="text-center text-slate-600">Track class activity throughout the day</p>
    </div>

    <!-- Controls + Chart Card -->
    <div class="bg-white rounded-2xl p-6">
        
        <!-- Date Picker & Stats -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <div class="flex items-center gap-3">
                <label for="datePicker" class="font-medium text-slate-700">Select Date:</label>
                <input 
                    type="date" 
                    id="datePicker" 
                    value="<?php echo date('Y-m-d'); ?>"
                    class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                />
                <button 
                    onclick="loadChart()" 
                    class="px-5 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition shadow-md"
                >
                    Update Chart
                </button>
            </div>

            <div id="totalClasses" class="text-lg font-semibold text-slate-700">
                Total: <span class="text-blue-600">0</span> classes
            </div>
        </div>

        <!-- Chart Container -->
        <div class="chart-container relative">
            <div id="loadingOverlay" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-xl hidden">
                <div class="animate-spin rounded-full h-12 w-12 border-4 border-blue-600 border-t-transparent"></div>
            </div>
            <canvas id="hourlyChart"></canvas>
        </div>

        <!-- Legend -->
        <div class="mt-4 flex justify-center gap-6 text-sm text-slate-600">
            <span>Hover bars for exact count</span>
            <span>•</span>
            <span>Peak hour shown in <strong class="text-blue-600">blue</strong></span>
        </div>
    </div>

   
</div>
</main>
<?php include 'includes/footer.php'; ?>
<script>
    let chartInstance = null;
    const ctx = document.getElementById('hourlyChart');
    const loading = document.getElementById('loadingOverlay');
    const totalSpan = document.getElementById('totalClasses').querySelector('span');

    function loadChart() {
        const date = document.getElementById('datePicker').value;
        document.getElementById('selectedDate')?.remove();

        // Show loading
        loading.classList.remove('hidden');
        ctx.parentElement.classList.add('loading');

        fetch(`?ajax=1&date=${date}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    alert(data.message || 'Failed to load data');
                    return;
                }

                const hourly = data.hourlyData;
                const total = hourly.reduce((a, b) => a + b, 0);
                totalSpan.textContent = total;

                const labels = Array.from({length: 24}, (_, i) => {
                    const h = i.toString().padStart(2, '0');
                    return `${h}:00`;
                });

                // Find peak hour
                const max = Math.max(...hourly);
                const peakIndex = hourly.indexOf(max);

                // Dynamic colors: highlight peak hour
                const backgroundColors = hourly.map((val, i) => 
                    i === peakIndex 
                        ? 'rgba(59, 130, 246, 0.8)'  // blue-500
                        : 'rgba(148, 163, 184, 0.6)' // slate-400
                );
                const borderColors = hourly.map((val, i) => 
                    i === peakIndex 
                        ? 'rgba(59, 130, 246, 1)' 
                        : 'rgba(100, 116, 139, 1)'
                );

                if (chartInstance) chartInstance.destroy();

                chartInstance = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Classes',
                            data: hourly,
                            backgroundColor: backgroundColors,
                            borderColor: borderColors,
                            borderWidth: 1.5,
                            borderRadius: 6,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            duration: 1200,
                            easing: 'easeOutQuart'
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: ctx => ` ${ctx.raw} class${ctx.raw !== 1 ? 'es' : ''}`
                                },
                                backgroundColor: 'rgba(15, 23, 42, 0.9)',
                                titleFont: { size: 13 },
                                bodyFont: { size: 14, weight: 'bold' },
                                padding: 12,
                                cornerRadius: 8
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: {
                                    font: { family: "'Courier New', monospace", size: 12, weight: 'bold' },
                                    color: '#475569'
                                },
                                title: {
                                    display: true,
                                    text: 'Hour of Day (24h)',
                                    font: { size: 14, weight: 'bold' },
                                    color: '#1e293b'
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(0,0,0,0.05)' },
                                ticks: {
                                    stepSize: 1,
                                    color: '#64748b',
                                    callback: value => value + (value === 1 ? ' class' : ' classes')
                                },
                                title: {
                                    display: true,
                                    text: 'Number of Classes',
                                    font: { size: 14, weight: 'bold' },
                                    color: '#1e293b'
                                }
                            }
                        }
                    }
                });

                // Hide loading
                loading.classList.add('hidden');
                ctx.parentElement.classList.remove('loading');
            })
            .catch(err => {
                console.error(err);
                alert('Network error');
                loading.classList.add('hidden');
            });
    }

    // Auto-load on page open
    window.addEventListener('load', loadChart);

    // Optional: Auto-update when date changes (no button needed)
    document.getElementById('datePicker').addEventListener('change', () => {
        setTimeout(loadChart, 300); // small delay for UX
    });
</script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>

</body>
</html>
<?php endif; ?>