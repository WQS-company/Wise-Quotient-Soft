<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Software Project Progress Statistics</title>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      font-family: sans-serif;
      padding: 20px;
      background: #f9f9f9;
    }

    h2 {
      color: #007f5f;
    }

    .stat-box {
      display: inline-block;
      padding: 15px;
      margin: 5px;
      background: #fff;
      border: 2px solid #007f5f;
      border-radius: 8px;
      font-weight: bold;
      color: #333;
      min-width: 140px;
      text-align: center;
    }

    canvas {
      margin-top: 30px;
      background: #fff;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 0 5px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body>

  <h2>📊 Software Project Progress Dashboard</h2>

  <div id="stats">
    <div class="stat-box">📅 Yesterday<br><span id="yesterday">3 Tasks</span></div>
    <div class="stat-box">🟢 Today<br><span id="today">5 Tasks</span></div>
    <div class="stat-box">📈 This Week<br><span id="week">18 Tasks</span></div>
    <div class="stat-box">📅 This Month<br><span id="month">42 Tasks</span></div>
    <div class="stat-box">📆 This Year<br><span id="year">128 Tasks</span></div>
    <div class="stat-box">🧮 Total<br><span id="total">213 Tasks</span></div>
  </div>

  <canvas id="chart" height="100"></canvas>

  <script>
    const fakeDates = [
      "2024-07-25", "2024-07-26", "2024-07-27",
      "2024-07-28", "2024-07-29", "2024-07-30",
      "2024-07-31"
    ];

    const fakeCompletions = [2, 3, 4, 6, 7, 5, 3];

    const ctx = document.getElementById('chart').getContext('2d');
    const chart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: fakeDates,
        datasets: [{
          label: 'Tasks Completed Per Day',
          data: fakeCompletions,
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
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: 'Tasks'
            }
          }
        },
        plugins: {
          legend: {
            display: true
          }
        }
      }
    });
  </script>

</body>
</html>
