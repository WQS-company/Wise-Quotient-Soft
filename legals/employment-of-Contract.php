<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Riyawa Contractors Nigeria Ltd - Employment Contract</title>
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
    .date-line {
      display: inline-block;
      margin-top: 5px;
      font-size: 12px;
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
  <div class="top-badge">EMPLOYMENT CONTRACT</div>

  <div class="form-header">
    <img src="logo.png" alt="Company Logo" />
    <div class="form-title">RIYAWA CONTRACTORS NIGERIA LIMITED</div>
    <div class="sub-title">Employment Agreement</div>
    <div class="passport-box">
      EMPLOYEE<br/>PASSPORT<br/>PHOTOGRAPH
    </div>
  </div>

  <div class="contract-info">
    <strong>Contract Ref:</strong> _____________________  
    <br><strong>Date:</strong> ______/ _______ /____________
  </div>

  <div class="section">
    <div class="section-title">1. PARTIES</div>
    <div class="form-group">Employer: Riyawa Contractors Nigeria Limited</div>
    <div class="form-group">Address: ________________________________________________________________________________</div>
    <div class="form-group">Employee: _______________________________________________________________________________</div>
    <div class="form-group">Address: ________________________________________________________________________________</div>
  </div>

  <div class="section">
    <div class="section-title">2. POSITION & DUTIES</div>
    <p>The Employee agrees to serve in the capacity of _________________________________________________ and to perform all duties and responsibilities associated with this role in accordance with company policies and the Labour Act, Laws of the Federal Republic of Nigeria.</p>
  </div>

  <div class="section">
    <div class="section-title">3. COMMENCEMENT & TERM</div>
    <p>Employment shall commence on ______/ _______ /____________ and continue until terminated in accordance with the terms herein.</p>
  </div>

  <div class="section">
    <div class="section-title">4. PROBATION PERIOD</div>
    <p>The Employee shall be on probation for a period of __________________ months, during which either party may terminate this Agreement by giving one (1) week’s written notice or payment in lieu thereof.</p>
  </div>

  <div class="section">
    <div class="section-title">5. REMUNERATION & BENEFITS</div>
    <p>Salary: ₦_________________________________________ per __________________, payable according to company payroll policy.  
    Benefits include _____________________________________________, subject to applicable company policy.</p>
  </div>

  <div class="section">
    <div class="section-title">6. WORKING HOURS & LEAVE ENTITLEMENT</div>
    <p>The Employee shall work from __________________ to __________________, ___________ days per week. Annual leave of ________ working days per year, sick leave, and public holidays shall be granted as per company policy and the Labour Act.</p>
  </div>

  <div class="section">
    <div class="section-title">7. IDENTIFICATION & EMERGENCY CONTACT</div>
    <div class="form-group">ID Type: ___________________________________________</div>
    <div class="form-group">ID Number: _________________________________________</div>
    <div class="form-group">BVN: _____________________________________________________________________________________</div>
    <div class="form-group">Emergency Contact Name: __________________________________________________________________</div>
    <div class="form-group">Relationship to Employee: ________________________________________________________________</div>
    <div class="form-group">Emergency Contact Phone: _________________________________________________________________</div>
    <div class="form-group">Valid Email Address: _____________________________________________________________________</div>
    <div class="form-group">Blood Group: _______________________________________</div>
    <div class="form-group">Known Medical Conditions (optional): _____________________________________________________</div>
  </div>

  <div class="section">
    <div class="section-title">8. CONFIDENTIALITY</div>
    <p>The Employee shall not, during or after employment, disclose any confidential information belonging to the Employer without prior written consent, except as required by law.</p>
  </div>

  <div class="section">
    <div class="section-title">9. NON-COMPETE</div>
    <p>For a period of __________ months after termination, the Employee shall not engage in employment or business that directly competes with the Employer within __________ km of the Employer’s place of business, except with written consent.</p>
  </div>

  <div class="section">
    <div class="section-title">10. DISPUTE RESOLUTION</div>
    <p>Any dispute arising under this Agreement shall be resolved amicably through negotiation, failing which it shall be referred to mediation or arbitration in Nigeria, before recourse to litigation.</p>
  </div>

  <div class="section">
    <div class="section-title">11. FORCE MAJEURE</div>
    <p>Neither party shall be held liable for failure to perform its obligations under this Agreement due to causes beyond its reasonable control, including but not limited to acts of God, war, strikes, or governmental restrictions.</p>
  </div>

  <div class="section">
    <div class="section-title">12. TERMINATION</div>
    <p>Either party may terminate this Agreement by giving _______________________ written notice or payment in lieu, subject to the Labour Act, Laws of the Federal Republic of Nigeria.</p>
  </div>

  <div class="section">
    <div class="section-title">13. GOVERNING LAW</div>
    <p>This Agreement shall be governed by and construed in accordance with the Laws of the Federal Republic of Nigeria.</p>
  </div>

  <div class="section">
    <p style="font-size:12px; text-align:center; margin-top:15px;">
      This contract may be executed in counterparts and/or electronically via PDF or e-signature, and each copy shall be deemed an original.
    </p>
  </div>

 <!-- ... your other contract sections remain unchanged ... -->

<!-- Contract sections remain unchanged (1 - 13) -->

  <!-- Signatures -->
  <div class="signature-section">
    <div class="signature-box">
      <span class="signature-line"></span>
      Employer’s Signature &nbsp;&nbsp;&nbsp;&nbsp; Date: ____/____/________
    </div>
    <div class="signature-box">
      <span class="signature-line"></span>
      Employee’s Signature &nbsp;&nbsp;&nbsp;&nbsp; Date: ____/____/________
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
