class FileUploader {
    constructor() {
        // const file = new File(
        // ["<html><body>Test</body></html>"],
        // "test.html",
        // { type: "text/html" }
        // );

        this.allFiles = [];
        // this.allFiles.push(file);
        
        this.fileInput = document.getElementById('file-explorer');
        this.dropZone = document.querySelector('.upload-zone');
        this.fileListUI = document.getElementById('file-list');
        this.errorDisplay = document.getElementById('error-display');
        this.statusText = document.getElementById('upload-status');
        this.submitBtn = document.getElementById('generate-btn');
        this.init();
    }

    init() {
        ['dragover', 'drop'].forEach(name => {
            this.dropZone.addEventListener(name, (e) => e.preventDefault());
        });
        this.dropZone.addEventListener('drop', (e) => {
            this.addFiles(e.dataTransfer.files)
        });
        this.fileInput.addEventListener('change', (e) => this.addFiles(e.target.files));

        this.submitBtn.disabled = true;
        this.submitBtn.style.opacity = "0.5";
        this.submitBtn.style.cursor = "not-allowed";

        this.render();
    }

    addFiles(newFiles) {
        const allowedExtensions = /\.(html|htm)$/i;
        const validFiles = [];
        let rejectedFiles = [];

        Array.from(newFiles).forEach(file => {
            if (allowedExtensions.test(file.name)) {
                validFiles.push(file);
            } else {
                rejectedFiles.push(file.name);
            }
        });

        if (rejectedFiles.length > 0) {
            this.errorDisplay.textContent = `Греда, маняк! Само файлове с разширение .html!`;
            this.errorDisplay.style.display = 'block';
        } else {
            this.errorDisplay.style.display = 'none';
        }

        this.allFiles = [...this.allFiles, ...validFiles];
        this.render();
    }

    removeFile(index) {
        this.allFiles.splice(index, 1);
        if (this.allFiles.length === 0) this.errorDisplay.style.display = 'none';
        this.render();
    }

    formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    render() {
        this.fileListUI.innerHTML = '';
        
        const hasFiles = this.allFiles.length > 0;
        this.statusText.style.display = hasFiles ? 'none' : 'block';

        this.allFiles.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'file-item';
            
            item.innerHTML = `
                <div class="file-info">
                    <span class="file-name">${file.name}</span>
                    <span class="file-size">${this.formatBytes(file.size)}</span>
                </div>
                <button type="button" class="btn-remove">Премахни</button>
            `;
            
            item.querySelector('.btn-remove').onclick = () => this.removeFile(index);
            this.fileListUI.appendChild(item);
        });

        this.submitBtn.disabled = !hasFiles;
        this.submitBtn.style.opacity = hasFiles ? "1" : "0.5";
        this.submitBtn.style.cursor = hasFiles ? "pointer" : "not-allowed";

        this.syncInput();
    }

    syncInput() {
        const dt = new DataTransfer();
        this.allFiles.forEach(file => dt.items.add(file));
        this.fileInput.files = dt.files;
    }
}

new FileUploader();