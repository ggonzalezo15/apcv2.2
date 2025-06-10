<?php
require_once 'config.php';

// Verificar autenticación
if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: auth/login.php');
    exit;
}

$pageTitle = 'Gestión de Equipos';
?>
<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-users"></i>
                Gestión de Equipos
            </h1>
            <p class="content-subtitle">Administración de equipos de trabajo</p>
        </div>
        
        <!-- Botón para agregar nuevo equipo -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: flex-end; align-items: center;">
                <div style="flex: 1;">
                    <input
                        type="text"
                        id="searchInput"
                        class="form-input"
                        placeholder="Buscar equipo por nombre..."
                        aria-label="Buscar equipo"
                        style="max-width: 300px;"
                        autocomplete="off"
                    >
                </div>
            <div>
                <button type="button" class="btn btn-primary" onclick="openModal('teamModal')">
                    <i class="fas fa-plus"></i>
                    Nuevo Equipo
                </button>
            </div>
            </div>
        </div>
        <!-- Tabla de equipos -->
        <div class="card">
            <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-table"></i>
                Lista de Equipos
            </h3>
            <p class="card-subtitle">Total: <span id="totalTeams">5</span> equipos registrados</p>
            </div>
            
            <div style="overflow-x: auto;">
            <table class="data-table" id="teamsTable" style="min-width: 500px;">
                <thead>
                <tr>
                    <th>Nombre del Equipo</th>
                    <th>Descripción</th>
                    <th style="vertical-align: middle; text-align: center;">Acciones</th>
                </tr>
                </thead>
                <tbody id="teamsTableBody">
                <!-- Las filas se llenarán dinámicamente con JS -->
                </tbody>
            </table>
            <!-- Pie de tabla para selector y paginación -->
            <div id="teamsTableFooter" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0 0;">
                <div id="pageSizeSelectorContainer"></div>
                <div id="teamsPagination"></div>
            </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal para crear/editar equipo -->
<div class="modal" id="teamModal">
    <div class="modal-overlay" onclick="closeModal('teamModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nuevo Equipo</h2>
            <button type="button" class="modal-close" onclick="closeModal('teamModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="teamForm">
            <div class="modal-body">
                <input type="hidden" id="teamId" name="teamId" value="">
                <div class="form-group">
                    <label class="form-label" for="teamName">Nombre del Equipo *</label>
                    <input type="text" class="form-input" id="teamName" name="teamName" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="teamDescription">Descripción</label>
                    <textarea class="form-input" id="teamDescription" name="teamDescription" rows="3"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('teamModal')" style="background-color: var(--secondary-color); color: white;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Guardar Equipo
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de notificación -->
<div class="modal" id="notificationModal" style="display:none;">
    <div class="modal-overlay" onclick="closeModal('notificationModal')"></div>
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2 id="notificationTitle">Notificación</h2>
            <button type="button" class="modal-close" onclick="closeModal('notificationModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p id="notificationMessage"></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="closeModal('notificationModal')">Aceptar</button>
        </div>
    </div>
</div>

<!-- Modal de confirmación de eliminación -->
<div class="modal" id="confirmDeleteModal" style="display:none;">
    <div class="modal-overlay" onclick="closeModal('confirmDeleteModal')"></div>
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2>Confirmar eliminación</h2>
            <button type="button" class="modal-close" onclick="closeModal('confirmDeleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p id="confirmDeleteMessage">¿Está seguro de que desea eliminar este equipo?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" onclick="closeModal('confirmDeleteModal')">Cancelar</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Eliminar</button>
        </div>
    </div>
</div>

<!-- Toast de notificación -->
<div id="toast" style="position: fixed; bottom: 32px; right: 32px; min-width: 220px; z-index: 9999; display: none; background: var(--primary-color, #2563eb); color: #fff; padding: 16px 24px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.12); font-size: 16px; font-weight: 500; align-items: center; gap: 8px;">
    <span id="toastIcon" style="margin-right: 8px;"></span>
    <span id="toastMessage"></span>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// --- Configuración ---
const API_URL = 'api/team/TeamController.php';
let editingTeamId = null;
let sortField = 'created_at';
let sortDir = 'desc';

// --- Cargar equipos al iniciar ---
document.addEventListener('DOMContentLoaded', loadTeams);

// --- Paginación ---
let currentPage = 1;
let pageSize = 10;
let totalTeamsCount = 0;

// Selector de líneas por página
function renderPageSizeSelector() {
    let container = document.getElementById('pageSizeSelectorContainer');
    if (!container) return;
    container.innerHTML = '';
    const label = document.createElement('label');
    label.textContent = 'Mostrar:';
    label.style = 'margin-right: 4px; font-weight: 500; color: var(--text-secondary);';
    const selector = document.createElement('select');
    selector.id = 'pageSizeSelector';
    selector.className = 'form-input';
    selector.style = 'width: auto; display: inline-block;';
    [5, 10, 20, 50, 100].forEach(size => {
        const opt = document.createElement('option');
        opt.value = size;
        opt.textContent = `${size} por página`;
        selector.appendChild(opt);
    });
    selector.value = pageSize;
    selector.onchange = function() {
        pageSize = parseInt(this.value);
        loadTeams(1);
    };
    container.appendChild(label);
    container.appendChild(selector);
}

// Llamar al renderizador del selector al cargar la página y tras cada render
renderPageSizeSelector();

function loadTeams(page = 1) {
    currentPage = page;
    setTableLoading(true);
    fetch(`${API_URL}?action=getAllTeams&limit=${pageSize}&offset=${(page-1)*pageSize}&sort=${sortField}&dir=${sortDir}`)
        .then(res => res.json())
        .then(data => {
            const teams = data.data || data;
            totalTeamsCount = data.total || teams.length;
            renderTeamsTable(teams);
            renderPagination();
        })
        .catch(() => {
            document.getElementById('teamsTableBody').innerHTML = '<tr><td colspan="5">Error al cargar equipos</td></tr>';
        })
        .finally(() => setTableLoading(false));
}

function setTableLoading(loading) {
    const tbody = document.getElementById('teamsTableBody');
    if (loading) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:40px 0;">
            <div class="loading-spinner"></div>
            <span style="display:block; margin-top:8px; color:var(--text-secondary);">Cargando equipos...</span>
        </td></tr>`;
    }
}

function renderTeamsTable(teams) {
    const tbody = document.getElementById('teamsTableBody');
    tbody.innerHTML = '';
    if (!teams.length) {
        tbody.innerHTML = '<tr><td colspan="3">No hay equipos registrados</td></tr>';
        document.getElementById('totalTeams').textContent = '0';
        return;
    }
    document.getElementById('totalTeams').textContent = teams.length;
    teams.forEach(team => {
        const created = team.created_at ? team.created_at.split(' ')[0] : '';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${team.name}</td>
            <td>${team.description || ''}</td>
            <td style="vertical-align: middle; text-align: center;">
                <div style="display: flex; gap: 4px; justify-content: center; align-items: center;">
                    <button type="button" class="btn-icon" onclick="editTeam('${team.id}')" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn-icon btn-danger" onclick="deleteTeam('${team.id}')" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
    renderPageSizeSelector();
}

function renderPagination() {
    const container = document.getElementById('teamsPagination');
    if (!container) return;
    container.innerHTML = '';
    const totalPages = Math.ceil(totalTeamsCount / pageSize);
    if (totalPages <= 1) { container.style.display = 'none'; return; }
    container.style.display = 'flex';
    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement('button');
        btn.className = 'btn' + (i === currentPage ? ' btn-primary' : '');
        btn.textContent = i;
        btn.style.minWidth = '36px';
        btn.onclick = () => loadTeams(i);
        container.appendChild(btn);
    }
}

function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
    if (modalId === 'teamModal') {
        document.getElementById('teamForm').reset();
        document.getElementById('modalTitle').textContent = 'Nuevo Equipo';
        editingTeamId = null;
    }
}

function showNotification(message, title = 'Notificación') {
    document.getElementById('notificationTitle').textContent = title;
    document.getElementById('notificationMessage').textContent = message;
    document.getElementById('notificationModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastIcon = document.getElementById('toastIcon');
    const toastMessage = document.getElementById('toastMessage');
    toastMessage.textContent = message;
    if (type === 'success') {
        toast.style.background = 'var(--success-color, #059669)';
        toastIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
    } else if (type === 'error') {
        toast.style.background = 'var(--danger-color, #dc2626)';
        toastIcon.innerHTML = '<i class="fas fa-times-circle"></i>';
    } else {
        toast.style.background = 'var(--primary-color, #2563eb)';
        toastIcon.innerHTML = '<i class="fas fa-info-circle"></i>';
    }
    toast.style.display = 'flex';
    setTimeout(() => { toast.style.display = 'none'; }, 3200);
}

function viewTeam(id) {
    fetch(`${API_URL}?action=getTeamById&id=${id}`)
        .then(res => res.json())
        .then(team => {
            alert(`Equipo: ${team.name}\nDescripción: ${team.description}`);
        });
}

function editTeam(id) {
    fetch(`${API_URL}?action=getTeamById&id=${id}`)
        .then(res => res.json())
        .then(team => {
            document.getElementById('modalTitle').textContent = 'Editar Equipo';
            document.getElementById('teamName').value = team.name || '';
            document.getElementById('teamDescription').value = team.description || '';
            editingTeamId = team.id;
            openModal('teamModal');
        });
}

let teamIdToDelete = null;
function showDeleteModal(id) {
    teamIdToDelete = id;
    document.getElementById('confirmDeleteModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
document.getElementById('confirmDeleteBtn').onclick = function() {
    if (teamIdToDelete) {
        deleteTeamConfirmed(teamIdToDelete);
        teamIdToDelete = null;
        closeModal('confirmDeleteModal');
    }
};
function deleteTeam(id) {
    showDeleteModal(id);
}
function deleteTeamConfirmed(id) {
    fetch(`${API_URL}?action=deleteTeam&id=${id}`, { method: 'DELETE' })
        .then(res => res.json())
        .then(result => {
            if (result && result.error) {
                showNotification('No se puede eliminar el equipo porque tiene gastos, ingresos u otros datos relacionados.\n\nDetalle: ' + result.error, 'Error al eliminar equipo');
                showToast('No se pudo eliminar el equipo.', 'error');
                return;
            }
            showToast('Equipo eliminado con éxito.', 'success');
            setTimeout(() => {
                fetch(`${API_URL}?action=getAllTeams&limit=${pageSize}&offset=${(currentPage-1)*pageSize}`)
                    .then(res => res.json())
                    .then(data => {
                        const teams = data.data || data;
                        if (teams.length === 0 && currentPage > 1) {
                            loadTeams(currentPage - 1);
                        } else {
                            loadTeams(currentPage);
                        }
                    });
            }, 200);
        })
        .catch(() => {
            showToast('Ocurrió un error al eliminar el equipo.', 'error');
        });
}

document.getElementById('teamForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const data = {
        name: document.getElementById('teamName').value,
        description: document.getElementById('teamDescription').value
    };
    let url = API_URL;
    let method = 'POST';
    let isEdit = false;
    if (editingTeamId) {
        url += `?action=updateTeam&id=${editingTeamId}`;
        method = 'PUT';
        isEdit = true;
    } else {
        url += '?action=createTeam';
    }
    fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(result => {
        closeModal('teamModal');
        if (result && result.error) {
            showToast('Ocurrió un error al guardar el equipo.', 'error');
        } else {
            showToast(isEdit ? 'Equipo editado con éxito.' : 'Equipo creado con éxito.', 'success');
        }
        loadTeams();
    })
    .catch(() => {
        showToast('Ocurrió un error al guardar el equipo.', 'error');
    });
});

// --- Filtro de búsqueda local por nombre de equipo ---
document.getElementById('searchInput').addEventListener('input', function() {
    const search = this.value.trim().toLowerCase();
    const rows = document.querySelectorAll('#teamsTableBody tr');
    let count = 0;
    rows.forEach(row => {
        // El nombre del equipo está en la primera columna (índice 0)
        const name = row.children[0]?.textContent.toLowerCase() || '';
        if (name.includes(search)) {
            row.style.display = '';
            count++;
        } else {
            row.style.display = 'none';
        }
    });
    document.getElementById('totalTeams').textContent = count;
});

// --- Sort interactivo en la tabla ---
document.addEventListener('DOMContentLoaded', function() {
    const ths = document.querySelectorAll('#teamsTable thead th');
    ths.forEach((th, idx) => {
        if (idx < 2) { // Solo para columnas Nombre y Descripción
            th.style.cursor = 'pointer';
            th.addEventListener('click', function() {
                const field = idx === 0 ? 'name' : 'description';
                if (sortField === field) {
                    sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    sortField = field;
                    sortDir = 'asc';
                }
                loadTeams(1);
            });
        }
    });
});

// Cerrar modal con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display === 'flex') {
                closeModal(modal.id);
            }
        });
    }
});
</script>
