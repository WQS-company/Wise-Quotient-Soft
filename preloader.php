<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MIA Loader</title>

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
  background: #f6f7fb;
  display:flex;
  justify-content:center;
  align-items:center;
  height:100vh;
  margin:0;
}

/* slight ambient vignette */
body:before{
  content:"";
  position:absolute;
  width:100%;
  height:100%;
  top:0;
  left:0;
  pointer-events:none;
  background:radial-gradient(circle at center,#ffffff 0%,#eef0ff 55%,#e5e7ff 100%);
  opacity:0.95;
}

.loader-box{
  width:160px;
  height:160px;
  position:relative;
}

/* faint grey circle */
.loader-base{
  width:160px;
  height:160px;
  border-radius:50%;
  border:3px solid rgba(120,110,255,0.16);
  position:absolute;
  top:0;
  left:0;
  filter:blur(0.4px);
}

/* rotating purple arc – 75% coverage */
.loader-ring{
  width:160px;
  height:160px;
  border-radius:50%;
  border:3px solid #6a5cff;
  border-bottom-color:transparent; /* only one side transparent -> ~75% arc visible */
  animation:spin 2.8s linear infinite;
  position:absolute;
  top:0;
  left:0;
  filter:drop-shadow(0 0 10px rgba(106,92,255,0.35));
  opacity:0.95;
}

/* center logo */
.loader-logo{
  width:160px;
  height:160px;
  position:absolute;
  top:0;
  left:0;
  display:flex;
  justify-content:center;
  align-items:center;
  animation:pulse 2.4s ease-in-out infinite;
}

.loader-logo h1{
  margin:0;
  font-size:48px;
  font-weight:700;
  color:#6a5cff;
  text-shadow:0 3px 14px rgba(106,92,255,0.42);
}

@keyframes spin{
  0%{ transform:rotate(0deg); }
  100%{ transform:rotate(360deg); }
}

@keyframes pulse{
  0%{ transform:scale(1); opacity:1; }
  50%{ transform:scale(1.07); opacity:0.88; }
  100%{ transform:scale(1); opacity:1; }
}
</style>

</head>
<body>

<div class="loader-box">
  <div class="loader-base"></div>
  <div class="loader-ring"></div>

  <div class="loader-logo">
    <h1>MIA</h1>
  </div>
</div>

</body>
</html>
