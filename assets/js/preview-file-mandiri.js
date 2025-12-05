// ============================================
// FILE PREVIEW SYSTEM - FORM MANDIRI
// Update: Support Multiple File Accumulative Upload
// ============================================

// FUNGSI BOOTSTRAP ALERT HELPER

function showAlert(message, type = 'warning') {
    const alertContainer = document.getElementById('alertContainer');
    const alertId = 'alert-' + Date.now();

    const iconMap = {
        'success': 'bi-check-circle-fill',
        'danger': 'bi-exclamation-circle-fill',
        'warning': 'bi-exclamation-triangle-fill',
        'info': 'bi-info-circle-fill'
    };

    const icon = iconMap[type] || iconMap['warning'];

    const alertHTML = `
         <div class="alert alert-${type} alert-dismissible fade show" role="alert" id="${alertId}">
             <i class="bi ${icon} me-2"></i>
             ${message}
             <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
         </div>
     `;

    alertContainer.innerHTML = alertHTML;

    // Scroll ke alert
    alertContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });

    // Auto dismiss setelah 5 detik
    setTimeout(() => {
        const alertElement = document.getElementById(alertId);
        if (alertElement) {
            const bsAlert = new bootstrap.Alert(alertElement);
            bsAlert.close();
        }
    }, 5000);
}

// Storage untuk menyimpan files secara permanen per field
const uploadedFilesStore = {
    logo: [],
    logo_perusahaan: [],
    nib: [],
    produk: [],
    proses: [],
    akta: []
};

// ================================================
// FUNGSI HELPER
// ================================================

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

    // Validasi manual untuk file hidden yang conditional
    const jenisPemohonChecked = document.querySelector('input[name="jenis_pemohon"]:checked');
    if (jenisPemohonChecked && jenisPemohonChecked.value === 'perusahaan') {
        if (uploadedFilesStore.logo_perusahaan.length === 0) {
            missingFiles.push('Logo Perusahaan');
        }
        if (uploadedFilesStore.akta.length === 0) {
            missingFiles.push('Akta Pendirian CV/PT');
        }
    }

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
                Preview tidak tersedia untuk format ini
            </div>
        `;
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function getMaxFiles(fieldType) {
    return (fieldType === 'logo' || fieldType === 'logo_perusahaan' || fieldType === 'akta') ? 1 : 5;
}

// ================================================
// FUNGSI UTAMA: Update File Input dengan DataTransfer
// ================================================

function updateFileInputWithDataTransfer(fieldType) {
    const inputMap = {
        logo: 'logo-file',
        logo_perusahaan: 'logo-perusahaan-file',
        nib: 'nib-file',
        produk: 'produk-file',
        proses: 'proses-file',
        akta: 'akta-file'
    };

    const fileInput = document.getElementById(inputMap[fieldType]);
    const dataTransfer = new DataTransfer();

    uploadedFilesStore[fieldType].forEach(file => {
        dataTransfer.items.add(file);
    });

    fileInput.files = dataTransfer.files;
}

// ================================================
// FUNGSI: Render Preview Items
// ================================================

function renderPreviewItems(fieldType, previewId) {
    const previewContainer = document.getElementById(previewId);
    previewContainer.innerHTML = '';

    const files = uploadedFilesStore[fieldType];
    const maxFiles = getMaxFiles(fieldType);

    if (files.length === 0) {
        previewContainer.style.display = 'none';
        return;
    }

    previewContainer.style.display = 'flex';
    previewContainer.style.flexWrap = 'wrap';

    files.forEach((file, index) => {
        const previewItem = document.createElement('div');
        previewItem.className = 'preview-item';
        previewItem.style.cssText = `
            position: relative; 
            width: 120px; 
            margin: 5px; 
            cursor: pointer;
            display: inline-block;
        `;
        previewItem.setAttribute('data-file-index', index);

        // Click untuk preview
        previewItem.addEventListener('click', function (e) {
            if (!e.target.closest('.remove-preview')) {
                showFilePreview(file, file.name);
            }
        });

        if (file.type.startsWith('image/')) {
            // Image preview
            const reader = new FileReader();
            reader.onload = (e) => {
                previewItem.innerHTML = `
                    <div style="position: relative; display: inline-block; width: 100%;">
                        <img src="${e.target.result}" 
                             style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px; border: 2px solid #dee2e6; transition: transform 0.2s; display: block;">
                        <button type="button" class="remove-preview" style="position: absolute; top: 0; right: 0; width: 28px; height: 28px; border-radius: 50%; background: #dc3545; color: white; border: none; font-size: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; line-height: 1; z-index: 10; transform: translate(40%, -40%);">×</button>
                    </div>
                    <div style="font-size: 11px; margin-top: 5px; text-align: center; word-break: break-word; color: #495057;">${file.name}</div>
                    <div style="font-size: 10px; text-align: center; color: #6c757d;">${formatFileSize(file.size)}</div>
                `;

                const img = previewItem.querySelector('img');
                const removeBtn = previewItem.querySelector('.remove-preview');

                previewItem.addEventListener('mouseenter', () => {
                    img.style.transform = 'scale(1.05)';
                });
                previewItem.addEventListener('mouseleave', () => {
                    img.style.transform = 'scale(1)';
                });

                removeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    removeFile(fieldType, index, previewId);
                });
            };
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
            // PDF preview
            previewItem.innerHTML = `
                <div style="position: relative; display: inline-block; width: 100%;">
                    <div class="pdf-preview" style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 120px; height: 120px; border-radius: 8px; border: 2px solid #dee2e6; background: #fff; transition: transform 0.2s;">
                        <i class="fas fa-file-pdf" style="font-size: 3rem; color: #dc3545; margin-bottom: 8px;"></i>
                        <div style="font-size: 10px; text-align: center; padding: 0 5px; color: #495057;">PDF</div>
                    </div>
                    <button type="button" class="remove-preview" style="position: absolute; top: 0; right: 0; width: 28px; height: 28px; border-radius: 50%; background: #dc3545; color: white; border: none; font-size: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; line-height: 1; z-index: 10; transform: translate(40%, -40%);">×</button>
                </div>
                <div style="font-size: 11px; margin-top: 5px; text-align: center; word-break: break-word; color: #495057;">${file.name}</div>
                <div style="font-size: 10px; text-align: center; color: #6c757d;">${formatFileSize(file.size)}</div>
            `;

            const pdfPreview = previewItem.querySelector('.pdf-preview');
            const removeBtn = previewItem.querySelector('.remove-preview');

            previewItem.addEventListener('mouseenter', () => {
                pdfPreview.style.transform = 'scale(1.05)';
            });
            previewItem.addEventListener('mouseleave', () => {
                pdfPreview.style.transform = 'scale(1)';
            });

            removeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                removeFile(fieldType, index, previewId);
            });
        }

        previewContainer.appendChild(previewItem);
    });

    // Tampilkan info jumlah file
    if (files.length > 0) {
        const infoDiv = document.createElement('div');
        infoDiv.style.cssText = 'width: 100%; margin-top: 10px; padding-top: 10px; border-top: 1px solid #dee2e6;';
        infoDiv.innerHTML = `
            <small class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                ${files.length} dari ${maxFiles} file terupload
                ${files.length < maxFiles ? `(Masih bisa upload ${maxFiles - files.length} file lagi)` : '(Sudah mencapai batas maksimal)'}
            </small>
        `;
        previewContainer.appendChild(infoDiv);
    }
}

// ================================================
// FUNGSI: Remove File
// ================================================

function removeFile(fieldType, index, previewId) {
    uploadedFilesStore[fieldType].splice(index, 1);
    updateFileInputWithDataTransfer(fieldType);
    renderPreviewItems(fieldType, previewId);
}

// ================================================
// FUNGSI: Handle File Selection (Accumulative)
// ================================================

function handleFileSelection(files, fieldType, previewId) {
    const maxFiles = getMaxFiles(fieldType);
    const currentCount = uploadedFilesStore[fieldType].length;

    const allowedTypes = {
        logo: ['image/jpeg', 'image/png', 'image/gif'],
        logo_perusahaan: ['image/jpeg', 'image/png', 'image/gif'],
        nib: ['application/pdf', 'image/jpeg', 'image/png'],
        produk: ['image/jpeg', 'image/png', 'image/gif'],
        proses: ['image/jpeg', 'image/png', 'image/gif'],
        akta: ['application/pdf']
    };

    const typeNames = {
        logo: 'Logo (JPG/PNG/GIF)',
        logo_perusahaan: 'Logo Perusahaan (JPG/PNG/GIF)',
        nib: 'NIB (PDF/JPG/PNG)',
        produk: 'Foto Produk (JPG/PNG/GIF)',
        proses: 'Foto Proses (JPG/PNG/GIF)',
        akta: 'Akta (PDF)'
    };

    const maxSize = 5 * 1024 * 1024; // 5MB

    Array.from(files).forEach((file) => {
        // Cek batas maksimal file
        if (uploadedFilesStore[fieldType].length >= maxFiles) {
            showAlert(`${typeNames[fieldType]}: Sudah mencapai batas maksimal ${maxFiles} file`, 'warning');
            return;
        }

        // Cek duplikat
        const isDuplicate = uploadedFilesStore[fieldType].some(f =>
            f.name === file.name && f.size === file.size && f.type === file.type
        );
        if (isDuplicate) {
            showAlert(`${file.name}: File ini sudah diupload sebelumnya`, 'warning');
            return;
        }

        // Validasi tipe file
        if (!allowedTypes[fieldType].includes(file.type)) {
            showAlert(`${file.name}: Format tidak didukung untuk ${typeNames[fieldType]}`, 'danger');
            return;
        }

        // Validasi ukuran
        if (file.size > maxSize) {
            showAlert(`${file.name}: Ukuran file terlalu besar (${formatFileSize(file.size)}). Maksimal 5MB.`, 'danger');
            return;
        }

        // Tambah file ke storage
        uploadedFilesStore[fieldType].push(file);
    });

    // Update preview dan file input
    updateFileInputWithDataTransfer(fieldType);
    renderPreviewItems(fieldType, previewId);
}

// ================================================
// FUNGSI: Setup File Upload untuk Setiap Zone
// ================================================

function setupFilePreview(dropZoneId, inputId, previewId, fieldType) {
    const dropZone = document.getElementById(dropZoneId);
    const fileInput = document.getElementById(inputId);

    if (!dropZone || !fileInput) return;

    // Click untuk upload
    dropZone.addEventListener('click', () => fileInput.click());

    // Drag & drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
        });
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.add('drag-over');
            dropZone.style.borderColor = '#007bff';
            dropZone.style.backgroundColor = '#e7f1ff';
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.remove('drag-over');
            dropZone.style.borderColor = '';
            dropZone.style.backgroundColor = '';
        });
    });

    dropZone.addEventListener('drop', (e) => {
        handleFileSelection(e.dataTransfer.files, fieldType, previewId);
    });

    fileInput.addEventListener('change', (e) => {
        handleFileSelection(e.target.files, fieldType, previewId);
        // Reset input value agar bisa upload file yang sama lagi
        e.target.value = '';
    });
}

// ================================================
// INITIALIZE - Jalankan saat DOM Ready
// ================================================

document.addEventListener('DOMContentLoaded', function () {
    setupFilePreview('logoDropZone', 'logo-file', 'logoPreview', 'logo');
    setupFilePreview('logoPerusahaanDropZone', 'logo-perusahaan-file', 'logoPerusahaanPreview', 'logo_perusahaan');
    setupFilePreview('nibDropZone', 'nib-file', 'nibPreview', 'nib');
    setupFilePreview('produkDropZone', 'produk-file', 'produkPreview', 'produk');
    setupFilePreview('prosesDropZone', 'proses-file', 'prosesPreview', 'proses');
    setupFilePreview('aktaDropZone', 'akta-file', 'aktaPreview', 'akta');

    // Handle Radio Button Jenis Pemohon
    const radioPemohon = document.querySelectorAll('input[name="jenis_pemohon"]');
    const profilPerusahaanSection = document.getElementById('profilPerusahaanSection');
    const aktaWrapper = document.getElementById('aktaWrapper');

    radioPemohon.forEach(radio => {
        radio.addEventListener('change', function () {
            if (this.value === 'perusahaan') {
                profilPerusahaanSection.style.display = 'block';
                aktaWrapper.style.display = 'block';

                // Set required
                document.getElementById('nama_perusahaan').required = true;
                document.getElementById('alamat_perusahaan').required = true;
                document.getElementById('no_telp_usaha').required = true;
                document.getElementById('email_perusahaan').required = true;
            } else {
                profilPerusahaanSection.style.display = 'none';
                aktaWrapper.style.display = 'none';


                // PENTING: Hapus atribut required dari input yang di hidden section
                const noTelpKop = document.getElementById('no_telp_kop');
                const emailPerusahaan = document.getElementById('email_perusahaan');
                if (noTelpKop) noTelpKop.required = false;
                if (emailPerusahaan) emailPerusahaan.required = false;

                // Remove required
                document.getElementById('nama_perusahaan').required = false;
                document.getElementById('alamat_perusahaan').required = false;
                document.getElementById('no_telp_kop').required = false;
                document.getElementById('email_perusahaan').required = false;

                // Clear data
                document.getElementById('nama_perusahaan').value = '';
                document.getElementById('alamat_perusahaan').value = '';
                document.getElementById('no_telp_kop').value = '';
                document.getElementById('email_perusahaan').value = '';
                uploadedFilesStore.logo_perusahaan = [];
                uploadedFilesStore.akta = [];
                renderPreviewItems('logo_perusahaan', 'logoPerusahaanPreview');
                renderPreviewItems('akta', 'aktaPreview');
            }
        });
    });
});

// ===== SIGNATURE PAD SETUP =====
const canvas = document.getElementById('signaturePad');
const ctx = canvas.getContext('2d', { willReadFrequently: true });

// Set canvas size dengan pixel ratio yang tepat
function initCanvas() {
    const rect = canvas.parentElement.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;

    const canvasSize = 200;

    // Set display size (css size)
    canvas.style.width = canvasSize + 'px';
    canvas.style.height = canvasSize + 'px';

    // Set actual size (resolution)
    canvas.width = canvasSize * dpr;
    canvas.height = canvasSize * dpr;

    // Scale context to match device pixel ratio
    ctx.scale(dpr, dpr);

    // Set drawing properties
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvasSize, canvasSize);
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
}

initCanvas();
let isDrawing = false;

// Get mouse/touch coordinates dengan scaling
function getCoords(e) {
    const rect = canvas.getBoundingClientRect();
    let x, y;

    if (e.touches) {
        x = e.touches[0].clientX - rect.left;
        y = e.touches[0].clientY - rect.top;
    } else {
        x = e.clientX - rect.left;
        y = e.clientY - rect.top;
    }

    return { x, y };
}

// Mouse events
canvas.addEventListener('mousedown', (e) => {
    isDrawing = true;
    const { x, y } = getCoords(e);
    ctx.beginPath();
    ctx.moveTo(x, y);
}, false);

canvas.addEventListener('mousemove', (e) => {
    if (!isDrawing) return;
    const { x, y } = getCoords(e);
    ctx.lineTo(x, y);
    ctx.stroke();
}, false);

canvas.addEventListener('mouseup', () => {
    isDrawing = false;
}, false);

canvas.addEventListener('mouseleave', () => {
    isDrawing = false;
}, false);

// Touch events
canvas.addEventListener('touchstart', (e) => {
    e.preventDefault();
    isDrawing = true;
    const { x, y } = getCoords(e);
    ctx.beginPath();
    ctx.moveTo(x, y);
}, false);

canvas.addEventListener('touchmove', (e) => {
    e.preventDefault();
    if (!isDrawing) return;
    const { x, y } = getCoords(e);
    ctx.lineTo(x, y);
    ctx.stroke();
}, false);

canvas.addEventListener('touchend', (e) => {
    e.preventDefault();
    isDrawing = false;
}, false);

// Clear signature
document.getElementById('btnClearSignature').addEventListener('click', () => {
    const canvasSize = 200;
    ctx.clearRect(0, 0, canvasSize, canvasSize);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvasSize, canvasSize);
});

// Check if canvas has signature
function hasSignature() {
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const data = imageData.data;

    for (let i = 0; i < data.length; i += 4) {
        if (data[i + 3] > 128) {
            return true;
        }
    }
    return false;
}

// Generate PDF and show preview modal
document.getElementById('btnGeneratePDF').addEventListener('click', async () => {
    const statusDiv = document.getElementById('statusGeneratePDF');
    const btnSubmit = document.getElementById('btnSubmit');

    console.log('=== GENERATE PDF CLICKED ===');
    console.log('Button Submit sebelum generate:', btnSubmit);
    console.log('Button disabled sebelum generate?', btnSubmit?.disabled);

    // Validasi semua field wajib terlebih dahulu
    const requiredFields = [
        { name: 'nama_usaha', label: 'Nama Usaha' },
        { name: 'kecamatan_usaha', label: 'Kecamatan Usaha' },
        { name: 'kel_desa_usaha', label: 'Kelurahan/Desa Usaha' },
        { name: 'jenis_usaha', label: 'Jenis Usaha' },
        { name: 'produk', label: 'Produk' },
        { name: 'jml_tenaga_kerja', label: 'Jumlah Tenaga Kerja' },
        { name: 'merek', label: 'Merek' },
        { name: 'kelas_merek', label: 'Kelas Merek' }
    ];

    let missingFields = [];

    for (const field of requiredFields) {
        const element = document.querySelector(`[name="${field.name}"]`);
        if (!element || !element.value || element.value.trim() === '') {
            missingFields.push(field.label);
        }
    }

    // Cek file uploads wajib
    if (uploadedFilesStore.logo.length === 0) {
        missingFields.push('Logo Merek');
    }
    if (uploadedFilesStore.nib.length === 0) {
        missingFields.push('NIB/RBA');
    }
    if (uploadedFilesStore.produk.length === 0) {
        missingFields.push('Foto Produk');
    }
    if (uploadedFilesStore.proses.length === 0) {
        missingFields.push('Foto Proses Produksi');
    }

    // Cek akta jika CV/PT
    const jenisUsaha = document.querySelector('[name="jenis_usaha"]')?.value;
    if ((jenisUsaha === 'cv' || jenisUsaha === 'pt') && uploadedFilesStore.akta.length === 0) {
        missingFields.push('Akta Pendirian CV/PT');
    }

    if (missingFields.length > 0) {
        showAlert(`Mohon lengkapi data berikut terlebih dahulu:<br><br>${missingFields.join('<br>')}<br><br>Setelah semua data lengkap, klik "Simpan Tanda Tangan" untuk melanjutkan.`, 'warning');
        return;
    }

    if (!hasSignature()) {
        showAlert('Mohon tanda tangani terlebih dahulu sebelum generate surat permohonan', 'warning');
        return;
    }

    // Cek profil perusahaan jika jenis pemohon = perusahaan
    const jenisPemohon = document.querySelector('input[name="jenis_pemohon"]:checked')?.value;
    if (jenisPemohon === 'perusahaan') {
        const namaPerusahaan = document.querySelector('[name="nama_perusahaan"]')?.value;
        const alamatPerusahaan = document.querySelector('[name="alamat_perusahaan"]')?.value;
        const noTelpKop = document.querySelector('[name="no_telp_kop"]')?.value;
        const emailPerusahaan = document.querySelector('[name="email_perusahaan"]')?.value;

        if (!namaPerusahaan || namaPerusahaan.trim() === '') {
            missingFields.push('Nama Perusahaan');
        }
        if (!alamatPerusahaan || alamatPerusahaan.trim() === '') {
            missingFields.push('Alamat Perusahaan');
        }
        if (!noTelpKop || noTelpKop.trim() === '') {
            missingFields.push('No. Telepon Kop Surat');
        }
        if (!emailPerusahaan || emailPerusahaan.trim() === '') {
            missingFields.push('Email Perusahaan');
        }
        if (uploadedFilesStore.logo_perusahaan.length === 0) {
            missingFields.push('Logo Perusahaan');
        }
        if (uploadedFilesStore.akta.length === 0) {
            missingFields.push('Akta Pendirian CV/PT');
        }
    }

    if (missingFields.length > 0) {
        showAlert(`Mohon lengkapi data berikut terlebih dahulu:<br><br>${missingFields.join('<br>')}<br><br>Setelah semua data lengkap, klik "Simpan Tanda Tangan" untuk melanjutkan.`, 'warning');
        return;
    }

    statusDiv.innerHTML = '<div class="alert alert-info mt-2"><i class="bi bi-hourglass-split me-2"></i>Sedang membuat PDF surat permohonan...</div>';

    try {
        const imageData = canvas.toDataURL('image/png');

        const formData = new FormData();
        formData.append('action', 'generate_pdf');
        formData.append('signature', imageData);
        formData.append('nik', document.getElementById('userNikData').value);

        // Tentukan tipe pengajuan berdasarkan halaman
        const isMandiri = window.location.pathname.includes('form-pendaftaran-mandiri');
        const isPerpanjangan = window.location.pathname.includes('form-perpanjangan');
        formData.append('tipe_pengajuan', isPerpanjangan ? 'perpanjangan' : 'mandiri');

        formData.append('nama_usaha', document.querySelector('input[name="nama_usaha"]').value);
        // Gabungkan alamat lengkap
        formData.append('kecamatan_usaha', document.getElementById('kecamatan_usaha').value);
        formData.append('rt_rw_usaha', document.getElementById('rt_rw_usaha').value);
        formData.append('kel_desa_usaha', document.getElementById('kel_desa_usaha').value);

        const noTelpPerusahaanEl = document.getElementById('no_telp_perusahaan');
        if (noTelpPerusahaanEl) {
            formData.append('no_telp_perusahaan', noTelpPerusahaanEl.value);
        } else {
            console.error('Element no_telp_perusahaan tidak ditemukan');
            statusDiv.innerHTML = '<div class="alert alert-danger mt-2">Error: Field No. Telepon Usaha tidak ditemukan</div>';
            return;
        }

        formData.append('jenis_usaha', document.querySelector('select[name="jenis_usaha"]').value);
        formData.append('produk', document.querySelector('input[name="produk"]').value);
        formData.append('jml_tenaga_kerja', document.querySelector('input[name="jml_tenaga_kerja"]').value);
        formData.append('merek', document.querySelector('input[name="merek"]').value);

        // Tambahkan data jenis pemohon dan profil perusahaan
        const jenisPemohonVal = document.querySelector('input[name="jenis_pemohon"]:checked')?.value || 'perseorangan';
        formData.append('jenis_pemohon', jenisPemohonVal);

        if (jenisPemohonVal === 'perusahaan') {
            formData.append('nama_perusahaan', document.querySelector('[name="nama_perusahaan"]')?.value || '');
            formData.append('alamat_perusahaan', document.querySelector('[name="alamat_perusahaan"]')?.value || '');
            formData.append('email_perusahaan', document.querySelector('[name="email_perusahaan"]')?.value || '');
            formData.append('no_telp_kop', document.getElementById('no_telp_kop')?.value || '');

            // Kirim logo perusahaan sebagai file jika ada
            if (uploadedFilesStore.logo_perusahaan.length > 0) {
                formData.append('logo_perusahaan_file', uploadedFilesStore.logo_perusahaan[0]);
            }
        }

        const response = await fetch('process/generate_surat_permohonan.php', {
            method: 'POST',
            body: formData
        });

        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const textResponse = await response.text();
            console.error('Non-JSON response:', textResponse);
            throw new Error('Server mengembalikan response non-JSON. Cek console untuk detail.');
        }

        const result = await response.json();
        console.log('=== RESPONSE GENERATE PDF ===', result);

        if (result.success) {
            console.log('PDF berhasil dibuat, path:', result.file_path);
            document.getElementById('suratpermohonanPath').value = result.file_path;

            console.log('Path sudah diset ke hidden input');

            statusDiv.innerHTML = '<div class="alert alert-success mt-2"><i class="bi bi-check-circle me-2"></i>PDF berhasil dibuat!</div>';

            // Update modal preview dan tampilkan
            showPreviewCard(result.file_path);

            // ENABLE BUTTON SUBMIT - MULTIPLE APPROACH
            console.log('Attempting to enable submit button...');

            if (btnSubmit) {
                // Method 1: Direct property
                btnSubmit.disabled = false;
                console.log('Method 1 - disabled property set to false');

                // Method 2: Remove attribute
                btnSubmit.removeAttribute('disabled');
                console.log('Method 2 - disabled attribute removed');

                // Method 3: Remove class
                btnSubmit.classList.remove('disabled');
                console.log('Method 3 - disabled class removed');

                // Method 4: Force inline style
                btnSubmit.style.pointerEvents = 'auto';
                btnSubmit.style.opacity = '1';
                btnSubmit.style.cursor = 'pointer';
                console.log('Method 4 - inline styles applied');

                // Verify
                console.log('=== VERIFICATION ===');
                console.log('Button disabled property:', btnSubmit.disabled);
                console.log('Button has disabled attribute:', btnSubmit.hasAttribute('disabled'));
                console.log('Button classes:', btnSubmit.className);
                console.log('Button computed style pointer-events:', window.getComputedStyle(btnSubmit).pointerEvents);

                // Scroll to button
                setTimeout(() => {
                    btnSubmit.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    console.log('Scrolled to submit button');
                }, 300);
            } else {
                console.error('FATAL: btnSubmit is null/undefined!');
            }

        } else {
            let errorMsg = result.message || 'Terjadi kesalahan';
            if (result.file && result.line) {
                errorMsg += ` (${result.file}:${result.line})`;
            }
            statusDiv.innerHTML = '<div class="alert alert-danger mt-2"><i class="bi bi-exclamation-circle me-2"></i>Error: ' + errorMsg + '</div>';
        }
    } catch (error) {
        console.error('Fetch error:', error);
        statusDiv.innerHTML = '<div class="alert alert-danger mt-2"><i class="bi bi-exclamation-circle me-2"></i>Gagal membuat PDF: ' + error.message + '</div>';
    }
});

// Fungsi untuk menampilkan preview card
function showPreviewCard(filePath) {
    const previewContainer = document.getElementById('previewSuratContainer');
    const previewContent = document.getElementById('previewSuratContent');

    // Ekstrak nama file dari path
    const filename = filePath.split(/[\\/]/).pop();

    // Buat blob/file object untuk preview
    fetch(filePath)
        .then(response => response.blob())
        .then(blob => {
            const file = new File([blob], filename, { type: 'application/pdf' });
            const fileSize = formatFileSize(blob.size);

            // Render preview seperti file lainnya
            previewContent.innerHTML = `
                 <div class="preview-item" style="position: relative; width: 120px; margin: 5px; cursor: pointer; display: inline-block;">
                     <div style="position: relative; display: inline-block; width: 100%;">
                         <div class="pdf-preview" style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 120px; height: 120px; border-radius: 8px; border: 2px solid #dee2e6; background: #fff; transition: transform 0.2s;">
                             <i class="fas fa-file-pdf" style="font-size: 3rem; color: #dc3545; margin-bottom: 8px;"></i>
                             <div style="font-size: 10px; text-align: center; padding: 0 5px; color: #495057;">PDF</div>
                         </div>
                     </div>
                     <div style="font-size: 11px; margin-top: 5px; text-align: center; word-break: break-word; color: #495057;">${filename}</div>
                     <div style="font-size: 10px; text-align: center; color: #6c757d;">${fileSize}</div>
                 </div>
             `;

            const previewItem = previewContent.querySelector('.preview-item');
            const pdfPreview = previewItem.querySelector('.pdf-preview');

            // Hover effect
            previewItem.addEventListener('mouseenter', () => {
                pdfPreview.style.transform = 'scale(1.05)';
            });
            previewItem.addEventListener('mouseleave', () => {
                pdfPreview.style.transform = 'scale(1)';
            });

            // Click untuk preview modal
            previewItem.addEventListener('click', () => {
                showFilePreview(file, filename);
            });
        })
        .catch(error => {
            console.error('Error loading PDF preview:', error);
            previewContent.innerHTML = '<div class="alert alert-danger small">Gagal memuat preview PDF</div>';
        });

    // Tampilkan container
    previewContainer.style.display = 'block';
    previewContent.style.display = 'flex';
    previewContent.style.flexWrap = 'wrap';

    // Scroll ke preview
    previewContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });

}
