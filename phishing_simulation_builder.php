<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Advanced Email Template Builder</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.css" rel="stylesheet"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
  <style>
    body { font-family: Arial, sans-serif; background: #f6f7fa; margin: 0;}
    .container { display: flex; min-height: 100vh; }
    .sidebar { width: 240px; background: #232946; color: #fff; padding: 18px 10px; display: flex; flex-direction: column; gap: 12px;}
    .tool-btn, .upload-label, .variable-btn { background: #eebbc3; color: #232946; border:none; border-radius:4px; padding: 7px 11px; margin-bottom:7px; font-weight:600; font-size:1em; cursor:pointer;}
    .main-area { flex: 1; display: flex; flex-direction: column; }
    .canvas-toolbar { background:#fff; border-bottom:1px solid #e2e6ef; padding:12px 18px; display:flex; gap:16px; align-items:center;}
    .canvas-toolbar button { background: #1976d2; color:#fff; border:none; border-radius:4px; padding:8px 16px; font-size:.98em; font-weight:600; cursor:pointer;}
    .canvas-area { flex:1; background: #f4f6fb; display:flex; justify-content:center; align-items:flex-start; overflow:auto; padding:24px 0;}
    #canvas { background:#fff; border-radius:10px; min-width:340px; min-height:500px; box-shadow:0 2px 18px #0001; padding:32px 12px; position:relative; width:500px; max-width:100%;}
    .block { border:1.5px dashed #ccc; border-radius:7px; margin:8px 13px; padding:12px 10px; min-height:36px; background:#f9fbff; position:relative; min-width:120px; cursor:move;}
    .block.selected { border:2.5px solid #1976d2; background:#e3eaf9;}
    .block .remove-btn { position: absolute; top:4px; right:4px; background: #d32f2f; color:#fff; border:none; border-radius:3px; font-size:.95em; padding:2px 7px; cursor:pointer;}
    .block img { max-width:95%; max-height:90px; display:block; margin:0 auto; }
    .asset-thumb { width:38px; height:38px; object-fit:contain; margin:2px; border:1px solid #ddd; border-radius:4px; background:white; cursor:pointer;}
    .upload-label { margin-bottom:12px;}
    .variable-bar { margin-bottom:10px; }
    .variable-btn { margin-right:6px; margin-bottom:2px; background: #c1c8e4; color:#232946; }
    .style-bar { margin-bottom:10px; }
    .style-bar button, .style-bar select, .style-bar input[type=color] { margin-right:4px; }
    .block [contenteditable]:focus { outline: 2px solid #1976d2; background: #e3eaf9;}
    .block .style-bar { background: #f0f0f5; border-radius: 4px; padding: 3px 5px; margin-bottom: 4px;}
  </style>
</head>
<body>
<div class="container">
  <div class="sidebar">
    <h2>Toolbox</h2>
    <button class="tool-btn" onclick="addBlock('header')">Header</button>
    <button class="tool-btn" onclick="addBlock('text')">Text</button>
    <button class="tool-btn" onclick="addBlock('button')">Button</button>
    <button class="tool-btn" onclick="addBlock('divider')">Divider</button>
    <button class="tool-btn" onclick="addBlock('footer')">Footer</button>
    <hr>
    <label class="upload-label">
      <input type="file" id="imgUpload" accept="image/*" style="display:none" onchange="uploadAsset(event)">
      + Upload Image
    </label>
    <div id="imageAssets" style="display:flex;flex-wrap:wrap;gap:3px; margin-bottom:10px;"></div>
    <hr>
    <label>Templates:</label>
    <select id="templateList" style="margin-bottom:7px;"></select>
    <button class="tool-btn" onclick="saveTemplate()">Save</button>
    <button class="tool-btn" onclick="loadTemplate()">Load</button>
  </div>
  <div class="main-area">
    <div class="canvas-toolbar">
      <button onclick="exportHTML()">Export HTML</button>
      <button onclick="resetCanvas()" style="background:#d32f2f;">Reset Canvas</button>
      <div class="variable-bar" style="margin-left:40px;">
        <b>Insert Variable:</b>
        <button class="variable-btn" onclick="insertVariable('{{FirstName}}')">First Name</button>
        <button class="variable-btn" onclick="insertVariable('{{LastName}}')">Last Name</button>
        <button class="variable-btn" onclick="insertVariable('{{Email}}')">Email</button>
        <button class="variable-btn" onclick="insertVariable('{{Department}}')">Department</button>
      </div>
    </div>
    <div class="canvas-area">
      <div id="canvas"></div>
    </div>
  </div>
</div>
<script>
let blocks = [];
let images = [];
let selectedBlock = null;
let lastEditable = null;

function addBlock(type, src = null) {
  let block = {
    id: "b"+Date.now()+"_"+Math.floor(Math.random()*9999),
    type: type,
    html: "",
    src: src,
    styles: { fontSize: "16px", color: "#232946", fontWeight: "normal", fontStyle: "normal", textDecoration: "none" }
  };
  if(type==='header') block.html = "Company Security Notification";
  if(type==='text') block.html = "This is a suspicious login attempt for {{FirstName}}.<br>Please verify.";
  if(type==='footer') block.html = "© 2025 Company. All rights reserved.";
  if(type==='button') block.html = "Verify Now";
  if(type==='divider') block.html = "";
  if(type==='image' && src) block.src = src;
  blocks.push(block);
  renderCanvas();
}

function renderCanvas() {
  let html = "";
  blocks.forEach((b, idx) => {
    let sel = (selectedBlock && selectedBlock.id===b.id) ? "selected":"";
    let styleStr = `font-size:${b.styles.fontSize};color:${b.styles.color};font-weight:${b.styles.fontWeight};font-style:${b.styles.fontStyle};text-decoration:${b.styles.textDecoration};`;
    let styleBar = '';
    if(['header','text','footer'].includes(b.type)) {
      styleBar = `<div class="style-bar">
        <select onchange="changeStyle('${b.id}','fontSize',this.value)">
          <option value="14px"${b.styles.fontSize==='14px'?' selected':''}>14px</option>
          <option value="16px"${b.styles.fontSize==='16px'?' selected':''}>16px</option>
          <option value="18px"${b.styles.fontSize==='18px'?' selected':''}>18px</option>
          <option value="22px"${b.styles.fontSize==='22px'?' selected':''}>22px</option>
        </select>
        <input type="color" value="${b.styles.color}" onchange="changeStyle('${b.id}','color',this.value)">
        <button onclick="toggleStyle('${b.id}','fontWeight')" style="font-weight:bold;">B</button>
        <button onclick="toggleStyle('${b.id}','fontStyle')" style="font-style:italic;">I</button>
        <button onclick="toggleStyle('${b.id}','textDecoration')" style="text-decoration:underline;">U</button>
        <button onclick="makeLink('${b.id}')">Link</button>
      </div>`;
    }
    if(b.type==='header'||b.type==='text'||b.type==='footer'){
      html += `<div class="block ${sel}" onclick="selectBlock(event,'${b.id}')" data-id="${b.id}">
        <span class="remove-btn" onclick="removeBlock(event,'${b.id}')">&times;</span>
        ${styleBar}
        <div contenteditable="true" spellcheck="false" style="${styleStr}" onfocus="lastEditable=this" oninput="updateBlockText('${b.id}',this.innerHTML)">${b.html}</div>
      </div>`;
    } else if(b.type==='button'){
      html += `<div class="block ${sel}" onclick="selectBlock(event,'${b.id}')" data-id="${b.id}">
        <span class="remove-btn" onclick="removeBlock(event,'${b.id}')">&times;</span>
        <button contenteditable="true" spellcheck="false" style="${styleStr}" onfocus="lastEditable=this" oninput="updateBlockText('${b.id}',this.innerText)">${b.html}</button>
      </div>`;
    } else if(b.type==='divider'){
      html += `<div class="block ${sel}" style="background:none;padding:0;min-height:8px;"
        onclick="selectBlock(event,'${b.id}')" data-id="${b.id}">
        <span class="remove-btn" onclick="removeBlock(event,'${b.id}')">&times;</span>
        <hr>
      </div>`;
    } else if(b.type==='image'){
      html += `<div class="block ${sel}" onclick="selectBlock(event,'${b.id}')" data-id="${b.id}">
        <span class="remove-btn" onclick="removeBlock(event,'${b.id}')">&times;</span>
        <img src="${b.src}">
      </div>`;
    }
  });
  document.getElementById("canvas").innerHTML = html;
  enableSortable();
}

function updateBlockText(id, html) {
  let b = blocks.find(x=>x.id===id);
  if(b) b.html = html;
}

function removeBlock(event, id) {
  event.stopPropagation();
  blocks = blocks.filter(x=>x.id!==id);
  if(selectedBlock && selectedBlock.id===id) selectedBlock = null;
  renderCanvas();
}

function selectBlock(e, id) {
  e.stopPropagation();
  selectedBlock = blocks.find(x=>x.id===id);
  renderCanvas();
}

function enableSortable(){
  if(window.sortableInstance) window.sortableInstance.destroy();
  window.sortableInstance = new Sortable(document.getElementById('canvas'), {
    animation: 150,
    handle: '.block',
    onEnd: function(evt){
      const oldIndex = evt.oldIndex;
      const newIndex = evt.newIndex;
      if (oldIndex !== newIndex) {
        const moved = blocks.splice(oldIndex, 1)[0];
        blocks.splice(newIndex, 0, moved);
        renderCanvas();
      }
    }
  });
}

function exportHTML(){
  let html = "";
  blocks.forEach(b=>{
    let styleStr = '';
    if(['header','text','footer','button'].includes(b.type)){
      styleStr = `font-size:${b.styles.fontSize};color:${b.styles.color};font-weight:${b.styles.fontWeight};font-style:${b.styles.fontStyle};text-decoration:${b.styles.textDecoration};`;
    }
    if(b.type==='header') html+=`<h2 style="${styleStr}">${b.html}</h2>\n`;
    if(b.type==='text') html+=`<p style="${styleStr}">${b.html}</p>\n`;
    if(b.type==='footer') html+=`<footer style="${styleStr}">${b.html}</footer>\n`;
    if(b.type==='button') html+=`<a href="#" style="display:inline-block;padding:12px 28px;background:#1976d2;color:#fff;border-radius:6px;text-decoration:none;font-weight:bold;${styleStr}">${b.html}</a>\n`;
    if(b.type==='divider') html+=`<hr>\n`;
    if(b.type==='image') html+=`<img src="${b.src}" style="max-width:100%;">\n`;
  });
  let w = window.open("", "", "width=700,height=600");
  w.document.write(`<pre style="white-space:pre-wrap;word-break:break-all;background:#f8f8fa;padding:16px 12px;">${html.replace(/</g,"&lt;")}</pre>`);
  w.document.title = "Exported HTML";
}

function resetCanvas(){
  if(confirm("Clear all blocks from the canvas?")){
    blocks = [];
    selectedBlock = null;
    renderCanvas();
  }
}

// --- Styling controls for text blocks ---
function changeStyle(id, prop, value) {
  let b = blocks.find(x=>x.id===id);
  if(!b) return;
  b.styles[prop] = value;
  renderCanvas();
}
function toggleStyle(id, prop) {
  let b = blocks.find(x=>x.id===id);
  if(!b) return;
  if(prop==='fontWeight') b.styles.fontWeight = (b.styles.fontWeight==='bold'?'normal':'bold');
  if(prop==='fontStyle') b.styles.fontStyle = (b.styles.fontStyle==='italic'?'normal':'italic');
  if(prop==='textDecoration') b.styles.textDecoration = (b.styles.textDecoration==='underline'?'none':'underline');
  renderCanvas();
}
// Insert a link at selection
function makeLink(id) {
  setTimeout(function(){
    let url = prompt("Enter URL:");
    if(url && lastEditable) {
      document.execCommand('createLink', false, url);
    }
  }, 10);
}

// --- Variable insertion ---
function insertVariable(varName) {
  if(document.activeElement && document.activeElement.isContentEditable){
    insertAtCaret(varName);
  } else if(lastEditable) {
    lastEditable.focus();
    insertAtCaret(varName);
  } else {
    alert("Click in a text block to insert.");
  }
}
function insertAtCaret(text) {
  let sel = window.getSelection();
  if (!sel.rangeCount) return;
  let range = sel.getRangeAt(0);
  range.deleteContents();
  range.insertNode(document.createTextNode(text));
  // Move caret after inserted text
  range.collapse(false);
  sel.removeAllRanges();
  sel.addRange(range);
}

// --- Image upload ---
function uploadAsset(event){
  const file = event.target.files[0];
  if(!file) return;
  let formData = new FormData();
  formData.append('file', file);
  fetch('upload.php', {
    method: 'POST',
    body: formData
  })
  .then(r=>r.json())
  .then(data=>{
    if(data.status==='ok'){
      images.push(data.url);
      renderAssets();
      addBlock('image', data.url);
    } else {
      alert("Upload error");
    }
  });
}

function renderAssets(){
  let el = document.getElementById('imageAssets');
  el.innerHTML = "";
  images.forEach(url=>{
    el.innerHTML += `<img src="${url}" class="asset-thumb" onclick="addBlock('image','${url}')">`;
  });
}

// --- Save/load templates ---
function saveTemplate() {
  let name = prompt("Template name?");
  if(!name) return;
  fetch('save.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ name: name, blocks: blocks })
  }).then(r=>r.json()).then(data=>{
    if(data.status==='ok') alert("Template saved!");
    else alert("Save failed");
    listTemplates();
  });
}
function loadTemplate() {
  let sel = document.getElementById('templateList');
  let name = sel.value;
  if(!name) return;
  fetch('load.php?name='+encodeURIComponent(name))
    .then(r=>r.json())
    .then(data=>{
      if(data.status==='ok'){
        blocks = data.blocks;
        renderCanvas();
      } else {
        alert("Load failed");
      }
    });
}
function listTemplates() {
  fetch('load.php?list=1').then(r=>r.json()).then(data=>{
    let sel = document.getElementById('templateList');
    sel.innerHTML = '';
    data.forEach(name=>{
      sel.innerHTML += `<option value="${name}">${name}</option>`;
    });
  });
}

// Expose functions
window.addBlock = addBlock;
window.exportHTML = exportHTML;
window.resetCanvas = resetCanvas;
window.uploadAsset = uploadAsset;
window.renderAssets = renderAssets;
window.removeBlock = removeBlock;
window.selectBlock = selectBlock;
window.updateBlockText = updateBlockText;
window.changeStyle = changeStyle;
window.toggleStyle = toggleStyle;
window.insertVariable = insertVariable;
window.makeLink = makeLink;
window.saveTemplate = saveTemplate;
window.loadTemplate = loadTemplate;

// Initial render and template list
renderCanvas();
listTemplates();
</script>
</body>
</html>