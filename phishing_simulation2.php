<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Phishing Template Builder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Cropper.js for image cropping -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <!-- SortableJS for drag & drop block reordering -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
    <style>
        body { font-family: Arial,sans-serif; margin:0; background:#f6f7fa;}
        .container { display: flex; height: 100vh; }
        .sidebar {
            width: 230px; background: #232946; color: #fff; padding: 20px 12px;
            display: flex; flex-direction: column; gap: 14px; min-width: 190px;
        }
        .sidebar h2 { font-size: 1.13em; margin-bottom: 10px; color:#eebbc3;}
        .sidebar label {font-size:.95em; margin-bottom:4px; color:#eebbc3;}
        .tool-btn, .upload-label {
            background: #eebbc3; color: #232946; border:none; border-radius:4px;
            padding: 7px 11px; margin-bottom:7px; font-weight:600; font-size:1em;
            cursor:pointer; transition:background .13s;
        }
        .tool-btn:hover, .upload-label:hover { background:#fff; }
        .asset-thumb {
            width:38px; height:38px; object-fit:contain; margin:2px; border:1px solid #ddd; border-radius:4px; background:white; cursor:pointer;
        }
        .main-area { flex: 1; display: flex; flex-direction: column; }
        .canvas-toolbar {
            background:#fff; border-bottom:1px solid #e2e6ef; padding:12px 18px; display:flex; gap:18px; align-items:center;
        }
        .canvas-toolbar button {
            background: #1976d2; color:#fff; border:none; border-radius:4px; padding:8px 16px; font-size:.98em; font-weight:600; cursor:pointer;
        }
        .canvas-toolbar button[style] { background:#d32f2f !important; }
        .canvas-area {
            flex:1; background: #f4f6fb; display:flex; justify-content:center; align-items:flex-start; overflow:auto; padding:26px 12px;
        }
        #canvas {
            background:#fff; border-radius:10px; min-width:340px; min-height:500px; box-shadow:0 2px 18px #0001; padding:32px 12px;
            position:relative; width:500px; max-width:100%;
        }
        .block {
            border:1.5px dashed #ccc; border-radius:7px; margin:8px 13px; padding:12px 10px; min-height:36px; background:#f9fbff;
            position:relative; min-width:120px;
        }
        .block.selected { border:2.5px solid #1976d2; background:#e3eaf9; }
        .block .remove-btn {
            position: absolute; top:4px; right:4px;
            background: #d32f2f; color:#fff; border:none; border-radius:3px; font-size:.95em; padding:2px 7px; cursor:pointer;
        }
        .block-img, .block-icon { max-width:95%; max-height:90px; display:block; margin:0 auto; }
        .draggable-img { cursor: grab; }
        .properties-panel {
            position:fixed; right:28px; top:70px; width:250px; background:#fff; border-radius:10px;
            box-shadow:0 2px 16px #0002; padding:15px 18px; z-index:20; min-height:90px;
            display:none;
        }
        .properties-panel.active { display:block; }
        .properties-panel label { font-weight:bold; margin:10px 0 3px 0; display:block; }
        .properties-panel input[type="text"], .properties-panel textarea {
            width: 100%; font-size:1em; border:1px solid #dce1ec; border-radius:5px; padding:5px 8px; margin-bottom:7px;
        }
        @media (max-width:700px){
            .container { flex-direction:column;}
            .sidebar { width:100%; flex-direction:row; flex-wrap:wrap; }
            .main-area { min-width:0;}
            .canvas-area {padding:7px;}
            #canvas {padding:12px 4px;}
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Sidebar: Drag and drop tools, asset upload, icon library -->
    <div class="sidebar">
        <h2>Toolbox</h2>
        <button class="tool-btn" onclick="addBlock('header')">Header</button>
        <button class="tool-btn" onclick="addBlock('text')">Text</button>
        <button class="tool-btn" onclick="addBlock('button')">Button</button>
        <button class="tool-btn" onclick="addBlock('footer')">Footer</button>
        <button class="tool-btn" onclick="addBlock('signature')">Signature</button>
        <button class="tool-btn" onclick="addBlock('divider')">Divider</button>
        <hr style="margin:12px 0;">
        <label>Insert Image</label>
        <label class="upload-label">
            <input type="file" id="imgUpload" accept="image/*" style="display:none" onchange="uploadAsset(event, 'image')">
            + Upload Image
        </label>
        <div id="imageAssets" style="display:flex;flex-wrap:wrap;gap:3px; margin-bottom:10px;"></div>
        <label>Insert Company Icon</label>
        <label class="upload-label">
            <input type="file" id="iconUpload" accept="image/*" style="display:none" onchange="uploadAsset(event, 'icon')">
            + Upload Icon
        </label>
        <div id="iconAssets" style="display:flex;flex-wrap:wrap;gap:3px;"></div>
    </div>
    <!-- Main builder area -->
    <div class="main-area">
        <div class="canvas-toolbar">
            <button onclick="exportHTML()">Export HTML</button>
            <button onclick="resetCanvas()" style="background:#d32f2f;">Reset Canvas</button>
        </div>
        <div class="canvas-area">
            <div id="canvas" onclick="deselectBlock(event)"></div>
        </div>
    </div>
</div>

<!-- Block Properties Panel -->
<div class="properties-panel" id="propertiesPanel"></div>

<script>
    // Asset library
    let images = [], icons = [];
    // Canvas blocks
    let blocks = [];
    let selectedBlock = null;

    // Add a new block
    function addBlock(type, src = null) {
        let block = {
            id: "b"+Date.now()+"_"+Math.floor(Math.random()*9999),
            type, html: "", props: {}, src,
            imgPos: {x:0,y:0}
        };
        // Default content
        if(type==='header') block.html = "Company Security Notification";
        if(type==='text') block.html = "This is a suspicious login attempt. Please verify.";
        if(type==='footer') block.html = "© 2025 Company. All rights reserved.";
        if(type==='button') block.html = "Verify Now";
        if(type==='signature') block.html = "Best regards,<br>Your IT Team";
        if(type==='divider') block.html = "";
        if(type==='image' && src) block.src = src;
        if(type==='icon' && src) block.src = src;
        blocks.push(block);
        renderCanvas();
    }

    // Render all blocks
    function renderCanvas() {
        let html = "";
        blocks.forEach((b, idx) => {
            let sel = (selectedBlock && selectedBlock.id===b.id) ? "selected":"";
            if(b.type==='header'||b.type==='text'||b.type==='footer'||b.type==='signature'){
                html += `<div class="block ${sel}" onclick="selectBlock(event,'${b.id}')" data-id="${b.id}">
                    <span class="remove-btn" onclick="removeBlock(event,'${b.id}')">&times;</span>
                    <div contenteditable="true" oninput="updateBlockText('${b.id}',this.innerHTML)" style="outline:none;">${b.html}</div>
                </div>`;
            } else if(b.type==='button'){
                html += `<div class="block ${sel}" onclick="selectBlock(event,'${b.id}')" data-id="${b.id}">
                    <span class="remove-btn" onclick="removeBlock(event,'${b.id}')">&times;</span>
                    <button style="font-size:1em; cursor:pointer;width:100%">${b.html}</button>
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
                    <img src="${b.src}" class="block-img draggable-img" style="position:relative; left:${b.imgPos.x}px; top:${b.imgPos.y}px;" 
                         draggable="false" onmousedown="startImgDrag(event, '${b.id}')">
                </div>`;
            } else if(b.type==='icon'){
                html += `<div class="block ${sel}" onclick="selectBlock(event,'${b.id}')" data-id="${b.id}">
                    <span class="remove-btn" onclick="removeBlock(event,'${b.id}')">&times;</span>
                    <img src="${b.src}" class="block-icon draggable-img" style="position:relative; left:${b.imgPos.x}px; top:${b.imgPos.y}px;"
                         draggable="false" onmousedown="startImgDrag(event, '${b.id}')">
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

    // Remove block
    function removeBlock(event, id) {
        event.stopPropagation();
        blocks = blocks.filter(x=>x.id!==id);
        if(selectedBlock && selectedBlock.id===id) selectedBlock = null;
        renderCanvas();
        showPropertiesPanel();
    }

    // Drag & drop reordering with SortableJS
    function enableSortable(){
        if(window.sortableInstance) window.sortableInstance.destroy();
        window.sortableInstance = new Sortable(document.getElementById('canvas'), {
            animation: 150,
            handle: '.block', // blocks themselves
            onEnd: function(evt){
                const oldIndex = evt.oldIndex;
                const newIndex = evt.newIndex;
                if (oldIndex !== newIndex) {
                    const moved = blocks.splice(oldIndex, 1)[0];
                    blocks.splice(newIndex, 0, moved);
                    renderCanvas();
                    showPropertiesPanel();
                }
            }
        });
    }

    // Select block
    function selectBlock(e, id) {
        e.stopPropagation();
        selectedBlock = blocks.find(x=>x.id===id);
        renderCanvas();
        showPropertiesPanel();
    }
    function deselectBlock(e){
        if(e.target.id==="canvas"){selectedBlock=null;renderCanvas();showPropertiesPanel();}
    }

    // Block Properties Panel
    function showPropertiesPanel(){
        let panel = document.getElementById("propertiesPanel");
        if(!selectedBlock){ panel.classList.remove("active"); panel.innerHTML=""; return;}
        let html = `<b>Block Properties</b><br>`;
        if(selectedBlock.type==='button'){
            html+=`<label>Button Text</label>
            <input type="text" value="${selectedBlock.html.replace(/"/g,"&quot;")}" 
                onchange="updateBlockProp('html',this.value)">`;
        }
        if(selectedBlock.type==='header'||selectedBlock.type==='text'||selectedBlock.type==='footer'||selectedBlock.type==='signature'){
            html+=`<label>Text</label>
            <textarea rows="3" onchange="updateBlockProp('html',this.value)">${selectedBlock.html.replace(/</g,"&lt;")}</textarea>`;
        }
        if(selectedBlock.type==='image'||selectedBlock.type==='icon'){
            html+=`<label>Image URL</label>
            <input type="text" value="${selectedBlock.src}" onchange="updateBlockProp('src',this.value)">`;
            html+=`<button style="margin-top:8px;" onclick="openCropper('${selectedBlock.id}')">Crop Image</button>`;
            html+=`<label>Position X (px)</label>
            <input type="number" value="${selectedBlock.imgPos.x}" onchange="updateImgPos('${selectedBlock.id}', 'x', this.value)">
            <label>Position Y (px)</label>
            <input type="number" value="${selectedBlock.imgPos.y}" onchange="updateImgPos('${selectedBlock.id}', 'y', this.value)">`;
        }
        html+= `<button style="margin-top:10px; background:#1976d2;color:#fff;" onclick="deselectBlock(event)">Close</button>`;
        panel.innerHTML = html;
        panel.classList.add("active");
    }
    function updateBlockProp(prop, val){
        if(!selectedBlock)return;
        selectedBlock[prop]=val;
        renderCanvas();
        showPropertiesPanel();
    }
    function updateImgPos(id, axis, val){
        let b = blocks.find(x=>x.id===id);
        if(!b) return;
        b.imgPos[axis] = parseInt(val)||0;
        renderCanvas();
        showPropertiesPanel();
    }

    // Upload image/icon asset
    function uploadAsset(event, type){
        const file = event.target.files[0];
        if(!file) return;
        const reader = new FileReader();
        reader.onload = function(e){
            const url = e.target.result;
            if(type==='image'){ images.push(url); renderAssets('image'); addBlock('image', url);}
            if(type==='icon'){ icons.push(url); renderAssets('icon'); addBlock('icon', url);}
        };
        reader.readAsDataURL(file);
    }

    // Render asset thumbs
    function renderAssets(type){
        let el = type==='image' ? document.getElementById('imageAssets') : document.getElementById('iconAssets');
        let arr = type==='image' ? images : icons;
        el.innerHTML = "";
        arr.forEach(url=>{
            el.innerHTML += `<img src="${url}" class="asset-thumb" onclick="addBlock('${type}','${url}')">`;
        });
    }

    // Export HTML
    function exportHTML(){
        let html = "";
        blocks.forEach(b=>{
            if(b.type==='header') html+=`<h2>${b.html}</h2>\n`;
            if(b.type==='text') html+=`<p>${b.html}</p>\n`;
            if(b.type==='footer') html+=`<footer>${b.html}</footer>\n`;
            if(b.type==='signature') html+=`<div style="font-style:italic">${b.html}</div>\n`;
            if(b.type==='button') html+=`<a href="#" style="display:inline-block;padding:12px 28px;background:#1976d2;color:#fff;border-radius:6px;text-decoration:none;font-weight:bold;">${b.html}</a>\n`;
            if(b.type==='divider') html+=`<hr>\n`;
            if(b.type==='image') html+=`<img src="${b.src}" style="max-width:100%;position:relative;left:${b.imgPos.x}px;top:${b.imgPos.y}px;">\n`;
            if(b.type==='icon') html+=`<img src="${b.src}" style="max-width:50px;vertical-align:middle;position:relative;left:${b.imgPos.x}px;top:${b.imgPos.y}px;">\n`;
        });
        // Show modal/download dialog or copy to clipboard
        let w = window.open("", "", "width=700,height=600");
        w.document.write(`<pre style="white-space:pre-wrap;word-break:break-all;background:#f8f8fa;padding:16px 12px;">${html.replace(/</g,"&lt;")}</pre>`);
        w.document.title = "Exported HTML";
    }

    // Reset Canvas
    function resetCanvas(){
        if(confirm("Clear all blocks from the canvas?")){
            blocks = [];
            selectedBlock = null;
            renderCanvas();
            showPropertiesPanel();
        }
    }

    // Demo assets for icon library (add more as needed)
    icons = [
        "https://upload.wikimedia.org/wikipedia/commons/4/44/Microsoft_logo.svg",
        "https://upload.wikimedia.org/wikipedia/commons/5/51/Apple_logo_black.svg",
        "https://upload.wikimedia.org/wikipedia/commons/2/2f/Google_2015_logo.svg"
    ];
    window.onload = function(){
        renderAssets('icon');
    };

    // Image dragging (move within block)
    let dragImgBlock = null, dragImgStart = {x:0, y:0}, dragImgInit = {x:0, y:0};
    document.addEventListener('mousemove', function(e){
        if(!dragImgBlock) return;
        let dx = e.clientX - dragImgStart.x;
        let dy = e.clientY - dragImgStart.y;
        dragImgBlock.imgPos.x = dragImgInit.x + dx;
        dragImgBlock.imgPos.y = dragImgInit.y + dy;
        renderCanvas();
        showPropertiesPanel();
    });
    document.addEventListener('mouseup', function(e){
        dragImgBlock = null;
    });
    function startImgDrag(e, id){
        e.stopPropagation();
        dragImgBlock = blocks.find(x=>x.id===id);
        dragImgStart = {x: e.clientX, y: e.clientY};
        dragImgInit = {x: dragImgBlock.imgPos.x, y: dragImgBlock.imgPos.y};
        // Prevent image dragging default
        e.preventDefault();
    }

    // Image cropping with Cropper.js (in popup)
    function openCropper(blockId) {
        let b = blocks.find(x=>x.id===blockId);
        if (!b) return;
        let cropModal = window.open("", "", "width=900,height=600");
        cropModal.document.write(`
            <html><head>
            <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet"/>
            </head><body style="margin:0;">
            <img id="cropImg" src="${b.src}" style="max-width:100%;max-height:500px;display:block;margin:auto;"/>
            <div style="text-align:center;margin-top:18px;">
            <button id="cropBtn" style="font-size:1.2em;background:#1976d2;color:#fff;padding:7px 22px;border:none;border-radius:5px;">Crop & Save</button>
            </div>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
            <script>
                var image = document.getElementById('cropImg');
                var cropper = new Cropper(image, { aspectRatio: NaN });
                document.getElementById('cropBtn').onclick = function() {
                    var croppedDataUrl = cropper.getCroppedCanvas().toDataURL();
                    window.opener.postMessage({type:'cropped', id:'${blockId}', url:croppedDataUrl}, '*');
                    window.close();
                }
            <\/script>
            </body></html>
        `);
    }
    window.addEventListener('message', function(event){
        if(event.data.type==='cropped'){
            let b = blocks.find(x=>x.id===event.data.id);
            if(b){ b.src = event.data.url; renderCanvas(); showPropertiesPanel(); }
        }
    });

    // Expose functions to global scope for HTML onclick
    window.addBlock = addBlock;
    window.removeBlock = removeBlock;
    window.selectBlock = selectBlock;
    window.deselectBlock = deselectBlock;
    window.updateBlockText = updateBlockText;
    window.updateBlockProp = updateBlockProp;
    window.updateImgPos = updateImgPos;
    window.uploadAsset = uploadAsset;
    window.startImgDrag = startImgDrag;
    window.openCropper = openCropper;
</script>
</body>
</html>