async function fetchTeams() {
    const res = await fetch('/api/teams');
    const teams = await res.json();
    const selector = document.getElementById('teamSelector');
    selector.innerHTML = '';
    teams.forEach(team => {
        const opt = document.createElement('option');
        opt.value = team.id;
        opt.textContent = team.name;
        selector.appendChild(opt);
    });
}

function getWeekRange(weekStr) {
    // weekStr: "2025-08" (YYYY-Www)
    const [year, week] = weekStr.split('-W');
    const firstDay = new Date(year, 0, 1 + (week - 1) * 7);
    while (firstDay.getDay() !== 1) firstDay.setDate(firstDay.getDate() + 1); // Lunes
    const lastDay = new Date(firstDay);
    lastDay.setDate(firstDay.getDate() + 6);
    return {
        start: firstDay.toISOString().slice(0,10),
        end: lastDay.toISOString().slice(0,10)
    };
}

async function loadReport() {
    const teamId = document.getElementById('teamSelector').value;
    const weekStr = document.getElementById('weekPicker').value;
    if (!teamId || !weekStr) return;
    const {start, end} = getWeekRange(weekStr.replace('W','-W'));
    const res = await fetch(`/api/report/income?team_id=${teamId}&start_date=${start}&end_date=${end}`);
    const data = await res.json();
    renderReport(data);
}

function renderReport(data) {
    const reportDiv = document.getElementById('report');
    reportDiv.innerHTML = '';
    Object.keys(data).forEach(day => {
        const dayBox = document.createElement('div');
        dayBox.className = 'day-container';
        const title = document.createElement('div');
        title.className = 'day-title';
        title.textContent = `Día: ${day}`;
        dayBox.appendChild(title);
        const table = document.createElement('table');
        table.innerHTML = `<tr><th>Contratista</th><th>Invoice</th><th>Tipo Trabajo</th><th>Unidades</th><th>Precio</th><th>Total</th></tr>`;
        data[day].forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${row.contractor}</td><td>${row.invoice_number}</td><td>${row.job_type}</td><td>${row.unit}</td><td>${row.price}</td><td>${row.total}</td>`;
            table.appendChild(tr);
        });
        dayBox.appendChild(table);
        reportDiv.appendChild(dayBox);
    });
}

window.onload = fetchTeams;
