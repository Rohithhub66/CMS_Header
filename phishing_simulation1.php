<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Phishing Email Builder</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" />
  <style>
    body, html {
      height: 100%;
      margin: 0;
      overflow: hidden;
    }
    #builder {
      border: 2px dashed #ccc;
      height: 100%;
      background-color: #f9f9f9;
      overflow-y: auto;
      position: relative;
    }
    .draggable {
      cursor: grab;
    }
    .email-part {
      border: 1px solid #ddd;
      padding: 10px;
      background-color: #fff;
      position: absolute;
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
      z-index: 10;
    }
    .resizable {
      resize: both;
      overflow: auto;
    }
    .toolbar {
      margin-bottom: 10px;
    }
    #templateList {
      max-height: 200px;
      overflow-y: auto;
    }
  </style>
</head>
<body class="container-fluid py-2">
  <h2>Phishing Email Builder</h2>
  <div class="row">
    <div class="col-md-2">
      <h5>Blocks</h5>
      <div class="draggable email-part" draggable="true" data-type="text">Editable Text</div>
      <div class="draggable email-part" draggable="true" data-type="button">Button</div>
      <div class="draggable email-part" draggable="true" data-type="image">Image Upload</div>
      <div class="draggable email-part" draggable="true" data-type="header">Header</div>
      <div class="draggable email-part" draggable="true" data-type="footer">Footer</div>
      <div class="draggable email-part" draggable="true" data-type="signature">Signature</div>
      <hr>
      <h6>Templates</h6>
      <ul id="templateList" class="list-group">
        <!-- Example entries -->
        <li class="list-group-item">HR Notice Template</li>
        <li class="list-group-item">IT Alert Template</li>
      </ul>
    </div>

    <div class="col-md-8">
      <div class="toolbar d-flex align-items-center flex-wrap mb-2">
        <label class="me-2">Font:</label>
        <select id="fontSelector" class="form-select w-auto me-2">
          <option value="Arial">Arial</option>
          <option value="Georgia">Georgia</option>
          <option value="Tahoma">Tahoma</option>
          <option value="Verdana">Verdana</option>
        </select>
        <button onclick="execCmd('bold')" class="btn btn-outline-dark me-1">Bold</button>
        <button onclick="execCmd('italic')" class="btn btn-outline-dark me-1">Italic</button>
        <button onclick="execCmd('underline')" class="btn btn-outline-dark me-1">Underline</button>
        <input type="color" id="fontColor" onchange="execCmd('foreColor', this.value)" class="form-control form-control-color me-2">
      </div>
      <div id="builder"></div>
      <div class="mt-3">
        <button id="previewBtn" class="btn btn-secondary">Preview</button>
        <button id="exportBtn" class="btn btn-success ms-2">Export HTML</button>
        <button id="sendBtn" class="btn btn-primary ms-2">Send Email</button>
        <button id="saveBtn" class="btn btn-info ms-2">Save to Database</button>
        <button id="resetBtn" class="btn btn-warning ms-2">Reset Template</button>
        <button id="clearBtn" class="btn btn-danger ms-2">Delete All Blocks</button>
      </div>
    </div>

    <div class="col-md-2">
      <h5>Brand Settings</h5>
      <input type="text" id="companyName" placeholder="Company Name" class="form-control mb-2">
      <input type="color" id="brandColor" class="form-control form-control-color mb-2" value="#003366">
      <input type="file" id="logoUploader" class="form-control">
    </div>
  </div>

  <!-- JavaScript and additional logic for functionality like loading templates will follow here -->
</body>
</html>
