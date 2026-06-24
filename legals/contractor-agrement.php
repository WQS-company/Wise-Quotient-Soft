<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Riyawa Contractors Nigeria Ltd - Contractor Agreement</title>
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
      margin-top: 25px;
      padding: 15px;
      border: 1.5px solid var(--border-color);
      border-radius: 6px;
      background: #fcfcfc;
    }
    .section-title {
      font-weight: bold;
      margin-bottom: 8px;
      font-size: 16px;
      color: var(--primary-color);
    }
    .form-group {
      margin-bottom: 8px;
      font-size: 14px;
    }
    .signature-section {
      margin-top: 40px;
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 20px;
    }
    .signature-box {
      flex: 1;
      min-width: 250px;
      text-align: left;
      font-size: 14px;
    }
    .signature-line {
      display: block;
      border-bottom: 1px solid #000;
      margin-top: 30px;
      padding-bottom: 2px;
      text-align: center;
      font-size: 14px;
    }
    .witness-section {
      margin-top: 40px;
    }
    .witness-section-title {
      font-weight: bold;
      margin-bottom: 10px;
      color: var(--primary-color);
    }
    .witness-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    .witness-box {
      border-bottom: 1px solid #000;
      padding-bottom: 2px;
      min-height: 25px;
      font-size: 14px;
    }
    @media print {
      #printButton { display: none; }
      body { padding: 0; background: none; }
      .form-wrapper { border: none; box-shadow: none; page-break-inside: avoid; }
      .section { page-break-inside: avoid; }
      .watermark {
        opacity: 0.05 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
    }
    @media (max-width: 768px) {
      .signature-section { flex-direction: column; }
      .witness-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<button id="printButton" onclick="window.print()">🖨️ Print Contract</button>

<div class="form-wrapper">
  <div class="watermark"></div>
  <div class="top-badge">CONTRACTOR AGREEMENT</div>

  <div class="form-header">
    <img src="logo.png" alt="Company Logo" />
    <div class="form-title">RIYAWA CONTRACTORS NIGERIA LIMITED</div>
    <div class="sub-title">Independent Contractor Agreement</div>
    <div class="passport-box">
      CONTRACTOR<br/>PASSPORT<br/>PHOTOGRAPH
    </div>
  </div>

  <div class="contract-info">
    <strong>Agreement Ref:</strong> _____________________  
    <br><strong>Date:</strong> ______/ _______ /____________
  </div>

  <div class="section">
    <div class="section-title">1. PARTIES</div>
    <div class="form-group">Client: Riyawa Contractors Nigeria Limited</div>
    <div class="form-group">Address: ________________________________________________________________________________</div>
    <div class="form-group">Contractor: ______________________________________________________________________________</div>
    <div class="form-group">Address: ________________________________________________________________________________</div>
  </div>

  <div class="section">
    <div class="section-title">2. SCOPE OF WORK</div>
    <p>The Contractor agrees to provide the following services: _________________________________________________________ in accordance with the specifications, timelines, and quality standards agreed between the parties.</p>
  </div>

  <div class="section">
    <div class="section-title">3. TERM</div>
    <p>This Agreement shall commence on ______/ _______ /____________ and continue until ______/ _______ /____________ unless earlier terminated in accordance with this Agreement.</p>
  </div>

  <div class="section">
    <div class="section-title">4. MILESTONES & DELIVERABLES</div>
    <p>Project milestones and deadlines shall be as follows:  
    __________________________________________________________________________________________</p>
  </div>

  <div class="section">
    <div class="section-title">5. PAYMENT TERMS</div>
    <p>The Client shall pay the Contractor a total sum of ₦_________________________ for the services rendered, payable as follows:  
    __________________________________________________________________________________________  
    Payment shall be made upon satisfactory completion of agreed milestones and submission of invoice.</p>
  </div>

  <div class="section">
    <div class="section-title">6. CONTRACTOR STATUS</div>
    <p>The Contractor is engaged as an independent contractor and not as an employee. Nothing in this Agreement shall be construed to create a partnership, joint venture, or employer-employee relationship.</p>
  </div>

  <div class="section">
    <div class="section-title">7. CONFIDENTIALITY</div>
    <p>The Contractor shall not, during or after this Agreement, disclose any confidential information belonging to the Client without prior written consent, except as required by law.</p>
  </div>

  <div class="section">
    <div class="section-title">8. INTELLECTUAL PROPERTY</div>
    <p>All work products, designs, reports, and materials created under this Agreement shall become the sole property of the Client upon full payment, unless otherwise agreed in writing.</p>
  </div>

  <div class="section">
    <div class="section-title">9. NON-COMPETE</div>
    <p>For a period of __________ months after completion, the Contractor shall not provide identical services to a direct competitor of the Client within __________ km without written consent.</p>
  </div>

  <div class="section">
    <div class="section-title">10. DISPUTE RESOLUTION</div>
    <p>Any dispute arising under this Agreement shall be resolved amicably through negotiation, failing which it shall be referred to mediation or arbitration in Nigeria, before recourse to litigation.</p>
  </div>

  <div class="section">
    <div class="section-title">11. FORCE MAJEURE</div>
    <p>Neither party shall be held liable for failure to perform its obligations due to causes beyond reasonable control, including acts of God, war, strikes, or governmental restrictions.</p>
  </div>

  <div class="section">
    <div class="section-title">12. TERMINATION</div>
    <p>Either party may terminate this Agreement by giving _______________________ written notice or payment in lieu, provided that all completed work up to the date of termination is paid for in full.</p>
  </div>

  <div class="section">
    <div class="section-title">13. GOVERNING LAW</div>
    <p>This Agreement shall be governed by and construed in accordance with the Laws of the Federal Republic of Nigeria.</p>
  </div>

  <div class="section">
    <p style="font-size:12px; text-align:center; margin-top:15px;">
      This agreement may be executed in counterparts and/or electronically via PDF or e-signature, and each copy shall be deemed an original.
    </p>
  </div>

  <!-- Signatures -->
  <div class="signature-section">
    <div class="signature-box">
      <span class="signature-line"></span>
      Client’s Signature &nbsp;&nbsp;&nbsp;&nbsp; Date: ____/____/________
    </div>
    <div class="signature-box">
      <span class="signature-line"></span>
      Contractor’s Signature &nbsp;&nbsp;&nbsp;&nbsp; Date: ____/____/________
    </div>
  </div>

  <!-- Witness -->
  <div class="witness-section">
    <div class="witness-section-title">Witness Information</div>
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
        Witness Signature &nbsp;&nbsp;&nbsp;&nbsp; Date: ____/____/________
      </div>
    </div>
  </div>

</div>

</body>
</html>
