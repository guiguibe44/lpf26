document.addEventListener('DOMContentLoaded', () => {
    const wrappers = document.querySelectorAll('.field-image');

    wrappers.forEach((wrapper) => {
        const input = wrapper.querySelector('input[type="file"]');
        if (!input) {
            return;
        }

        let preview = wrapper.querySelector('[data-ea-image-live-preview]');
        if (!preview) {
            preview = document.createElement('img');
            preview.setAttribute('data-ea-image-live-preview', '1');
            preview.style.maxWidth = '120px';
            preview.style.maxHeight = '120px';
            preview.style.marginTop = '8px';
            preview.style.borderRadius = '8px';
            preview.style.objectFit = 'cover';
            preview.style.display = 'none';
            input.insertAdjacentElement('afterend', preview);
        }

        input.addEventListener('change', (event) => {
            const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
            if (!file) {
                preview.removeAttribute('src');
                preview.style.display = 'none';
                return;
            }

            if (!file.type.startsWith('image/')) {
                preview.removeAttribute('src');
                preview.style.display = 'none';
                return;
            }

            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';
        });
    });
});
