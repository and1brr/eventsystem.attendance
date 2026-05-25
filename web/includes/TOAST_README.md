# Toast Notification System - Complete Integration Guide

## System Overview

The toast notification system has been fully integrated using **SweetAlert2** as the underlying library. This modern, feature-rich library provides toasts, modals, and confirmation dialogs.

### Files Created

1. **`includes/toasts.js`** - Main toast helper functions
2. **`includes/toasts.css`** - Custom styling (white/green theme, animations)
3. **`includes/ajax_toasts.js`** - AJAX helper for automatic toast handling
4. **`includes/json_helpers.php`** - Server-side JSON response builders
5. **`TOAST_IMPLEMENTATION_GUIDE.js`** - Detailed implementation examples

### Files Modified

1. **`partials/header.php`** - Added SweetAlert2 CDN includes
2. **`partials/footer.php`** - Added toast helper script includes
3. **`web/login.php`** - Integrated toast system
4. **`web/scan.php`** - Added scanner-specific toasts and highlights

---

## Quick Start

### 1. Basic Toast Usage (Client-Side)

```javascript
// Success
showSuccess('Item saved successfully');

// Error
showError('Failed to save', 'Database error occurred');

// Warning
showWarning('Duplicate entry detected');

// Info
showInfo('Processing your request...');
```

### 2. Server-Side Response (JSON)

```php
<?php
require_once 'includes/json_helpers.php';

// Success response (auto-shows green toast)
json_success(['id' => 123], 'Student added successfully');

// Error response (auto-shows red toast)
json_error('Failed to add student', 'Matric number already exists');

// Validation errors
json_validation_error([
    'name' => 'Name is required',
    'email' => 'Invalid email format'
]);
```

### 3. AJAX Form Submission with Automatic Toast

```javascript
await fetchWithToast({
    url: '/api/students.php',
    method: 'POST',
    body: { name: 'John', matric_no: '12345' },
    successCallback: (data) => {
        console.log('Student created:', data);
        refreshStudentsList();
    }
});
```

### 4. Delete Confirmation

```javascript
deleteWithConfirmation({
    url: '/api/students/123',
    itemName: 'Student Records',
    successCallback: () => {
        location.reload();
    }
});
```

---

## API Response Format

All API endpoints return this JSON structure:

```json
{
  "success": true,
  "data": {
    "student_id": 123,
    "name": "John Doe"
  },
  "toast": {
    "type": "success|error|warning|info",
    "title": "Action completed successfully",
    "details": "Additional error details if needed",
    "show": true
  }
}
```

The client automatically:
1. Displays the toast with appropriate color/icon
2. Calls success/error callbacks
3. Manages loading states
4. Handles errors gracefully

---

## Integration Checklist

### Core Integration (DONE ✅)
- [x] SweetAlert2 library loaded in header
- [x] Toast CSS styling applied
- [x] Toast helper JS functions available
- [x] AJAX toast handler available
- [x] JSON response helpers created

### Page Integration Status

#### Login Page ✅
- [x] Toast system integrated
- [x] Password toggle implemented
- [x] Loading state working

#### Dashboard ✅
- [x] Toast includes added
- [x] Logout confirmation with toast
- [x] Welcome message ready

#### Scanner Page ✅
- [x] Scanner success toasts
- [x] Error toasts for invalid QR
- [x] Camera permission error handling
- [x] Green border highlight on success
- [x] Success sound on valid scan
- [x] Scan cooldown warning

#### Events Page (TODO)
- [ ] Add event success toast
- [ ] Edit event success toast
- [ ] Delete event confirmation + toast
- [ ] Event validation error toasts

#### Student Management (TODO)
- [ ] Add student success toast
- [ ] Edit student success toast
- [ ] Delete student confirmation + toast
- [ ] Validation error toasts

#### Logs/History (TODO)
- [ ] Export success toast
- [ ] Filter error handling
- [ ] Refresh action toast

---

## Usage Examples by Page

### Events CRUD Page

```javascript
// In /web/events.php

// Add Event
document.getElementById('addEventForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const body = Object.fromEntries(formData);
    
    const result = await fetchWithToast({
        url: '/api/events.php',
        method: 'POST',
        body: body,
        successCallback: (data) => {
            e.target.reset();
            setTimeout(() => location.reload(), 1500);
        }
    });
});

// Delete Event
function deleteEvent(eventId, eventName) {
    deleteWithConfirmation({
        url: `/api/events.php?id=${eventId}`,
        itemName: `Event: ${eventName}`,
        successCallback: () => {
            setTimeout(() => location.reload(), 1000);
        }
    });
}

// Edit Event
document.getElementById('editEventForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const eventId = document.getElementById('eventId').value;
    const formData = new FormData(e.target);
    const body = Object.fromEntries(formData);
    
    const result = await fetchWithToast({
        url: `/api/events.php?id=${eventId}`,
        method: 'PUT',
        body: body,
        successCallback: (data) => {
            e.target.reset();
            setTimeout(() => location.reload(), 1500);
        }
    });
});
```

### Student Management

```javascript
// In /web/students.php or API

// Add/Edit Student
document.getElementById('studentForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const isEdit = !!document.getElementById('studentId').value;
    const method = isEdit ? 'PUT' : 'POST';
    const url = isEdit 
        ? `/api/students.php?id=${document.getElementById('studentId').value}`
        : '/api/students.php';
    
    await fetchWithToast({
        url: url,
        method: method,
        body: new FormData(e.target),
        successCallback: () => {
            e.target.reset();
            loadStudents();
        }
    });
});

// Delete Student
function deleteStudent(studentId, studentName) {
    deleteWithConfirmation({
        url: `/api/students.php?id=${studentId}`,
        itemName: `Student: ${studentName}`,
        successCallback: () => {
            loadStudents();
        }
    });
}

// Load Students with error handling
async function loadStudents() {
    try {
        const response = await fetch('/api/students.php');
        const data = await response.json();
        
        if (data.success) {
            renderStudentsList(data.data);
        } else {
            showError('Failed to load students', data.toast.details);
        }
    } catch (error) {
        showError('Network error', error.message);
    }
}
```

### Real-time Input Validation

```javascript
// Validate inputs as user types
document.getElementById('email')?.addEventListener('blur', async (e) => {
    const email = e.target.value;
    
    if (!email) {
        showWarning('Email is required');
        return;
    }
    
    // Check if email already exists
    const response = await fetch(`/api/check-email.php?email=${encodeURIComponent(email)}`);
    const data = await response.json();
    
    if (!data.success) {
        showWarning(data.toast.title);
        e.target.classList.add('border-red-500');
    } else {
        e.target.classList.remove('border-red-500');
    }
});
```

---

## Toast Types Reference

### Success Toast (Green)
```javascript
showSuccess('Action completed successfully');
```
Use for: Student added, event created, attendance recorded, file exported

### Error Toast (Red)
```javascript
showError('Action failed', 'Optional details about the error');
```
Use for: Validation failures, database errors, network errors, permission denied

### Warning Toast (Yellow/Orange)
```javascript
showWarning('Please review this before proceeding');
```
Use for: Duplicate entries, unsaved changes, invalid inputs, retry needed

### Info Toast (Blue)
```javascript
showInfo('Processing your request');
```
Use for: Status updates, help tips, generic notifications

### Confirmation Dialog
```javascript
showConfirm({
    title: 'Delete this student?',
    message: 'This action cannot be undone',
    confirmText: 'Delete',
    cancelText: 'Cancel',
    type: 'delete', // 'delete', 'warning', 'confirm'
    onConfirm: () => { /* delete logic */ }
});
```

---

## Styling Customization

All toasts use the white/green theme defined in `includes/toasts.css`:

- **Success**: Green (#22c55e) on light green background
- **Error**: Red (#ef4444) on light red background
- **Warning**: Orange (#f97316) on light orange background
- **Info**: Blue (#3b82f6) on light blue background

### Customize Colors

Edit `includes/toasts.css` section `.toast-success`, `.toast-error`, etc.:

```css
.toast-success .toast-popup {
    border-left-color: #YOUR_COLOR !important;
    background: linear-gradient(135deg, #LIGHT_COLOR 0%, #LIGHTER_COLOR 100%) !important;
}
```

---

## Advanced Features

### Loading Dialog
```javascript
showLoading('Processing your request...');
// ... do work ...
closeLoading();
```

### Highlight Element on Success
```javascript
highlightSuccess(element, 1000); // Flash green border for 1 second
```

### Play Success Sound
```javascript
playSuccessSound(); // Automatic beep on successful scan
```

### Debounced AJAX Calls
```javascript
const debouncedSearch = debounceAjax(async (query) => {
    await fetchWithToast({
        url: '/api/search.php?q=' + encodeURIComponent(query),
        successCallback: (results) => updateUI(results)
    });
}, 500); // Wait 500ms after user stops typing
```

---

## Troubleshooting

### Toasts Not Showing?
1. Verify `includes/toasts.js` is loaded in footer
2. Check browser console for errors (F12)
3. Ensure SweetAlert2 CDN link is not blocked

### Wrong Color/Style?
1. Check `includes/toasts.css` is loaded
2. Verify your custom CSS doesn't override toast styles
3. Clear browser cache (Ctrl+Shift+Delete)

### AJAX Calls Not Working?
1. Check API endpoint returns valid JSON
2. Include `includes/json_helpers.php` in your API
3. Use `json_success()`, `json_error()` functions

### Scanner not showing success feedback?
1. Ensure `showScannerSuccess()` is called (not just `showSuccess()`)
2. Verify scanner element has id="reader"
3. Check browser allows sound playback

---

## Security Notes

- All user inputs are sanitized with `htmlspecialchars()`
- Database errors are never exposed to users
- Session tokens must be validated before operations
- Use `require_login()` to protect endpoints
- Use `require_role()` to check permissions

---

## Performance Tips

1. Use `debounceAjax()` for search/filter inputs
2. Batch API requests when possible
3. Cache frequently accessed data
4. Use proper error handling to avoid retry storms
5. Set appropriate toast timeouts (3-5 seconds)

---

## Next Steps

1. Add toasts to remaining CRUD pages (Events, Students, Users)
2. Create API endpoints using `json_helpers.php`
3. Implement debounced search with validation toasts
4. Add export/import success toasts
5. Add real-time status updates in dashboard

---

**Last Updated**: May 2026
**System**: QR Attendance Management
**Library**: SweetAlert2 v11
**Theme**: White/Green Professional
