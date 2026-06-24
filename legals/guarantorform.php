<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>AgriTrust Farmers' Program - Guarantor’s Form</title>
  <style>
    :root {
      --primary-color: #0db95d;
      --dark-color: #003144;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

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

    #printButton:hover {
      background-color: #0aa34c;
    }

    .form-wrapper {
      max-width: 900px;
      margin: auto;
      background: #fff;
      border: 2px solid var(--dark-color);
      padding: 30px;
      position: relative;
      box-shadow: 0 0 10px rgba(0,0,0,0.05);
    }

    .watermark {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-image: url('best-logo.png'); /* <-- Replace logo if needed */
      background-repeat: repeat;
      background-position: center;
      background-size: 200px;
      opacity: 0.05;
      z-index: 0;
      pointer-events: none;
    }

    .form-wrapper > *:not(.watermark) {
      position: relative;
      z-index: 1;
    }

    .form-header {
      text-align: center;
      position: relative;
      margin-bottom: 20px;
    }
@media print {
  #printButton {
    display: none;
  }

  body {
    padding: 0;
    background: none;
  }

  .form-wrapper {
    border: none;
    box-shadow: none;
    page-break-inside: avoid;
  }

  .section {
    page-break-inside: avoid;
  }

  .passport-box,
  .guarantor-photo {
    position: static !important;
    float: right;
    margin: 10px 0 10px 20px;
    page-break-inside: avoid;
  }

  .watermark {
    opacity: 0.05 !important;
    background-image: url('best-logo.png') !important;
    background-repeat: repeat !important;
    background-position: center !important;
    background-size: 200px !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}

    .form-header img {
      width: 70px;
      margin-bottom: 10px;
    }

    .form-title {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 10px;
      color: var(--dark-color);
    }

    .top-badge {
      position: absolute;
      top: -15px;
      left: -15px;
      background: var(--dark-color);
      color: #fff;
      padding: 8px 14px;
      font-size: 13px;
      font-weight: bold;
      border-radius: 4px 0 10px 0;
    }

    .passport-box {
      position: absolute;
      top: 0;
      right: 0;
      width: 150px;
      height: 170px;
      border: 2px solid #000;
      text-align: center;
      font-size: 13px;
      line-height: 1.4;
      background: #fafafa;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 10px;
    }

    .note {
      margin-top: 100px;
      font-size: 14px;
      line-height: 1.6;
    }

    .note strong {
      font-weight: bold;
    }

    .section {
      margin-top: 30px;
      padding: 20px;
      border: 1.5px solid #ccc;
      border-radius: 8px;
      position: relative;
      background: #fcfcfc;
    }

    .section-title {
      font-weight: bold;
      margin-bottom: 10px;
      font-size: 16px;
      color: var(--primary-color);
    }

    .guarantor-photo {
      position: absolute;
      top: 20px;
      right: 20px;
      width: 120px;
      height: 140px;
      border: 2px solid #000;
      background: #fefefe;
      text-align: center;
      font-size: 12px;
      line-height: 1.4;
      padding: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

   .form-group {
  margin-bottom: 10px;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.form-group input[type="text"] {
  flex: 1;
  min-width: 120px;
  border: none;
  border-bottom: 1px solid #000;
  padding: 4px 6px;
  font-size: 14px;
  background: transparent;
}
.form-group {
  display: flex;
  align-items: center;
  gap: 6px;
}


    .form-footer {
      display: flex;
      justify-content: space-between;
      margin-top: 40px;
    }

    .form-footer .line {
      width: 48%;
      border-top: 1px solid #000;
      padding-top: 5px;
      text-align: center;
      font-size: 14px;
    }

    @media (max-width: 768px) {
      .passport-box,
      .guarantor-photo {
        position: static;
        margin: 20px auto;
      }

      .form-footer {
        flex-direction: column;
        gap: 20px;
      }

      .form-footer .line {
        width: 100%;
      }

      .note {
        margin-top: 20px;
      }
    }

    @media print {
      #printButton {
        display: none;
      }

      body {
        padding: 0;
        background: none;
      }

      .form-wrapper {
        border: none;
        box-shadow: none;
        page-break-inside: avoid;
      }

      .watermark {
        opacity: 0.05 !important;
      }
    }
  </style>
</head>
<body>

  <button id="printButton" onclick="window.print()">🖨️ Print Form</button>

  <div class="form-wrapper">
    <div class="watermark"></div>
    <div class="top-badge">GUARANTOR’S FORM</div>

    <div class="form-header">
      <img src="logo.png" alt="Nigeria Coat of Arms" />
      <div class="form-title">AGRITRUST FARMERS' PROGRAM</div>
    </div>

    <div class="passport-box">
      APPLICANT<br/>PASSPORT<br/>PHOTOGRAPH
    </div>

    <div class="note">
      <strong>IMPORTANT:</strong> This empowerment program requires that a candidate seeking support must provide two (2) passport photographs and acceptable persons as Guarantors. If you are willing to stand as a guarantor, kindly complete this form in your own handwriting.<br><br>

      Please note that it is very risky to stand as a guarantor for persons you do not know well.<br><br>

      <strong>Acceptable Guarantors include:</strong> Traditional Rulers, Magistrates, Local Government Chairmen, Heads of Agricultural Institutions, Senior Civil Servants (Grade Level 12 and above), Bank Officials, or Community Leaders of repute.
    </div>

    <div class="section">
  <div class="section-title">GUARANTOR A (Please attach photocopy of Official ID Card)</div>
  <div class="guarantor-photo">GUARANTOR'S<br/>PASSPORT<br/>PHOTOGRAPH</div>

  <div class="form-group">I <input type="text" /></div>
  <div class="form-group">Home Address <input type="text" /></div>
  <div class="form-group">Office Address <input type="text" /></div>
  <div class="form-group">of <input type="text" /> and <input type="text" /></div>
  <div class="form-group">stand as a Guarantor To (Name of Applicant) <input type="text" /></div>
  <div class="form-group">Who is applying for AgriTrust Farmers' Program.</div>
  <div class="form-group">My Telephone Number is <input type="text" /></div>

  <p>
    I irrevocably and unconditionally guarantee to indemnify the AgriTrust Program from any loss, fraud, or default arising from the actions or inactions of the applicant before, during or after the disbursement or support.
  </p>
  <p>
    I also agree to present the applicant whenever required for verification or accountability purposes. This guarantee shall be governed by the laws of the Federal Republic of Nigeria.
  </p>
  <div class="form-group">Name: <input type="text" /> Signature/Date: <input type="text" /></div>
</div>

<div class="section">
  <div class="section-title">GUARANTOR B (Please attach photocopy of Official ID Card)</div>
  <div class="guarantor-photo">GUARANTOR'S<br/>PASSPORT<br/>PHOTOGRAPH</div>

  <div class="form-group">I <input type="text" /></div>
  <div class="form-group">Home Address <input type="text" /></div>
  <div class="form-group">Office Address <input type="text" /></div>
  <div class="form-group">of <input type="text" /> and <input type="text" /></div>
  <div class="form-group">stand as a Guarantor To (Name of Applicant) <input type="text" /></div>
  <div class="form-group">Who is applying for  AgriTrust Farmers' Program.</div>
  <div class="form-group">My Telephone Number is <input type="text" /></div>

  <p>
    I irrevocably and unconditionally guarantee to indemnify the AgriTrust Program from any loss, fraud, or default arising from the actions or inactions of the applicant before, during or after the disbursement or support.
  </p>
  <p>
    I also agree to present the applicant whenever required for verification or accountability purposes. This guarantee shall be governed by the laws of the Federal Republic of Nigeria.
  </p>
  <div class="form-group">Name: <input type="text" /> Signature/Date: <input type="text" /></div>
</div>
</div>

</body>
</html>
