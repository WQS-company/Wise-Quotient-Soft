<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>AgriTrust Program - Coordinator Application & Oath Form</title>
  <style>
    :root {
      --primary-color: #0db95d;
      --dark-color: #003144;
      --border-color: #888;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #f4f4f4;
      color: #111;
      padding: 20px;
    }
    #printButton {
      display: block;
      margin: 20px auto;
      padding: 10px 20px;
      font-size: 16px;
      background-color: var(--primary-color);
      color: #fff;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }
    #printButton:hover { background-color: #0aa34c; }
    .form-wrapper {
      max-width: 900px;
      margin: auto;
      background: #fff;
      border: 2px solid var(--dark-color);
      padding: 30px;
      position: relative;
      box-shadow: 0 0 12px rgba(0,0,0,0.08);
    }
    .watermark {
      position: absolute;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background-image: url('logo.png');
      background-repeat: repeat;
      background-position: center;
      background-size: 200px;
      opacity: 0.05;
      z-index: 0;
      pointer-events: none;
    }
    .form-wrapper > *:not(.watermark) { position: relative; z-index: 1; }
    .form-header {
      text-align: center;
      margin-bottom: 20px;
      padding-top: 20px;
    }
    .form-header img { width: 90px; margin-bottom: 10px; }
    .form-title {
      font-size: 22px;
      font-weight: 700;
      margin-bottom: 5px;
      color: var(--dark-color);
    }
    .sub-title {
      font-size: 14px;
      font-weight: bold;
      color: #444;
      margin-bottom: 15px;
    }
    .top-badge {
      position: absolute;
      top: -15px; left: -15px;
      background: var(--dark-color);
      color: #fff;
      padding: 8px 14px;
      font-size: 13px;
      font-weight: bold;
      border-radius: 4px 0 10px 0;
    }
    .passport-box {
      width: 140px;
      height: 160px;
      border: 2px solid #000;
      text-align: center;
      font-size: 13px;
      background: #fafafa;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 8px;
      margin: 0 auto 15px auto;
    }
    .section {
      margin-top: 20px;
      padding: 14px;
      border: 1.5px solid var(--border-color);
      border-radius: 6px;
      background: #fcfcfc;
    }
    .section-title {
      font-weight: bold;
      margin-bottom: 8px;
      font-size: 15px;
      color: var(--primary-color);
    }
    .para { font-size: 14px; line-height: 1.5; margin-bottom: 8px; }
    label { display: block; margin: 4px 0; font-size: 14px; }
    .checkbox-group { margin-left: 15px; }
    .signature-section {
      margin-top: 36px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 30px;
    }
    .signature-box {
      text-align: left;
      font-size: 14px;
    }
    .signature-line {
      display:block;
      border-bottom:1px solid #000;
      min-height:60px;
      margin-top:6px;
      padding-top:6px;
    }
    .sig-label { display:block; margin-top:8px; text-align:center; font-size:13px; }
    @media print {
      #printButton { display: none; }
      body { padding: 0; background: none; }
      .form-wrapper { border: none; box-shadow: none; page-break-inside: avoid; }
      .section, .signature-section { page-break-inside: avoid; }
      .watermark { opacity:0.05 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
    @media (max-width: 768px) {
      .signature-section { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

  <button id="printButton" onclick="window.print()">🖨️ Print Form</button>

  <div class="form-wrapper">
    <div class="watermark"></div>
    <div class="top-badge">AGRI-TRUST COORDINATOR APPLICATION</div>

    <div class="form-header">
      <img src="logo.png" alt="Company Logo" />
      <div class="form-title">RIYAWA CONTRACTORS NIGERIA LIMITED</div>
      <div class="sub-title">AgriTrust Program - Ward/LGA/State Coordinator Application & Oath</div>
      <div class="passport-box">
        COORDINATOR<br/>PASSPORT<br/>PHOTO
      </div>
    </div>

    <div class="section">
      <div class="section-title">PERSONAL INFORMATION</div>
      <p class="para"><strong>Full Name:</strong> ____________________________________________</p>
      <p class="para"><strong>Date of Birth:</strong> ____ / ____ / ________</p>
      <p class="para"><strong>Gender:</strong> Male ☐  Female ☐</p>
      <p class="para"><strong>Residential Address:</strong> _____________________________________________________________________</p>
      <p class="para"><strong>Phone Number:</strong> _____________________________</p>
      <p class="para"><strong>Email:</strong> ____________________________________________</p>
      <p class="para"><strong>National ID (NIN):</strong> _____________________________</p>
      <p class="para"><strong>BVN:</strong> _____________________________</p>
      <p class="para"><strong>Level of Education:</strong> _____________________________</p>
      <p class="para"><strong>Field of Study:</strong> _____________________________</p>
      <p class="para"><strong>Any Leadership Skills or Current Leadership Role:</strong> ____________________________________________</p>
    </div>

    <div class="section">
      <div class="section-title">PROGRAM COVERAGE AREA</div>
      <div class="checkbox-group">
        <label><input type="checkbox"> Ward</label>
        <label><input type="checkbox"> LGA</label>
        <label><input type="checkbox"> State</label>
      </div>
      <p class="para"><strong>Ward Name (if applicable):</strong> _____________________________</p>
      <p class="para"><strong>LGA Name (if applicable):</strong> _____________________________</p>
      <p class="para"><strong>State Name:</strong> _____________________________</p>
    </div>

    <div class="section">
      <div class="section-title">POSITION / ROLE</div>
      <p class="para">Tick your intended position (with abbreviation & full meaning):</p>
      <div class="checkbox-group">
        <label><input type="checkbox"> SC (Super Coordinator)</label>
        <label><input type="checkbox"> C (Coordinator)</label>
        <label><input type="checkbox"> AC1 (Assistant Coordinator 1)</label>
        <label><input type="checkbox"> AC2 (Assistant Coordinator 2)</label>
        <label><input type="checkbox"> AC3 (Assistant Coordinator 3)</label>
      </div>
      <p class="para"><strong>Unique Role Code (e.g., SC1, C3, AC1-02):</strong> _____________________________</p>
    </div>

    <div class="section">
      <div class="section-title">BANKING INFORMATION</div>
      <p class="para"><strong>Bank Name:</strong> ____________________________________________</p>
      <p class="para"><strong>Account Name:</strong> ____________________________________________</p>
      <p class="para"><strong>Account Number:</strong> _____________________________</p>
    </div>

    <div class="section">
      <div class="section-title">OATH OF OFFICE</div>
      <p class="para">
        I, ________________________________________, having been appointed as a Coordinator for the AgriTrust Program, do solemnly swear/affirm that I will:
      </p>
      <ul style="margin-left:20px; font-size:14px;">
        <li>Faithfully discharge my duties in accordance with the program’s rules and objectives.</li>
        <li>Coordinate beneficiaries in my assigned Ward, LGA, or State with integrity, fairness, and transparency.</li>
        <li>Ensure proper documentation and reporting of all program activities under my supervision.</li>
        <li>Not engage in any act of fraud, misappropriation, or misconduct.</li>
      </ul>
      <p class="para">
        So help me God.
      </p>
    </div>

    <div class="signature-section">
      <div class="signature-box">
        <div class="sig-label">Coordinator’s Signature</div>
        <div class="signature-line"></div>
        <div style="text-align:center;">Name & Date</div>
      </div>
      <div class="signature-box">
        <div class="sig-label">Program Head’s Signature</div>
        <div class="signature-line"></div>
        <div style="text-align:center;">Name & Date</div>
      </div>
    </div>

  </div>

</body>
</html>
