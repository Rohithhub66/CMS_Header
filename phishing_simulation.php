<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Phishing Email Builder</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" />
  <style>
    #builder {
      border: 2px dashed #ccc;
      padding: 20px;
      min-height: 80vh;
      background-color: #f9f9f9;
      overflow-y: auto;
    }
    .draggable {
      cursor: grab;
    }
    .email-part {
      border: 1px solid #ddd;
      padding: 10px;
      margin-bottom: 10px;
      background-color: #fff;
      position: relative;
    }
    .delete-btn {
      position: absolute;
      top: 5px;
      right: 5px;
      background: red;
      color: white;
      border: none;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      font-size: 12px;
      line-height: 1;
      text-align: center;
      cursor: pointer;
    }
  </style>
</head>
<body class="container py-4">
  <h2>Phishing Email Builder</h2>
  <div class="mb-3">
    <label for="templateStyle" class="form-label">Select Style</label>
    <select id="templateStyle" class="form-select">
      <option>Corporate</option>
      <option>Casual</option>
      <option>Warning</option>
    </select>
  </div>

  <div class="row">
    <div class="col-md-3">
      <h5>Blocks</h5>
      <div class="draggable email-part" draggable="true" data-type="text">Editable Text</div>
      <div class="draggable email-part" draggable="true" data-type="button">Button</div>
      <div class="draggable email-part" draggable="true" data-type="image">Image Upload</div>
      <div class="draggable email-part" draggable="true" data-type="header">Header</div>
      <div class="draggable email-part" draggable="true" data-type="footer">Footer</div>
      <div class="draggable email-part" draggable="true" data-type="signature">Signature</div>
    </div>
    <div class="col-md-9">
      <h5>Email Template</h5>
      <div id="builder"></div>
      <button id="previewBtn" class="btn btn-secondary mt-3">Preview</button>
      <button id="exportBtn" class="btn btn-success mt-3 ms-2">Export HTML</button>
      <button id="sendBtn" class="btn btn-primary mt-3 ms-2">Send Email</button>
      <button id="saveBtn" class="btn btn-info mt-3 ms-2">Save to Database</button>
      <button id="resetBtn" class="btn btn-warning mt-3 ms-2">Reset Template</button>
      <button id="clearBtn" class="btn btn-danger mt-3 ms-2">Delete All Blocks</button>
    </div>
  </div>

  <!-- Modal for image cropping -->
  <div class="modal fade" id="cropModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Crop Image</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <img id="cropImage" style="max-width:100%;">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" id="cropBtn">Crop</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal for preview -->
  <div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Email Preview</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="previewContent"></div>
        </div>
      </div>
    </div>
  </div>

  <input type="file" id="imageInput" style="display:none">

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
  <script>
    let builder = document.getElementById('builder');
    let dragSrc = null;
    let cropper;

    function addDeleteButton(el) {
      const delBtn = document.createElement('button');
      delBtn.innerHTML = '&times;';
      delBtn.className = 'delete-btn';
      delBtn.onclick = () => el.remove();
      el.appendChild(delBtn);
    }

    document.querySelectorAll('.draggable').forEach(el => {
      el.addEventListener('dragstart', e => {
        dragSrc = el;
      });
    });

    builder.addEventListener('dragover', e => e.preventDefault());
    builder.addEventListener('drop', e => {
      e.preventDefault();
      const type = dragSrc.dataset.type;
      let node;

      switch (type) {
        case 'text':
          node = document.createElement('div');
          node.contentEditable = true;
          node.innerText = 'Editable Text';
          break;
        case 'button':
          node = document.createElement('button');
          node.innerText = 'Click Me';
          node.className = 'btn btn-primary';
          break;
        case 'image':
          document.getElementById('imageInput').click();
          return;
        case 'header':
          node = document.createElement('h1');
          node.innerText = 'Header Text';
          break;
        case 'footer':
          node = document.createElement('footer');
          node.innerText = 'Footer Content';
          break;
        case 'signature':
          node = document.createElement('p');
          node.innerText = 'Best Regards,\nYour Name';
          break;
      }

      if (node) {
        node.classList.add('email-part');
        addDeleteButton(node);
        builder.appendChild(node);
      }
    });

    document.getElementById('imageInput').addEventListener('change', function (e) {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function (evt) {
          document.getElementById('cropImage').src = evt.target.result;
          const modal = new bootstrap.Modal(document.getElementById('cropModal'));
          modal.show();

          if (cropper) cropper.destroy();
          cropper = new Cropper(document.getElementById('cropImage'), {
            aspectRatio: NaN,
            viewMode: 1
          });
        };
        reader.readAsDataURL(file);
      }
    });

    document.getElementById('cropBtn').addEventListener('click', () => {
      const canvas = cropper.getCroppedCanvas();
      const img = document.createElement('img');
      img.src = canvas.toDataURL();
      img.classList.add('email-part');
      addDeleteButton(img);
      builder.appendChild(img);
      bootstrap.Modal.getInstance(document.getElementById('cropModal')).hide();
    });

    document.getElementById('previewBtn').addEventListener('click', () => {
      const content = builder.innerHTML;
      document.getElementById('previewContent').innerHTML = content;
      const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
      previewModal.show();
    });

    document.getElementById('exportBtn').addEventListener('click', () => {
      const content = builder.innerHTML;
      const blob = new Blob([content], { type: 'text/html' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'phishing_email.html';
      a.click();
      URL.revokeObjectURL(url);
    });

    document.getElementById('sendBtn').addEventListener('click', () => {
      const htmlContent = builder.innerHTML;
      fetch('send_email.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ html: htmlContent })
      }).then(res => res.text()).then(alert);
    });

    document.getElementById('saveBtn').addEventListener('click', () => {
      const htmlContent = builder.innerHTML;
      fetch('save_template.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ html: htmlContent })
      }).then(res => res.text()).then(alert);
    });

    document.getElementById('resetBtn').addEventListener('click', () => {
      location.reload();
    });

    document.getElementById('clearBtn').addEventListener('click', () => {
      builder.innerHTML = '';
    });
  </script>
</body>
</html>
