/**
 * Ana JavaScript Dosyası
 * 
 * Ortak JavaScript fonksiyonları ve interaktif öğeler
 */

// DOM Yüklendiğinde çalış
document.addEventListener('DOMContentLoaded', function() {
    
    // Auto-hide alerts after 5 seconds
    autoHideAlerts();
    
    // Confirm delete actions
    setupDeleteConfirmations();
    
    // Mobile menu toggle
    setupMobileMenu();
    
    // Table row click handlers
    setupTableRowClicks();
    
    // Form validation
    setupFormValidation();
});

/**
 * Alert mesajlarını otomatik gizle
 */
function autoHideAlerts() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
        // Sadece success ve info mesajlarını otomatik gizle
        if (alert.classList.contains('alert-success') || alert.classList.contains('alert-info')) {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                alert.style.transition = 'all 0.3s ease';
                
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }, 5000);
        }
    });
}

/**
 * Silme işlemleri için onay dialog'u
 */
function setupDeleteConfirmations() {
    const deleteButtons = document.querySelectorAll('[data-confirm-delete]');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm-delete') || 
                          'Bu kaydı silmek istediğinizden emin misiniz?';
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
}

/**
 * Mobil menü toggle
 */
function setupMobileMenu() {
    const menuToggle = document.querySelector('[data-mobile-menu-toggle]');
    const navbarMenu = document.querySelector('.navbar-menu');
    
    if (menuToggle && navbarMenu) {
        menuToggle.addEventListener('click', function() {
            navbarMenu.classList.toggle('active');
            this.classList.toggle('active');
        });
    }
}

/**
 * Tablo satırlarına tıklama özelliği
 */
function setupTableRowClicks() {
    const clickableRows = document.querySelectorAll('[data-href]');
    
    clickableRows.forEach(row => {
        row.style.cursor = 'pointer';
        
        row.addEventListener('click', function(e) {
            // Eğer bir buton veya linke tıklanmışsa ignore et
            if (e.target.closest('a, button')) {
                return;
            }
            
            const href = this.getAttribute('data-href');
            if (href) {
                window.location.href = href;
            }
        });
    });
}

/**
 * Form validasyonu
 */
function setupFormValidation() {
    const forms = document.querySelectorAll('[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = form.querySelectorAll('[required]');
            
            // Önceki hata mesajlarını temizle
            form.querySelectorAll('.field-error').forEach(error => error.remove());
            form.querySelectorAll('.error').forEach(field => field.classList.remove('error'));
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    
                    const errorMsg = document.createElement('span');
                    errorMsg.className = 'field-error';
                    errorMsg.style.color = 'var(--error-color)';
                    errorMsg.style.fontSize = '0.875rem';
                    errorMsg.style.marginTop = 'var(--spacing-xs)';
                    errorMsg.style.display = 'block';
                    errorMsg.textContent = 'Bu alan zorunludur.';
                    
                    field.parentElement.appendChild(errorMsg);
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                
                // İlk hatalı alana scroll yap
                const firstError = form.querySelector('.error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
            }
        });
    });
}

/**
 * Loading indicator göster
 */
function showLoading(message = 'Yükleniyor...') {
    const overlay = document.createElement('div');
    overlay.id = 'loading-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    `;
    
    overlay.innerHTML = `
        <div style="background: white; padding: 2rem; border-radius: 0.5rem; text-align: center;">
            <div style="border: 4px solid #f3f3f3; border-top: 4px solid #2563eb; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 1rem;"></div>
            <p style="margin: 0; color: #374151;">${message}</p>
        </div>
    `;
    
    // Spin animasyonu
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
    
    document.body.appendChild(overlay);
}

/**
 * Loading indicator gizle
 */
function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.remove();
    }
}

/**
 * Toast bildirimi göster
 */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.cssText = `
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        padding: 1rem 1.5rem;
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;
    
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#2563eb'
    };
    
    toast.style.borderLeft = `4px solid ${colors[type] || colors.info}`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Export fonksiyonları (ihtiyaç halinde)
window.showLoading = showLoading;
window.hideLoading = hideLoading;
window.showToast = showToast;