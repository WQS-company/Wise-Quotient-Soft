<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Programming Globe - Wise Quotient Soft</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600&display=swap" rel="stylesheet">
  <style>
    body {
      background: #0f172a;
      font-family: 'Orbitron', sans-serif;
      color: white;
    }
    .globe {
      width: 300px;
      height: 300px;
      border-radius: 50%;
      background: radial-gradient(circle at center, #1e3a8a, #0f172a);
      box-shadow: 0 0 25px #3b82f6;
      animation: rotate 20s linear infinite;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }
    .globe::before {
      content: '';
      width: 100%;
      height: 100%;
      border-radius: 50%;
      border: 1px dashed #38bdf8;
      position: absolute;
      top: 0;
      left: 0;
      animation: spin 10s linear infinite;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    @keyframes rotate {
      0% { transform: rotateY(0deg); }
      100% { transform: rotateY(360deg); }
    }
  </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center px-4 py-10 space-y-12">

  <!-- Header -->
  <div class="text-center">
    <h1 class="text-4xl md:text-5xl font-bold text-blue-400">Wise Quotient Soft</h1>
    <p class="text-sm text-gray-300 mt-2">Global Tech Leader in AI & Software Innovation</p>
  </div>

  <!-- Programming Language Globe -->
  <div class="flex flex-col md:flex-row items-center gap-12">
    <div class="globe text-center text-lg font-semibold tracking-wide text-blue-200">
      <div>
        <div>Python</div>
        <div>JavaScript</div>
        <div>C++</div>
        <div>Go</div>
        <div>Rust</div>
        <div>Java</div>
        <div>PHP</div>
        <div>TypeScript</div>
      </div>
    </div>

    <!-- AI Prompt Display Panel -->
    <div class="bg-gray-900 p-6 rounded-2xl shadow-xl max-w-md w-full">
      <h2 class="text-2xl font-bold mb-4 text-white">AI Prompt Area</h2>
      <p class="text-sm text-blue-200 mb-2">“Generate a GIF of a rotating programming globe with languages labeled across hemispheres and an AI assistant in the corner.”</p>
      <div class="mt-4">
        <button class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-white font-medium">Run Prompt</button>
        <p class="text-xs text-gray-400 mt-2">Try this in <a href="https://app.runwayml.com/" class="underline text-sky-400" target="_blank">Runway Gen-3</a></p>
      </div>
    </div>
  </div>

  <!-- Cards Section -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-6xl mt-8">
    <div class="bg-gray-800 p-6 rounded-xl hover:scale-105 transform transition">
      <h3 class="text-xl text-blue-400 font-bold mb-2">Python</h3>
      <p class="text-gray-300 text-sm">Powerful for AI, data science & automation.</p>
    </div>
    <div class="bg-gray-800 p-6 rounded-xl hover:scale-105 transform transition">
      <h3 class="text-xl text-yellow-400 font-bold mb-2">JavaScript</h3>
      <p class="text-gray-300 text-sm">Language of the web, dynamic and versatile.</p>
    </div>
    <div class="bg-gray-800 p-6 rounded-xl hover:scale-105 transform transition">
      <h3 class="text-xl text-purple-400 font-bold mb-2">TypeScript</h3>
      <p class="text-gray-300 text-sm">JS with types — safer and scalable.</p>
    </div>
    <div class="bg-gray-800 p-6 rounded-xl hover:scale-105 transform transition">
      <h3 class="text-xl text-green-400 font-bold mb-2">Go</h3>
      <p class="text-gray-300 text-sm">Modern, simple, fast — great for backend.</p>
    </div>
    <div class="bg-gray-800 p-6 rounded-xl hover:scale-105 transform transition">
      <h3 class="text-xl text-pink-400 font-bold mb-2">Rust</h3>
      <p class="text-gray-300 text-sm">Safe and performant for system-level dev.</p>
    </div>
    <div class="bg-gray-800 p-6 rounded-xl hover:scale-105 transform transition">
      <h3 class="text-xl text-orange-400 font-bold mb-2">PHP</h3>
      <p class="text-gray-300 text-sm">Backend powerhouse behind many CMS.</p>
    </div>
  </div>

  <!-- Footer -->
  <footer class="mt-12 text-gray-500 text-sm">
    &copy; 2025 Wise Quotient Soft. All rights reserved.
  </footer>
</body>
</html>
