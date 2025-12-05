// ============================================
// INISIALISASI SELECT2
// ============================================
$(document).ready(function() {
  // Inisialisasi Select2
  $('.select2-dropdown').select2({
    theme: 'bootstrap-5',
    width: '100%',
    placeholder: 'Pilih...',
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

  // Load data wilayah dengan nilai saat ini
  loadDataWilayah();
});

// ============================================
// ALERT MODAL HELPER
// ============================================
function showAlert(message, type = 'info') {
  const iconMap = {
    'success': 'bi-check-circle-fill text-success',
    'danger': 'bi-exclamation-circle-fill text-danger',
    'warning': 'bi-exclamation-triangle-fill text-warning',
    'info': 'bi-info-circle-fill text-info'
  };

  const titleMap = {
    'success': 'Berhasil',
    'danger': 'Error',
    'warning': 'Peringatan',
    'info': 'Informasi'
  };

  const icon = iconMap[type] || iconMap['info'];
  const title = titleMap[type] || titleMap['info'];

  // Hapus modal lama jika ada
  const oldModal = document.getElementById('alertModal');
  if (oldModal) {
    oldModal.remove();
  }

  // Buat modal baru
  const modalHTML = `
    <div class="modal fade" id="alertModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              <i class="bi ${icon} me-2"></i>${title}
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            ${message}
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>
  `;

  document.body.insertAdjacentHTML('beforeend', modalHTML);
  const modal = new bootstrap.Modal(document.getElementById('alertModal'));
  modal.show();

  // Auto dismiss untuk success
  if (type === 'success') {
    setTimeout(() => {
      modal.hide();
    }, 3000);
  }
}

// ============================================
// FORMAT NOMOR WHATSAPP KE 62
// ============================================

// Validasi input - hanya angka
document.getElementById('telepon').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '');
});

// Format nomor WhatsApp ke 62 saat blur (kehilangan fokus)
document.getElementById('telepon').addEventListener('blur', function() {
    let value = this.value.trim();
    
    if (!value) {
        showAlert('Nomor WhatsApp tidak boleh kosong!', 'warning');
        return;
    }
    
    // Hapus semua karakter non-digit
    value = value.replace(/\D/g, '');
    
    // Jika diawali 0, ganti dengan 62
    if (value.startsWith('0')) {
        value = '62' + value.substring(1);
    }
    // Jika tidak diawali 62 dan tidak kosong, tambahkan 62
    else if (!value.startsWith('62') && value.length > 0) {
        value = '62' + value;
    }
    
    this.value = value;
});

// ============================================
// LOAD DATA WILAYAH DENGAN NILAI EXISTING
// ============================================
async function loadDataWilayah() {
  const currentProvinsi = $('#current_provinsi').val();
  const currentKabupaten = $('#current_kabupaten').val();
  const currentKecamatan = $('#current_kecamatan').val();
  const currentDesa = $('#current_desa').val();

  try {
    // 1. Load Provinsi
    await loadProvinsi();
    
    // 2. Set nilai provinsi dan load kabupaten jika ada
    if (currentProvinsi) {
      $('#provinsi').val(currentProvinsi).trigger('change');
      await loadKabupaten(currentProvinsi);
      
      // 3. Set nilai kabupaten dan load kecamatan jika ada
      if (currentKabupaten) {
        $('#kabupaten').val(currentKabupaten).trigger('change');
        await loadKecamatan(currentKabupaten);
        
        // 4. Set nilai kecamatan dan load desa jika ada
        if (currentKecamatan) {
          $('#kecamatan').val(currentKecamatan).trigger('change');
          await loadDesa(currentKecamatan);
          
          // 5. Set nilai desa jika ada
          if (currentDesa) {
            $('#kel_desa').val(currentDesa).trigger('change');
          }
        }
      }
    }
  } catch (error) {
    console.error('Error loading wilayah data:', error);
  }
}

// ============================================
// LOAD DATA WILAYAH
// ============================================

// Load Provinsi
function loadProvinsi() {
  return new Promise((resolve, reject) => {
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
          
          resolve();
        } else {
          reject('Failed to load provinsi');
        }
      },
      error: function(xhr, status, error) {
        console.error('Error loading provinsi:', error);
        reject(error);
      }
    });
  });
}

// Load Kabupaten
function loadKabupaten(provinsiKode) {
  return new Promise((resolve, reject) => {
    const $kabupaten = $('#kabupaten');
    const $kecamatan = $('#kecamatan');
    const $desa = $('#kel_desa');
    
    // Reset dropdown berikutnya hanya jika bukan saat load pertama
    if (!$('#current_kabupaten').val()) {
      $kabupaten.empty().append('<option value="">Pilih Kabupaten/Kota</option>').prop('disabled', true);
      $kecamatan.empty().append('<option value="">Pilih Kecamatan</option>').prop('disabled', true);
      $desa.empty().append('<option value="">Pilih Kelurahan/Desa</option>').prop('disabled', true);
    }

    if (!provinsiKode) {
      resolve();
      return;
    }

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
          $kabupaten.empty().append('<option value="">Pilih Kabupaten/Kota</option>');
          response.data.forEach(function(item) {
            $kabupaten.append(`<option value="${item.kode}">${item.nama}</option>`);
          });
          $kabupaten.prop('disabled', false);
          resolve();
        } else {
          reject('Failed to load kabupaten');
        }
      },
      error: function(xhr, status, error) {
        console.error('Error loading kabupaten:', error);
        reject(error);
      }
    });
  });
}

// Load Kecamatan
function loadKecamatan(kabupatenKode) {
  return new Promise((resolve, reject) => {
    const $kecamatan = $('#kecamatan');
    const $desa = $('#kel_desa');
    
    // Reset dropdown berikutnya hanya jika bukan saat load pertama
    if (!$('#current_kecamatan').val()) {
      $kecamatan.empty().append('<option value="">Pilih Kecamatan</option>').prop('disabled', true);
      $desa.empty().append('<option value="">Pilih Kelurahan/Desa</option>').prop('disabled', true);
    }

    if (!kabupatenKode) {
      resolve();
      return;
    }

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
          $kecamatan.empty().append('<option value="">Pilih Kecamatan</option>');
          response.data.forEach(function(item) {
            $kecamatan.append(`<option value="${item.kode}">${item.nama}</option>`);
          });
          $kecamatan.prop('disabled', false);
          resolve();
        } else {
          reject('Failed to load kecamatan');
        }
      },
      error: function(xhr, status, error) {
        console.error('Error loading kecamatan:', error);
        reject(error);
      }
    });
  });
}

// Load Desa
function loadDesa(kecamatanKode) {
  return new Promise((resolve, reject) => {
    const $desa = $('#kel_desa');
    
    // Reset dropdown hanya jika bukan saat load pertama
    if (!$('#current_desa').val()) {
      $desa.empty().append('<option value="">Pilih Kelurahan/Desa</option>').prop('disabled', true);
    }

    if (!kecamatanKode) {
      resolve();
      return;
    }

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
          $desa.empty().append('<option value="">Pilih Kelurahan/Desa</option>');
          response.data.forEach(function(item) {
            $desa.append(`<option value="${item.kode}">${item.nama}</option>`);
          });
          $desa.prop('disabled', false);
          resolve();
        } else {
          reject('Failed to load desa');
        }
      },
      error: function(xhr, status, error) {
        console.error('Error loading desa:', error);
        reject(error);
      }
    });
  });
}

// ============================================
// EVENT LISTENERS UNTUK DROPDOWN BERTINGKAT
// ============================================

// Event: Provinsi berubah -> Load Kabupaten
$('#provinsi').on('change', function() {
  const provinsiKode = $(this).val();
  
  // Reset nilai current untuk mencegah auto-select saat perubahan manual
  $('#current_kabupaten').val('');
  $('#current_kecamatan').val('');
  $('#current_desa').val('');
  
  // Reset dropdown
  $('#kabupaten').empty().append('<option value="">Pilih Kabupaten/Kota</option>').prop('disabled', true).trigger('change');
  $('#kecamatan').empty().append('<option value="">Pilih Kecamatan</option>').prop('disabled', true).trigger('change');
  $('#kel_desa').empty().append('<option value="">Pilih Kelurahan/Desa</option>').prop('disabled', true).trigger('change');
  
  if (provinsiKode) {
    loadKabupaten(provinsiKode);
  }
});

// Event: Kabupaten berubah -> Load Kecamatan
$('#kabupaten').on('change', function() {
  const kabupatenKode = $(this).val();
  
  // Reset nilai current
  $('#current_kecamatan').val('');
  $('#current_desa').val('');
  
  // Reset dropdown
  $('#kecamatan').empty().append('<option value="">Pilih Kecamatan</option>').prop('disabled', true).trigger('change');
  $('#kel_desa').empty().append('<option value="">Pilih Kelurahan/Desa</option>').prop('disabled', true).trigger('change');
  
  if (kabupatenKode) {
    loadKecamatan(kabupatenKode);
  }
});

// Event: Kecamatan berubah -> Load Desa
$('#kecamatan').on('change', function() {
  const kecamatanKode = $(this).val();
  
  // Reset nilai current
  $('#current_desa').val('');
  
  // Reset dropdown
  $('#kel_desa').empty().append('<option value="">Pilih Kelurahan/Desa</option>').prop('disabled', true).trigger('change');
  
  if (kecamatanKode) {
    loadDesa(kecamatanKode);
  }
});

// ============================================
// VALIDASI INPUT
// ============================================

// Validasi NIK - hanya angka
document.getElementById('nik').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '');
});

// Validasi Telepon - hanya angka
document.getElementById('telepon').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '');
});

// Format RT/RW
document.getElementById('rt_rw').addEventListener('input', function(e) {
  let value = this.value;
  value = value.replace(/[^\d\/]/g, '');
  
  const slashCount = (value.match(/\//g) || []).length;
  if (slashCount > 1) {
    value = value.substring(0, value.lastIndexOf('/'));
  }
  
  this.value = value;
});

// Auto format RT/RW on blur
document.getElementById('rt_rw').addEventListener('blur', function() {
  let value = this.value.trim();
  if (!value) return;
  
  let parts = value.split('/');
  let rt = parts[0] ? parts[0].replace(/\D/g, '') : '';
  let rw = parts[1] ? parts[1].replace(/\D/g, '') : '';
  
  if (rt) {
    rt = rt.substring(0, 3).padStart(3, '0');
    if (!rw) {
      rw = '001';
    } else {
      rw = rw.substring(0, 3).padStart(3, '0');
    }
    this.value = rt + '/' + rw;
  } else {
    this.value = '';
  }
});

// ============================================
// FILE UPLOAD PREVIEW
// ============================================

document.getElementById('fileKTP').addEventListener('change', function(e) {
  const file = e.target.files[0];
  const previewContainer = document.getElementById('previewContainer');
  const currentKtpPreview = document.getElementById('currentKtpPreview');
  const previewImage = document.getElementById('previewImage');
  const pdfPreview = document.getElementById('pdfPreview');
  const pdfFileName = document.getElementById('pdfFileName');

  if (file) {
    // Validasi ukuran
    if (file.size > 1024 * 1024) {
showAlert('File terlalu besar. Maksimal 1 MB.', 'warning');
      e.target.value = '';
      previewContainer.classList.remove('show');
      return;
    }

    // Sembunyikan preview KTP lama
    if (currentKtpPreview) {
      currentKtpPreview.style.display = 'none';
    }

    const fileType = file.type;

    if (fileType.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = function(event) {
        previewImage.src = event.target.result;
        previewImage.style.display = 'block';
        pdfPreview.style.display = 'none';
        previewContainer.classList.add('show');
      };
      reader.readAsDataURL(file);
    } else if (fileType === 'application/pdf') {
      pdfFileName.textContent = file.name;
      previewImage.style.display = 'none';
      pdfPreview.style.display = 'block';
      previewContainer.classList.add('show');
    }
  } else {
    previewContainer.classList.remove('show');
    if (currentKtpPreview) {
      currentKtpPreview.style.display = 'block';
    }
  }
});

function removePreview() {
  const currentKtpPreview = document.getElementById('currentKtpPreview');
  document.getElementById('fileKTP').value = '';
  document.getElementById('previewContainer').classList.remove('show');
  
  if (currentKtpPreview) {
    currentKtpPreview.style.display = 'block';
  }
}

// ============================================
// FORM SUBMISSION
// ============================================

document.getElementById('editProfilForm').addEventListener('submit', async function(e) {
  e.preventDefault();

  const btnSubmit = document.getElementById('btnSubmit');
  const spinner = document.getElementById('loadingSpinner');

  const nik = document.getElementById('nik').value;
  const telepon = document.getElementById('telepon').value;
  const email = document.getElementById('email').value;
  const rtRw = document.getElementById('rt_rw').value;
  
  // Validasi wilayah
  const provinsi = $('#provinsi').val();
  const kabupaten = $('#kabupaten').val();
  const kecamatan = $('#kecamatan').val();
  const desa = $('#kel_desa').val();

  if (!provinsi || !kabupaten || !kecamatan || !desa) {
   showAlert("Semua field alamat wajib dipilih.", 'warning');
    return;
  }

  // Validasi NIK
  if (nik.length !== 16) {
    showAlert("NIK harus 16 digit.", 'warning');
    return;
  }

  // Validasi RT/RW
  const rtRwPattern = /^\d{3}\/\d{3}$/;
  if (!rtRwPattern.test(rtRw)) {
    showAlert("Format RT/RW tidak valid. Contoh: 002/006", 'warning');
    return;
  }


  // Disable button + loading
  btnSubmit.disabled = true;
  btnSubmit.classList.add('btn-disabled');
  spinner.style.display = 'inline-block';

  const formData = new FormData(this);

  try {
    const response = await fetch('process/proses_edit_profil.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.success) {
      showAlert(result.message || 'Profil berhasil diperbarui!', 'success');
      
      // Jika ada redirect (karena NIK berubah)
      if (result.redirect) {
        setTimeout(() => {
         window.location.href = result.redirect;
       }, 2000);
      } else {
       setTimeout(() => {
         window.location.reload();
       }, 2000);
      }
    } else {
     showAlert(result.message || 'Gagal memperbarui profil.', 'danger');
     btnSubmit.disabled = false;
     btnSubmit.classList.remove('btn-disabled');
     spinner.style.display = 'none';
    }
  } catch (error) {
    console.error(error);
   showAlert('Terjadi kesalahan pada server: ' + error.message, 'danger');
   btnSubmit.disabled = false;
   btnSubmit.classList.remove('btn-disabled');
   spinner.style.display = 'none';
  } 
});