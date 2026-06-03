const searchInput = document.getElementById('searchInput');
const chips = document.querySelectorAll('.chip, .chipf');
const operationsList = document.getElementById('operationsList');
let currentOpId = null;
let pressTimer = null;
let menuTimer = null;

function syncCategoryOptions(typeSelect, categorySelect, preferredValue = null) {
    if (!typeSelect || !categorySelect) {
        return;
    }

    const type = typeSelect.value;
    let firstVisible = null;
    let fallbackValue = null;
    let preferredIsVisible = false;

    Array.from(categorySelect.options).forEach(option => {
        const isFallback = option.dataset.fallback === 'true';
        const isVisible = isFallback || option.dataset.type === type;

        option.hidden = !isVisible;
        option.disabled = !isVisible;

        if (isFallback) {
            fallbackValue = option.value;
        }

        if (isVisible && !isFallback && firstVisible === null) {
            firstVisible = option.value;
        }

        if (isVisible && option.value === preferredValue) {
            preferredIsVisible = true;
        }
    });

    if (preferredValue && preferredIsVisible) {
        categorySelect.value = preferredValue;
        return;
    }

    const selectedOption = categorySelect.selectedOptions[0];

    const selectedIsFallback = selectedOption?.dataset.fallback === 'true';

    if (!selectedOption || selectedOption.disabled || (selectedIsFallback && firstVisible !== null && preferredValue === null)) {
        categorySelect.value = firstVisible ?? fallbackValue ?? '';
    }
}

const addTypeSelect = document.getElementById('addType');
const addCategorySelect = document.getElementById('addCategory');
const editTypeSelect = document.getElementById('editType');
const editCategorySelect = document.getElementById('editCategory');

syncCategoryOptions(addTypeSelect, addCategorySelect);

addTypeSelect?.addEventListener('change', () => syncCategoryOptions(addTypeSelect, addCategorySelect));
editTypeSelect?.addEventListener('change', () => syncCategoryOptions(editTypeSelect, editCategorySelect));

searchInput.addEventListener('input', () => {
    const term = searchInput.value.toLowerCase();

    document.querySelectorAll('#operationsList .op').forEach(op => {
        const name = op.querySelector('.op-name').innerText.toLowerCase();
        const amount = op.querySelector('.op-amt').innerText.toLowerCase();
        const date = op.querySelector('.op-date').innerText.toLowerCase();
        const sub = op.querySelector('.op-sub') ? op.querySelector('.op-sub').innerText.toLowerCase() : '';
        const combined = `${name} ${amount} ${date} ${sub}`;

        op.style.display = combined.includes(term) ? 'flex' : 'none';
    });
});

chips.forEach(chip => {
    chip.addEventListener('click', () => {
        const filter = chip.dataset.filter;

        chips.forEach(c => c.classList.remove('active-chip'));
        chip.classList.add('active-chip');

        document.querySelectorAll('#operationsList .op').forEach(op => {
            op.style.display = filter === 'all' || op.dataset.type === filter ? 'flex' : 'none';
        });
    });
});

document.querySelectorAll('.op').forEach(op => {
    op.addEventListener('mousedown', (event) => {
        pressTimer = setTimeout(() => showContextMenu(event, op), 600);
    });

    op.addEventListener('mouseup', () => clearTimeout(pressTimer));
    op.addEventListener('mouseleave', () => clearTimeout(pressTimer));
    op.addEventListener('dblclick', () => openEditModal(op.dataset.id));
});

function toggleAddOperationModal() {
    const modal = document.getElementById('addOperationModal');
    modal.style.display = modal.style.display === 'none' ? 'flex' : 'none';
}

function showContextMenu(event, op) {
    const menu = document.getElementById('contextMenu');
    currentOpId = op.dataset.id;

    clearTimeout(menuTimer);
    menu.style.display = 'block';
    menu.style.left = `${event.pageX}px`;
    menu.style.top = `${event.pageY}px`;

    menuTimer = setTimeout(() => {
        menu.style.display = 'none';
    }, 3000);
}

function editOperation(id) {
    openEditModal(id);
}

function deleteOperation(id) {
    if (!confirm('Видалити операцію?')) return;

    fetch(`/operations/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    }).then(res => res.json()).then(data => {
        if (data.success) location.reload();
    });
}

function editAccount() {
    openEditModal(currentOpId);
}

function deleteAccount() {
    if (currentOpId) {
        deleteOperation(currentOpId);
    }
}

function openEditModal(id) {
    const op = document.querySelector(`.op[data-id='${id}']`);

    if (!op) return;

    document.getElementById('contextMenu').style.display = 'none';
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = op.dataset.name;
    document.getElementById('editAmount').value = op.dataset.amount;
    document.getElementById('editAccount').value = op.dataset.account;
    document.getElementById('editType').value = op.dataset.type;
    syncCategoryOptions(editTypeSelect, editCategorySelect, op.dataset.category);
    document.getElementById('editMethod').value = op.dataset.method;
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

document.getElementById('editForm').addEventListener('submit', function (event) {
    event.preventDefault();

    const id = document.getElementById('editId').value;

    fetch(`/operations/${id}`, {
        method: 'PUT',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            category_id: document.getElementById('editCategory').value,
            account_id: document.getElementById('editAccount').value,
            name: document.getElementById('editName').value,
            amount: document.getElementById('editAmount').value,
            type: document.getElementById('editType').value,
            method: document.getElementById('editMethod').value
        })
    })
        .then(res => {
            if (!res.ok) {
                throw new Error('Помилка запиту');
            }

            return res.json();
        })
        .then(data => {
            if (data.budget_warning) {
                alert(data.budget_warning);
            }

            location.reload();
        })
        .catch(error => {
            console.error(error);
            alert('Не вдалося зберегти зміни');
        });
});

window.addEventListener('click', (event) => {
    const editModal = document.getElementById('editModal');
    const addModal = document.getElementById('addOperationModal');
    const contextMenu = document.getElementById('contextMenu');

    if (event.target === editModal) {
        closeEditModal();
    }

    if (event.target === addModal) {
        toggleAddOperationModal();
    }

    if (!contextMenu.contains(event.target) && !event.target.closest('.op')) {
        contextMenu.style.display = 'none';
    }
});
