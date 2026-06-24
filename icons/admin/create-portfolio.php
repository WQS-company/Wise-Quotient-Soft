<?php
$path_to_root = "../../";
$page_title = "Add Portfolio Project";
require_once dirname(dirname(__DIR__)) . '/includes/dashboard_header.php';
?>

<!-- JQuery, Select2, and CKEditor local imports -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.ckeditor.com/4.25.1/standard/ckeditor.js"></script>

<style>
  .progress-container {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.95);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    text-align: center;
  }
  .progress-bar {
    height: 24px;
    background-color: var(--color-primary);
  }
  .file-size {
    font-size: 0.85em;
  }
  /* Style Select2 to match theme */
  .select2-container--default .select2-selection--single {
    border: 1px solid var(--color-border);
    border-radius: 0.5rem;
    height: 42px;
    padding: 6px 12px;
  }
  .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 40px;
  }
</style>

<!-- Upload Overlay -->
<div class="progress-container" id="progressOverlay">
  <div class="w-75 max-width-md card-theme p-5">
    <h5 class="fw-bold text-body mb-3"><i class="fas fa-spinner fa-spin text-primary me-2"></i> Uploading project... Please wait.</h5>
    <div class="progress my-4" style="height: 15px;">
      <div id="uploadProgress" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
    </div>
    <div class="text-muted small mb-4">
      <span id="uploadedSize">Uploaded: 0 KB</span> <span class="mx-2">|</span>
      <span id="totalSize">Total: 0 KB</span> <span class="mx-2">|</span>
      <span id="remainingTime">Time left: --</span>
    </div>
    <div class="d-flex justify-content-center gap-2">
      <button class="btn btn-warning btn-sm fw-semibold" id="pauseBtn"><i class="fas fa-pause me-1"></i> Pause</button>
      <button class="btn btn-danger btn-sm fw-semibold" id="cancelBtn"><i class="fas fa-times me-1"></i> Cancel</button>
    </div>
  </div>
</div>

<div class="card-theme max-width-md mx-auto">
  <div class="card-theme-header">
    <h5 class="card-theme-title">
      <i class="fas fa-plus-circle text-primary"></i> 
      Add New Portfolio Project
    </h5>
    <span class="text-muted small">Publish a project to portfolio list</span>
  </div>
  <div class="card-theme-body">
    <form id="projectForm" enctype="multipart/form-data">
      <input type="hidden" name="ajax" value="1">

      <!-- Title -->
      <div class="mb-4">
        <label class="form-label form-label-theme">Project Title <span class="text-danger">*</span></label>
        <input name="title" class="form-control form-control-theme" placeholder="e.g. E-Commerce Platform v2" required>
      </div>

      <!-- Description -->
      <div class="mb-4">
        <label class="form-label form-label-theme">Description <span class="text-danger">*</span></label>
        <textarea name="description" id="descriptionEditor" class="form-control form-control-theme" rows="4"></textarea>
      </div>

      <!-- URLs and File Uploads -->
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label form-label-theme">Live Demo URL</label>
          <input name="live_url" class="form-control form-control-theme" placeholder="e.g. https://demo.wisequotientsoft.com">
        </div>
        <div class="col-md-6">
          <label class="form-label form-label-theme">Download File / URL</label>
          <input type="file" name="download_file" class="form-control form-control-theme mb-2 file-input">
          <input type="text" name="download_url" class="form-control form-control-theme" placeholder="Or paste download URL link">
        </div>
      </div>

      <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" name="enable_download" id="enable_download">
        <label class="form-check-label text-body fw-medium" for="enable_download">Enable Download link on frontend</label>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label form-label-theme">Documentation File / URL</label>
          <input type="file" name="doc_file" class="form-control form-control-theme mb-2 file-input">
          <input type="text" name="doc_url" class="form-control form-control-theme" placeholder="Or paste documentation URL link">
        </div>
        <div class="col-md-6">
          <label class="form-label form-label-theme">Video Demo File / URL</label>
          <input type="file" name="video_file" class="form-control form-control-theme mb-2 file-input">
          <input type="text" name="video_url" class="form-control form-control-theme" placeholder="Or paste video URL link">
        </div>
      </div>

      <!-- Tech Stack Section -->
      <div class="mb-4">
        <label class="form-label form-label-theme">Technology Stack Used <span class="text-danger">*</span></label>
        <div id="techStackContainer">
          <div class="mb-2 d-flex align-items-center gap-2">
            <input type="text" name="tech_stacks[]" class="form-control form-control-theme" placeholder="e.g. React.js, Laravel" required>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">
              <i class="fas fa-minus"></i>
            </button>
          </div>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm mt-1" onclick="addTechStackField()">
          <i class="fas fa-plus"></i> Add Tech Stack
        </button>
      </div>

      <!-- Project Details: Budget, Timeline, Features -->
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label form-label-theme">Expected Budget ($)</label>
          <input type="number" step="0.01" name="expected_amount" class="form-control form-control-theme" placeholder="e.g. 5000">
        </div>
        <div class="col-md-6">
          <label class="form-label form-label-theme">Actual Budget ($)</label>
          <input type="number" step="0.01" name="actual_amount" class="form-control form-control-theme" placeholder="e.g. 4800">
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label form-label-theme">Start Date</label>
          <input type="date" name="start_date" class="form-control form-control-theme">
        </div>
        <div class="col-md-6">
          <label class="form-label form-label-theme">End Date</label>
          <input type="date" name="end_date" class="form-control form-control-theme">
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label form-label-theme">Number of Features</label>
          <input type="number" name="num_features" class="form-control form-control-theme" placeholder="e.g. 12">
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
                $userOptions[] = "<option value='{$row['id']}'>{$row['name']} ({$row['email']})</option>";
              }
              echo implode('', $userOptions);
            ?>
          </select>
        </div>
      </div>

      <!-- Team Members with Project Manager radio -->
      <div class="mb-4">
        <label class="form-label form-label-theme">Team Members <small class="text-muted">(Select radio to mark Project Manager)</small></label>
        <div id="teamMembersContainer">
          <div class="mb-2 d-flex align-items-center gap-2">
            <select name="team_members[]" class="form-select select2 form-control-theme flex-grow-1">
              <option value="">-- Select Team Member --</option>
              <?php echo implode('', $userOptions); ?>
            </select>
            <input type="radio" name="project_manager_index" value="0" checked title="Mark as Project Manager">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">
              <i class="fas fa-minus"></i>
            </button>
          </div>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm mt-1" onclick="addTeamMemberField()">
          <i class="fas fa-plus"></i> Add Team Member
        </button>
      </div>

      <!-- Project Images Upload with Caption -->
      <div class="mb-4">
        <label class="form-label form-label-theme">Project Screenshot Images</label>
        <div id="imageContainer"></div>
        <button type="button" class="btn btn-outline-primary btn-sm mt-1" onclick="addImageField()">
          <i class="fas fa-plus"></i> Add Image Field
        </button>
      </div>

      <!-- Submit -->
      <div class="d-grid mt-5">
        <button type="submit" class="btn btn-theme py-2"><i class="fas fa-upload me-2"></i> Submit & Publish Project</button>
      </div>
    </form>
  </div>
</div>

<script>
  $(document).ready(function () {
    $('.select2').select2({
      width: '100%',
      placeholder: '-- Select --',
      allowClear: true
    });
  });

  // Initialize CKEditor
  CKEDITOR.replace('descriptionEditor');

  function addTechStackField() {
    const container = document.getElementById('techStackContainer');
    const div = document.createElement('div');
    div.classList.add('mb-2', 'd-flex', 'align-items-center', 'gap-2');
    div.innerHTML = `
      <input type="text" name="tech_stacks[]" class="form-control form-control-theme" placeholder="e.g. Node.js, MySQL" required>
      <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">
        <i class="fas fa-minus"></i>
      </button>
    `;
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
        <?php echo addslashes(implode('', $userOptions)); ?>
      </select>
      <input type="radio" name="project_manager_index" value="${index}" title="Mark as Project Manager">
      <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">
        <i class="fas fa-minus"></i>
      </button>
    `;

    container.appendChild(div);

    $(div).find('select.select2').select2({
      width: '100%',
      placeholder: '-- Select --',
      allowClear: true
    });
  }

  function addImageField() {
    const imageContainer = document.getElementById('imageContainer');
    const div = document.createElement('div');
    div.classList.add('mb-2', 'd-flex', 'align-items-center', 'gap-2');
    div.innerHTML = `
      <input type="file" name="project_images[]" accept="image/*" class="form-control form-control-theme file-input" required>
      <input type="text" name="image_captions[]" placeholder="Enter image caption" class="form-control form-control-theme" required>
      <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">
        <i class="fas fa-minus"></i>
      </button>
    `;
    imageContainer.appendChild(div);
    attachFileSizeEvent(div.querySelector('.file-input'));
  }

  function formatBytes(bytes) {
    if (bytes === 0) return '0 KB';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  function attachFileSizeEvent(input) {
    input.addEventListener('change', () => {
      const next = input.nextElementSibling;
      if (next && next.classList.contains('file-size')) {
        next.remove();
      }
      if (input.files.length > 0) {
        const sizeInfo = document.createElement('small');
        sizeInfo.className = 'text-muted file-size d-block mt-1';
        sizeInfo.textContent = `Selected: ${formatBytes(input.files[0].size)}`;
        input.insertAdjacentElement('afterend', sizeInfo);
      }
    });
  }

  // Handle upload logic
  window.onload = function () {
    const form = document.getElementById('projectForm');
    const overlay = document.getElementById('progressOverlay');
    const progressBar = document.getElementById('uploadProgress');
    const uploadedSizeEl = document.getElementById('uploadedSize');
    const totalSizeEl = document.getElementById('totalSize');
    const remainingTimeEl = document.getElementById('remainingTime');
    let xhr = null;
    let uploadStart = 0;

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const formData = new FormData(form);
      formData.set('description', CKEDITOR.instances.descriptionEditor.getData());
      formData.set('ajax', '1');

      overlay.style.display = 'flex';
      progressBar.style.width = '0%';
      progressBar.textContent = '0%';
      uploadedSizeEl.textContent = 'Uploaded: 0 KB';
      totalSizeEl.textContent = 'Total: Calculating...';
      remainingTimeEl.textContent = 'Time left: --';

      xhr = new XMLHttpRequest();
      // Points to admin/upload-project.php from icons/admin/
      xhr.open('POST', '../../admin/upload-project.php', true);

      xhr.upload.onloadstart = function () {
        uploadStart = Date.now();
      };

      xhr.upload.onprogress = function (e) {
        if (e.lengthComputable) {
          const percent = Math.round((e.loaded / e.total) * 100);
          const uploaded = formatBytes(e.loaded);
          const total = formatBytes(e.total);
          const elapsed = (Date.now() - uploadStart) / 1000;
          const speed = e.loaded / elapsed;
          const remaining = e.total - e.loaded;
          const eta = remaining / speed;

          progressBar.style.width = percent + '%';
          progressBar.textContent = percent + '%';
          uploadedSizeEl.textContent = 'Uploaded: ' + uploaded;
          totalSizeEl.textContent = 'Total: ' + total;
          remainingTimeEl.textContent = 'Time left: ' + Math.round(eta) + 's';
        }
      };

      xhr.onload = function () {
        overlay.style.display = 'none';
        if (xhr.status === 200) {
          try {
            const data = JSON.parse(xhr.responseText);
            if (data.success) {
              Swal.fire({
                icon: 'success',
                title: 'Project Uploaded!',
                text: '✅ Your project was uploaded successfully.',
                confirmButtonText: 'OK'
              });
              form.reset();
              document.getElementById('imageContainer').innerHTML = '';
              CKEDITOR.instances.descriptionEditor.setData('');
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Upload Failed',
                text: '❌ Error: ' + data.error,
                confirmButtonText: 'OK'
              });
            }
          } catch(err) {
            Swal.fire({
              icon: 'error',
              title: 'Parsing Error',
              text: 'Could not understand response from server.',
              confirmButtonText: 'OK'
            });
          }
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Server Error',
            text: '❌ An unexpected server error occurred.',
            confirmButtonText: 'OK'
          });
        }
      };

      xhr.onerror = function () {
        overlay.style.display = 'none';
        Swal.fire({
          icon: 'error',
          title: 'Upload Error',
          text: '❌ There was a problem with the upload.',
          confirmButtonText: 'OK'
        });
      };

      xhr.send(formData);
    });

    // Cancel upload
    document.getElementById('cancelBtn').onclick = () => {
      if (xhr) xhr.abort();
      overlay.style.display = 'none';
      Swal.fire({
        icon: 'info',
        title: 'Upload Canceled',
        text: '❗ Your upload was canceled.',
        confirmButtonText: 'OK'
      });
    };

    // Pause button alert
    document.getElementById('pauseBtn').onclick = () => {
      Swal.fire({
        icon: 'info',
        title: 'Feature Not Available',
        text: '⏸️ Pause/resume not supported using XHR.',
        confirmButtonText: 'OK'
      });
    };

    // Show size for default file inputs
    document.querySelectorAll('.file-input').forEach(input => {
      attachFileSizeEvent(input);
    });
  };
</script>

<?php
require_once dirname(dirname(__DIR__)) . '/includes/dashboard_footer.php';
?>
