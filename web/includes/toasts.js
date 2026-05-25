/**
 * ============================================================================
 * Toast Notification System using SweetAlert2
 * ============================================================================
 * Centralized toast and confirmation dialog management
 * Usage: showToast("Message", "success"); or confirmDelete(...);
 * ============================================================================
 */

/**
 * Show a toast notification
 * @param {string} title - Toast message title or full message
 * @param {string} type - 'success', 'error', 'warning', 'info'
 * @param {string} subtitle - Optional subtitle/details
 * @param {number} timer - Auto-dismiss time in ms (0 = no auto-dismiss)
 */
function showToast(title, type = 'info', subtitle = '', timer = 3000) {
    // Determine icon based on type
    const iconMap = {
        'success': 'success',
        'error': 'error',
        'warning': 'warning',
        'info': 'info'
    };

    Swal.fire({
        title: title,
        html: subtitle ? `<small>${subtitle}</small>` : undefined,
        icon: iconMap[type] || 'info',
        toast: true,
        position: 'top-right',
        showConfirmButton: false,
        showCloseButton: true,
        timer: timer,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        },
        willClose: (toast) => {
            toast.style.animation = 'fadeOut 0.3s ease forwards';
        },
        customClass: {
            container: `toast-${type}`,
            popup: 'toast-popup',
            title: 'toast-title',
            htmlContainer: 'toast-html',
            closeButton: 'toast-close-btn'
        }
    });
}

/**
 * Show a success toast (shorthand)
 * @param {string} message - Success message
 * @param {number} timer - Auto-dismiss time (default 3000ms)
 */
function showSuccess(message, timer = 3000) {
    showToast(message, 'success', '', timer);
    playSuccessSound();
}

/**
 * Show an error toast (shorthand)
 * @param {string} message - Error message
 * @param {string} details - Optional error details
 * @param {number} timer - Auto-dismiss time (default 4000ms)
 */
function showError(message, details = '', timer = 4000) {
    showToast(message, 'error', details, timer);
}

/**
 * Show a warning toast (shorthand)
 * @param {string} message - Warning message
 * @param {number} timer - Auto-dismiss time (default 4000ms)
 */
function showWarning(message, timer = 4000) {
    showToast(message, 'warning', '', timer);
}

/**
 * Show an info toast (shorthand)
 * @param {string} message - Info message
 * @param {number} timer - Auto-dismiss time (default 3000ms)
 */
function showInfo(message, timer = 3000) {
    showToast(message, 'info', '', timer);
}

/**
 * Show a confirmation dialog
 * @param {object} config - Configuration object
 *   - title: Dialog title
 *   - message: Dialog message
 *   - confirmText: Confirm button text (default: "Delete")
 *   - cancelText: Cancel button text (default: "Cancel")
 *   - type: 'delete', 'confirm', 'warning' (affects color)
 *   - onConfirm: Callback function when confirmed
 *   - confirmButtonColor: Button color override
 */
function showConfirm(config) {
    const {
        title = 'Confirm Action',
        message = 'Are you sure?',
        confirmText = 'Delete',
        cancelText = 'Cancel',
        type = 'warning',
        onConfirm = null,
        confirmButtonColor = '#ef4444'
    } = config;

    // Color mapping
    const colorMap = {
        'delete': '#ef4444',    // Red
        'warning': '#f97316',   // Orange
        'confirm': '#22c55e'    // Green
    };

    Swal.fire({
        title: title,
        html: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: colorMap[type] || confirmButtonColor,
        cancelButtonColor: '#9ca3af',
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        allowOutsideClick: false,
        allowEscapeKey: true,
        customClass: {
            popup: 'confirm-popup',
            title: 'confirm-title',
            htmlContainer: 'confirm-html'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
        }
    });
}

/**
 * Show delete confirmation dialog
 * @param {string} itemName - Name of item to delete (e.g., "Student", "Event")
 * @param {function} onConfirm - Callback to execute on confirmation
 */
function confirmDelete(itemName, onConfirm) {
    showConfirm({
        title: `Delete ${itemName}?`,
        message: `You are about to delete this ${itemName.toLowerCase()}. This action cannot be undone.`,
        confirmText: 'Delete',
        cancelText: 'Cancel',
        type: 'delete',
        onConfirm: onConfirm
    });
}

/**
 * Show a loading dialog with spinner
 * @param {string} message - Loading message
 */
function showLoading(message = 'Loading...') {
    Swal.fire({
        title: message,
        icon: 'info',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: async () => {
            await Swal.showLoading();
        },
        customClass: {
            popup: 'loading-popup'
        }
    });
}

/**
 * Close the current modal (loading dialog, etc.)
 */
function closeLoading() {
    Swal.close();
}

/**
 * Play success sound (short beep)
 */
function playSuccessSound() {
    // Create a simple beep sound using Web Audio API
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();

        oscillator.connect(gain);
        gain.connect(audioContext.destination);

        oscillator.frequency.value = 800; // Hz
        oscillator.type = 'sine';

        gain.gain.setValueAtTime(0.3, audioContext.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);

        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.1);
    } catch (e) {
        console.log('Audio context not available');
    }
}

/**
 * Highlight element with green border (for scanner feedback)
 * @param {HTMLElement} element - Element to highlight
 * @param {number} duration - Duration in ms (default 1000ms)
 */
function highlightSuccess(element, duration = 1000) {
    const originalBorder = element.style.border;
    element.style.border = '3px solid #22c55e';
    element.style.boxShadow = '0 0 10px rgba(34, 197, 94, 0.5)';

    setTimeout(() => {
        element.style.border = originalBorder;
        element.style.boxShadow = '';
    }, duration);
}

/**
 * Show scanner-specific success indicator
 * @param {string} message - Success message (e.g., "Student scan successful")
 */
function showScannerSuccess(message) {
    showSuccess(message, 2500);
    playSuccessSound();

    // Optional: Add visual feedback to scanner container if it exists
    const scannerBox = document.getElementById('reader');
    if (scannerBox) {
        highlightSuccess(scannerBox, 800);
    }
}

/**
 * Initialize toast system on page load
 */
function initializeToastSystem() {
    // SweetAlert2 is already loaded from CDN
    console.log('Toast system initialized');
}

// Auto-initialize on DOM ready
document.addEventListener('DOMContentLoaded', initializeToastSystem);
