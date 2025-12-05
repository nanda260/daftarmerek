// BOOTSTRAP ALERT & CONFIRM MODALS
function showAlert(message, type = 'warning') {
    const icon = type === 'danger' ? '❌' : type === 'success' ? '✅' : '⚠️';

    const alertModal = `
    <div class="modal fade" id="alertModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
          <div class="modal-body text-center p-4">
            <div class="fs-1 mb-3">${icon}</div>
            <p class="mb-0" style="white-space: pre-line;">${message}</p>
          </div>
          <div class="modal-footer border-0 justify-content-center">
            <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
          </div>
        </div>
      </div>
    </div>
  `;

    const existingModal = document.getElementById('alertModal');
    if (existingModal) existingModal.remove();

    document.body.insertAdjacentHTML('beforeend', alertModal);
    const modal = new bootstrap.Modal(document.getElementById('alertModal'));
    modal.show();

    document.getElementById('alertModal').addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
}

function showConfirm(message, onConfirm, onCancel = null) {
    const confirmModal = `
    <div class="modal fade" id="confirmModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-body text-center p-4">
            <div class="fs-1 mb-3">⚠️</div>
            <p class="mb-0" style="white-space: pre-line;">${message}</p>
          </div>
          <div class="modal-footer border-0 justify-content-center gap-2">
            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal" id="btnModalCancel">Batal</button>
            <button type="button" class="btn btn-primary px-4" id="btnModalConfirm">Ya, Lanjutkan</button>
          </div>
        </div>
      </div>
    </div>
  `;

    const existingModal = document.getElementById('confirmModal');
    if (existingModal) existingModal.remove();

    document.body.insertAdjacentHTML('beforeend', confirmModal);
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    modal.show();

    document.getElementById('btnModalConfirm').addEventListener('click', function () {
        modal.hide();
        if (onConfirm) onConfirm();
    });

    document.getElementById('btnModalCancel').addEventListener('click', function () {
        if (onCancel) onCancel();
    });

    document.getElementById('confirmModal').addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
}

// TABEL PRODUK
document.getElementById('addProdukRow').addEventListener('click', function () {
    const tbody = document.getElementById('produkTableBody');
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td><input type="text" class="form-control form-control-sm produk-nama" required></td>
        <td><input type="number" class="form-control form-control-sm produk-jumlah" min="1" required></td>
        <td><input type="number" class="form-control form-control-sm produk-harga" min="0" required></td>
        <td><input type="text" class="form-control form-control-sm produk-omset bg-light" readonly value="Rp 0"></td>
        <td class="text-center">
            <button type="button" class="btn btn-danger btn-sm remove-produk">
                <i class="fas fa-times"></i>
            </button>
        </td>
    `;
    tbody.appendChild(newRow);
    updateRemoveButtons();
    attachCalculationListeners();
});

document.getElementById('produkTableBody').addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-produk') || e.target.parentElement.classList.contains('remove-produk')) {
        const button = e.target.classList.contains('remove-produk') ? e.target : e.target.parentElement;
        button.closest('tr').remove();
        updateRemoveButtons();
        calculateTotalOmset();
    }
});

function updateRemoveButtons() {
    const rows = document.querySelectorAll('#produkTableBody tr');
    rows.forEach((row) => {
        const removeBtn = row.querySelector('.remove-produk');
        removeBtn.disabled = rows.length === 1;
    });
}

function calculateRowOmset(row) {
    const jumlah = parseFloat(row.querySelector('.produk-jumlah').value) || 0;
    const harga = parseFloat(row.querySelector('.produk-harga').value) || 0;
    const omset = jumlah * harga;
    row.querySelector('.produk-omset').value = 'Rp ' + omset.toLocaleString('id-ID');
    calculateTotalOmset();
}

function calculateTotalOmset() {
    const rows = document.querySelectorAll('#produkTableBody tr');
    let total = 0;
    rows.forEach(row => {
        const jumlah = parseFloat(row.querySelector('.produk-jumlah').value) || 0;
        const harga = parseFloat(row.querySelector('.produk-harga').value) || 0;
        total += (jumlah * harga);
    });
    document.getElementById('totalOmset').textContent = 'Rp ' + total.toLocaleString('id-ID');
}

function attachCalculationListeners() {
    const rows = document.querySelectorAll('#produkTableBody tr');
    rows.forEach(row => {
        const jumlahInput = row.querySelector('.produk-jumlah');
        const hargaInput = row.querySelector('.produk-harga');
        jumlahInput.removeEventListener('input', () => calculateRowOmset(row));
        hargaInput.removeEventListener('input', () => calculateRowOmset(row));
        jumlahInput.addEventListener('input', () => calculateRowOmset(row));
        hargaInput.addEventListener('input', () => calculateRowOmset(row));
    });
}

attachCalculationListeners();

// RT/RW FORMATTING
const rtRwInput = document.getElementById('rt_rw');
Inputmask("999/999", {
    placeholder: "___/___",
    clearMaskOnLostFocus: false
}).mask(rtRwInput);

document.getElementById('rt_rw').addEventListener('blur', function () {
    let value = this.value.trim();
    if (!value) return;

    let parts = value.split('/');
    let rt = parts[0] ? parts[0].replace(/\D/g, '') : '';
    let rw = parts[1] ? parts[1].replace(/\D/g, '') : '';

    if (rt) {
        rt = rt.substring(0, 3).padStart(3, '0');
        rw = rw ? rw.substring(0, 3).padStart(3, '0') : '001';
        this.value = rt + '/' + rw;
    } else {
        this.value = '';
    }
});

// MODAL PREVIEW
function createPreviewModal() {
    if (document.getElementById('filePreviewModal')) {
        return;
    }

    const modalHTML = `
        <div class="modal fade" id="filePreviewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 1000px;">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h6 class="modal-title mb-0" id="modalFileName">Preview File</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-2" style="height: 70vh; overflow: auto; background: #f8f9fa;">
                        <div id="modalPreviewContent" class="d-flex align-items-center justify-content-center h-100 p-2">
                            <!-- Content will be inserted here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function showFilePreview(file, fileName) {
    createPreviewModal();

    const modal = new bootstrap.Modal(document.getElementById('filePreviewModal'));
    const modalFileName = document.getElementById('modalFileName');
    const modalContent = document.getElementById('modalPreviewContent');

    modalFileName.textContent = fileName;
    modalContent.innerHTML = '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>';

    modal.show();

    if (file.type === 'application/pdf') {
        const fileURL = URL.createObjectURL(file);
        modalContent.innerHTML = `
            <iframe src="${fileURL}" 
                    class="w-100 h-100 border rounded">
            </iframe>
        `;
    } else if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function (e) {
            modalContent.innerHTML = `
                <img src="${e.target.result}" 
                     alt="${fileName}" 
                     class="img-fluid rounded" 
                     style="max-width: 100%; max-height: 100%; object-fit: contain;">
            `;
        };
        reader.readAsDataURL(file);
    } else {
        modalContent.innerHTML = `
            <div class="alert alert-warning mb-0 small">
                <i class="fas fa-exclamation-triangle me-1"></i>
                Preview tidak tersedia
            </div>
        `;
    }
}

// FILE STORAGE
const fileStorage = {
    'nib_files': [],
    'foto_produk': [],
    'foto_proses': [],
    'akta_files': [],
    'logo1': [],
    'logo2': [],
    'logo3': []
};

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function handleFileInput(inputElement, storageKey, previewContainerId, maxFiles = 5) {
    const files = Array.from(inputElement.files);
    if (fileStorage[storageKey].length + files.length > maxFiles) {
        showAlert(`Maksimal ${maxFiles} file untuk ${storageKey.replace(/_/g, ' ')}`);
        inputElement.value = '';
        return;
    }
    files.forEach(file => {
        const exists = fileStorage[storageKey].some(f => f.name === file.name && f.size === file.size);
        if (!exists) {
            fileStorage[storageKey].push(file);
        }
    });
    updateFilePreview(storageKey, previewContainerId);
    inputElement.value = '';
}

function updateFilePreview(storageKey, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = '';

    if (fileStorage[storageKey].length === 0) {
        container.style.display = 'none';
        return;
    }

    container.style.display = 'flex';

    fileStorage[storageKey].forEach((file, index) => {
        const previewItem = document.createElement('div');
        previewItem.className = 'preview-item';
        previewItem.style.cssText = 'position: relative; width: 120px; margin: 5px; cursor: pointer;';

        previewItem.addEventListener('click', function (e) {
            if (!e.target.closest('.remove-preview')) {
                showFilePreview(file, file.name);
            }
        });
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                previewItem.innerHTML = `
                    <img src="${e.target.result}" style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px; border: 2px solid #dee2e6; transition: transform 0.2s;">
                    <button type="button" onclick="event.stopPropagation(); removeFile('${storageKey}', ${index}, '${containerId}')" class="remove-preview">×</button>
                    <div style="font-size: 11px; margin-top: 5px; text-align: center; word-break: break-word; color: #495057;">${file.name}</div>
                    <div style="font-size: 10px; text-align: center; color: #6c757d;">${formatFileSize(file.size)}</div>
                `;
                const img = previewItem.querySelector('img');
                previewItem.addEventListener('mouseenter', () => {
                    img.style.transform = 'scale(1.05)';
                });
                previewItem.addEventListener('mouseleave', () => {
                    img.style.transform = 'scale(1)';
                });
            };
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
            previewItem.innerHTML = `
                <div class="pdf-preview" style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 120px; height: 120px; border-radius: 8px; border: 2px solid #dee2e6; background: #fff; transition: transform 0.2s;">
                    <i class="fas fa-file-pdf" style="font-size: 3rem; color: #dc3545; margin-bottom: 8px;"></i>
                    <div style="font-size: 10px; text-align: center; padding: 0 5px; color: #495057;">PDF</div>
                </div>
                <button type="button" onclick="event.stopPropagation(); removeFile('${storageKey}', ${index}, '${containerId}')" class="remove-preview">×</button>
                <div style="font-size: 11px; margin-top: 5px; text-align: center; word-break: break-word; color: #495057;">${file.name}</div>
                <div style="font-size: 10px; text-align: center; color: #6c757d;">${formatFileSize(file.size)}</div>
            `;
            const pdfPreview = previewItem.querySelector('.pdf-preview');
            previewItem.addEventListener('mouseenter', () => {
                pdfPreview.style.transform = 'scale(1.05)';
            });
            previewItem.addEventListener('mouseleave', () => {
                pdfPreview.style.transform = 'scale(1)';
            });
        }
        container.appendChild(previewItem);
    });
}

function removeFile(storageKey, index, containerId) {
    fileStorage[storageKey].splice(index, 1);
    updateFilePreview(storageKey, containerId);
}

function setupDragDropWithStorage(dropZone, fileInput, previewContainer, storageKey, maxFiles = 5, maxSizeMB = 1) {
    if (!dropZone || !fileInput) return;

    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('drag-over');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');

        const dt = new DataTransfer();
        const droppedFiles = Array.from(e.dataTransfer.files);

        if (fileStorage[storageKey].length + droppedFiles.length > maxFiles) {
            showAlert(`Maksimal ${maxFiles} file. Anda sudah memiliki ${fileStorage[storageKey].length} file.`);
            return;
        }
        for (let file of droppedFiles) {
            if (file.size > maxSizeMB * 1024 * 1024) {
                showAlert(`File ${file.name} melebihi ${maxSizeMB} MB.`, 'danger');
                return;
            }
            dt.items.add(file);
        }

        fileInput.files = dt.files;
        handleFileInput(fileInput, storageKey, previewContainer.id, maxFiles);
    });

    fileInput.addEventListener('change', () => {
        const files = Array.from(fileInput.files);

        if (fileStorage[storageKey].length + files.length > maxFiles) {
            showAlert(`Maksimal ${maxFiles} file. Anda sudah memiliki ${fileStorage[storageKey].length} file.`);
            fileInput.value = '';
            return;
        }
        for (let file of files) {
            if (file.size > maxSizeMB * 1024 * 1024) {
                showAlert(`File ${file.name} melebihi ${maxSizeMB} MB.`, 'danger');
                fileInput.value = '';
                return;
            }
        }
        handleFileInput(fileInput, storageKey, previewContainer.id, maxFiles);
    });
}

// LEGALITAS
const legalitasFiles = [];

function setupLegalitasDragDrop() {
    const dropZone = document.getElementById('legalitasDropZone');
    const fileInput = document.getElementById('legalitas-file-input');

    if (!dropZone || !fileInput) return;

    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('drag-over');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        handleLegalitasFiles(Array.from(e.dataTransfer.files));
    });
    fileInput.addEventListener('change', (e) => {
        handleLegalitasFiles(Array.from(e.target.files));
        fileInput.value = '';
    });
}

function handleLegalitasFiles(files) {
    files.forEach(file => {
        if (file.size > 1 * 1024 * 1024) {
            showAlert(`File ${file.name} melebihi 1 MB.`, 'danger');
            return;
        }
        const validExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        const ext = file.name.split('.').pop().toLowerCase();
        if (!validExtensions.includes(ext)) {
            showAlert(`File ${file.name} memiliki format tidak valid. Gunakan PDF, JPG, atau PNG.`, 'danger');
            return;
        }

        const isDuplicate = legalitasFiles.some(f =>
            f.file.name === file.name && f.file.size === file.size
        );

        if (isDuplicate) {
            showAlert(`File ${file.name} sudah diupload.`, 'warning');
            return;
        }
        legalitasFiles.push({
            id: Date.now() + Math.random(),
            file: file,
            jenisFileId: null,
            jenisFileName: null
        });
    });
    renderLegalitasFileList();
}

function renderLegalitasFileList() {
    const container = document.getElementById('legalitasFileList');
    if (legalitasFiles.length === 0) {
        container.innerHTML = '';
        return;
    }
    let html = '<div class="table-responsive mt-3"><table class="table table-bordered table-hover">';
    html += '<thead class="table-light">';
    html += '<tr>';
    html += '<th style="width: 5%">No</th>';
    html += '<th style="width: 35%">Nama File</th>';
    html += '<th style="width: 10%">Ukuran</th>';
    html += '<th style="width: 30%">Jenis Legalitas <span class="text-danger">*</span></th>';
    html += '<th style="width: 10%">Aksi</th>';
    html += '</tr>';
    html += '</thead>';
    html += '<tbody>';
    legalitasFiles.forEach((item, index) => {
        html += `<tr id="legalitas-row-${item.id}">`;
        html += `<td class="text-center">${index + 1}</td>`;
        html += `<td><small>${item.file.name}</small></td>`;
        html += `<td><small>${formatFileSize(item.file.size)}</small></td>`;
        html += `<td>`;
        html += `<select class="form-select form-select-sm legalitas-select" data-file-id="${item.id}" onchange="handleLegalitasSelect(${item.id}, this)" required>`;
        html += `<option value="">Pilih Jenis Legalitas</option>`;

        masterLegalitas.forEach(master => {
            // Filter: hanya tampilkan id_jenis_file yang <= 13
            if (master.id_jenis_file < 13) {
                const selected = item.jenisFileId == master.id_jenis_file ? 'selected' : '';
                html += `<option value="${master.id_jenis_file}" ${selected}>${master.nama_jenis_file}</option>`;
            }
        });

        html += `<option value="custom" ${item.jenisFileId === 'custom' ? 'selected' : ''}>Lainnya (Ketik Sendiri)</option>`;
        html += `</select>`;

        // Text input untuk custom legalitas
        const showCustomInput = item.jenisFileId === 'custom' || item.customName;
        html += `<input type="text" 
                        class="form-control form-control-sm mt-2 custom-legalitas-input" 
                        id="custom-input-${item.id}" 
                        placeholder="Ketik nama legalitas..." 
                        value="${item.customName || ''}"
                        style="display: ${showCustomInput ? 'block' : 'none'};"
                        onchange="updateCustomLegalitas(${item.id}, this.value)">`;

        html += `</td>`;
        html += `<td class="text-center">`;
        html += `<button type="button" class="btn btn-sm btn-info me-1" onclick="previewLegalitasFile(${item.id})">`;
        html += `<i class="fas fa-eye"></i>`;
        html += `</button>`;
        html += `<button type="button" class="btn btn-sm btn-danger" onclick="removeLegalitasFile(${item.id})">`;
        html += `<i class="fas fa-trash"></i>`;
        html += `</button>`;
        html += `</td>`;
        html += `</tr>`;
    });

    html += '</tbody>';
    html += '</table></div>';

    container.innerHTML = html;
}

function handleLegalitasSelect(fileId, selectElement) {
    const value = selectElement.value;
    const customInput = document.getElementById(`custom-input-${fileId}`);

    if (value === 'custom') {
        customInput.style.display = 'block';
        customInput.required = true;

        const item = legalitasFiles.find(f => f.id === fileId);
        if (item) {
            item.jenisFileId = 'custom';
            item.jenisFileName = null;
        }
    } else {
        customInput.style.display = 'none';
        customInput.required = false;
        customInput.value = '';

        selectJenisFile(fileId, value);
    }
}

function updateCustomLegalitas(fileId, customName) {
    const item = legalitasFiles.find(f => f.id === fileId);
    if (!item) return;

    item.customName = customName.trim();
    item.jenisFileName = customName.trim();
}

function previewLegalitasFile(fileId) {
    const item = legalitasFiles.find(f => f.id === fileId);
    if (!item) return;

    showFilePreview(item.file, item.file.name);
}

function selectJenisFile(fileId, jenisFileId) {
    const item = legalitasFiles.find(f => f.id === fileId);
    if (!item) return;

    item.jenisFileId = jenisFileId;

    const master = masterLegalitas.find(m => m.id_jenis_file == jenisFileId);
    item.jenisFileName = master ? master.nama_jenis_file : null;
}

function removeLegalitasFile(fileId) {
    const index = legalitasFiles.findIndex(f => f.id === fileId);
    if (index > -1) {
        legalitasFiles.splice(index, 1);
        renderLegalitasFileList();
    }
}

function prepareLegalitasForSubmission() {
    const hiddenContainer = document.getElementById('legalitasHiddenInputs');
    hiddenContainer.innerHTML = '';

    for (let item of legalitasFiles) {
        if (!item.jenisFileId) {
            if (item.jenisFileId === 'custom' && !item.customName) {
                showAlert('Semua file legalitas harus memilih jenis legalitasnya!\nUntuk "Lainnya", mohon ketik nama legalitas.', 'danger');
                return false;
            }
        }
    }

    // Group files dan siapkan data custom
    const groupedFiles = {};
    const customFiles = [];

    legalitasFiles.forEach(item => {
        if (item.jenisFileId === 'custom') {
            customFiles.push({
                file: item.file,
                customName: item.customName
            });
        } else {
            if (!groupedFiles[item.jenisFileId]) {
                groupedFiles[item.jenisFileId] = [];
            }
            groupedFiles[item.jenisFileId].push(item.file);
        }
    });

    Object.keys(groupedFiles).forEach(jenisFileId => {
        const dataTransfer = new DataTransfer();

        groupedFiles[jenisFileId].forEach(file => {
            dataTransfer.items.add(file);
        });

        const input = document.createElement('input');
        input.type = 'file';
        input.name = `legalitas_files_${jenisFileId}[]`;
        input.multiple = true;
        input.files = dataTransfer.files;

        hiddenContainer.appendChild(input);
    });

    // Handle custom legalitas files
    customFiles.forEach((item, index) => {
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(item.file);

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.name = `custom_legalitas_file_${index}`;
        fileInput.files = dataTransfer.files;

        const nameInput = document.createElement('input');
        nameInput.type = 'hidden';
        nameInput.name = `custom_legalitas_name_${index}`;
        nameInput.value = item.customName;

        hiddenContainer.appendChild(fileInput);
        hiddenContainer.appendChild(nameInput);
    });


    return true;
}

// FORM SUBMISSION
function handleFormSubmit(e) {
    e.preventDefault();

    console.log('=== FORM SUBMISSION STARTED ===');

    // 0. Validasi jenis pemohon
    const jenisPemohon = document.querySelector('input[name="jenis_pemohon"]:checked').value;

    if (jenisPemohon === 'perusahaan') {

        if (fileStorage['akta_files'].length === 0) {
            showAlert('Akta Pendirian CV/PT wajib diupload untuk jenis pemohon Perusahaan!');
            return false;
        }
    }

    // 1. Validasi produk
    const rows = document.querySelectorAll('#produkTableBody tr');
    const produkData = [];

    rows.forEach(row => {
        const nama = row.querySelector('.produk-nama').value.trim();
        const jumlah = parseInt(row.querySelector('.produk-jumlah').value) || 0;
        const harga = parseInt(row.querySelector('.produk-harga').value) || 0;
        if (nama && jumlah > 0 && harga > 0) {
            produkData.push({
                nama,
                jumlah,
                harga,
                omset: jumlah * harga,
                kapasitas: jumlah + ' unit'
            });
        }
    });
    if (produkData.length === 0) {
        showAlert('Mohon isi minimal 1 data produk dengan lengkap!');
        return false;
    }

    document.getElementById('produkData').value = JSON.stringify(produkData);

    // 2. Validasi file wajib
    const requiredFiles = {
        'nib_files': 'File NIB',
        'foto_produk': 'Foto Produk',
        'foto_proses': 'Foto Proses Produksi',
        'logo1': 'Logo Merek Alternatif 1',
        'logo2': 'Logo Merek Alternatif 2',
        'logo3': 'Logo Merek Alternatif 3'
    };
    for (let [key, label] of Object.entries(requiredFiles)) {
        if (!fileStorage[key] || fileStorage[key].length === 0) {
            showAlert(`${label} wajib diupload!`);
            return false;
        }
    }
    // 3. Validasi legalitas
    if (legalitasFiles.length === 0) {
        showAlert('Minimal upload 1 file legalitas!', 'warning');
        return false;
    }

    if (!prepareLegalitasForSubmission()) {
        return false;
    }

    console.log('File storage keys:', Object.keys(fileStorage).filter(k => fileStorage[k].length > 0));

    // 4. Transfer files ke form inputs
    const form = document.getElementById('formPendaftaran');

    form.querySelectorAll('input[type="file"][data-temp-input="true"]').forEach(el => el.remove());

    Object.keys(fileStorage).forEach(storageKey => {
        if (fileStorage[storageKey] && fileStorage[storageKey].length > 0) {
            let inputName = storageKey;
            if (!['logo1', 'logo2', 'logo3'].includes(storageKey)) {
                inputName = storageKey + '[]';
            }

            let originalInput = form.querySelector(`input[name="${inputName}"]`);

            if (originalInput) {
                const dataTransfer = new DataTransfer();
                fileStorage[storageKey].forEach(file => {
                    dataTransfer.items.add(file);
                });
                originalInput.files = dataTransfer.files;
                console.log(`✓ Set ${fileStorage[storageKey].length} files to ${inputName}`);
            } else {
                console.error(`✗ Input not found for ${storageKey}`);
            }
        }
    });

    // 5. Konfirmasi
    showConfirm(
        'Apakah Anda yakin semua data yang diisi sudah benar dan lengkap?\n\n' +
        'Setiap akun hanya dapat melakukan 1 kali pendaftaran merek.\n\n' +
        'Data yang sudah dikirim tidak dapat diubah.',
        function () {
            const btnSubmit = document.getElementById('btnSubmit');
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin pe-2"></i> Mengirim Data...';
            window.formChanged = false;

            console.log('=== SUBMITTING FORM ===');

            const formData = new FormData(form);

            for (let pair of formData.entries()) {
                if (pair[1] instanceof File) {
                    console.log(pair[0] + ': ' + pair[1].name);
                } else {
                    console.log(pair[0] + ': ' + pair[1]);
                }
            }

            form.submit();
        },
        function () {
            console.log('User cancelled submission');
        }
    );

    return false;
}

// KELURAHAN/DESA DATA
const desaKelurahan = {
    "Sidoarjo": ["Sidoarjo", "Lemahputro", "Magersari", "Gebang", "Celep", "Bulusidokare", "Urangagung", "Banjarbendo", "Blurukidul", "Cemengbakalan", "Jati", "Kemiri", "Lebo", "Rangkalkidul", "Sarirogo", "Suko", "Sumput", "Cemengkalan", "Pekawuman", "Pucang", "Pucanganom", "Sekardangan", "Sidoklumpuk", "Sidokumpul"],
    "Buduran": ["Buduran", "Sawohan", "Siwalanpanji", "Prasung", "Banjarkemantren", "Banjarsari", "Damarsi", "Dukuhtengah", "Entalsewu", "Pagerwojo", "Sidokerto", "Sidomulyo", "Sidokepung", "Sukorejo", "Wadungasin"],
    "Candi": ["Candi", "Durungbanjar", "Larangan", "Sumokali", "Sepande", "Kebonsari", "Kedensari", "Bligo", "Balongdowo", "Balonggabus", "Durungbanjar", "Durungbedug", "Gelam", "Jambangan", "Kalipecabean", "Karangtanjung", "Kebonsari", "Kedungkendo", "Kedungpeluk", "Kendalpencabean", "Klurak", "Ngampelsari", "Sidodadi", "Sugihwaras", "Sumorame", "Tenggulunan", "Wedoroklurak"],
    "Porong": ["Porong", "Kebonagung", "Kesambi", "Plumbon", "Pesawahan", "Gedang", "Juwetkenongo", "Kedungboto", "Wunut", "Pamotan", "Kebakalan", "Gempol Pasargi", "Glagaharum", "Lajuk", "Candipari"],
    "Krembung": ["Krembung", "Balanggarut", "Cangkring", "Gading", "Jenggot", "Kandangan", "Kedungrawan", "Kedungsumur", "Keperkeret", "Lemujut", "Ploso", "Rejeni", "Tambakrejo", "Tanjekwagir", "Wangkal", "Wonomlati", "Waung", "Mojoruntut"],
    "Tulangan": ["Tulangan", "Jiken", "Kajeksan", "Kebaran", "Kedondong", "Kepatihan", "Kepunten", "Medalem", "Pangkemiri", "Sudimoro", "Tlasih", "Gelang", "Kepadangan", "Grabagan", "Singopadu", "Kemantren", "Janti", "Modong", "Grogol", "Kenongo", "Grinting"],
    "Tanggulangin": ["kalisampurno", "kedensari", "Ganggang Pnjang", "Randegan", "Kalitengah", "Kedung Banteng", "Putat", "Ketapang", "Kalidawir", "Ketegan", "Banjar Panji", "Gempolsari", "Sentul", "Penatarsewu", "Banjarsari", "Ngaban", "Boro", "Kludan"],
    "Jabon": ["Trompoasri", "Kedung Pandan", "Permisan", "Semambung", "Pangrih", "Kupang", "Tambak Kalisogo", "Kedungrejo", "Kedungcangkring", "Keboguyang", "Jemirahan", "Balongtani", "dukuhsari"],
    "Krian": ["Sidomojo", "Sidomulyo", "Sidorejo", "Tempel", "Terik", "Terungkulon", "Terungwetan", "Tropodo", "Watugolong", "Krian", "Kemasan", "Tambakkemeraan", "Sedenganmijen", "Bareng Krajan", "Keraton", "Keboharan", "Katerungan", "Jeruk Gamping", "Junwangi", "Jatikalang", "Gamping", "Ponokawan"],
    "Balongbendo": ["Balongbendo", "", "WonoKupang", "Kedungsukodani", "Kemangsen", "Penambangan", "Seduri", "Seketi", "Singkalan", "SumoKembangsri", "Waruberon", "Watesari", "Wonokarang", "Jeruklegi", "Jabaran", "Suwaluh", "Gadungkepuhsari", "Bogempinggir", "Bakungtemenggungan", "Bakungpringgodani", "Wringinpitu", "Bakalan"],
    "Wonoayu": ["Becirongengor", "Candinegoro", "Jimbaran Kulon", "Jimbaran wetan", "Pilang", "Karangturi", "Ketimang", "Lambangan", "Mohorangagung", "Mulyodadi", "Pagerngumbuk", "Plaosan", "Ploso", "Popoh", "Sawocangkring", "semambung", "Simoangin-angin", "Simoketawang", "Sumberejo", "Tanggul", "Wonoayu", "Wonokalang", "Wonokasian"],
    "Tarik": ["Tarik", "Klantingsari", "GedangKlutuk", "Mergosari", "Kedinding", "Kemuning", "Janti", "Mergobener", "Mliriprowo", "Singogalih", "Kramat Temenggung", "Kedungbocok", "Segodobancang", "Gampingrowo", "Mindugading", "Kalimati", "Banjarwungu", "Balongmacekan", "Kendalsewu", "Sebani"],
    "Prambon": ["Prambon", "Bendotretek", "Bulang", "Cangkringturi", "Gampang", "Gedangrowo", "Jati alun-alun", "Watutulis", "jatikalang", "jedongcangkring", "Kajartengguli", "Kedungkembanr", "Kedung Sugo", "Kedungwonokerto", "Penjangkkungan", "Simogirang", "Simpang", "Temu", "Wirobiting", "Wonoplintahan"],
    "Taman": ["Taman", "Trosobo", "Sepanjang", "Ngelom", "Ketegan", "Jemundo", "Geluran", "Wage", "Bebekan", "Kalijaten", "Tawangsari", "Sidodadi", "Sambibulu", "Sadang", "Maduretno", "Krembangan", "Pertapan", "Kramatjegu", "Kletek", "Tanjungsari", "Kedungturi", "Gilang", "Bringinbendo", "Bohar", "Wonocolo"],
    "Waru": ["Waru", "Tropodo", "Kureksari", "Jambangan", "Medaeng", "Berbek", "Bungurasih", "Janti", "Kedungrejo", "Kepuhkiriman", "Ngingas", "Pepelegi", "Tambakoso", "Tambakrejo", "Tambahsawah", "Tambaksumur", "Wadungasri", "Wedoro"],
    "Gedangan": ["Gedangan", "Ketajen", "Wedi", "Bangah", "Sawotratap", "Semambung", "Ganting", "Tebel", "Kebonanom", "Gemurung", "Karangbong", "Kebiansikep", "Kragan", "Punggul", "Seruni"],
    "Sedati": ["Sedati", "Pabean", "Semampir", "Banjarkemuningtambak", "Pulungan", "Betro", "Segoro Tambak", "Gisik Cemandi", "Cemandi", "Kalanganyar", "Buncitan", "Wangsan", "Pranti", "Pepe", "Sedatiagung", "Sedatigede", "Tambakcemandi"],
    "Sukodono": ["Sukodono", "Jumputrejo", "Kebonagung", "Keloposepuluh", "Jogosatru", "Suruh", "Ngaresrejo", "Cangkringsari", "Masangan Wetan", "Masangan Kulon", "Bangsri", "Anggaswangi", "Pandemonegoro", "Panjunan", "Pekarungan", "Plumbungan", "Sambungrejo", "Suko", "Wilayut"]
};

document.getElementById('kecamatan').addEventListener('change', function () {
    const kecamatan = this.value;
    const kelDesaSelect = document.getElementById('kel_desa');
    kelDesaSelect.innerHTML = '<option value="">-Pilih Kelurahan/Desa-</option>';
    if (kecamatan && desaKelurahan[kecamatan]) {
        desaKelurahan[kecamatan].forEach(function (desa) {
            const option = document.createElement('option');
            option.value = desa;
            option.textContent = desa;
            kelDesaSelect.appendChild(option);
        });
        kelDesaSelect.disabled = false;
    } else {
        kelDesaSelect.disabled = true;
    }
});

document.getElementById('no_telp_perusahaan').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '');
});

document.getElementById('no_telp_perusahaan').addEventListener('blur', function () {
    let value = this.value.trim();

    if (!value) return;

    value = value.replace(/\D/g, '');

    if (value.startsWith('0')) {
        value = '62' + value.substring(1);
    } else if (!value.startsWith('62') && value.length > 0) {
        value = '62' + value;
    }

    this.value = value;
});

// JENIS PEMOHON
document.querySelectorAll('input[name="jenis_pemohon"]').forEach(radio => {
    radio.addEventListener('change', function () {
        const aktaWrapper = document.getElementById('aktaWrapper');
        const aktaInput = document.getElementById('akta-file');

        if (this.value === 'perusahaan') {
            aktaWrapper.style.display = 'block';
        } else {
            aktaWrapper.style.display = 'none';

            fileStorage['akta_files'] = [];
            updateFilePreview('akta_files', 'aktaPreview');
        }
    });
});

// INITIALIZE
document.addEventListener('DOMContentLoaded', function () {
    console.log('Initializing form...');

    // Setup file uploads
    setupDragDropWithStorage(
        document.getElementById('nibDropZone'),
        document.getElementById('nib-file'),
        document.getElementById('nibPreview'),
        'nib_files', 5, 10
    );
    setupDragDropWithStorage(
        document.getElementById('produkDropZone'),
        document.getElementById('product-file'),
        document.getElementById('produkPreview'),
        'foto_produk', 5, 1
    );
    setupDragDropWithStorage(
        document.getElementById('prosesDropZone'),
        document.getElementById('prosesproduksi-file'),
        document.getElementById('prosesPreview'),
        'foto_proses', 5, 1
    );
    setupDragDropWithStorage(
        document.getElementById('logo1DropZone'),
        document.getElementById('logo1-file'),
        document.getElementById('logo1Preview'),
        'logo1', 1, 1
    );
    setupDragDropWithStorage(
        document.getElementById('logo2DropZone'),
        document.getElementById('logo2-file'),
        document.getElementById('logo2Preview'),
        'logo2', 1, 1
    );
    setupDragDropWithStorage(
        document.getElementById('logo3DropZone'),
        document.getElementById('logo3-file'),
        document.getElementById('logo3Preview'),
        'logo3', 1, 1
    );

    setupDragDropWithStorage(
        document.getElementById('aktaDropZone'),
        document.getElementById('akta-file'),
        document.getElementById('aktaPreview'),
        'akta_files', 3, 5
    );


    // Setup legalitas
    setupLegalitasDragDrop();

    // Setup form submit
    const form = document.getElementById('formPendaftaran');
    if (form) {
        form.addEventListener('submit', handleFormSubmit);
    }
    window.formChanged = false;
    document.querySelectorAll('#formPendaftaran input, #formPendaftaran textarea, #formPendaftaran select').forEach(element => {
        element.addEventListener('change', function () {
            window.formChanged = true;
        });
    });
    window.addEventListener('beforeunload', function (e) {
        if (window.formChanged) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    });
    console.log('Form initialized successfully');
});