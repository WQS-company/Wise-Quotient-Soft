<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Riyawa Contractors Nigeria Ltd - Non-Disclosure Agreement (NDA)</title>
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

  <button id="printButton" onclick="window.print()">🖨️ Print NDA</button>

  <div class="form-wrapper">
    <div class="watermark"></div>
    <div class="top-badge">NON-DISCLOSURE AGREEMENT</div>

    <div class="form-header">
      <img src="logo.png" alt="Company Logo" />
      <div class="form-title">RIYAWA CONTRACTORS NIGERIA LIMITED</div>
      <div class="sub-title">Mutual / One-Way Non-Disclosure Agreement</div>

      <div class="passport-box">
        PARTY<br/>PASSPORT<br/>PHOTOGRAPH
      </div>
    </div>

    <div class="contract-info">
      <strong>Agreement Ref:</strong> _____________________
      <br><strong>Date:</strong> ______/ _______ /____________
    </div>

    <div class="section">
      <div class="section-title">PARTIES</div>
      <p class="para">This Non-Disclosure Agreement ("Agreement") is entered into between:</p>
      <p class="para"><strong>Disclosing Party:</strong> Riyawa Contractors Nigeria Limited, Address:______________________________________________________________</p>
      <p class="para"><strong>Receiving Party:_______________________________________________________________________</strong> Address:___________________________________________________________________</p>
    </div>

    <div class="section">
      <div class="section-title">1. DEFINITIONS</div>
      <p class="para"><strong>"Confidential Information"</strong> means all non-public, proprietary or confidential information disclosed by either party (in writing, orally or by inspection) including but not limited to: business plans, budgets, forecasts, strategies, technical data, designs, drawings, trade secrets, customer lists, pricing, supplier information, software, and other materials marked or described as confidential.</p>
    </div>

    <div class="section">
      <div class="section-title">2. EXCLUSIONS</div>
      <p class="para">Confidential Information does not include information that: (a) is or becomes publicly available other than by breach of this Agreement; (b) was already known to the Receiving Party without restriction prior to disclosure; (c) is lawfully obtained from a third party without confidentiality obligations; or (d) is independently developed by the Receiving Party.</p>
    </div>

    <div class="section">
      <div class="section-title">3. OBLIGATIONS OF RECEIVING PARTY</div>
      <p class="para">The Receiving Party will: (a) keep Confidential Information strictly confidential; (b) not disclose it to any third party except as permitted; (c) use the Confidential Information solely for the Purpose: __________________________; and (d) take at least the same degree of care to protect the Confidential Information as it uses to protect its own confidential materials, but in no event less than reasonable care.</p>
    </div>

    <div class="section">
      <div class="section-title">4. PERMITTED DISCLOSURES</div>
      <p class="para">The Receiving Party may disclose Confidential Information to its employees, officers, professional advisors or contractors who have a need to know for the Purpose, provided such persons are bound by confidentiality obligations at least as restrictive as this Agreement.</p>
    </div>

    <div class="section">
      <div class="section-title">5. TERM</div>
      <p class="para">This Agreement is effective as of the date above and the obligations with respect to Confidential Information shall continue for a period of _____________ years from the date of disclosure, except with respect to trade secrets which shall remain protected to the extent permitted by law.</p>
    </div>

    <div class="section">
      <div class="section-title">6. RETURN OR DESTRUCTION</div>
      <p class="para">Upon request of the Disclosing Party or upon termination of discussions, the Receiving Party shall promptly return or destroy all documents and materials containing Confidential Information and certify in writing that it has done so, except for one archival copy retained solely for record-keeping or legal compliance.</p>
    </div>

    <div class="section">
      <div class="section-title">7. NO LICENSE</div>
      <p class="para">Nothing in this Agreement grants the Receiving Party any rights, by license or otherwise, to any Confidential Information except as expressly set forth herein.</p>
    </div>

    <div class="section">
      <div class="section-title">8. REMEDIES</div>
      <p class="para">The Receiving Party acknowledges that unauthorized disclosure may cause irreparable harm to the Disclosing Party for which damages would be an inadequate remedy. In addition to any other remedies available, the Disclosing Party shall be entitled to seek injunctive relief and specific performance.</p>
    </div>

    <div class="section">
      <div class="section-title">9. GOVERNING LAW & DISPUTE RESOLUTION</div>
      <p class="para">This Agreement shall be governed by and construed in accordance with the laws of the Federal Republic of Nigeria. Any dispute shall first be referred to negotiation and mediation; failing resolution, disputes shall be referred to arbitration in Nigeria before litigation.</p>
    </div>

    <div class="section">
      <div class="section-title">10. SEVERABILITY</div>
      <p class="para">If any provision of this Agreement is found invalid or unenforceable, the remaining provisions shall continue in full force and effect.</p>
    </div>

    <div class="section">
      <div class="section-title">11. ENTIRE AGREEMENT</div>
      <p class="para">This Agreement constitutes the entire agreement between the parties concerning its subject matter and supersedes all prior agreements and understandings regarding the same.</p>
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
        <div style="text-align:center; font-weight:bold; margin-top:6px;">Receiving Party</div>
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
