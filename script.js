document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('print-form');
    
    const uploadZone = document.querySelector('.upload-zone');
    uploadZone.addEventListener('click', () => {
        alert('File selection triggered!');
    });
});