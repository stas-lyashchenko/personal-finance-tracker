let selectedCatId = null;
const editForm = document.getElementById('editForm');

function togglePanel(id) {
    const panel = document.getElementById(id);
    panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
}

function toggleExpenseForm() {
    togglePanel('addExpenseForm');
}

function toggleIncomeForm() {
    togglePanel('addIncomeForm');
}

function toggleExpenseIconPicker() {
    togglePanel('expenseIconModal');
    showFirstIconGroup('expenseIconModal');
}

function toggleIncomeIconPicker() {
    togglePanel('incomeIconModal');
    showFirstIconGroup('incomeIconModal');
}

function toggleEditIconPicker() {
    togglePanel('editIconModal');
    showFirstIconGroup('editIconModal');
}

function showExpenseIcons(category) {
    showIconsInPicker('expenseIconModal', category);
}

function showIncomeIcons(category) {
    showIconsInPicker('incomeIconModal', category);
}

function showEditIcons(category) {
    showIconsInPicker('editIconModal', category);
}

function showIconsInPicker(pickerId, category) {
    document.querySelectorAll(`#${pickerId} .icon-group`).forEach(group => {
        group.style.display = group.dataset.category === category ? 'flex' : 'none';
    });
}

function showFirstIconGroup(pickerId) {
    const picker = document.getElementById(pickerId);
    const firstGroup = picker?.querySelector('.icon-group');

    if (picker?.style.display !== 'none' && firstGroup && !picker.querySelector('.icon-group[style*="flex"]')) {
        showIconsInPicker(pickerId, firstGroup.dataset.category);
    }
}

function selectIcon(option, iconInputId, colorInputId, previewId, pickerId) {
    const icon = option.dataset.icon;
    const color = option.dataset.color;

    document.getElementById(iconInputId).value = icon;
    document.getElementById(colorInputId).value = color;
    document.getElementById(previewId).src = `/images/icons/${icon}`;
    document.getElementById(pickerId).style.display = 'none';
}

document.querySelectorAll('.icon-option-expense').forEach(option => {
    option.addEventListener('click', function () {
        selectIcon(this, 'selectedExpenseIcon', 'selectedExpenseColor', 'selectedExpenseIconPreview', 'expenseIconModal');
    });
});

document.querySelectorAll('.icon-option-income').forEach(option => {
    option.addEventListener('click', function () {
        selectIcon(this, 'selectedIncomeIcon', 'selectedIncomeColor', 'selectedIncomeIconPreview', 'incomeIconModal');
    });
});

document.querySelectorAll('.icon-option-edit').forEach(option => {
    option.addEventListener('click', function () {
        selectIcon(this, 'editIcon', 'editColor', 'editIconPreview', 'editIconModal');
    });
});

document.querySelectorAll('.icon-btn.edit').forEach((btn, index) => {
    btn.addEventListener('click', function () {
        const cat = document.querySelectorAll('.cat')[index];
        selectedCatId = cat.dataset.id;

        const name = cat.querySelector('.cat-name').innerText;
        const amountText = cat.querySelector('.cat-amount').innerText.replace(/[^\d.,-]/g, '').replace(',', '.');
        const iconSrc = cat.querySelector('.cat-ico img').src;
        const icon = iconSrc.split('/').slice(-2).join('/');
        const color = cat.querySelector('.cat-ico').classList[1] || 'gray';

        document.getElementById('editId').value = selectedCatId;
        document.getElementById('editName').value = name;
        document.getElementById('editAmount').value = parseFloat(amountText) || 0;
        document.getElementById('editIconPreview').src = iconSrc;
        document.getElementById('editIcon').value = icon;
        document.getElementById('editColor').value = color;
        document.getElementById('editModal').style.display = 'flex';
    });
});

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

document.getElementById('editModal')?.addEventListener('click', function (event) {
    if (event.target === this) {
        closeEditModal();
    }
});

if (editForm) {
    editForm.addEventListener('submit', function (event) {
        event.preventDefault();

        const formData = new FormData(this);
        formData.append('_method', 'PUT');

        fetch(`/categories/${selectedCatId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Помилка редагування');
                }
            })
            .catch(error => {
                console.error(error);
                alert('Помилка мережі або сервера');
            });
    });
}

document.querySelectorAll('.icon-btn.delete').forEach((btn, index) => {
    btn.addEventListener('click', function () {
        const cat = document.querySelectorAll('.cat')[index];
        const catId = cat.dataset.id;

        if (!confirm('Видалити категорію?')) return;

        fetch(`/categories/${catId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Помилка при видаленні категорії');
                }
            });
    });
});
