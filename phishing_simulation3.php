<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Drag & Drop Blocks</title>
<style>
  html, body {
    margin: 0; padding: 0; height: 100%;
    font-family: Arial, sans-serif;
  }
  body {
    display: flex;
    height: 100vh;
    overflow: hidden;
  }
  #toolbox {
    width: 180px;
    background: #f0f0f0;
    border-right: 1px solid #ccc;
    padding: 15px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  #toolbox button {
    padding: 10px;
    font-size: 14px;
    cursor: pointer;
    border: 1px solid #aaa;
    background: white;
    border-radius: 4px;
    user-select: none;
    transition: background-color 0.2s ease;
  }
  #toolbox button:hover {
    background-color: #ddd;
  }
  #builderContainer {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    background: #fafafa;
    overflow: auto;
    padding: 10px;
  }
  #builder {
    position: relative;
    width: 800px;
    height: 600px;
    border: 2px dashed #aaa;
    background: white;
    box-shadow: 0 0 8px rgba(0,0,0,0.1);
    overflow: hidden;
  }
  #formatToolbar {
    width: 220px;
    background: #f9f9f9;
    border-left: 1px solid #ccc;
    padding: 15px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .block {
    position: absolute;
    border: 1px solid #ccc;
    background: white;
    padding: 8px;
    box-sizing: border-box;
    cursor: move;
    min-width: 50px;
    min-height: 30px;
    user-select: text;
    border-radius: 3px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  }
  .block[contenteditable="true"]:focus {
    outline: 2px solid #4a90e2;
  }
  .delete-btn {
    position: absolute;
    top: 2px;
    right: 4px;
    font-size: 14px;
    color: red;
    cursor: pointer;
    z-index: 10;
  }
  .resize-handle {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 10px;
    height: 10px;
    cursor: se-resize;
    background: #ccc;
    z-index: 10;
  }
  .block img {
    max-width: 100%;
    height: auto;
    display: block;
  }
</style>
</head>
<body>

<div id="toolbox" aria-label="Toolbox">
  <button data-type="header">Add Header</button>
  <button data-type="text">Add Text</button>
  <button data-type="button">Add Button</button>
  <button data-type="footer">Add Footer</button>
  <button data-type="signature">Add Signature</button>
  <button data-type="image">Add Image</button>
  <button id="resetCanvas">Reset Canvas</button>
</div>

<div id="builderContainer">
  <div id="builder" tabindex="0" aria-label="Canvas"></div>
</div>

<div id="formatToolbar" aria-label="Formatting Options">
  <label>Font Family
    <select id="fontFamily">
      <option value="Arial, sans-serif">Arial</option>
      <option value="'Times New Roman', serif">Times New Roman</option>
      <option value="'Courier New', monospace">Courier New</option>
    </select>
  </label>
  <label>Font Size
    <select id="fontSize">
      <option value="12px">12</option>
      <option value="14px" selected>14</option>
      <option value="16px">16</option>
      <option value="18px">18</option>
      <option value="20px">20</option>
    </select>
  </label>
  <div>
    <button id="boldBtn"><b>B</b></button>
    <button id="italicBtn"><i>I</i></button>
    <button id="underlineBtn"><u>U</u></button>
  </div>
  <label>Font Color
    <input type="color" id="fontColor" value="#000000" />
  </label>
</div>

<script>
  const builder = document.getElementById('builder');
  const resetCanvasBtn = document.getElementById('resetCanvas');
  let selectedBlock = null;

  function createBlock(type) {
    const block = document.createElement('div');
    block.className = 'block';
    block.style.top = '10px';
    block.style.left = '10px';
    block.style.width = '200px';
    block.style.height = '50px';
    block.setAttribute('tabindex', '0');

    if (type === 'text') {
      block.contentEditable = true;
      block.textContent = 'Editable Text';
    } else if (type === 'header') {
      block.contentEditable = true;
      block.style.fontWeight = 'bold';
      block.style.fontSize = '24px';
      block.textContent = 'Header Text';
    } else if (type === 'footer') {
      block.contentEditable = true;
      block.style.fontSize = '12px';
      block.style.color = '#666';
      block.textContent = 'Footer Content';
    } else if (type === 'signature') {
      block.contentEditable = true;
      block.style.fontStyle = 'italic';
      block.style.whiteSpace = 'pre-wrap';
      block.textContent = 'Best Regards,\nYour Name';
    } else if (type === 'button') {
      block.contentEditable = false;
      const btn = document.createElement('button');
      btn.textContent = 'Click Me';
      btn.style.width = '100%';
      btn.style.height = '100%';
      btn.style.cursor = 'pointer';
      block.appendChild(btn);
    } else if (type === 'image') {
      block.contentEditable = false;
      const img = document.createElement('img');
      img.src = '';
      img.alt = 'Uploaded Image';

      const input = document.createElement('input');
      input.type = 'file';
      input.accept = 'image/*';
      input.style.display = 'none';

      input.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (e) {
          img.src = e.target.result;
        };
        reader.readAsDataURL(file);
      });

      block.addEventListener('dblclick', () => input.click());
      block.appendChild(img);
      block.appendChild(input);
      block.style.height = 'auto';
    }

    addDeleteButton(block);
    addResizeHandle(block);
    builder.appendChild(block);
    makeDraggable(block);
    selectBlock(block);
    return block;
  }

  function addDeleteButton(block) {
    const btn = document.createElement('span');
    btn.className = 'delete-btn';
    btn.textContent = '✖';
    btn.title = 'Delete';
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      builder.removeChild(block);
      if (selectedBlock === block) {
        selectedBlock = null;
        clearFormatToolbar();
      }
    });
    block.appendChild(btn);
  }

  function addResizeHandle(block) {
    const handle = document.createElement('div');
    handle.className = 'resize-handle';
    block.appendChild(handle);

    handle.addEventListener('mousedown', (e) => {
      e.preventDefault();
      e.stopPropagation();

      const startX = e.clientX;
      const startY = e.clientY;
      const startWidth = block.offsetWidth;
      const startHeight = block.offsetHeight;

      function onMove(ev) {
        block.style.width = startWidth + (ev.clientX - startX) + 'px';
        block.style.height = startHeight + (ev.clientY - startY) + 'px';
      }

      function onStop() {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onStop);
      }

      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onStop);
    });
  }

function makeDraggable(el) {
    let pos = { left: 0, top: 0, x: 0, y: 0 };

    const mouseDownHandler = function(e) {
      e.preventDefault();

      pos = {
        left: el.offsetLeft,
        top: el.offsetTop,
        x: e.clientX,
        y: e.clientY,
      };

      document.addEventListener('mousemove', mouseMoveHandler);
      document.addEventListener('mouseup', mouseUpHandler);

      selectBlock(el);
    };

    const mouseMoveHandler = function(e) {
      e.preventDefault();

      const dx = e.clientX - pos.x;
      const dy = e.clientY - pos.y;

      let newLeft = pos.left + dx;
      let newTop = pos.top + dy;

      // Keep inside builder boundaries
      newLeft = Math.max(0, Math.min(newLeft, builder.clientWidth - el.offsetWidth));
      newTop = Math.max(0, Math.min(newTop, builder.clientHeight - el.offsetHeight));

      el.style.left = newLeft + 'px';
      el.style.top = newTop + 'px';
    };

    const mouseUpHandler = function(e) {
      document.removeEventListener('mousemove', mouseMoveHandler);
      document.removeEventListener('mouseup', mouseUpHandler);
    };

    el.addEventListener('mousedown', mouseDownHandler);
  }

  function selectBlock(block) {
    if (selectedBlock) selectedBlock.style.outline = 'none';
    selectedBlock = block;
    if (block) {
      block.style.outline = '2px solid #4a90e2';
      updateFormatToolbar(block);
    } else {
      clearFormatToolbar();
    }
  }

  function updateFormatToolbar(block) {
    if (!block) return;
    const computed = window.getComputedStyle(block);
    document.getElementById('fontFamily').value = computed.fontFamily.replace(/['"]/g, '');
    document.getElementById('fontSize').value = computed.fontSize;
    document.getElementById('fontColor').value = rgbToHex(computed.color);
    document.getElementById('boldBtn').classList.toggle('active', computed.fontWeight >= 700);
    document.getElementById('italicBtn').classList.toggle('active', computed.fontStyle === 'italic');
    document.getElementById('underlineBtn').classList.toggle('active', computed.textDecorationLine.includes('underline'));
  }

  function clearFormatToolbar() {
    document.getElementById('fontFamily').value = '';
    document.getElementById('fontSize').value = '';
    document.getElementById('fontColor').value = '#000000';
    document.getElementById('boldBtn').classList.remove('active');
    document.getElementById('italicBtn').classList.remove('active');
    document.getElementById('underlineBtn').classList.remove('active');
  }

  function rgbToHex(rgb) {
    const result = /^rgba?\((\d+),\s*(\d+),\s*(\d+)/i.exec(rgb);
    return result ? "#" + ((1 << 24) + (+result[1] << 16) + (+result[2] << 8) + +result[3]).toString(16).slice(1) : '#000000';
  }

  document.getElementById('toolbox').addEventListener('click', e => {
    if (e.target.tagName !== 'BUTTON') return;
    const type = e.target.getAttribute('data-type');
    if (!type) return;
    createBlock(type);
  });

  resetCanvasBtn.addEventListener('click', () => {
    builder.innerHTML = '';
    selectedBlock = null;
    clearFormatToolbar();
  });

  document.getElementById('fontFamily').addEventListener('change', () => {
    if (selectedBlock) selectedBlock.style.fontFamily = document.getElementById('fontFamily').value;
  });
  document.getElementById('fontSize').addEventListener('change', () => {
    if (selectedBlock) selectedBlock.style.fontSize = document.getElementById('fontSize').value;
  });
  document.getElementById('fontColor').addEventListener('input', () => {
    if (selectedBlock) selectedBlock.style.color = document.getElementById('fontColor').value;
  });

  document.getElementById('boldBtn').addEventListener('click', () => {
    if (!selectedBlock || selectedBlock.contentEditable === 'false') return;
    const btn = document.getElementById('boldBtn');
    btn.classList.toggle('active');
    selectedBlock.style.fontWeight = btn.classList.contains('active') ? '700' : '400';
  });

  document.getElementById('italicBtn').addEventListener('click', () => {
    if (!selectedBlock || selectedBlock.contentEditable === 'false') return;
    const btn = document.getElementById('italicBtn');
    btn.classList.toggle('active');
    selectedBlock.style.fontStyle = btn.classList.contains('active') ? 'italic' : 'normal';
  });

  document.getElementById('underlineBtn').addEventListener('click', () => {
    if (!selectedBlock || selectedBlock.contentEditable === 'false') return;
    const btn = document.getElementById('underlineBtn');
    btn.classList.toggle('active');
    selectedBlock.style.textDecoration = btn.classList.contains('active') ? 'underline' : 'none';
  });

  builder.addEventListener('click', e => {
    let block = e.target.closest('.block');
    if (block) selectBlock(block);
    else selectBlock(null);
  });
  // Delete selected block with Delete key
document.addEventListener('keydown', function (e) {
  if (e.key === 'Delete' && selectedBlock && builder.contains(selectedBlock)) {
    builder.removeChild(selectedBlock);
    selectedBlock = null;
    clearFormatToolbar();
  }
});
</script>

</body>
</html>
