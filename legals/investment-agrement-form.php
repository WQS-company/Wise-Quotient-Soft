<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Riyawa Contractors Nigeria Ltd - Investment Terms Agreement</title>
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
    .contract-info {
      font-size: 14px;
      margin-top: 10px;
      text-align: left;
    }
    .contract-info strong { color: var(--dark-color); }
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
    .underline {
      display:inline-block;
      border-bottom:1px solid #000;
      min-width:200px;
      padding-bottom:2px;
    }
    .long-underline {
      display:block;
      border-bottom:1px solid #000;
      min-width:100%;
      padding-bottom:6px;
      margin:6px 0 8px 0;
    }
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
    .witness-section {
      margin-top: 28px;
      border:1.2px solid var(--border-color);
      padding:12px;
      border-radius:6px;
    }
    .witness-grid {
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:18px;
    }
    .witness-box {
      border-bottom:1px solid #000;
      min-height:40px;
      padding-top:6px;
      font-size:14px;
    }
    @media print {
      #printButton { display: none; }
      body { padding: 0; background: none; }
      .form-wrapper { border: none; box-shadow: none; page-break-inside: avoid; }
      .section, .signature-section, .witness-section { page-break-inside: avoid; }
      .watermark { opacity:0.05 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
    @media (max-width: 768px) {
      .signature-section { grid-template-columns: 1fr; }
      .witness-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

  <button id="printButton" onclick="window.print()">🖨️ Print Investment Terms</button>

  <div class="form-wrapper">
    <div class="watermark"></div>
    <div class="top-badge">INVESTMENT TERMS AGREEMENT</div>

    <div class="form-header">
      <img src="logo.png" alt="Company Logo" />
      <div class="form-title">RIYAWA CONTRACTORS NIGERIA LIMITED</div>
      <div class="sub-title">Private Investment Terms & Conditions</div>

      <div class="passport-box">
        INVESTOR<br/>PASSPORT<br/>PHOTOGRAPH
      </div>
    </div>

    <div class="contract-info">
      <strong>Agreement Ref:</strong> _____________________
      <br><strong>Date:</strong> ______/ _______ /____________
    </div>

    <div class="section">
      <div class="section-title">PARTIES</div>
      <p class="para"><strong>Company:</strong> Riyawa Contractors Nigeria Limited, Address:______________________________________________________________</p>
      <p class="para"><strong>Investor:</strong> __________________________________________ Address:______________________________________________________________</p>
    </div>

    <div class="section">
      <div class="section-title">1. PURPOSE</div>
      <p class="para">This Investment Terms Agreement (“Agreement”) outlines the terms under which the Investor agrees to provide capital to the Company for business operations, projects, and related activities.</p>
    </div>

    <div class="section">
      <div class="section-title">2. INVESTMENT AMOUNT</div>
      <p class="para">The Investor agrees to invest the sum of ₦_____________________________________ for _____________________________________________________________________________________________________</span> in the Company.</p>
    </div>

    <div class="section">
      <div class="section-title">3. RETURN ON INVESTMENT</div>
      <p class="para">The Company agrees to provide the Investor with a return of ______%</span> per annum / project cycle, payable in accordance with the agreed schedule.</p>
    </div>

    <div class="section">
      <div class="section-title">4. TERM OF INVESTMENT</div>
      <p class="para">The term of this investment shall be _________ months/years</span> commencing on the date of fund receipt.</p>
    </div>

    <div class="section">
      <div class="section-title">5. PAYMENT TERMS</div>
      <p class="para">All payments due to the Investor shall be made via bank transfer to the account provided in writing by the Investor.</p>
    </div>

    <div class="section">
      <div class="section-title">6. RIGHTS & OBLIGATIONS</div>
      <p class="para">The Investor shall have the right to receive periodic updates on the use of funds and project status. The Company shall ensure that funds are used solely for the agreed purpose.</p>
    </div>

    <div class="section">
      <div class="section-title">7. CONFIDENTIALITY</div>
      <p class="para">Both parties agree to keep all investment-related information confidential, except where disclosure is required by law.</p>
    </div>

    <div class="section">
      <div class="section-title">8. TERMINATION</div>
      <p class="para">This Agreement may be terminated by mutual consent or upon completion of the investment term and full payment of returns.</p>
    </div>

    <div class="section">
      <div class="section-title">9. GOVERNING LAW</div>
      <p class="para">This Agreement shall be governed by and construed in accordance with the laws of the Federal Republic of Nigeria.</p>
    </div>

    <div class="section">
      <p class="para" style="text-align:center; font-size:12px;">
        This Agreement may be executed in counterparts and/or electronically; each executed counterpart is an original and all counterparts together constitute one instrument.
      </p>
    </div>

    <!-- Signatures -->
    <div class="signature-section">
      <div class="signature-box">
        <div class="sig-label">For and on behalf of</div>
        <div style="text-align:center; font-weight:bold; margin-top:6px;">Riyawa Contractors Nigeria Limited</div>
        <div class="signature-line"></div>
        <div style="text-align:center;">Authorized Signatory</div>
        <div style="text-align:center; margin-top:6px;">Date: ______/ _______ / ____________</div>
      </div>

      <div class="signature-box">
        <div class="sig-label">For and on behalf of</div>
        <div style="text-align:center; font-weight:bold; margin-top:6px;">Investor</div>
        <div class="long-underline"></div>
        <div class="signature-line"></div>
        <div style="text-align:center;">Authorized Signatory</div>
        <div style="text-align:center; margin-top:6px;">Date: ______/ _______ / ____________</div>
      </div>
    </div>

    <!-- Witness -->
    <div class="witness-section" style="margin-top:28px;">
      <div class="section-title">Witness Information</div>
      <div class="witness-grid">
        <div>
          <div class="witness-box"></div>
          Witness Name
        </div>
        <div>
          <div class="witness-box"></div>
          Witness Address
        </div>
        <div>
          <div class="witness-box"></div>
          Witness Occupation
        </div>
        <div>
          <div class="witness-box"></div>
          Witness Signature & Date: ______/ _______ / ____________
        </div>
      </div>
    </div>

  </div>

</body>
</html>
