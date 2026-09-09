document.getElementById('compactSessionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const startTime = document.getElementById('compactStart').value;
    const endTime = document.getElementById('compactEnd').value;
    
    if (!form.checkValidity()) {
        e.stopPropagation();
        form.classList.add('was-validated');
        return;
    }
    
    if (startTime >= endTime) {
        alert('End time must be after start');
        return;
    }
    
    alert('Time saved!');
    form.classList.remove('was-validated');
});