const moneyFormatter = new Intl.NumberFormat('uk-UA', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

const periodLabels = {
    week: 'за поточний тиждень',
    month: 'за поточний місяць',
    year: 'за поточний рік',
    custom: 'за обраний період',
};

const palette = ['#2563eb', '#14b8a6', '#f97316', '#8b5cf6', '#ef4444', '#22c55e', '#0ea5e9'];
const chartHitRegions = {};
let chartTooltip;

function money(value) {
    return `${window.currencySymbol || '₴'} ${moneyFormatter.format(value || 0)}`;
}

function changeText(value) {
    const sign = value > 0 ? '+' : '';
    return `${sign}${value || 0}% до попереднього періоду`;
}

function fitText(text, maxLength = 18) {
    return text && text.length > maxLength ? `${text.slice(0, maxLength - 1)}…` : text;
}

function sizeCanvas(canvas) {
    const ratio = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * ratio;
    canvas.height = rect.height * ratio;
    const ctx = canvas.getContext('2d');
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    return { ctx, width: rect.width, height: rect.height };
}

function tooltip() {
    if (!chartTooltip) {
        chartTooltip = document.createElement('div');
        chartTooltip.className = 'chart-tooltip';
        document.body.appendChild(chartTooltip);
    }

    return chartTooltip;
}

function showTooltip(event, title, subtitle) {
    const el = tooltip();
    el.innerHTML = `${title}<span>${subtitle}</span>`;
    el.style.left = `${event.clientX}px`;
    el.style.top = `${event.clientY}px`;
    el.classList.add('show');
}

function hideTooltip() {
    tooltip().classList.remove('show');
}

function bindChartHover(canvas) {
    if (!canvas || canvas.dataset.hoverBound) return;

    canvas.dataset.hoverBound = '1';
    canvas.addEventListener('mousemove', event => {
        const rect = canvas.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;
        const hit = (chartHitRegions[canvas.id] || []).find(region => {
            if (region.kind === 'circle') {
                return Math.hypot(x - region.x, y - region.y) <= region.radius;
            }

            return x >= region.x && x <= region.x + region.width && y >= region.y && y <= region.y + region.height;
        });

        if (!hit) {
            canvas.style.cursor = 'default';
            hideTooltip();
            return;
        }

        canvas.style.cursor = 'pointer';
        showTooltip(event, hit.title, hit.subtitle);
    });
    canvas.addEventListener('mouseleave', () => {
        canvas.style.cursor = 'default';
        hideTooltip();
    });
}

function drawEmpty(ctx, width, height) {
    ctx.fillStyle = '#e2e8f0';
    ctx.beginPath();
    ctx.arc(width / 2, height / 2 - 10, 28, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = '#64748b';
    ctx.font = '600 14px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('Даних ще немає', width / 2, height / 2 + 34);
}

function roundRect(ctx, x, y, width, height, radius) {
    const r = Math.min(radius, height / 2, width / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + width, y, x + width, y + height, r);
    ctx.arcTo(x + width, y + height, x, y + height, r);
    ctx.arcTo(x, y + height, x, y, r);
    ctx.arcTo(x, y, x + width, y, r);
    ctx.closePath();
}

function drawHorizontalBars(canvasId, labels, values) {
    const canvas = document.getElementById(canvasId);
    const { ctx, width, height } = sizeCanvas(canvas);
    bindChartHover(canvas);
    chartHitRegions[canvasId] = [];
    ctx.clearRect(0, 0, width, height);

    if (!values.length || Math.max(...values) <= 0) {
        drawEmpty(ctx, width, height);
        return;
    }

    const padding = { top: 18, right: 42, bottom: 18, left: 132 };
    const chartWidth = width - padding.left - padding.right;
    const rowHeight = Math.min(34, (height - padding.top - padding.bottom) / values.length);
    const max = Math.max(...values, 1);

    ctx.font = '12px Arial';
    values.forEach((value, index) => {
        const y = padding.top + index * rowHeight + 6;
        const barWidth = Math.max(8, (value / max) * chartWidth);
        const color = palette[index % palette.length];

        ctx.fillStyle = '#64748b';
        ctx.textAlign = 'right';
        ctx.fillText(fitText(labels[index] || 'Без назви', 17), padding.left - 12, y + 14);

        roundRect(ctx, padding.left, y, chartWidth, 13, 7);
        ctx.fillStyle = '#e2e8f0';
        ctx.fill();

        const gradient = ctx.createLinearGradient(padding.left, 0, padding.left + barWidth, 0);
        gradient.addColorStop(0, color);
        gradient.addColorStop(1, `${color}99`);
        roundRect(ctx, padding.left, y, barWidth, 13, 7);
        ctx.fillStyle = gradient;
        ctx.fill();
        chartHitRegions[canvasId].push({
            kind: 'rect',
            x: padding.left,
            y: y - 6,
            width: chartWidth,
            height: 26,
            title: labels[index] || 'Без назви',
            subtitle: money(value),
        });

        ctx.fillStyle = '#0f172a';
        ctx.textAlign = 'left';
        ctx.font = '700 12px Arial';
        ctx.fillText(money(value), padding.left + Math.min(barWidth + 10, chartWidth - 54), y + 14);
        ctx.font = '12px Arial';
    });
}

function drawDonut(canvasId, labels, values) {
    const canvas = document.getElementById(canvasId);
    const { ctx, width, height } = sizeCanvas(canvas);
    bindChartHover(canvas);
    chartHitRegions[canvasId] = [];
    ctx.clearRect(0, 0, width, height);

    const total = values.reduce((sum, value) => sum + Math.max(0, value), 0);
    if (!total) {
        drawEmpty(ctx, width, height);
        return;
    }

    const centerX = width * 0.38;
    const centerY = height / 2;
    const radius = Math.min(width, height) * 0.28;
    let angle = -Math.PI / 2;
    const colors = ['#16a34a', '#ef4444'];

    values.forEach((value, index) => {
        const slice = (Math.max(0, value) / total) * Math.PI * 2;
        ctx.beginPath();
        ctx.moveTo(centerX, centerY);
        ctx.arc(centerX, centerY, radius, angle, angle + slice);
        ctx.closePath();
        ctx.fillStyle = colors[index % colors.length];
        ctx.fill();
        chartHitRegions[canvasId].push({
            kind: 'rect',
            x: width * 0.62,
            y: centerY - 50 + index * 58,
            width: width * 0.35,
            height: 46,
            title: labels[index] || 'Потік',
            subtitle: money(value),
        });
        angle += slice;
    });

    ctx.globalCompositeOperation = 'destination-out';
    ctx.beginPath();
    ctx.arc(centerX, centerY, radius * 0.58, 0, Math.PI * 2);
    ctx.fill();
    ctx.globalCompositeOperation = 'source-over';

    labels.forEach((label, index) => {
        const x = width * 0.68;
        const y = centerY - 34 + index * 58;
        ctx.fillStyle = colors[index % colors.length];
        roundRect(ctx, x, y - 10, 14, 14, 4);
        ctx.fill();
        ctx.fillStyle = '#64748b';
        ctx.font = '12px Arial';
        ctx.textAlign = 'left';
        ctx.fillText(label, x + 24, y);
        ctx.fillStyle = '#0f172a';
        ctx.font = '800 16px Arial';
        ctx.fillText(money(values[index] || 0), x + 24, y + 22);
    });
}

function drawAreaLine(canvasId, labels, values) {
    const canvas = document.getElementById(canvasId);
    const { ctx, width, height } = sizeCanvas(canvas);
    bindChartHover(canvas);
    chartHitRegions[canvasId] = [];
    ctx.clearRect(0, 0, width, height);

    if (!values.length) {
        drawEmpty(ctx, width, height);
        return;
    }

    const padding = { top: 22, right: 28, bottom: 42, left: 64 };
    const chartWidth = width - padding.left - padding.right;
    const chartHeight = height - padding.top - padding.bottom;
    const min = Math.min(...values, 0);
    const max = Math.max(...values, 1);
    const span = max - min || 1;

    ctx.strokeStyle = '#e2e8f0';
    ctx.lineWidth = 1;
    ctx.font = '12px Arial';
    ctx.fillStyle = '#64748b';
    ctx.textAlign = 'right';

    for (let i = 0; i <= 4; i++) {
        const value = max - (span / 4) * i;
        const y = padding.top + (chartHeight / 4) * i;
        ctx.beginPath();
        ctx.moveTo(padding.left, y);
        ctx.lineTo(padding.left + chartWidth, y);
        ctx.stroke();
        ctx.fillText(money(value), padding.left - 10, y + 4);
    }

    const points = values.map((value, index) => ({
        x: padding.left + (values.length === 1 ? chartWidth : (chartWidth / (values.length - 1)) * index),
        y: padding.top + chartHeight - ((value - min) / span) * chartHeight,
    }));

    const lineGradient = ctx.createLinearGradient(0, padding.top, 0, padding.top + chartHeight);
    lineGradient.addColorStop(0, 'rgb(37 99 235 / 22%)');
    lineGradient.addColorStop(1, 'rgb(37 99 235 / 0%)');
    ctx.beginPath();
    ctx.moveTo(points[0].x, padding.top + chartHeight);
    points.forEach(point => ctx.lineTo(point.x, point.y));
    ctx.lineTo(points[points.length - 1].x, padding.top + chartHeight);
    ctx.closePath();
    ctx.fillStyle = lineGradient;
    ctx.fill();

    ctx.strokeStyle = '#2563eb';
    ctx.lineWidth = 3;
    ctx.beginPath();
    points.forEach((point, index) => {
        index === 0 ? ctx.moveTo(point.x, point.y) : ctx.lineTo(point.x, point.y);
    });
    ctx.stroke();

    points.forEach((point, index) => {
        if (values.length > 18 && index % Math.ceil(values.length / 10) !== 0 && index !== values.length - 1) return;
        ctx.beginPath();
        ctx.arc(point.x, point.y, 4, 0, Math.PI * 2);
        ctx.fillStyle = '#fff';
        ctx.fill();
        ctx.strokeStyle = '#2563eb';
        ctx.lineWidth = 2;
        ctx.stroke();
    });

    points.forEach((point, index) => {
        chartHitRegions[canvasId].push({
            kind: 'circle',
            x: point.x,
            y: point.y,
            radius: 12,
            title: labels[index] || 'Період',
            subtitle: money(values[index]),
        });
    });

    ctx.fillStyle = '#64748b';
    ctx.font = '12px Arial';
    ctx.textAlign = 'center';
    labels.forEach((label, index) => {
        if (labels.length > 12 && index % Math.ceil(labels.length / 8) !== 0 && index !== labels.length - 1) return;
        ctx.fillText(label, points[index].x, height - 14);
    });
}

function removeCleanResultInsight() {
    document.querySelectorAll('.stats-insights .insight-card').forEach(card => {
        const title = card.querySelector('span')?.textContent?.trim().toLowerCase();

        if (title === 'чистий результат') {
            card.remove();
        }
    });
}

function removeBalanceSubtitle() {
    document.getElementById('statBalanceSub')?.remove();
}

function renderStats(period = 'month') {
    const data = window.statsData?.[period] || {};
    removeBalanceSubtitle();
    removeCleanResultInsight();
    document.getElementById('statBalance').innerText = money(data.balance);
    document.getElementById('statIncome').innerText = money(data.income);
    document.getElementById('statExpense').innerText = money(data.expense);
    document.getElementById('statIncomeSub').innerText = changeText(data.incomeChange);
    document.getElementById('statExpenseSub').innerText = changeText(data.expenseChange);
    document.getElementById('statAvgExpense').innerText = money(data.avgDailyExpense);
    document.getElementById('statSavingsRate').innerText = `${data.savingsRate || 0}%`;
    document.getElementById('statTopCategory').innerText = data.topCategory || 'Немає даних';
    document.getElementById('largestOperationPill').innerText = `Найбільша витрата: ${data.largestOperation || 'Немає даних'} · ${money(data.largestOperationValue)}`;

    drawHorizontalBars('categoryChart', data.categoryLabels || [], data.categoryValues || []);
    drawDonut('flowChart', data.flowLabels || [], data.flowValues || []);
    drawAreaLine('trendChart', data.trendLabels || [], data.trendValues || []);
}

document.querySelectorAll('#statsPeriodTabs .seg').forEach(button => {
    button.addEventListener('click', () => {
        document.querySelector('#statsPeriodTabs .active-seg')?.classList.remove('active-seg');
        button.classList.add('active-seg');
        renderStats(button.dataset.period);
    });
});

const initialPeriod = window.selectedStatsPeriod || 'month';
document.querySelector('#statsPeriodTabs .active-seg')?.classList.remove('active-seg');
document.querySelector(`#statsPeriodTabs .seg[data-period="${initialPeriod}"]`)?.classList.add('active-seg');
renderStats(initialPeriod);

window.addEventListener('resize', () => {
    const period = document.querySelector('#statsPeriodTabs .active-seg')?.dataset.period || 'month';
    renderStats(period);
});
