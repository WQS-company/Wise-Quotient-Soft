<?php
// DB config
$conn = new mysqli("localhost", "u835060520_agritrust", "@Abdul3232", "u835060520_agritrust");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Fetch stats function for all users
function fetchStats($conn) {
    $sql = "SELECT created_at FROM seerbit_customers ORDER BY created_at DESC";
    $res = $conn->query($sql);

    $dates = [];
    while ($row = $res->fetch_assoc()) {
        $d = (new DateTime($row['created_at']))->format('Y-m-d');
        $dates[] = $d;
    }

    $cnts = array_count_values($dates);
    ksort($cnts);

    // Date ranges
    $today      = date('Y-m-d');
    $yesterday  = date('Y-m-d', strtotime("-1 day"));
    $weekStart  = date('Y-m-d', strtotime("monday this week"));
    $monthStart = date('Y-m-01');
    $yearStart  = date('Y-01-01');

    $stats = [
        'today' => 0, 'yesterday' => 0,
        'week' => 0, 'month' => 0,
        'year' => 0, 'total' => count($dates)
    ];

    foreach ($dates as $d) {
        if ($d === $today) $stats['today']++;
        if ($d === $yesterday) $stats['yesterday']++;
        if ($d >= $weekStart) $stats['week']++;
        if ($d >= $monthStart) $stats['month']++;
        if ($d >= $yearStart) $stats['year']++;
    }

    return [
        'chart' => ['labels' => array_keys($cnts), 'values' => array_values($cnts)],
        'stats' => $stats
    ];
}

// AJAX call
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header('Content-Type: application/json');
    echo json_encode(fetchStats($conn));
    exit;
}

$data = fetchStats($conn);
$chartLabels = json_encode($data['chart']['labels']);
$chartValues = json_encode($data['chart']['values']);
$stats = $data['stats'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>AgriTrust Users Registration Statistics</title>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family: sans-serif; padding: 20px; background: #f9f9f9; }
    h2 { color: #007f5f; }
    .stat-box {
      display: inline-block;
      padding: 15px;
      margin: 5px;
      background: #fff;
      border: 2px solid #007f5f;
      border-radius: 8px;
      font-weight: bold;
      color: #333;
      min-width: 120px;
      text-align: center;
    }
    canvas { margin-top: 30px; background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 0 5px rgba(0,0,0,0.1); }
  </style>
</head>
<body>
  <h2>📊 AgriTrust Users Registration Statistics (Real-time)</h2>
  <div id="stats">
    <div class="stat-box">📅 Yesterday<br><span id="yesterday"><?= $stats['yesterday'] ?></span></div>
    <div class="stat-box">🟢 Today<br><span id="today"><?= $stats['today'] ?></span></div>
    <div class="stat-box">📈 This Week<br><span id="week"><?= $stats['week'] ?></span></div>
    <div class="stat-box">📅 This Month<br><span id="month"><?= $stats['month'] ?></span></div>
    <div class="stat-box">📆 This Year<br><span id="year"><?= $stats['year'] ?></span></div>
    <div class="stat-box">🧮 Total<br><span id="total"><?= $stats['total'] ?></span></div>
  </div>

  <canvas id="chart" height="100"></canvas>

  <script>
    const ctx = document.getElementById('chart').getContext('2d');
    const chart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: <?= $chartLabels ?>,
        datasets: [{
          label: 'Registrations',
          data: <?= $chartValues ?>,
          borderColor: '#007f5f',
          backgroundColor: 'rgba(0,127,95,0.1)',
          fill: true,
          tension: 0.4,
          pointRadius: 3,
          pointBackgroundColor: '#007f5f'
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: { beginAtZero: true }
        },
        plugins: {
          legend: { display: true }
        }
      }
    });

    function refreshStats() {
      $.getJSON("?ajax=1", function(d) {
        $('#yesterday').text(d.stats.yesterday);
        $('#today').text(d.stats.today);
        $('#week').text(d.stats.week);
        $('#month').text(d.stats.month);
        $('#year').text(d.stats.year);
        $('#total').text(d.stats.total);

        chart.data.labels = d.chart.labels;
        chart.data.datasets[0].data = d.chart.values;
        chart.update();
      });
    }

    setInterval(refreshStats, 1000); // refresh every second
  </script>
</body>
</html>
