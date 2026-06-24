<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Riyawa Contractors Nigeria Ltd — Exit Policy & Release Form</title>
  <style>
    :root {
      --primary-color: #0db95d;
      --dark-color: #003144;
      --border-color: #888;
      --page-width: 920px;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      background: #f4f4f4;
      color: #111;
      padding: 20px;
    }

    #printButton {
      display: block;
      margin: 20px auto;
      padding: 10px 20px;
      font-size: 16px;
      background: var(--primary-color);
      color: #fff;
      border: none;
      border-radius: 6px;
      cursor: pointer;
    }
    #printButton:hover { background: #0aa34c; }

    .form-wrapper {
      max-width: var(--page-width);
      margin: auto;
      background: #fff;
      border: 2px solid var(--dark-color);
      padding: 28px;
      position: relative;
      box-shadow: 0 6px 20px rgba(0,0,0,0.06);
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

    .top-badge {
      position: absolute;
      top: -16px;
      left: -16px;
      background: var(--dark-color);
      color: #fff;
      padding: 8px 14px;
      font-weight: 700;
      border-radius: 4px 0 10px 0;
      font-size: 13px;
    }

    .form-header { text-align: center; margin-bottom: 18px; padding-top: 10px; }
    .form-header img { width: 88px; margin-bottom: 8px; display: block; margin-left: auto; margin-right: auto; }
    .form-title { font-size: 20px; font-weight: 700; color: var(--dark-color); margin-bottom: 4px; text-align: center; }
    .sub-title { font-size: 13px; color: #444; text-align: center; margin-bottom: 12px; }

    .passport-box {
      width: 140px;
      height: 160px;
      border: 2px solid #000;
      background: #fafafa;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 14px auto;
      font-size: 13px;
      text-align: center;
      padding: 8px;
    }

    .contract-info { font-size: 14px; margin-top: 6px; text-align: left; }
    .contract-info strong { color: var(--dark-color); }

    .section {
      margin-top: 18px;
      padding: 14px;
      border: 1.3px solid var(--border-color);
      border-radius: 6px;
      background: #fcfcfc;
    }
    .section-title {
      font-weight: 700;
      color: var(--primary-color);
      margin-bottom: 8px;
      font-size: 15px;
    }
    .para { font-size: 14px; line-height: 1.5; margin-bottom: 8px; white-space: pre-wrap; }

    /* signature area */
    .signature-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 26px;
      margin-top: 28px;
    }
    .signature-box { text-align: left; font-size: 14px; }
    .signature-line {
      display: block;
      border-bottom: 1px solid #000;
      min-height: 70px;
      margin-top: 8px;
      padding-top: 6px;
    }
    .sig-caption { text-align: center; margin-top: 8px; font-size: 13px; }

    .witness-block {
      margin-top: 26px;
      border: 1.2px solid var(--border-color);
      padding: 12px;
      border-radius: 6px;
    }
    .witness-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px;
    }
    .witness-box {
      border-bottom: 1px solid #000;
      min-height: 40px;
      padding-top: 6px;
      font-size: 14px;
    }

    @media (max-width: 768px) {
      .signature-grid { grid-template-columns: 1fr; }
      .witness-grid { grid-template-columns: 1fr; }
    }

    @media print {
      #printButton { display: none; }
      body { padding: 0; background: none; }
      .form-wrapper { border: none; box-shadow: none; page-break-inside: avoid; }
      .section, .signature-grid, .witness-block { page-break-inside: avoid; }
      .watermark { opacity: 0.05 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>

  <button id="printButton" onclick="window.print()">🖨️ Print Exit Policy</button>

  <div class="form-wrapper">
    <div class="watermark"></div>
    <div class="top-badge">EXIT POLICY & RELEASE</div>

    <div class="form-header">
      <img src="logo.png" alt="Riyawa Logo" />
      <div class="form-title">RIYAWA CONTRACTORS NIGERIA LIMITED</div>
      <div class="sub-title">Employee / Contractor Exit Policy & Clearance Form</div>

      <div class="passport-box">
        EMPLOYEE /<br/>CONTRACTOR<br/>PASSPORT
      </div>
    </div>

    <div class="contract-info">
      <strong>Reference No:</strong> _____________________
      <br><strong>Date of Exit:</strong> ______/ _______ /____________
    </div>

    <div class="section">
      <div class="section-title">PARTIES</div>
      <p class="para">This Exit Policy & Release ("Form") documents the exit arrangements between Riyawa Contractors Nigeria Limited (the "Company") and the exiting employee/contractor.</p>

      <p class="para">Exiting Individual:
      _______________________________________________</p>

      <p class="para">Position / Role: __________________________    Department: __________________________</p>
    </div>

    <div class="section">
      <div class="section-title">1. SCOPE</div>
      <p class="para">This Form covers notice, handover, clearance, final pay, return of company property, confidentiality obligations and any post-employment obligations.</p>
    </div>

    <div class="section">
      <div class="section-title">2. NOTICE</div>
      <p class="para">Notice given by the exiting party: ________ days.  Last working day: ________/__________/__________.  Where payment in lieu applies, amount agreed: ₦_________________________.</p>
    </div>

    <div class="section">
      <div class="section-title">3. HANDOVER & KNOWLEDGE TRANSFER</div>
      <p class="para">The exiting party shall complete a handover of duties and documentation to the successor or supervisor. Handover tasks completed:
      ___________________________________________________________</p>

      <p class="para">Handover accepted by: __________________________ (Name & Signature)</p>
    </div>

    <div class="section">
      <div class="section-title">4. COMPANY PROPERTY & ACCESS</div>
      <p class="para">The exiting party must return all Company property before departure, including but not limited to: keys, ID card, laptops, phones, vehicles, documents, access tokens and uniforms. Returned items (list):
      ___________________________________________________________</p>

      <p class="para">Access (systems/accounts) revoked on: ____/____/________</p>
    </div>

    <div class="section">
      <div class="section-title">5. FINAL PAYMENTS & BENEFITS</div>
      <p class="para">Final pay calculation (basic, accrued leave, deductions, severance if any):
      ___________________________________________________________</p>

      <p class="para">Final payment to be made by: ____/____/________.  Bank / Payment details on file:
      ___________________________________________________________</p>
    </div>

    <div class="section">
      <div class="section-title">6. CONFIDENTIALITY & RETURN OF INFORMATION</div>
      <p class="para">The exiting party reaffirms obligations to maintain confidentiality of Company information and trade secrets. Any confidential materials in possession must be returned or securely destroyed. Confirmed: Yes / No</p>
    </div>

    <div class="section">
      <div class="section-title">7. NON-SOLICITATION & NON-COMPETE (IF APPLICABLE)</div>
      <p class="para">Where applicable, the exiting party acknowledges any post-termination restrictions agreed in prior contracts, including non-solicitation of staff/customers for ______ months and any non-compete radius of ______ km.</p>
    </div>

    <div class="section">
      <div class="section-title">8. EXIT INTERVIEW & REFERENCE</div>
      <p class="para">Exit interview conducted by: __________________________.  Date: ____/____/________</p>
      <p class="para">Reference to be provided: Yes / No.  Reference contact details:
      ________________________________________________</p>
    </div>

    <div class="section">
      <div class="section-title">9. RELEASE & SETTLEMENT</div>
      <p class="para">Upon completion of handover and return of property, and after final payment, the exiting party releases the Company from any further obligations arising from the employment/contract up to the exit date, except liabilities that cannot by law be waived.</p>

      <p class="para">If any special settlement terms apply, list here:
      ___________________________________________________________</p>
    </div>

    <div class="section">
      <div class="section-title">10. DISPUTE RESOLUTION</div>
      <p class="para">Any dispute arising under this Exit Policy or settlement shall be resolved first by discussion and mediation; failing which disputes shall be referred to arbitration in Nigeria before litigation.</p>
    </div>

    <div class="section">
      <div class="section-title">11. GOVERNING LAW</div>
      <p class="para">This Form and any settlement shall be governed by the Laws of the Federal Republic of Nigeria.</p>
    </div>

    <div class="section">
      <p class="para" style="text-align:center; font-size:12px;">
        This Exit Policy Form may be executed in counterparts and/or electronically; each executed copy is an original.
      </p>
    </div>

    <!-- signatures -->
    <div class="signature-grid">
      <div class="signature-box">
        <div class="signature-line"></div>
        <div class="sig-caption">Exiting Party — Name & Signature</div>
        <div style="text-align:center; margin-top:6px;">Date: ______/______/________</div>
      </div>

      <div class="signature-box">
        <div class="signature-line"></div>
        <div class="sig-caption">Authorized Company Representative — Name & Signature</div>
        <div style="text-align:center; margin-top:6px;">Date: ______/______/________</div>
      </div>
    </div>

    <!-- witness -->
    <div class="witness-block">
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
          Witness Signature & Date: ______/______/________
        </div>
      </div>
    </div>

  </div>

</body>
</html>
