<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>African Classes Enrollment</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body{
  background:#f1f3f4;
  font-family:Inter,system-ui,sans-serif;
}
.enroll-card{
  max-width:720px;
  margin:60px auto;
  background:#fff;
  border-radius:14px;
  box-shadow:0 1px 6px rgba(0,0,0,.12);
  overflow:hidden;
}
.form-header{
  background:linear-gradient(135deg,#1f7ae0,#f6b500);
  color:#fff;
  padding:28px;
}
.form-body{padding:32px;}
.q-block{border-bottom:1px solid #e0e0e0;padding:22px 0;}
.q-title{font-weight:600;margin-bottom:14px;}
.btn-main{
  background:#1f7ae0;
  color:#fff;
  border:none;
  padding:10px 28px;
  border-radius:6px;
  font-weight:600;
}
#loadingOverlay{
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.6);
  display:none;
  align-items:center;
  justify-content:center;
  z-index:9999;
}
.loading-box{
  background:#fff;
  padding:30px;
  border-radius:12px;
  text-align:center;
}

/* SUCCESS CARD */
#successCard{
  display:none;
  margin-top:25px;
  background:#e9f7ef;
  border:1px solid #28a745;
  border-radius:8px;
  padding:22px;
  text-align:center;
}
#successCard i{
  font-size:36px;
  color:#28a745;
}

/* ERROR MESSAGE */
#errorBox{
  display:none;
  margin-top:20px;
  background:#fff3cd;
  border:1px solid #ffc107;
  border-radius:8px;
  padding:12px;
  font-weight:600;
  color:#856404;
}
</style>
</head>
<body>

<div class="enroll-card">

<div class="form-header">
  <h4>African Classes – Student Enrollment</h4>
  <small>Please fill all required fields carefully</small>
</div>

<div class="form-body">
<form id="enrollForm">

  <div class="q-block">
    <div class="q-title">Full Name</div>
    <div class="row">
      <div class="col-md-6 mb-2">
        <input
          type="text"
          name="first_name"
          class="form-control"
          placeholder="e.g. Abdurrashid"
          required
        >
      </div>
      <div class="col-md-6">
        <input
          type="text"
          name="last_name"
          class="form-control"
          placeholder="e.g. Musa"
          required
        >
      </div>
    </div>
  </div>

  <div class="q-block">
    <div class="q-title">Phone Number</div>
    <input
      type="text"
      name="phone"
      class="form-control"
      placeholder="e.g. 08012345678"
      required
    >
  </div>

  <div class="q-block">
    <div class="q-title">Email</div>
    <input
      type="email"
      name="email"
      class="form-control"
      placeholder="e.g. abdurrashid@email.com"
    >
  </div>

  <div class="q-block">
    <div class="q-title">Gender</div>
    <select name="gender" class="form-select">
      <option value="">Select gender</option>
      <option>Male</option>
      <option>Female</option>
    </select>
  </div>

  <div class="q-block">
    <div class="q-title">Home Address</div>
    <textarea
      name="address"
      class="form-control"
      placeholder="e.g. No. 12 Kaduna Road, Zaria"
      required
    ></textarea>
  </div>

  <div class="q-block">
    <div class="q-title">Class of Interest</div>
    <select
      name="interest"
      id="interestSelect"
      class="form-select"
      onchange="updateQuestion()"
      required
    >
      <option value="">Select a class</option>
      <option value="Computer">Computer Class</option>
      <option value="AI">AI Class</option>
    </select>
  </div>

  <div class="q-block">
    <div class="q-title" id="basicQuestion">
      Please select a class to see the question
    </div>

    <div class="form-check">
      <input
        class="form-check-input"
        type="radio"
        name="basic_answer"
        value="Yes"
        required
      >
      Yes
    </div>

    <div class="form-check">
      <input
        class="form-check-input"
        type="radio"
        name="basic_answer"
        value="No"
      >
      No
    </div>
  </div>

  <div class="q-block">
    <div class="q-title">Days Available</div>

    <div class="form-check"><input type="checkbox" name="days[]" value="Monday"> Monday</div>
    <div class="form-check"><input type="checkbox" name="days[]" value="Tuesday"> Tuesday</div>
    <div class="form-check"><input type="checkbox" name="days[]" value="Wednesday"> Wednesday</div>
    <div class="form-check"><input type="checkbox" name="days[]" value="Thursday"> Thursday</div>
    <div class="form-check"><input type="checkbox" name="days[]" value="Friday"> Friday</div>
    <div class="form-check"><input type="checkbox" name="days[]" value="Saturday"> Saturday</div>
    <div class="form-check"><input type="checkbox" name="days[]" value="Sunday"> Sunday</div>
  </div>

<div class="q-block">
  <div class="q-title">Preferred Time</div>

  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label fw-semibold">From</label>
      <input
        type="time"
        name="time_from"
        class="form-control"
        title="From (e.g. 08:00 AM)"
        required
      >
    </div>

    <div class="col-md-6">
      <label class="form-label fw-semibold">To</label>
      <input
        type="time"
        name="time_to"
        class="form-control"
        title="To (e.g. 10:00 AM)"
        required
      >
    </div>
  </div>
</div>


  <div class="q-block">
    <div class="q-title">Computer Knowledge</div>

    <div class="form-check">
      <input
        type="radio"
        name="computer_knowledge"
        value="Yes"
        onclick="showLevel(true)"
        required
      >
      Yes
    </div>

    <div class="form-check">
      <input
        type="radio"
        name="computer_knowledge"
        value="No"
        onclick="showLevel(false)"
      >
      No
    </div>

    <div id="levelBox" style="display:none;margin-top:10px;">
      <select name="knowledge_level" class="form-select">
        <option value="">Select your level</option>
        <option>Beginner</option>
        <option>Intermediate</option>
        <option>Advanced</option>
      </select>
    </div>
  </div>

  <div class="q-block">
    <div class="q-title">Learning Channel</div>

    <div class="form-check"><input type="checkbox" name="channel[]" value="WhatsApp"> WhatsApp</div>
    <div class="form-check"><input type="checkbox" name="channel[]" value="Facebook"> Facebook</div>
    <div class="form-check"><input type="checkbox" name="channel[]" value="YouTube"> YouTube</div>
    <div class="form-check"><input type="checkbox" name="channel[]" value="Physical"> Physical</div>
  </div>

  <div class="q-block">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" id="agreeRules" required>
      <label>
        I agree to the
        <a href="rules.html" target="_blank" style="color:#1f7ae0;font-weight:600;">
          African Classes rules and regulations
        </a>
      </label>
    </div>
  </div>

  <div class="text-end mt-4">
    <button type="submit" class="btn-main">Submit Enrollment</button>
  </div>

</form>


<div id="errorBox"></div>

<!-- SUCCESS DISPLAY -->
<div id="successCard">
<i class="bi bi-check-circle-fill"></i>
<h5 class="mt-2">Congratulations 🎉</h5>
<p id="successText"></p>
</div>

</div>
</div>

<div id="loadingOverlay">
<div class="loading-box">
<div class="spinner-border text-primary mb-3"></div>
<div>Submitting...</div>
</div>
</div>

<script>
function updateQuestion(){
let q=document.getElementById("basicQuestion");
let v=document.getElementById("interestSelect").value;

if(v==="Computer"){
q.innerText="Do you have basic computer operation knowledge?";
}else if(v==="AI"){
q.innerText="Do you have any programming or AI background?";
}else{
q.innerText="Please select a class to see the question";
}
}

function showLevel(show){
document.getElementById("levelBox").style.display=show?"block":"none";
}

document.getElementById("enrollForm").addEventListener("submit", function(e){
e.preventDefault();
document.getElementById("loadingOverlay").style.display="flex";

fetch("ac-server.php",{method:"POST",body:new FormData(this)})
.then(r=>r.json())
.then(res=>{
document.getElementById("loadingOverlay").style.display="none";

if(res.status==="success"){
document.getElementById("successCard").style.display="block";
document.getElementById("successText").innerHTML=
"Your Registration Number: <strong>"+res.reg_number+"</strong><br>"+res.message;
this.reset();
}

if(res.status==="duplicate"){
document.getElementById("errorBox").style.display="block";
document.getElementById("errorBox").innerText = res.message;
let input=document.querySelector(`[name="${res.field}"]`);
if(input){
input.classList.add("is-invalid");
input.focus();
}
}

});
});
</script>

</body>
</html>
