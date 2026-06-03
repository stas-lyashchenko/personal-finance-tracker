document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-modal-target]').forEach(button => {
        button.addEventListener('click', () => {
            const modal = document.getElementById(button.dataset.modalTarget);
            if (modal) modal.style.display = 'flex';
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(button => {
        button.addEventListener('click', () => {
            button.closest('.modal').style.display = 'none';
        });
    });

    window.openImportModal = function () {
        document.getElementById('importModal').style.display = 'flex';
    };

    window.closeImportModal = function () {
        document.getElementById('importModal').style.display = 'none';
    };

    const avatarButton = document.getElementById('avatarButton');
    const avatarInput = document.getElementById('avatarInput');
    const avatarForm = document.getElementById('avatarForm');
    const importFileInput = document.getElementById('file');
    const importFileName = document.getElementById('fileName');

    if (avatarButton && avatarInput && avatarForm) {
        avatarButton.addEventListener('click', () => avatarInput.click());
        avatarInput.addEventListener('change', () => {
            if (avatarInput.files.length) {
                avatarForm.submit();
            }
        });
    }

    if (importFileInput && importFileName) {
        importFileInput.addEventListener('change', () => {
            importFileName.textContent = importFileInput.files.length
                ? importFileInput.files[0].name
                : importFileName.dataset.emptyText;
        });
    }

    document.getElementById('importForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const fileInput = document.getElementById('file');

        if (!fileInput.files.length) {
            alert('ÐžÐ±ÐµÑ€Ñ–Ñ‚ÑŒ Ñ„Ð°Ð¹Ð»');
            return;
        }

        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('bank', document.getElementById('bank').value);

        fetch('/import', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: formData
        })
            .then(async res => {
                const data = await res.json();

                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'ÐŸÐ¾Ð¼Ð¸Ð»ÐºÐ° Ñ–Ð¼Ð¿Ð¾Ñ€Ñ‚Ñƒ');
                }

                return data;
            })
            .then(data => {
                const skipped = data.skipped ? `, Ð¿Ñ€Ð¾Ð¿ÑƒÑ‰ÐµÐ½Ð¾: ${data.skipped}` : '';
                const duplicates = data.duplicates ? `, äóáë³êàò³â: ${data.duplicates}` : '';
                alert('²ìïîðòîâàíî: ' + data.count + skipped + duplicates);
                location.reload();
            })
            .catch(error => alert(error.message));
    });

});

window.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
});
