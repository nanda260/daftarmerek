// BOOTSTRAP ALERT MODAL
function showAlert(message, type = 'warning') {
  const icon = type === 'danger' ? '❌' : type === 'success' ? '✅' : '⚠️';
  
  const alertModal = `
    <div class="modal fade" id="alertModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
          <div class="modal-body text-center p-4">
            <div class="fs-1 mb-3">${icon}</div>
            <p class="mb-0">${message}</p>
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
  
  document.getElementById('alertModal').addEventListener('hidden.bs.modal', function() {
    this.remove();
  });
}

// INISIALISASI SELECT2
$(document).ready(function() {
  $('.select2-dropdown').select2({
    theme: 'bootstrap-5',
    width: '100%',
    placeholder: function() {
      return $(this).data('placeholder') || 'Pilih...';
    },
    allowClear: true,
    language: {
      noResults: function() {
        return "Tidak ada hasil ditemukan";
      },
      searching: function() {
        return "Mencari...";
      }
    }
  });

  loadProvinsi();
});

// LOAD DATA WILAYAH

// Load Provinsi
function loadProvinsi() {
  $.ajax({
    url: 'process/get_wilayah.php',
    type: 'GET',
    data: { type: 'provinsi' },
    dataType: 'json',
    success: function(response) {
      if (response.success) {
        const $provinsi = $('#provinsi');
        $provinsi.empty().append('<option value="">Pilih Provinsi</option>');
        
        response.data.forEach(function(item) {
          $provinsi.append(`<option value="${item.kode}">${item.nama}</option>`);
        });
        
        $provinsi.trigger('change');
      }
    },
    error: function(xhr, status, error) {
      console.error('Error loading provinsi:', error);
      showAlert('Gagal memuat data provinsi. Silakan refresh halaman.', 'danger');
    }
  });
}

// Load Kabupaten
function loadKabupaten(provinsiKode) {
  const $kabupaten = $('#kabupaten');
  const $kecamatan = $('#kecamatan');
  const $desa = $('#kel_desa');
  
  // Reset dropdown
  $kabupaten.empty().append('<option value="">Pilih Kabupaten/Kota</option>').prop('disabled', true).trigger('change');
  $kecamatan.empty().append('<option value="">Pilih Kecamatan</option>').prop('disabled', true).trigger('change');
  $desa.empty().append('<option value="">Pilih Kelurahan/Desa</option>').prop('disabled', true).trigger('change');

  if (!provinsiKode) return;

  $.ajax({
    url: 'process/get_wilayah.php',
    type: 'GET',
    data: { 
      type: 'kabupaten',
      parent: provinsiKode
    },
    dataType: 'json',
    success: function(response) {
      if (response.success) {
        response.data.forEach(function(item) {
          $kabupaten.append(`<option value="${item.kode}">${item.nama}</option>`);
        });
        $kabupaten.prop('disabled', false).trigger('change');
      }
    },
    error: function(xhr, status, error) {
      console.error('Error loading kabupaten:', error);
      showAlert('Gagal memuat data kabupaten.', 'danger');
    }
  });
}

// Load Kecamatan
function loadKecamatan(kabupatenKode) {
  const $kecamatan = $('#kecamatan');
  const $desa = $('#kel_desa');
  
  // Reset dropdown
  $kecamatan.empty().append('<option value="">Pilih Kecamatan</option>').prop('disabled', true).trigger('change');
  $desa.empty().append('<option value="">Pilih Kelurahan/Desa</option>').prop('disabled', true).trigger('change');

  if (!kabupatenKode) return;

  $.ajax({
    url: 'process/get_wilayah.php',
    type: 'GET',
    data: { 
      type: 'kecamatan',
      parent: kabupatenKode
    },
    dataType: 'json',
    success: function(response) {
      if (response.success) {
        response.data.forEach(function(item) {
          $kecamatan.append(`<option value="${item.kode}">${item.nama}</option>`);
        });
        $kecamatan.prop('disabled', false).trigger('change');
      }
    },
    error: function(xhr, status, error) {
      console.error('Error loading kecamatan:', error);
      showAlert('Gagal memuat data kecamatan.', 'danger');
    }
  });
}

// Load Desa
function loadDesa(kecamatanKode) {
  const $desa = $('#kel_desa');
  
  // Reset dropdown
  $desa.empty().append('<option value="">Pilih Kelurahan/Desa</option>').prop('disabled', true).trigger('change');

  if (!kecamatanKode) return;

  $.ajax({
    url: 'process/get_wilayah.php',
    type: 'GET',
    data: { 
      type: 'desa',
      parent: kecamatanKode
    },
    dataType: 'json',
    success: function(response) {
      if (response.success) {
        response.data.forEach(function(item) {
          $desa.append(`<option value="${item.kode}">${item.nama}</option>`);
        });
        $desa.prop('disabled', false).trigger('change');
      }
    },
    error: function(xhr, status, error) {
      console.error('Error loading desa:', error);
      showAlert('Gagal memuat data kelurahan/desa.', 'danger');
    }
  });
}

// EVENT LISTENERS UNTUK DROPDOWN BERTINGKAT

// Event: Provinsi berubah -> Load Kabupaten
$('#provinsi').on('change', function() {
  const provinsiKode = $(this).val();
  loadKabupaten(provinsiKode);
});

// Event: Kabupaten berubah -> Load Kecamatan
$('#kabupaten').on('change', function() {
  const kabupatenKode = $(this).val();
  loadKecamatan(kabupatenKode);
});

// Event: Kecamatan berubah -> Load Desa
$('#kecamatan').on('change', function() {
  const kecamatanKode = $(this).val();
  loadDesa(kecamatanKode);
});

// VALIDASI INPUT

// Validasi input angka (NIK)
document.getElementById('nik').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '');
});

// Validasi nomor telepon
document.getElementById('telepon').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '');
});

// Format nomor telepon ke 62
document.getElementById('telepon').addEventListener('blur', function() {
  let value = this.value.trim();
  
  if (!value) return;
  
  value = value.replace(/\D/g, '');
  
  if (value.startsWith('0')) {
    value = '62' + value.substring(1);
  }
  else if (!value.startsWith('62') && value.length > 0) {
    value = '62' + value;
  }
  
  this.value = value;
});

// Format RT/RW
   const rtRwInput = document.getElementById('rt_rw');
   Inputmask("999/999", {
     placeholder: "___/___",
     clearMaskOnLostFocus: false
   }).mask(rtRwInput);

document.getElementById('rt_rw').addEventListener('blur', function() {
  let value = this.value.trim();
  
  if (!value) return;
  
  let parts = value.split('/');
  let rt = parts[0] ? parts[0].replace(/\D/g, '') : '';
  let rw = parts[1] ? parts[1].replace(/\D/g, '') : '';
  
  if (rt) {
    rt = rt.substring(0, 3);
    rt = rt.padStart(3, '0');
    
    if (!rw) {
      rw = '001';
    } else {
      rw = rw.substring(0, 3);
      rw = rw.padStart(3, '0');
    }
    
    this.value = rt + '/' + rw;
  } else {
    this.value = '';
  }
});

// FILE UPLOAD & PREVIEW

// Update nama file dan preview
function updateFileName() {
  const fileInput = document.getElementById('fileKTP');
  const fileName = document.getElementById('fileName');
  const previewWrapper = document.getElementById('ktpPreviewContainer');
  const previewImg = document.getElementById('ktpPreviewImg');
  const pdfPreviewBox = document.getElementById('pdfPreviewBox');
  const pdfFileName = document.getElementById('pdfFileName');
  const fileSizeInfo = document.getElementById('fileSizeInfo');

  if (fileInput.files.length > 0) {
    const file = fileInput.files[0];

    // Validasi ukuran file
    if (file.size > 1024 * 1024) {
      showAlert(`File terlalu besar (${(file.size / 1024 / 1024).toFixed(2)} MB). Maksimal 1 MB.`);
      clearFilePreview();
      return;
    }

    // Validasi tipe file
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
    if (!allowedTypes.includes(file.type)) {
      showAlert('Format file harus PDF, JPG, JPEG, atau PNG.');
      clearFilePreview();
      return;
    }

    // Update nama file dan ukuran
    fileName.textContent = file.name;
    fileName.style.color = '#28a745';
    fileName.style.fontWeight = '500';
    
    const fileSizeKB = (file.size / 1024).toFixed(0);
    fileSizeInfo.textContent = `✓ File valid (${fileSizeKB} KB)`;

    previewWrapper.classList.add('show');

    if (file.type === 'application/pdf') {
      previewImg.classList.remove('show');
      pdfPreviewBox.classList.add('show');
      pdfFileName.textContent = file.name;
    } else {
      pdfPreviewBox.classList.remove('show');
      previewImg.classList.add('show');

      const reader = new FileReader();
      reader.onload = function(e) {
        previewImg.src = e.target.result;
      };
      reader.readAsDataURL(file);
    }
  } else {
    clearFilePreview();
  }
}

function clearFilePreview() {
  const fileInput = document.getElementById('fileKTP');
  const fileName = document.getElementById('fileName');
  const previewWrapper = document.getElementById('ktpPreviewContainer');
  const previewImg = document.getElementById('ktpPreviewImg');
  const pdfPreviewBox = document.getElementById('pdfPreviewBox');
  const fileSizeInfo = document.getElementById('fileSizeInfo');

  fileInput.value = '';
  fileName.textContent = 'Tidak ada file yang dipilih.';
  fileName.style.color = '#666';
  fileName.style.fontWeight = 'normal';
  
  previewImg.src = '';
  previewImg.classList.remove('show');
  pdfPreviewBox.classList.remove('show');
  previewWrapper.classList.remove('show');
  fileSizeInfo.textContent = '';
}

// FORM SUBMISSION
document.getElementById('registrationForm').addEventListener('submit', async (e) => {
  e.preventDefault();

  const btnSubmit = document.getElementById('btnSubmit');
  const spinner = document.getElementById('loadingSpinner');

  const nik = document.getElementById('nik').value;
  const telepon = document.getElementById('telepon').value;
  const email = document.getElementById('email').value;
  const fileKTP = document.getElementById('fileKTP').files[0];
  const rtRw = document.getElementById('rt_rw').value;
  
  // Validasi dropdown wilayah
  const provinsi = $('#provinsi').val();
  const kabupaten = $('#kabupaten').val();
  const kecamatan = $('#kecamatan').val();
  const desa = $('#kel_desa').val();

  if (!provinsi) {
    showAlert('Provinsi wajib dipilih.');
    return;
  }

  if (!kabupaten) {
    showAlert('Kabupaten/Kota wajib dipilih.');
    return;
  }

  if (!kecamatan) {
    showAlert('Kecamatan wajib dipilih.');
    return;
  }

  if (!desa) {
    showAlert('Kelurahan/Desa wajib dipilih.');
    return;
  }

  // Validasi NIK
  if (nik.length !== 16) {
    showAlert(`NIK harus 16 digit. Anda memasukkan ${nik.length} digit.`);
    return;
  }

  // Validasi RT/RW
  const rtRwPattern = /^\d{3}\/\d{3}$/;
  if (!rtRwPattern.test(rtRw)) {
    showAlert('Format RT/RW tidak valid. Contoh: 002/006');
    document.getElementById('rt_rw').focus();
    return;
  }

  // Validasi nomor telepon dengan format 62
  if (!telepon.startsWith('62')) {
    showAlert('Nomor WhatsApp harus diawali dengan 62.');
    return;
  }
  
  if (telepon.length < 11 || telepon.length > 15) {
    showAlert('Nomor WhatsApp tidak valid. Format: 62xxxxx (11-15 digit)');
    return;
  }

  // Validasi file
  if (!fileKTP) {
    showAlert('File KTP belum dipilih. Silakan upload file KTP.');
    return;
  }

  btnSubmit.disabled = true;
  btnSubmit.classList.add('btn-disabled');
  spinner.style.display = 'inline-block';

  const formData = new FormData(e.target);

  try {
    const response = await fetch('process/proses_registrasi.php', {
      method: 'POST',
      body: formData
    });

    if (!response.ok) {
      throw new Error('Server error: ' + response.status);
    }

    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text();
      console.error('Response bukan JSON:', text);
      throw new Error('Server mengembalikan response yang tidak valid.');
    }

    const result = await response.json();

    if (result.success) {
      // Tampilkan modal sukses
      const modal = new bootstrap.Modal(document.getElementById('successModal'));
      modal.show();

      e.target.reset();
      clearFilePreview();
      
      $('.select2-dropdown').val(null).trigger('change');

      setTimeout(() => {
        window.location.href = result.redirect || 'login.php';
      }, 3000);

    } else {
      let errorMessage = result.message || "Terjadi kesalahan yang tidak diketahui.";
      if (result.details) {
        errorMessage += ` Detail: ${result.details}`;
      }
      showAlert(errorMessage, 'danger');
    }
  } catch (err) {
    console.error('Error:', err);
    showAlert(`Gagal menghubungi server: ${err.message}`, 'danger');
  } finally {
    btnSubmit.disabled = false;
    btnSubmit.classList.remove('btn-disabled');
    spinner.style.display = 'none';
  }
});