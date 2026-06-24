<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Riyawa Contractors Nigeria Ltd - Memorandum of Understanding</title>
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
      text-transform: uppercase;
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
      text-transform: uppercase;
    }
    .para { font-size: 14px; line-height: 1.5; margin-bottom: 8px; text-align: justify; }
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

  <button id="printButton" onclick="window.print()">🖨️ Print MOU</button>

  <div class="form-wrapper">
    <div class="watermark"></div>
    <div class="top-badge">MEMORANDUM OF UNDERSTANDING</div>

    <div class="form-header">
      <img src="logo.png" alt="Company Logo" />
      <div class="form-title">RIYAWA CONTRACTORS NIGERIA LIMITED</div>
      <div class="sub-title">High-Level Corporate MOU – Nigerian Law Compliant</div>

      <div class="passport-box">
        PARTY A / PARTY B<br/>PASSPORT<br/>PHOTOGRAPH
      </div>
    </div>

    <div class="contract-info">
      <strong>MOU Reference:</strong> _____________________
      <br><strong>Date:</strong> ______/ _______ /____________
    </div>

    <div class="section">
      <div class="section-title">RECITALS</div>
      <p class="para">This Memorandum of Understanding (“MOU”) is made between Riyawa Contractors Nigeria Limited (“Party A”), duly incorporated under the laws of the Federal Republic of Nigeria with its registered address at _______________________________________, and __________________________________ (“Party B”) of ___________________________________________. Both parties desire to set forth the terms and conditions governing their collaboration as described herein.</p>
    </div>

    <div class="section">
      <div class="section-title">1. DEFINITIONS</div>
      <p class="para">For purposes of this MOU, the following terms shall have the meanings assigned to them herein unless the context otherwise requires: “Effective Date” means the date first written above; “Confidential Information” means all proprietary, technical, business, or financial information disclosed by one party to the other; and “Purpose” refers to the agreed objectives set out in Clause 2.</p>
    </div>

    <div class="section">
      <div class="section-title">2. PURPOSE</div>
      <p class="para">The purpose of this MOU is to establish a cooperative framework under which both parties will engage in ________________________________________________________________.</p>
    </div>

    <div class="section">
      <div class="section-title">3. OBLIGATIONS OF THE PARTIES</div>
      <p class="para">Each party undertakes to perform its respective roles diligently, provide timely information, and comply with all applicable Nigerian laws and regulations.</p>
    </div>

    <div class="section">
      <div class="section-title">4. TERM</div>
      <p class="para">This MOU shall commence on the Effective Date and remain in force for ________ months/years unless terminated earlier in accordance with Clause 10.</p>
    </div>

    <div class="section">
      <div class="section-title">5. REPRESENTATIONS & WARRANTIES</div>
      <p class="para">Each party represents and warrants that it has full legal authority to enter into this MOU and that execution and performance of this MOU will not violate any applicable laws or contractual obligations.</p>
    </div>

    <div class="section">
      <div class="section-title">6. CONFIDENTIALITY</div>
      <p class="para">Both parties agree to maintain strict confidentiality regarding any Confidential Information exchanged under this MOU and not to disclose such information except as required by law.</p>
    </div>

    <div class="section">
      <div class="section-title">7. NON-BINDING NATURE</div>
      <p class="para">Except for Clauses relating to Confidentiality, Governing Law, and Dispute Resolution, this MOU is intended as a statement of mutual intentions and does not create any legally binding obligations until a definitive agreement is executed.</p>
    </div>

    <div class="section">
      <div class="section-title">8. FORCE MAJEURE</div>
      <p class="para">Neither party shall be liable for any failure to perform due to causes beyond its reasonable control including acts of God, government restrictions, strikes, war, or civil disturbance.</p>
    </div>

    <div class="section">
      <div class="section-title">9. DISPUTE RESOLUTION</div>
      <p class="para">Any dispute arising under this MOU shall first be resolved amicably through negotiation and mediation. Failing such resolution, disputes shall be referred to arbitration in Nigeria in accordance with the Arbitration and Conciliation Act, Cap A18, Laws of the Federation of Nigeria.</p>
    </div>

    <div class="section">
      <div class="section-title">10. TERMINATION</div>
      <p class="para">Either party may terminate this MOU upon ______ days’ written notice to the other party.</p>
    </div>

    <div class="section">
      <div class="section-title">11. GOVERNING LAW</div>
      <p class="para">This MOU shall be governed by and construed in accordance with the laws of the Federal Republic of Nigeria.</p>
    </div>

    <div class="section">
      <p class="para" style="text-align:center; font-size:12px;">
        This MOU may be executed in counterparts and/or electronically; each executed counterpart is an original, and all counterparts together constitute one instrument.
      </p>
    </div>

    <!-- Signatures -->
    <div class="signature-section">
      <div class="signature-box">
        <div class="sig-label">Signed for and on behalf of</div>
        <div style="text-align:center; font-weight:bold; margin-top:6px;">Riyawa Contractors Nigeria Limited</div>
        <div class="signature-line"></div>
        <div style="text-align:center;">Authorized Signatory</div>
        <div style="text-align:center; margin-top:6px;">Date: ______/ _______ / ____________</div>
      </div>

      <div class="signature-box">
        <div class="sig-label">Signed for and on behalf of</div>
        <div style="text-align:center; font-weight:bold; margin-top:6px;">Party B</div>
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
