const sidebar = document.getElementById('sidebar');
const hoverZone = document.getElementById('hoverZone');
const editForm = document.getElementById('editForm');
let selectedAccountId = null;
let pressTimer = null;
let menuTimer = null;

if (hoverZone) {
    hoverZone.addEventListener('mouseenter', () => sidebar?.classList.add('open'));
}

if (sidebar) {
    sidebar.addEventListener('mouseleave', () => sidebar.classList.remove('open'));
}

document.querySelectorAll('.chip, .chipf').forEach(chip => {
    chip.addEventListener('click', () => {
        document.querySelectorAll('.chip, .chipf').forEach(item => item.classList.remove('active-chip'));
        chip.classList.add('active-chip');
    });
});

document.querySelectorAll('.seg').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelector('.active-seg')?.classList.remove('active-seg');
        btn.classList.add('active-seg');
    });
});

function togglePanel(id) {
    const panel = document.getElementById(id);
    panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
}

function toggleForm() {
    togglePanel('addForm');
}

function toggleSavingsForm() {
    togglePanel('addSavingsForm');
}

function toggleIconPicker() {
    togglePanel('iconModal');
    showFirstIconGroup('iconModal');
}

function toggleSavingsIconPicker() {
    togglePanel('savingsIconModal');
    showFirstIconGroup('savingsIconModal');
}

function toggleEditIconPicker() {
    togglePanel('editIconModal');
    showFirstIconGroup('editIconModal');
}

function showIcons(category) {
    document.querySelectorAll('#iconModal .icon-group').forEach(group => {
        group.style.display = group.dataset.category === category ? 'flex' : 'none';
    });
}

function showSavingsIcons(category) {
    document.querySelectorAll('#savingsIconModal .icon-group').forEach(group => {
        group.style.display = group.dataset.category === category ? 'flex' : 'none';
    });
}

function showEditIcons(category) {
    document.querySelectorAll('#editIconModal .icon-group').forEach(group => {
        group.style.display = group.dataset.category === category ? 'flex' : 'none';
    });
}

function showFirstIconGroup(pickerId) {
    const picker = document.getElementById(pickerId);
    const firstGroup = picker?.querySelector('.icon-group');

    if (picker?.style.display !== 'none' && firstGroup && !picker.querySelector('.icon-group[style*="flex"]')) {
        document.querySelectorAll(`#${pickerId} .icon-group`).forEach(group => {
            group.style.display = group === firstGroup ? 'flex' : 'none';
        });
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

document.querySelectorAll('.icon-option').forEach(option => {
    option.addEventListener('click', function () {
        selectIcon(this, 'selectedIcon', 'selectedColor', 'selectedIconPreview', 'iconModal');
    });
});

document.querySelectorAll('.icon-option-savings').forEach(option => {
    option.addEventListener('click', function () {
        selectIcon(this, 'selectedSavingsIcon', 'selectedSavingsColor', 'selectedSavingsIconPreview', 'savingsIconModal');
    });
});

document.querySelectorAll('.icon-option-edit-account').forEach(option => {
    option.addEventListener('click', function () {
        selectIcon(this, 'editIcon', 'editColor', 'editIconPreview', 'editIconModal');
    });
});

document.querySelectorAll('.item').forEach(item => {
    item.addEventListener('mousedown', function (event) {
        pressTimer = setTimeout(() => showMenu(event, this), 600);
    });

    item.addEventListener('mouseup', () => clearTimeout(pressTimer));
    item.addEventListener('mouseleave', () => clearTimeout(pressTimer));
    item.addEventListener('dblclick', function () {
        selectedAccountId = this.dataset.id;
        editAccount();
    });
});

function showMenu(event, element) {
    const menu = document.getElementById('contextMenu');
    selectedAccountId = element.dataset.id;

    clearTimeout(menuTimer);
    menu.style.top = `${event.pageY}px`;
    menu.style.left = `${event.pageX}px`;
    menu.style.display = 'block';

    menuTimer = setTimeout(() => {
        menu.style.display = 'none';
    }, 3000);
}

function deleteAccount() {
    if (!selectedAccountId || !confirm('Видалити рахунок?')) return;

    fetch(`/accounts/${selectedAccountId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    }).then(() => location.reload());
}

function editAccount() {
    if (!selectedAccountId) return;

    fetch(`/accounts/${selectedAccountId}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('editName').value = data.name;
            document.getElementById('editBalance').value = data.balance;
            document.getElementById('editIcon').value = data.icon;
            document.getElementById('editColor').value = data.color;
            document.getElementById('editIconPreview').src = `/images/icons/${data.icon}`;
            document.getElementById('contextMenu').style.display = 'none';
            document.getElementById('editModal').style.display = 'flex';
        });
}

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

        fetch(`/accounts/${selectedAccountId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: new FormData(this)
        }).then(() => location.reload());
    });
}

window.addEventListener('click', (event) => {
    const contextMenu = document.getElementById('contextMenu');

    if (contextMenu && !contextMenu.contains(event.target) && !event.target.closest('.item')) {
        contextMenu.style.display = 'none';
    }
});

function openModal(type) {
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('type').value = type;
}

function closeModal() {
    document.getElementById('modal').style.display = 'none';
}

const balanceHitPoints = [];
let balanceTooltip;

function money(value) {
    return `${window.currencySymbol || '₴'} ${(value || 0).toLocaleString('uk-UA', { maximumFractionDigits: 2 })}`;
}

function chartTooltip() {
    if (!balanceTooltip) {
        balanceTooltip = document.createElement('div');
        balanceTooltip.className = 'chart-tooltip';
        document.body.appendChild(balanceTooltip);
    }

    return balanceTooltip;
}

function showBalanceTooltip(event, point) {
    const tooltip = chartTooltip();
    tooltip.innerHTML = `${money(point.value)}<span>${point.label}</span>`;
    tooltip.style.left = `${event.clientX}px`;
    tooltip.style.top = `${event.clientY}px`;
    tooltip.classList.add('show');
}

function hideBalanceTooltip() {
    chartTooltip().classList.remove('show');
}

function drawSmoothLine(ctx, points, connectFirst = false) {
    points.forEach((point, index) => {
        if (index === 0) {
            connectFirst ? ctx.lineTo(point.x, point.y) : ctx.moveTo(point.x, point.y);
            return;
        }

        const previous = points[index - 1];
        const controlX = previous.x + (point.x - previous.x) / 2;
        ctx.bezierCurveTo(controlX, previous.y, controlX, point.y, point.x, point.y);
    });
}

function drawBalanceChart() {
    const canvas = document.getElementById('balanceChart');

    if (!canvas || !window.balanceChartData) return;

    const labels = window.balanceChartData.labels || [];
    const values = window.balanceChartData.values || [];
    const ctx = canvas.getContext('2d');
    const ratio = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * ratio;
    canvas.height = rect.height * ratio;
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.clearRect(0, 0, rect.width, rect.height);
    balanceHitPoints.length = 0;

    const padding = { top: 24, right: 26, bottom: 38, left: 62 };
    const width = rect.width - padding.left - padding.right;
    const height = rect.height - padding.top - padding.bottom;
    const min = Math.min(...values, 0);
    const max = Math.max(...values, 1);
    const span = max - min || 1;

    const panelGradient = ctx.createLinearGradient(0, 0, 0, rect.height);
    panelGradient.addColorStop(0, 'rgba(37, 99, 235, 0.04)');
    panelGradient.addColorStop(1, 'rgba(20, 184, 166, 0.02)');
    ctx.fillStyle = panelGradient;
    ctx.fillRect(0, 0, rect.width, rect.height);

    ctx.strokeStyle = '#dbeafe';
    ctx.lineWidth = 1;
    ctx.font = '12px Arial';
    ctx.fillStyle = '#64748b';
    ctx.textAlign = 'right';

    for (let i = 0; i <= 4; i++) {
        const y = padding.top + (height / 4) * i;
        const value = max - (span / 4) * i;
        ctx.beginPath();
        ctx.moveTo(padding.left, y);
        ctx.lineTo(padding.left + width, y);
        ctx.stroke();
        ctx.fillText(Math.round(value).toLocaleString('uk-UA'), padding.left - 10, y + 4);
    }

    const points = values.map((value, index) => ({
        x: padding.left + (values.length === 1 ? width : (width / (values.length - 1)) * index),
        y: padding.top + height - ((value - min) / span) * height,
    }));

    if (points.length) {
        const areaGradient = ctx.createLinearGradient(0, padding.top, 0, padding.top + height);
        areaGradient.addColorStop(0, 'rgba(37, 99, 235, 0.24)');
        areaGradient.addColorStop(0.65, 'rgba(20, 184, 166, 0.08)');
        areaGradient.addColorStop(1, 'rgba(37, 99, 235, 0)');

        ctx.beginPath();
        ctx.moveTo(points[0].x, padding.top + height);
        drawSmoothLine(ctx, points, true);
        ctx.lineTo(points[points.length - 1].x, padding.top + height);
        ctx.closePath();
        ctx.fillStyle = areaGradient;
        ctx.fill();

        ctx.strokeStyle = '#1d4ed8';
        ctx.lineWidth = 3;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.beginPath();
        drawSmoothLine(ctx, points);
        ctx.stroke();

        points.forEach((point, index) => {
            ctx.beginPath();
            ctx.arc(point.x, point.y, 7, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(37, 99, 235, 0.14)';
            ctx.fill();
            ctx.beginPath();
            ctx.arc(point.x, point.y, 4, 0, Math.PI * 2);
            ctx.fillStyle = '#ffffff';
            ctx.fill();
            ctx.strokeStyle = '#2563eb';
            ctx.lineWidth = 2;
            ctx.stroke();

            balanceHitPoints.push({
                x: point.x,
                y: point.y,
                label: labels[index],
                value: values[index],
            });
        });
    }

    ctx.fillStyle = '#64748b';
    ctx.textAlign = 'center';
    labels.forEach((label, index) => {
        const x = padding.left + (labels.length === 1 ? width : (width / (labels.length - 1)) * index);
        ctx.fillText(label, x, padding.top + height + 26);
    });
}

document.getElementById('balanceChart')?.addEventListener('mousemove', event => {
    const canvas = event.currentTarget;
    const rect = canvas.getBoundingClientRect();
    const x = event.clientX - rect.left;
    const y = event.clientY - rect.top;
    const point = balanceHitPoints.find(item => Math.hypot(x - item.x, y - item.y) <= 14);

    if (!point) {
        canvas.style.cursor = 'default';
        hideBalanceTooltip();
        return;
    }

    canvas.style.cursor = 'pointer';
    showBalanceTooltip(event, point);
});

document.getElementById('balanceChart')?.addEventListener('mouseleave', event => {
    event.currentTarget.style.cursor = 'default';
    hideBalanceTooltip();
});

let chartResizeFrame = null;

drawBalanceChart();
window.addEventListener('resize', () => {
    if (chartResizeFrame) {
        cancelAnimationFrame(chartResizeFrame);
    }

    chartResizeFrame = requestAnimationFrame(drawBalanceChart);
});
