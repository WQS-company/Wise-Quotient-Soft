<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>WiseQuotient Soft - Portfolio</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
  <style>
    :root {
      --primary-color: #002f6c;
      --accent-color: #ff7b00;
      --accent-color-dark: #e86000;
    }

    body {
      background: linear-gradient(to bottom right, #021a40, #07284f);
      color: #fff;
      font-family: 'Segoe UI', sans-serif;
      min-height: 100vh;
      overflow-x: hidden;
      padding-top: 80px;
    }

    .section-title {
      text-align: center;
      margin-bottom: 40px;
      color: var(--accent-color);
    }

    .project-card {
      background: #fff;
      color: #333;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
      overflow: hidden;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .project-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
    }

    .project-image {
      height: 200px;
      object-fit: cover;
      width: 100%;
    }

    .project-body {
      padding: 20px;
    }

    .project-title {
      font-size: 1.25rem;
      font-weight: bold;
      color: var(--primary-color);
    }

    .project-desc {
      font-size: 0.95rem;
      margin-bottom: 10px;
      min-height: 40px;
    }

    .badge-tech {
      background: var(--accent-color);
      color: #fff;
      margin-right: 5px;
      margin-bottom: 5px;
    }

    .card-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .card-actions button {
      font-size: 0.8rem;
      white-space: nowrap;
    }

    @media (max-width: 576px) {
      .project-body {
        padding: 16px;
      }

      .card-actions button {
        padding: 5px 8px;
        font-size: 0.75rem;
      }
    }

    .filters {
      margin-bottom: 30px;
    }

    .search-wrapper {
      position: relative;
    }

    .search-wrapper input {
      border-radius: 30px;
      padding: 10px 40px 10px 20px;
      border: none;
      width: 100%;
    }

    .search-wrapper .fa-search {
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: #666;
    }

    .filter-group {
      margin-bottom: 15px;
    }

    .filter-label {
      font-weight: bold;
      color: #fff;
      margin-bottom: 5px;
    }

    .filter-buttons .btn {
      margin: 4px 6px 4px 0;
    }

    .filter-buttons .btn.active {
      background-color: var(--accent-color);
      color: #fff;
    }

    .project-status {
      font-size: 0.8rem;
      font-weight: bold;
    }

    .status-live { color: #28a745; }
    .status-progress { color: #ffc107; }
    .status-archived { color: #dc3545; }

    .load-more {
      display: block;
      margin: 40px auto 0;
    }
  </style>
</head>
<body>

<div class="container">
  <h2 class="section-title">Our Portfolio Projects</h2>

  <!-- Search Input -->
  <div class="row mb-4">
    <div class="col-md-8 offset-md-2">
      <div class="search-wrapper">
        <input type="text" class="form-control" placeholder="Search projects...">
        <i class="fas fa-search"></i>
      </div>
    </div>
  </div>

  <!-- Filters Section -->
  <div class="row filters">
    <!-- Categories -->
    <div class="col-md-6 filter-group">
      <div class="filter-label">Categories:</div>
      <div class="filter-buttons">
        <button class="btn btn-outline-light btn-sm active">All</button>
        <button class="btn btn-outline-light btn-sm">Web App</button>
        <button class="btn btn-outline-light btn-sm">Mobile App</button>
        <button class="btn btn-outline-light btn-sm">ERP</button>
        <button class="btn btn-outline-light btn-sm">AI</button>
      </div>
    </div>
  </div>

  <!-- Project Cards -->
  <div class="row g-4">
    <!-- Sample Project Card -->
    <div class="col-md-4">
      <div class="project-card">
        <img src="https://via.placeholder.com/400x200" class="project-image" alt="Project Image">
        <div class="project-body">
          <h5 class="project-title">AI-Powered Dashboard</h5>
          <p class="project-desc">An intelligent admin panel with real-time data and automation features.</p>
          <div class="mb-2">
            <span class="badge badge-tech">React</span>
            <span class="badge badge-tech">Python</span>
            <span class="badge badge-tech">Flask</span>
          </div>
          <p class="project-status status-live">🟢 Live</p>
          <div class="card-actions mt-3">
            <button class="btn btn-outline-primary btn-sm"><i class="fas fa-eye"></i> View</button>
            <button class="btn btn-outline-success btn-sm"><i class="fas fa-link"></i> Live</button>
            <button class="btn btn-outline-secondary btn-sm"><i class="fas fa-download"></i> Docs</button>
            <button class="btn btn-outline-danger btn-sm"><i class="fas fa-video"></i> Demo</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add more cards here dynamically -->
  </div>

  <!-- Empty State -->
  <div class="text-center text-warning mt-5" style="display: none;">
    <h5>No projects match your filter or search criteria.</h5>
  </div>

  <!-- Load More -->
  <button class="btn btn-warning load-more">Load More Projects</button>
</div>

</body>
</html>
