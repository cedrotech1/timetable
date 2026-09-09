// Utility functions for the application

function showAlert(title, message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <strong>${title}</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Find the first .card-body in the main content area
    const cardBody = document.querySelector('main .card-body');
    if (cardBody) {
        cardBody.insertBefore(alertDiv, cardBody.firstChild);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
}

// Function to handle AJAX errors
function handleAjaxError(error) {
    console.error('AJAX Error:', error);
    showAlert('Error', 'An error occurred while processing your request. Please try again.', 'danger');
} 