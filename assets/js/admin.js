// assets/js/admin.js — Admin Panel JavaScript

// Auto-dismiss alerts after 4 seconds
document.addEventListener('DOMContentLoaded', function () {
    const alert = document.getElementById('adminFlash');
    if (alert) setTimeout(() => alert.remove(), 4000);

    // Sidebar toggle for mobile
    const toggle  = document.querySelector('.sidebar-toggle');
    const sidebar = document.getElementById('adminSidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
        });
    }
});

// Confirm delete prompts are handled inline via onclick="return confirm(...)"
// Image preview before upload
const imageInput = document.querySelector('input[type="file"][name="image"]');
if (imageInput) {
    imageInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            let preview = document.getElementById('imagePreview');
            if (!preview) {
                preview = document.createElement('img');
                preview.id = 'imagePreview';
                preview.style.cssText = 'height:80px;border-radius:6px;margin-top:8px;border:1px solid #dee2e6';
                this.parentElement.appendChild(preview);
            }
            preview.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}
