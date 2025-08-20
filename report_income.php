
<?php
require_once 'config.php';
if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: auth/login.php');
    exit;
}
$pageTitle = 'Reporte de Ingresos por Equipo';
include 'includes/header.php';
?>
<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-table"></i>
                Reporte de Ingresos por Equipo
            </h1>
            <p class="content-subtitle">Consulta de ingresos por equipo y semana</p>
        </div>
        <div class="card">
            <div class="card-header" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                <form method="POST" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                    <?php
                    function getTeams($pdo) {
                        $stmt = $pdo->prepare("SELECT id, name FROM teams WHERE active = 1");
                        $stmt->execute();
                        return $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                    $pdo = new PDO(
                        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                        DB_USER,
                        DB_PASS,
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    $teams = getTeams($pdo);
                    ?>
                    <div style="display: flex; gap: 16px; align-items: flex-end;">
                        <div class="filter-section">
                            <label class="filter-label" for="teamSelector">Equipo</label>
                            <select name="team_id" id="teamSelector" class="form-input" required>
                                <option value="">Seleccione equipo</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?= htmlspecialchars($team['id']) ?>" <?= (isset($_POST['team_id']) && $_POST['team_id'] == $team['id']) ? 'selected' : '' ?>><?= htmlspecialchars($team['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-section">
                            <label class="filter-label" for="weekPicker">Semana</label>
                            <input type="week" name="week" id="weekPicker" class="form-input" value="<?= htmlspecialchars($_POST['week'] ?? '') ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-bottom:2px;">
                            <i class="fas fa-search"></i> Ver Reporte
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title" style="display: flex; align-items: center; gap: 18px;">
                    <span><i class="fas fa-calendar-day"></i> Resultados por Día</span>
                    <?php
                    $selectedTeamName = '';
                    if (!empty($_POST['team_id'])) {
                        foreach ($teams as $team) {
                            if ($team['id'] === $_POST['team_id']) {
                                $selectedTeamName = $team['name'];
                                break;
                            }
                        }
                    }
                    ?>
                    <?php if ($selectedTeamName): ?>
                        <span style="font-size:1em; color:#2563eb; font-weight:500;">Equipo: <?= htmlspecialchars($selectedTeamName) ?></span>
                    <?php endif; ?>
                </h3>
            </div>
            <div style="overflow-x: auto;">
                <?php
                function getReport($pdo, $team_id, $start_date, $end_date) {
                    $sql = "SELECT
                        i.income_date,
                        i.invoice_number,
                        i.contractor_ids,
                        il.quantity,
                        il.unit_price,
                        il.total_amount,
                        jt.name AS job_type
                    FROM incomes i
                    JOIN income_lines il ON i.id = il.income_id
                    JOIN job_types jt ON il.job_types_id = jt.id
                    WHERE i.team_id = :team_id AND i.income_date BETWEEN :start_date AND :end_date
                    ORDER BY i.income_date";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':team_id' => $team_id,
                        ':start_date' => $start_date,
                        ':end_date' => $end_date
                    ]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $report = [];
                    foreach ($rows as $row) {
                        $day = $row['income_date'];
                        $contractors = [];
                        if (!empty($row['contractor_ids'])) {
                            $ids = json_decode($row['contractor_ids'], true);
                            if (is_array($ids)) {
                                $in = implode(',', array_map(fn($id) => $pdo->quote($id), $ids));
                                $csql = "SELECT name FROM contractors WHERE id IN ($in)";
                                $cstmt = $pdo->query($csql);
                                $contractors = $cstmt->fetchAll(PDO::FETCH_COLUMN);
                            }
                        }
                        $row['contractor_names'] = $contractors;
                        if (!isset($report[$day])) $report[$day] = [];
                        $report[$day][] = $row;
                    }
                    return $report;
                }
                $report = [];
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $team_id = $_POST['team_id'] ?? '';
                    $week = $_POST['week'] ?? '';
                    if ($team_id && $week) {
                        $year = substr($week, 0, 4);
                        $w = substr($week, 6, 2);
                        $dto = new DateTime();
                        $dto->setISODate($year, $w);
                        $start_date = $dto->format('Y-m-d');
                        $dto->modify('+6 days');
                        $end_date = $dto->format('Y-m-d');
                        $report = getReport($pdo, $team_id, $start_date, $end_date);
                    }
                }
                ?>
                <?php if ($report): ?>
                    <?php foreach ($report as $day => $rows): ?>
                        <div class="card" style="margin-bottom: 24px;">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-calendar-day"></i>
                                    Día: <?= htmlspecialchars($day) ?>
                                </h3>
                            </div>
                            <div style="overflow-x: auto;">
                                <table class="data-table sortable-table" style="min-width: 900px;">
                                    <thead>
                                        <tr>
                                            <th>Contratistas</th>
                                            <th>Invoice</th>
                                            <th>Tipo Trabajo</th>
                                            <th>Unidades</th>
                                            <th>Precio</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $totalDia = 0;
                                        foreach ($rows as $row): 
                                            $totalDia += floatval($row['total_amount']);
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars(implode(', ', $row['contractor_names'])) ?></td>
                                                <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                                                <td><?= htmlspecialchars($row['job_type']) ?></td>
                                                <td><?= htmlspecialchars($row['quantity']) ?></td>
                                                <td><?= htmlspecialchars($row['unit_price']) ?></td>
                                                <td><?= htmlspecialchars($row['total_amount']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <?php
                                        // Métodos de pago por día
                                        $sql = "SELECT pt.name AS payment_method, SUM(ip.amount) AS total_amount, COUNT(ip.id) AS payment_count
                                                FROM income_payments ip
                                                INNER JOIN payment_types pt ON ip.payment_type_id = pt.id
                                                INNER JOIN incomes i ON ip.income_id = i.id
                                                WHERE i.team_id = :team_id AND i.income_date = :income_date AND pt.status = 'active'
                                                GROUP BY pt.id, pt.name
                                                ORDER BY pt.name ASC";
                                        $stmt = $pdo->prepare($sql);
                                        $stmt->execute([
                                            ':team_id' => $_POST['team_id'],
                                            ':income_date' => $day
                                        ]);
                                        $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        ?>
                                        <tr>
                                            <td colspan="3" style="text-align:left; color:#2563eb; font-size:0.98em; font-weight:500;">
                                                <?php if ($methods): ?>
                                                    <span style="font-weight:bold;">Métodos de Pago:</span>
                                                    <?php foreach ($methods as $m): ?>
                                                        <span style="margin-right:12px;">
                                                            <?= htmlspecialchars($m['payment_method']) ?>: <span style="font-weight:bold;"><?= number_format($m['total_amount'],2) ?></span> (<?= $m['payment_count'] ?>)
                                                        </span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td colspan="2" style="text-align:right; font-weight:bold; color:#2563eb;">Total del día:</td>
                                            <td style="font-weight:bold; color:#2563eb;"><?= number_format($totalDia, 2) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                    <div style="text-align:center; color:#2563eb; font-weight:bold;">No hay datos para los filtros seleccionados.</div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
