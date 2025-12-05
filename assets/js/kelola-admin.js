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

// HANDLE EDIT BUTTON
document.querySelectorAll('.btn-edit').forEach(function(btn) {
  btn.addEventListener('click', function() {
    const nip = this.dataset.nip;
    const nama = this.dataset.nama;
    const email = this.dataset.email;
    const whatsapp = this.dataset.whatsapp;
    
    // Update form
    document.getElementById('formTitle').textContent = 'Edit Data Admin';
    document.getElementById('formAction').value = 'update';
    document.getElementById('adminNip').value = nip;
    document.getElementById('nip').value = nip;
    document.getElementById('nip').setAttribute('readonly', 'readonly');
    document.getElementById('nama_lengkap').value = nama;
    document.getElementById('email').value = email;
    document.getElementById('no_wa').value = whatsapp;
    document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-circle me-1"></i> Update Admin';
    document.getElementById('cancelBtn').style.display = 'inline-block';
    
    // Scroll to form
    document.getElementById('adminForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
});

// HANDLE CANCEL BUTTON
document.getElementById('cancelBtn').addEventListener('click', function() {
  resetForm();
});

// HANDLE DELETE BUTTON
let deleteNip = null;

document.querySelectorAll('.btn-delete').forEach(function(btn) {
  btn.addEventListener('click', function() {
    deleteNip = this.dataset.nip;
    const nama = this.dataset.nama;
    
    showDeleteConfirm(nama, deleteNip);
  });
});

// Konfirmasi hapus dengan modal custom
function showDeleteConfirm(adminName, nip) {
  const confirmModal = `
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-body text-center p-4">
            <div class="fs-1 mb-3">⚠️</div>
            <h5 class="mb-3">Konfirmasi Hapus</h5>
            <p class="mb-1">Apakah Anda yakin ingin menghapus akun admin:</p>
            <p class="fw-bold">${adminName}</p>
          </div>
          <div class="modal-footer border-0 justify-content-center gap-2">
            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-danger px-4" id="confirmDeleteAction">
              <i class="bi bi-trash3 me-1"></i> Hapus
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
  
  const existingModal = document.getElementById('confirmDeleteModal');
  if (existingModal) existingModal.remove();
  
  document.body.insertAdjacentHTML('beforeend', confirmModal);
  const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
  modal.show();
  
  // Handle konfirmasi hapus
  document.getElementById('confirmDeleteAction').addEventListener('click', function() {
    window.location.href = 'kelola-admin.php?action=delete&nip=' + encodeURIComponent(nip);
  });
  
  document.getElementById('confirmDeleteModal').addEventListener('hidden.bs.modal', function() {
    this.remove();
  });
}

// RESET FORM FUNCTION
function resetForm() {
  document.getElementById('formTitle').textContent = 'Tambah Data Admin';
  document.getElementById('formAction').value = 'add';
  document.getElementById('adminNip').value = '';
  document.getElementById('nip').value = '';
  document.getElementById('nip').removeAttribute('readonly');
  document.getElementById('nama_lengkap').value = '';
  document.getElementById('email').value = '';
  document.getElementById('no_wa').value = '';
  document.getElementById('submitBtn').innerHTML = '<i class="bi bi-person-plus me-1"></i> Daftar Akun';
  document.getElementById('cancelBtn').style.display = 'none';
}

// AUTO DISMISS ALERTS
setTimeout(function() {
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(function(alert) {
    const bsAlert = new bootstrap.Alert(alert);
    bsAlert.close();
  });
}, 5000);

// FORMAT NOMOR WHATSAPP
document.getElementById('no_wa').addEventListener('input', function() {
  // Hanya izinkan angka
  this.value = this.value.replace(/\D/g, '');
});

// VALIDASI FORM SEBELUM SUBMIT
document.getElementById('adminForm').addEventListener('submit', function(e) {
  const action = document.getElementById('formAction').value;
  const nip = document.getElementById('nip').value.trim();
  const nama = document.getElementById('nama_lengkap').value.trim();
  const email = document.getElementById('email').value.trim();
  const noWa = document.getElementById('no_wa').value.trim();
  
  // Validasi NIP (hanya untuk tambah)
  if (action === 'add' && !nip) {
    e.preventDefault();
    showAlert('NIP tidak boleh kosong.');
    return false;
  }
  
  // Validasi Nama
  if (!nama) {
    e.preventDefault();
    showAlert('Nama lengkap tidak boleh kosong.');
    return false;
  }
  
  // Validasi Email
  if (!email) {
    e.preventDefault();
    showAlert('Email tidak boleh kosong.');
    return false;
  }
  
  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailPattern.test(email)) {
    e.preventDefault();
    showAlert('Format email tidak valid.');
    return false;
  }
  
  // Validasi Nomor WhatsApp
  if (!noWa) {
    e.preventDefault();
    showAlert('Nomor WhatsApp tidak boleh kosong.');
    return false;
  }
  
  const waPattern = /^(08|628)\d{8,12}$/;
  if (!waPattern.test(noWa)) {
    e.preventDefault();
    showAlert('Format nomor WhatsApp tidak valid.<br>Contoh: 081234567890 atau 6281234567890');
    return false;
  }
  
  return true;
});