<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>KYC Loader</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html, body {
      height: 100%;
      width: 100%;
      background: radial-gradient(circle at center, #0db95d 0%, #003144 100%);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      color: #fff;
      flex-direction: column;
    }

    .box-container {
      background: linear-gradient(135deg, #ffffff0f, #ffffff1c);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
      padding: 30px;
      max-width: 400px;
      width: 90%;
      text-align: center;
      animation: fadeIn 1s ease forwards;
      position: relative;
    }

    .kyc-loader {
      position: relative;
      width: 180px;
      height: 180px;
      margin: 0 auto 20px;
      border-radius: 50%;
      background: radial-gradient(circle at center, #14d47c 0%, #00794e 80%);
      box-shadow:
        0 0 15px rgba(0, 255, 128, 0.5),
        0 0 35px rgba(0, 255, 128, 0.3),
        0 0 60px rgba(0, 255, 128, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      animation: pulse 2.5s infinite ease-in-out;
    }

    .kyc-loader::before,
    .kyc-loader::after {
      content: "";
      position: absolute;
      border-radius: 50%;
    }

    .kyc-loader::before {
      top: -15px;
      left: -15px;
      right: -15px;
      bottom: -15px;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .kyc-loader::after {
      top: -30px;
      left: -30px;
      right: -30px;
      bottom: -30px;
      border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .kyc-text {
      font-size: 48px;
      font-weight: bold;
      color: rgba(255, 255, 255, 0.85);
      letter-spacing: 2px;
      text-shadow: 0 0 10px rgba(0, 255, 128, 0.5);
      z-index: 2;
    }

    .orbiter {
      position: absolute;
      width: 100%;
      height: 100%;
      animation: rotate 5s linear infinite;
      z-index: 1;
    }

    .orbiter::before {
      content: "";
      position: absolute;
      top: 50%;
      left: 50%;
      width: 10px;
      height: 10px;
      background-color: #ffffff;
      border-radius: 50%;
      transform: translate(85px, -50%);
      box-shadow: 0 0 10px rgba(255, 255, 255, 0.7);
    }

    .text-block {
      margin-top: 20px;
      padding: 15px;
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.05);
      border-left: 4px solid #00ff88;
    }

    .text-block p {
      font-size: 1.05rem;
      color: #ffffff;
      margin-bottom: 10px;
      line-height: 1.5;
    }

    .text-block p strong {
      color: #00ff88;
    }

    .warning-text {
      border-left: 4px solid #ffaa00;
      background: linear-gradient(135deg, #fff4d10a, #ffcc000a);
      color: #ffefbb;
    }

    @keyframes rotate {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    @keyframes pulse {
      0%, 100% {
        transform: scale(1);
        box-shadow:
          0 0 15px rgba(0, 255, 128, 0.5),
          0 0 35px rgba(0, 255, 128, 0.3),
          0 0 60px rgba(0, 255, 128, 0.2);
      }
      50% {
        transform: scale(1.05);
        box-shadow:
          0 0 25px rgba(0, 255, 128, 0.6),
          0 0 45px rgba(0, 255, 128, 0.4),
          0 0 75px rgba(0, 255, 128, 0.3);
      }
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  </style>
</head>
<body>

  <!-- Main One Box Container -->
  <div class="box-container" id="box">
    <div class="kyc-loader">
      <div class="orbiter"></div>
      <div class="kyc-text">KYC</div>
    </div>

    <div class="text-block" id="messageBlock" style="display:none;">
      <p>👋 <strong>Heads up!</strong> We're preparing to launch the <strong>KYC form</strong>.<br/>
      Please <strong>check back shortly</strong> and thank you for your patience 🙏</p>
    </div>

    <div class="text-block warning-text" id="warningBlock" style="display:none;">
      <p>⚠️ <strong>Important:</strong> The KYC window is open for <strong>only 2 weeks</strong>.<br/>
      Don’t delay! Ensure you submit <strong>valid and accurate information</strong> to avoid disqualification. ⏳</p>
    </div>
  </div>

  <script>
    // Show message and warning after loader time
    setTimeout(() => {
      document.getElementById('messageBlock').style.display = 'block';
      document.getElementById('warningBlock').style.display = 'block';
    }, 5000);
  </script>

</body>
</html>
