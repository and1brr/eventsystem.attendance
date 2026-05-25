/**
 * ============================================================================
 * Toast Client-Side Handler for AJAX Responses
 * ============================================================================
 * Automatically handles toast notifications from JSON API responses
 * Usage: See examples at bottom of file
 * ============================================================================
 */

/**
 * Process AJAX response and show appropriate toast
 * @param {object} response - JSON response object from server
 * @param {string} defaultMessage - Default message if toast not in response
 * @returns {boolean} - True if response indicates success
 */
function handleToastResponse(response, defaultMessage = 'Operation completed') {
    // Check if response has toast data
    if (response && response.toast && response.toast.show) {
        const toast = response.toast;
        showToast(toast.title, toast.type, toast.details, 
                  toast.type === 'error' ? 4000 : 3000);
    } else if (defaultMessage) {
        showToast(defaultMessage, response?.success ? 'success' : 'info');
    }

    return response?.success ?? false;
}

/**
 * Make AJAX request and handle toast response automatically
 * @param {object} config - Fetch configuration
 *   - url: endpoint URL
 *   - method: HTTP method (GET, POST, etc)
 *   - body: request body (for POST)
 *   - headers: additional headers
 *   - successCallback: function to call on success
 *   - errorCallback: function to call on error
 *   - showLoading: show loading spinner (default: true)
 */
async function fetchWithToast(config) {
    const {
        url = '',
        method = 'GET',
        body = null,
        headers = {},
        successCallback = null,
        errorCallback = null,
        showLoading = true
    } = config;

    try {
        if (showLoading) {
            showLoading('Processing...');
        }

        const fetchConfig = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                ...headers
            }
        };

        if (body) {
            fetchConfig.body = JSON.stringify(body);
        }

        const response = await fetch(url, fetchConfig);
        const data = await response.json();

        if (showLoading) {
            closeLoading();
        }

        // Handle toast from response
        handleToastResponse(data);

        // Call success callback if provided and response is successful
        if (data.success && typeof successCallback === 'function') {
            successCallback(data.data || data);
        } else if (!data.success && typeof errorCallback === 'function') {
            errorCallback(data);
        }

        return data;
    } catch (error) {
        console.error('Fetch error:', error);

        if (showLoading) {
            closeLoading();
        }

        showError('Network error', 'Please check your connection and try again');

        if (typeof errorCallback === 'function') {
            errorCallback({ error: error.message });
        }

        return { success: false, error: error.message };
    }
}

/**
 * Handle form submission with AJAX and toast notifications
 * @param {HTMLFormElement} form - Form element
 * @param {object} config - Configuration
 *   - url: form submission URL (default: form.action)
 *   - method: HTTP method (default: form.method)
 *   - successCallback: function to call on success
 *   - errorCallback: function to call on error
 *   - successMessage: custom success message
 */
function handleFormSubmitWithToast(form, config = {}) {
    const {
        url = form.action || '',
        method = form.method || 'POST',
        successCallback = null,
        errorCallback = null,
        successMessage = 'Form submitted successfully'
    } = config;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Validate form
        if (!form.checkValidity()) {
            showWarning('Please fill in all required fields');
            return false;
        }

        const formData = new FormData(form);
        const body = Object.fromEntries(formData);

        // Disable submit button
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn?.textContent;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
        }

        try {
            const result = await fetchWithToast({
                url: url,
                method: method,
                body: body,
                showLoading: false,
                successCallback: (data) => {
                    form.reset();
                    if (typeof successCallback === 'function') {
                        successCallback(data);
                    }
                },
                errorCallback: (data) => {
                    if (typeof errorCallback === 'function') {
                        errorCallback(data);
                    }
                }
            });

            if (result.success) {
                showSuccess(successMessage);
            }
        } finally {
            // Re-enable submit button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    });
}

/**
 * Delete item with confirmation and toast
 * @param {object} config - Configuration
 *   - url: deletion endpoint
 *   - itemName: name/description of item to delete
 *   - successCallback: function to call on successful deletion
 *   - errorCallback: function to call on error
 */
function deleteWithConfirmation(config) {
    const {
        url = '',
        itemName = 'item',
        successCallback = null,
        errorCallback = null
    } = config;

    confirmDelete(itemName, async () => {
        showLoading('Deleting...');

        try {
            const response = await fetch(url, { method: 'DELETE' });
            const data = await response.json();

            closeLoading();
            handleToastResponse(data);

            if (data.success && typeof successCallback === 'function') {
                successCallback(data.data || data);
            } else if (!data.success && typeof errorCallback === 'function') {
                errorCallback(data);
            }
        } catch (error) {
            closeLoading();
            console.error('Delete error:', error);
            showError('Delete failed', 'Please try again');

            if (typeof errorCallback === 'function') {
                errorCallback({ error: error.message });
            }
        }
    });
}

/**
 * Show validation errors as individual toasts or grouped message
 * @param {object} errors - Validation errors (field => message)
 * @param {boolean} individual - Show individual toasts or one grouped message
 */
function showValidationErrors(errors, individual = false) {
    if (typeof errors === 'string') {
        showWarning(errors);
        return;
    }

    if (individual) {
        Object.values(errors).forEach(message => {
            showWarning(message, 4000);
        });
    } else {
        const errorList = Object.values(errors).join('\n• ');
        showWarning('Please fix the following errors:\n• ' + errorList, 5000);
    }
}

/**
 * Debounce AJAX calls (e.g., for search input)
 * @param {function} fn - Function to debounce
 * @param {number} delay - Delay in milliseconds
 */
function debounceAjax(fn, delay = 500) {
    let timeoutId;
    return function(...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
}

/**
 * Example Usage in HTML:
 * 
 * // Simple AJAX call with toast
 * fetchWithToast({
 *     url: '/api/students',
 *     method: 'POST',
 *     body: { name: 'John', matric_no: '123456' },
 *     successCallback: (data) => {
 *         // Handle successful response
 *         console.log('Student added:', data);
 *     }
 * });
 * 
 * // Form submission with toast
 * const form = document.getElementById('studentForm');
 * handleFormSubmitWithToast(form, {
 *     url: '/api/students',
 *     method: 'POST',
 *     successMessage: 'Student added successfully',
 *     successCallback: (data) => {
 *         // Refresh student list, etc.
 *     }
 * });
 * 
 * // Delete with confirmation
 * deleteWithConfirmation({
 *     url: '/api/students/123',
 *     itemName: 'Student',
 *     successCallback: () => {
 *         // Refresh list after deletion
 *     }
 * });
 * 
 * // Validation errors
 * showValidationErrors({
 *     email: 'Invalid email address',
 *     password: 'Password too short'
 * });
 */
