<?php
/**
 * ============================================================================
 * Toast Response Helper - For AJAX API Endpoints
 * ============================================================================
 * Use this helper to return JSON responses that trigger toast notifications
 * on the client side.
 * ============================================================================
 */

/**
 * Send a JSON response with toast notification
 * @param array $data - Response data
 * @param string $toastType - 'success', 'error', 'warning', 'info'
 * @param string $toastTitle - Toast title/message
 * @param string $toastDetails - Optional details/error message
 * @param int $httpStatus - HTTP status code
 */
function send_json_response(
    $data = [],
    $toastType = 'info',
    $toastTitle = 'Operation completed',
    $toastDetails = '',
    $httpStatus = 200
) {
    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=utf-8');

    $response = [
        'success' => in_array($toastType, ['success', 'info']),
        'data' => $data,
        'toast' => [
            'type' => $toastType,
            'title' => $toastTitle,
            'details' => $toastDetails,
            'show' => true
        ]
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Success response with toast
 * @param array $data - Response data
 * @param string $message - Success message
 */
function json_success($data = [], $message = 'Operation successful') {
    send_json_response($data, 'success', $message, '', 200);
}

/**
 * Error response with toast
 * @param string $message - Error message
 * @param string $details - Error details
 * @param int $httpStatus - HTTP status code (default 400)
 */
function json_error($message = 'An error occurred', $details = '', $httpStatus = 400) {
    send_json_response([], 'error', $message, $details, $httpStatus);
}

/**
 * Warning response with toast
 * @param array $data - Response data
 * @param string $message - Warning message
 */
function json_warning($data = [], $message = 'Warning') {
    send_json_response($data, 'warning', $message, '', 200);
}

/**
 * Info response with toast
 * @param array $data - Response data
 * @param string $message - Info message
 */
function json_info($data = [], $message = 'Information') {
    send_json_response($data, 'info', $message, '', 200);
}

/**
 * Validation error response
 * @param array $errors - Validation errors (field => message)
 */
function json_validation_error($errors) {
    $errorMessage = is_array($errors) ? implode(', ', $errors) : $errors;
    send_json_response(
        ['errors' => $errors],
        'error',
        'Validation failed',
        $errorMessage,
        422
    );
}

/**
 * Database error response (don't expose real error to frontend)
 * @param string $internalError - Error for logging
 */
function json_database_error($internalError = '') {
    if ($internalError) {
        error_log('Database Error: ' . $internalError);
    }
    send_json_response(
        [],
        'error',
        'Database error',
        'Please try again later',
        500
    );
}

/**
 * Not found response
 * @param string $resource - Resource that was not found
 */
function json_not_found($resource = 'Resource') {
    send_json_response(
        [],
        'error',
        "$resource not found",
        'The requested resource does not exist',
        404
    );
}

/**
 * Unauthorized response
 */
function json_unauthorized() {
    send_json_response(
        [],
        'error',
        'Unauthorized',
        'You do not have permission to perform this action',
        403
    );
}

/**
 * Unauthenticated response (not logged in)
 */
function json_unauthenticated() {
    send_json_response(
        [],
        'error',
        'Session expired',
        'Please log in again',
        401
    );
}
