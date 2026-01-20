document.addEventListener('DOMContentLoaded', () => {
    const printForm = document.getElementById('print-form');
    const fileInput = document.getElementById('file-explorer');

    printForm.addEventListener('submit', async (e) => {
        // Спираме презареждането на страницата
        e.preventDefault();

        console.log("Стартиране на генерирането... luh calm");

        // 1. Събираме всички данни от формата автоматично
        const formData = new FormData(printForm);
        
        // 2. Добавяме файловете ръчно, ако не са хванати автоматично
        if (fileInput.files.length > 0) {
            for (let i = 0; i < fileInput.files.length; i++) {
                formData.append('files[]', fileInput.files[i]);
            }
        }

        // 3. Подготвяме обекта за метаданните, който твоят PHP клас очаква
        // Тук мапваме имената от HTML-а към ключовете за MetadataBuilder
        const metaInput = {
            placement: formData.get('metadata_placement') || 'none', // Трябва да добавиш name="metadata_placement" на селекта
            title: formData.get('title'),
            author: formData.get('author'),
            course: formData.get('course'),
            source: window.location.href, // Пример за източник
            context: {
                generatedAt: new Date().toISOString().slice(0, 16).replace('T', ' ')
            }
        };

        // Добавяме ги в FormData като JSON низ, за да е лесно на PHP-то
        formData.append('meta_input', JSON.stringify(metaInput));

        try {
            //====================================================
            //===== ТУКА ТРЯБВА ДА ДОБАВИМ ЛЕГИТ УРЛ–А ===========
            //====================================================
            const response = await fetch('backend/generate.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error('Сървърна грешка');

            const result = await response.json();
            
            // 5. Визуализираме резултата в preview-area
            updatePreview(result);
            
            console.log("Maznichka batyo, готово е!");

        } catch (error) {
            console.error("Греда:", error);
            alert("Нещо се счупи при генерирането. Lacone.");
        }
    });
});

/**
 * Примерна функция за обновяване на прегледа
 */
function updatePreview(data) {
    const previewArea = document.querySelector('.preview-area');
    if (data.lines) {
        previewArea.innerHTML = `<pre style="font-family: monospace; white-space: pre-wrap;">${data.lines.join('\n')}</pre>`;
    }
}