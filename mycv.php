<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Abdurrashid Sani | Full Stack Software Engineer</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"/>

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: #f1f5f9;
      color: #0f172a;
    }
    .cv-wrapper {
      max-width: 1100px;
      margin: 30px auto;
      background: #ffffff;
      border-radius: 18px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.08);
      overflow: hidden;
    }
    .left-panel {
      background: #0f172a;
      color: #e5e7eb;
      padding: 30px;
    }
    .profile-img {
      width: 140px;
      height: 140px;
      border-radius: 50%;
      border: 4px solid #22c55e;
      object-fit: cover;
      margin-bottom: 15px;
    }
    .name {
      font-size: 26px;
      font-weight: 800;
      color: #ffffff;
    }
    .role {
      color: #22c55e;
      font-weight: 600;
      margin-bottom: 20px;
    }
    .section-title {
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 1px;
      margin-top: 30px;
      margin-bottom: 12px;
      color: #22c55e;
      text-transform: uppercase;
    }
    .skill {
      background: #1e293b;
      border-radius: 20px;
      padding: 6px 12px;
      font-size: 13px;
      margin: 4px;
      display: inline-block;
    }
    .right-panel {
      padding: 40px;
    }
    .content-title {
      font-size: 18px;
      font-weight: 800;
      margin-bottom: 12px;
      border-left: 4px solid #22c55e;
      padding-left: 10px;
    }
    .job-title {
      font-weight: 700;
      font-size: 16px;
    }
    .company {
      font-weight: 600;
      color: #22c55e;
    }
    .date {
      font-size: 13px;
      color: #64748b;
    }
    ul li {
      font-size: 14px;
      margin-bottom: 6px;
      color: #334155;
    }
  </style>
</head>

<body>

<div class="cv-wrapper">
  <div class="row g-0">

    <!-- LEFT PANEL -->
    <div class="col-md-4 left-panel text-center">
      <img src="images/LS.jpeg" class="profile-img" alt="Profile">
      <div class="name">Abdurrashid Sani</div>
      <div class="role">Full Stack Software Engineer</div>

      <div class="section-title">Contact</div>
      <p>📍 Kaduna, Nigeria</p>
      <p>📞 +234 806 867 3647</p>
      <p>✉️ abdurrashidsani20@gmail.com</p>
      <p>🌐 www.wisequotientsoft.com</p>
      <p>💻 github.com/abdurrashidsani</p>

      <div class="section-title">Tech Stack</div>
      <div class="text-start">
        <span class="skill">React.js</span>
        <span class="skill">React Native</span>
        <span class="skill">Angular</span>
        <span class="skill">Node.js</span>
        <span class="skill">Express.js</span>
        <span class="skill">PHP (Laravel)</span>
        <span class="skill">MySQL</span>
        <span class="skill">PostgreSQL</span>
        <span class="skill">REST APIs</span>
        <span class="skill">JWT Authentication</span>
        <span class="skill">AWS (EC2, S3)</span>
        <span class="skill">Docker</span>
        <span class="skill">CI/CD</span>
        <span class="skill">Git & GitHub</span>
      </div>

      <div class="section-title">Soft Skills</div>
      <p>Problem Solving</p>
      <p>Team Leadership</p>
      <p>Communication</p>
      <p>Agile Collaboration</p>

      <div class="section-title">Languages</div>
      <p>English — Advanced</p>
      <p>Arabic — Intermediate</p>
    </div>

    <!-- RIGHT PANEL -->
    <div class="col-md-8 right-panel">

      <div class="content-title">Professional Summary</div>
      <p>
        Results-driven Full Stack Software Engineer with 3+ years of experience building
        scalable web and mobile applications using React, React Native, Node.js, and PHP.
        Strong expertise in RESTful API development, mobile-first UI/UX,
        database optimization, and cloud deployment.
        Passionate about secure, high-performance systems and production-ready solutions.
      </p>

      <div class="content-title mt-4">Professional Experience</div>

      <div class="mb-4">
        <div class="job-title">Full Stack Engineer / CEO</div>
        <div class="company">Wise Quotient Soft — Nigeria</div>
        <div class="date">2022 – Present</div>
        <ul>
          <li>Developed and maintained 10+ web and mobile applications using React, React Native, Node.js, and PHP.</li>
          <li>Built cross-platform mobile apps (Android & iOS) with React Native and REST APIs.</li>
          <li>Designed secure backend APIs with JWT-based authentication and role management.</li>
          <li>Optimized SQL queries and API performance, reducing response times by 40%.</li>
          <li>Deployed and monitored applications on AWS with Docker and CI/CD pipelines.</li>
        </ul>
      </div>

      <div class="content-title mt-4">Key Projects</div>

      <div class="mb-3">
        <strong>Staff Fund Request & Approval System</strong>
        <ul>
          <li>Tech: React Native, PHP, MySQL, REST API</li>
          <li>Built a full mobile-first approval workflow system.</li>
          <li>Reduced manual processing time by 60%.</li>
        </ul>
      </div>

      <div class="mb-3">
        <strong>Voice-Based Exam System for the Blind</strong>
        <ul>
          <li>Tech: Python, Flask, VOSK, Text-to-Speech</li>
          <li>Enabled accessible computer-based exams using speech recognition.</li>
        </ul>
      </div>

      <div class="mb-3">
        <strong>Secure Face & Gesture Authentication System</strong>
        <ul>
          <li>Tech: Flask, InsightFace, MediaPipe</li>
          <li>Implemented multi-factor biometric authentication.</li>
        </ul>
      </div>

      <div class="content-title mt-4">Education</div>
      <div class="job-title">B.Sc. Computer Science</div>
      <div class="company">Kaduna State University</div>
      <div class="date">2019 – 2024</div>

      <div class="content-title mt-4">Certifications</div>
      <ul>
        <li>AWS Cloud Practitioner (Recommended)</li>
        <li>Google Cybersecurity Fundamentals</li>
        <li>Agile Scrum Fundamentals</li>
      </ul>

      <div class="content-title mt-4">Availability</div>
      <p>✔ Open to Remote, Hybrid & On-Site Roles</p>
      <p>✔ Available for International Opportunities</p>

    </div>
  </div>
</div>

</body>
</html>
