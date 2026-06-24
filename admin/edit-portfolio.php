<?php
$path_to_root = "../";
$page_title = "Edit Portfolio Project";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$projectId = isset($_GET['id']) ? wqs_decrypt_id($_GET['id']) : 0;
if (!$projectId) {
    echo "<div class='alert alert-danger'>Invalid project ID.</div>";
    require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
    exit;
}

// Fetch main project
$stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->bind_param("i", $projectId);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    echo "<div class='alert alert-danger'>Project not found.</div>";
    require_once dirname(__DIR__) . '/includes/dashboard_footer.php';
    exit;
}

// Fetch tech stacks
$techStacks = [];
$tsResult = $db->query("SELECT stack_name FROM project_tech_stacks WHERE project_id = $projectId");
if ($tsResult) {
    while($row = $tsResult->fetch_assoc()) $techStacks[] = $row['stack_name'];
}

// Fetch features
$features = [];
$fResult = $db->query("SELECT feature_name FROM project_features WHERE project_id = $projectId");
if ($fResult) {
    while($row = $fResult->fetch_assoc()) $features[] = $row['feature_name'];
}

// Fetch team
$teams = [];
$tResult = $db->query("SELECT user_id, is_manager FROM project_teams WHERE project_id = $projectId");
if ($tResult) {
    while($row = $tResult->fetch_assoc()) $teams[] = $row;
}

// Fetch images
$images = [];
$imgResult = $db->query("SELECT id, image_path, caption FROM project_images WHERE project_id = $projectId");
if ($imgResult) {
    while($row = $imgResult->fetch_assoc()) $images[] = $row;
}
?>

<!-- JQuery, Select2, and CKEditor local imports -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.ckeditor.com/4.25.1/standard/ckeditor.js"></script>

<style>
  .progress-container {
    display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(255, 255, 255, 0.95); z-index: 9999;
    align-items: center; justify-content: center; flex-direction: column; text-align: center;
  }
  .progress-bar { height: 24px; background-color: var(--color-primary); }
  .file-size { font-size: 0.85em; }
  .select2-container--default .select2-selection--single {
    border: 1px solid var(--color-border); border-radius: 0.5rem; height: 42px; padding: 6px 12px;
  }
  .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; }
  
  .existing-img-card {
      border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.5rem; margin-bottom: 0.5rem;
      display: flex; align-items: center; gap: 1rem;
  }
  .existing-img-card img { width: 80px; height: 60px; object-fit: cover; border-radius: 4px; }
</style>

<!-- Upload Overlay -->
<div class="progress-container" id="progressOverlay">
  <div class="w-75 max-width-md card-theme p-5">
    <h5 class="fw-bold text-body mb-3"><i class="fas fa-spinner fa-spin text-primary me-2"></i> Updating project... Please wait.</h5>
    <div class="progress my-4" style="height: 15px;">
      <div id="uploadProgress" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
    </div>
    <div class="text-muted small mb-4">
      <span id="uploadedSize">Uploaded: 0 KB</span> <span class="mx-2">|</span>
      <span id="totalSize">Total: 0 KB</span> <span class="mx-2">|</span>
      <span id="remainingTime">Time left: --</span>
    </div>
  </div>
</div>

<div class="card-theme max-width-md mx-auto">
  <div class="card-theme-header">
    <h5 class="card-theme-title">
      <i class="fas fa-edit text-warning"></i> 
      Edit Portfolio Project
    </h5>
  </div>
  <div class="card-theme-body">
    <form id="projectForm" enctype="multipart/form-data">
      <input type="hidden" name="ajax" value="1">
      <input type="hidden" name="project_id" value="<?= $projectId ?>">

      <!-- Title -->
      <div class="mb-4">
        <label class="form-label form-label-theme">Project Title <span class="text-danger">*</span></label>
        <input name="title" class="form-control form-control-theme" value="<?= htmlspecialchars($project['title']) ?>" required>
      </div>

      <!-- Description -->
      <div class="mb-4">
        <label class="form-label form-label-theme">Description <span class="text-danger">*</span></label>
        <textarea name="description" id="descriptionEditor" class="form-control form-control-theme" rows="4"><?= htmlspecialchars($project['description']) ?></textarea>
      </div>

      <!-- URLs and File Uploads -->
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label form-label-theme">Live Demo URL</label>
          <input name="live_url" class="form-control form-control-theme" value="<?= htmlspecialchars($project['live_url']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label form-label-theme">Download File / URL</label>
          <?php if($project['download_url']): ?>
            <div class="small mb-1 text-success"><i class="fas fa-check-circle"></i> Currently: <?= htmlspecialchars(basename($project['download_url'])) ?></div>
          <?php endif; ?>
          <input type="file" name="download_file" class="form-control form-control-theme mb-2 file-input">
          <input type="text" name="download_url" class="form-control form-control-theme" value="<?= htmlspecialchars($project['download_url']) ?>" placeholder="Or paste download URL link">
        </div>
      </div>

      <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" name="enable_download" id="enable_download" <?= $project['enable_download'] ? 'checked' : '' ?>>
        <label class="form-check-label text-body fw-medium" for="enable_download">Enable Download link on frontend</label>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label form-label-theme">Documentation File / URL</label>
          <?php if($project['doc_url']): ?>
            <div class="small mb-1 text-success"><i class="fas fa-check-circle"></i> Currently: <?= htmlspecialchars(basename($project['doc_url'])) ?></div>
          <?php endif; ?>
          <input type="file" name="doc_file" class="form-control form-control-theme mb-2 file-input">
          <input type="text" name="doc_url" class="form-control form-control-theme" value="<?= htmlspecialchars($project['doc_url']) ?>" placeholder="Or paste documentation URL link">
        </div>
        <div class="col-md-6">
          <label class="form-label form-label-theme">Video Demo File / URL</label>
          <?php if($project['video_url']): ?>
            <div class="small mb-1 text-success"><i class="fas fa-check-circle"></i> Currently attached</div>
          <?php endif; ?>
          <input type="file" name="video_file" class="form-control form-control-theme mb-2 file-input">
          <input type="text" name="video_url" class="form-control form-control-theme" value="<?= htmlspecialchars($project['video_url']) ?>" placeholder="Or paste video URL link">
        </div>
      </div>

      <!-- Tech Stack Section -->
      <div class="mb-4">
        <label class="form-label form-label-theme">Technology Stack Used <span class="text-danger">*</span></label>
        <div id="techStackContainer">
          <?php if(empty($techStacks)): ?>
            <div class="mb-2 d-flex align-items-center gap-2">
              <input type="text" name="tech_stacks[]" class="form-control form-control-theme" required>
              <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()"><i class="fas fa-minus"></i></button>
            </div>
          <?php else: ?>
            <?php foreach($techStacks as $ts): ?>
            <div class="mb-2 d-flex align-items-center gap-2">
              <input type="text" name="tech_stacks[]" class="form-control form-control-theme" value="<?= htmlspecialchars($ts) ?>" required>
              <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()"><i class="fas fa-minus"></i></button>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm mt-1" onclick="addTechStackField()"><i class="fas fa-plus"></i> Add Tech Stack</button>
      </div>

      <!-- Key Features Section -->
      <div class="mb-4">
        <label class="form-label form-label-theme">Key Features & Capabilities</label>
        <div id="featuresContainer">
          <?php foreach($features as $f): ?>
            <div class="mb-2 d-flex align-items-center gap-2">
              <input type="text" name="features[]" class="form-control form-control-theme" value="<?= htmlspecialchars($f) ?>">
              <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()"><i class="fas fa-minus"></i></button>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm mt-1" onclick="addFeatureField()"><i class="fas fa-plus"></i> Add Feature</button>
      </div>

      <!-- Project Details: Budget, Timeline, Features -->
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label form-label-theme">Expected Budget ($)</label>
          <input type="number" step="0.01" name="expected_amount" class="form-control form-control-theme" value="<?= $project['expected_amount'] ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label form-label-theme">Actual Budget ($)</label>
          <input type="number" step="0.01" name="actual_amount" class="form-control form-control-theme" value="<?= $project['actual_amount'] ?>">
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label form-label-theme">Start Date</label>
          <input type="date" name="start_date" class="form-control form-control-theme" value="<?= $project['start_date'] ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label form-label-theme">End Date</label>
          <input type="date" name="end_date" class="form-control form-control-theme" value="<?= $project['end_date'] ?>">
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label form-label-theme">Number of Features</label>
          <input type="number" name="num_features" class="form-control form-control-theme" value="<?= $project['num_features'] ?>">
        </div>

        <!-- Project Owner Dropdown -->
        <div class="col-md-6">
          <label class="form-label form-label-theme">Assign Project Owner</label>
          <select name="assigned_user_id" class="form-select select2 form-control-theme">
            <option value="">-- Select Project Owner --</option>
            <?php
              $res = $db->query("SELECT id, name, email FROM users ORDER BY name ASC");
              $userOptions = [];
              while ($row = $res->fetch_assoc()) {
                $sel = ($project['assigned_user_id'] == $row['id']) ? 'selected' : '';
                $opt = "<option value='{$row['id']}' $sel>{$row['name']} ({$row['email']})</option>";
                echo $opt;
                // For JS injection without selected
                $userOptions[] = "<option value='{$row['id']}'>{$row['name']} ({$row['email']})</option>";
              }
            ?>
          </select>
        </div>
      </div>

      <!-- Team Members -->
      <div class="mb-4">
        <label class="form-label form-label-theme">Team Members <small class="text-muted">(Select radio to mark Project Manager)</small></label>
        <div id="teamMembersContainer">
          <?php foreach($teams as $idx => $t): ?>
          <div class="mb-2 d-flex align-items-center gap-2">
            <select name="team_members[]" class="form-select select2 form-control-theme flex-grow-1">
              <option value="">-- Select Team Member --</option>
              <?php
                $res->data_seek(0);
                while ($row = $res->fetch_assoc()) {
                  $sel = ($t['user_id'] == $row['id']) ? 'selected' : '';
                  echo "<option value='{$row['id']}' $sel>{$row['name']} ({$row['email']})</option>";
                }
              ?>
            </select>
            <input type="radio" name="project_manager_index" value="<?= $idx ?>" <?= $t['is_manager'] ? 'checked' : '' ?> title="Mark as Project Manager">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()"><i class="fas fa-minus"></i></button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm mt-1" onclick="addTeamMemberField()"><i class="fas fa-plus"></i> Add Team Member</button>
      </div>

      <!-- Project Images -->
      <div class="mb-4">
        <label class="form-label form-label-theme">Project Screenshot Images</label>
        
        <?php if(!empty($images)): ?>
            <div class="mb-3 p-3 bg-light rounded border">
                <label class="fw-bold mb-2">Existing Images</label>
                <?php foreach($images as $img): ?>
                <div class="existing-img-card">
                    <img src="<?= htmlspecialchars(strpos($img['image_path'], 'http') === 0 ? $img['image_path'] : $path_to_root . $img['image_path']) ?>" alt="">
                    <input type="text" name="existing_image_captions[<?= $img['id'] ?>]" class="form-control form-control-sm flex-grow-1" value="<?= htmlspecialchars($img['caption']) ?>">
                    <div class="form-check text-danger ms-2">
                        <input class="form-check-input" type="checkbox" name="delete_images[]" value="<?= $img['id'] ?>" id="del_<?= $img['id'] ?>">
                        <label class="form-check-label small fw-bold" for="del_<?= $img['id'] ?>">Delete</label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div id="imageContainer"></div>
        <button type="button" class="btn btn-outline-primary btn-sm mt-1" onclick="addImageField()"><i class="fas fa-plus"></i> Add New Image</button>
      </div>

      <!-- Submit -->
      <div class="d-grid mt-5">
        <button type="submit" class="btn btn-warning py-2 fw-bold text-dark"><i class="fas fa-save me-2"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
  $(document).ready(function () {
    $('.select2').select2({ width: '100%', placeholder: '-- Select --', allowClear: true });
  });

  CKEDITOR.replace('descriptionEditor');

  function addTechStackField() {
    const container = document.getElementById('techStackContainer');
    const div = document.createElement('div');
    div.classList.add('mb-2', 'd-flex', 'align-items-center', 'gap-2');
    div.innerHTML = `<input type="text" name="tech_stacks[]" class="form-control form-control-theme" required>
      <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()"><i class="fas fa-minus"></i></button>`;
    container.appendChild(div);
  }

  function addFeatureField() {
    const container = document.getElementById('featuresContainer');
    const div = document.createElement('div');
    div.classList.add('mb-2', 'd-flex', 'align-items-center', 'gap-2');
    div.innerHTML = `<input type="text" name="features[]" class="form-control form-control-theme">
      <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()"><i class="fas fa-minus"></i></button>`;
    container.appendChild(div);
  }

  function addTeamMemberField() {
    const container = document.getElementById('teamMembersContainer');
    const index = container.children.length;
    const div = document.createElement('div');
    div.classList.add('mb-2', 'd-flex', 'align-items-center', 'gap-2');
    div.innerHTML = `
      <select name="team_members[]" class="form-select select2 form-control-theme flex-grow-1">
        <option value="">-- Select Team Member --</option>
        <?php echo addslashes(implode('', $userOptions ?? [])); ?>
      </select>
      <input type="radio" name="project_manager_index" value="${index}" title="Mark as Project Manager">
      <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()"><i class="fas fa-minus"></i></button>`;
    container.appendChild(div);
    $(div).find('select.select2').select2({ width: '100%', placeholder: '-- Select --', allowClear: true });
  }

  function addImageField() {
    const imageContainer = document.getElementById('imageContainer');
    const div = document.createElement('div');
    div.classList.add('mb-2', 'd-flex', 'align-items-center', 'gap-2');
    div.innerHTML = `
      <input type="file" name="project_images[]" accept="image/*" class="form-control form-control-theme file-input" required>
      <input type="text" name="image_captions[]" placeholder="Enter image caption" class="form-control form-control-theme" required>
      <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()"><i class="fas fa-minus"></i></button>
    `;
    imageContainer.appendChild(div);
  }

  window.onload = function () {
    const form = document.getElementById('projectForm');
    const overlay = document.getElementById('progressOverlay');
    const progressBar = document.getElementById('uploadProgress');
    const uploadedSizeEl = document.getElementById('uploadedSize');
    const totalSizeEl = document.getElementById('totalSize');

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const formData = new FormData(form);
      formData.set('description', CKEDITOR.instances.descriptionEditor.getData());

      overlay.style.display = 'flex';
      progressBar.style.width = '0%';
      progressBar.textContent = '0%';

      let xhr = new XMLHttpRequest();
      xhr.open('POST', 'update-project.php', true);

      xhr.upload.onprogress = function (e) {
        if (e.lengthComputable) {
          const percent = Math.round((e.loaded / e.total) * 100);
          progressBar.style.width = percent + '%';
          progressBar.textContent = percent + '%';
        }
      };

      xhr.onload = function () {
        overlay.style.display = 'none';
        if (xhr.status === 200) {
          try {
            const data = JSON.parse(xhr.responseText);
            if (data.success) {
              Swal.fire({
                icon: 'success', title: 'Project Updated!', text: 'Your changes have been saved.', confirmButtonText: 'OK'
              }).then(() => { window.location.reload(); });
            } else {
              Swal.fire({ icon: 'error', title: 'Update Failed', text: data.error, confirmButtonText: 'OK' });
            }
          } catch(err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Invalid response.', confirmButtonText: 'OK' });
          }
        }
      };
      xhr.send(formData);
    });
  };
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
