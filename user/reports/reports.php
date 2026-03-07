<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/customer_auth_check.php';
requireLogin();

if ($_SESSION['role'] !== 'customer') {
    header('Location: ' . $baseUrl . '/grocery/grocery_dashboard.php');
    exit();
}

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Get user's group
$group_stmt = $conn->prepare("
    SELECT g.group_id, g.group_name, g.group_type, gm.member_role
    FROM group_members gm
    JOIN groups g ON gm.group_id = g.group_id
    WHERE gm.user_id = ?
    LIMIT 1
");
$group_stmt->bind_param("i", $user_id);
$group_stmt->execute();
$group_info = $group_stmt->get_result()->fetch_assoc();
$group_id = $group_info['group_id'] ?? null;

// ── 1. SUMMARY STATS ──────────────────────────────────────────────────────────
$summary_stmt = $conn->prepare("
    SELECT
        COUNT(ci.item_id)                                                         AS total_items,
        SUM(CASE WHEN ci.expiry_status = 'fresh'       THEN 1 ELSE 0 END)        AS fresh_items,
        SUM(CASE WHEN ci.expiry_status = 'near_expiry' THEN 1 ELSE 0 END)        AS near_expiry_items,
        SUM(CASE WHEN ci.expiry_status = 'expired'     THEN 1 ELSE 0 END)        AS expired_items,
        COUNT(DISTINCT ci.category_id)                                            AS categories_used,
        SUM(CASE WHEN ci.quantity <= 1 THEN 1 ELSE 0 END)                        AS low_stock_items
    FROM customer_items ci
    WHERE ci.group_id = ?
      AND ci.quantity > 0
");
$summary_stmt->bind_param("i", $group_id);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();

// ── 2. WASTE METRICS ─────────────────────────────────────────────────────────
$waste_stmt = $conn->prepare("
    SELECT
        COUNT(*)                                                                   AS total_waste_events,
        SUM(ABS(ciu.quantity_change))                                              AS total_wasted_qty,
        SUM(CASE WHEN ciu.update_type = 'spoiled' THEN ABS(ciu.quantity_change) ELSE 0 END) AS spoiled_qty,
        SUM(CASE WHEN ciu.update_type = 'expired' THEN ABS(ciu.quantity_change) ELSE 0 END) AS expired_qty,
        SUM(CASE WHEN ciu.update_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  AND ciu.update_type IN ('spoiled','expired')
                 THEN ABS(ciu.quantity_change) ELSE 0 END)                         AS waste_this_week,
        SUM(CASE WHEN ciu.update_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                                          AND DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  AND ciu.update_type IN ('spoiled','expired')
                 THEN ABS(ciu.quantity_change) ELSE 0 END)                         AS waste_last_week
    FROM customer_inventory_updates ciu
    JOIN customer_items ci ON ciu.item_id = ci.item_id
    WHERE ci.group_id = ?
      AND ciu.update_type IN ('spoiled','expired')
");
$waste_stmt->bind_param("i", $group_id);
$waste_stmt->execute();
$waste = $waste_stmt->get_result()->fetch_assoc();

// ── 3. WEEKLY CONSUMED / ADDED ────────────────────────────────────────────────
$weekly_stmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN ciu.update_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  AND ciu.update_type = 'consumed'
                 THEN ABS(ciu.quantity_change) ELSE 0 END)  AS this_week_consumed,
        SUM(CASE WHEN ciu.update_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                                          AND DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  AND ciu.update_type = 'consumed'
                 THEN ABS(ciu.quantity_change) ELSE 0 END)  AS last_week_consumed,
        SUM(CASE WHEN ciu.update_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  AND ciu.update_type = 'added'
                 THEN ciu.quantity_change ELSE 0 END)        AS this_week_added,
        SUM(CASE WHEN ciu.update_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                                          AND DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  AND ciu.update_type = 'added'
                 THEN ciu.quantity_change ELSE 0 END)        AS last_week_added
    FROM customer_inventory_updates ciu
    JOIN customer_items ci ON ciu.item_id = ci.item_id
    WHERE ci.group_id = ?
");
$weekly_stmt->bind_param("i", $group_id);
$weekly_stmt->execute();
$weekly = $weekly_stmt->get_result()->fetch_assoc();

// ── 4. CONSUMPTION TREND (last 4 weeks) ──────────────────────────────────────
$trend_stmt = $conn->prepare("
    SELECT
        YEARWEEK(ciu.update_date, 1)                           AS yr_week,
        DATE_FORMAT(MIN(ciu.update_date), '%b %d')             AS week_start,
        SUM(CASE WHEN ciu.update_type = 'consumed'             THEN ABS(ciu.quantity_change) ELSE 0 END) AS consumed,
        SUM(CASE WHEN ciu.update_type IN ('spoiled','expired') THEN ABS(ciu.quantity_change) ELSE 0 END) AS wasted,
        SUM(CASE WHEN ciu.update_type = 'added'                THEN ciu.quantity_change      ELSE 0 END) AS added
    FROM customer_inventory_updates ciu
    JOIN customer_items ci ON ciu.item_id = ci.item_id
    WHERE ci.group_id = ?
      AND ciu.update_date >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK)
    GROUP BY YEARWEEK(ciu.update_date, 1)
    ORDER BY yr_week ASC
");
$trend_stmt->bind_param("i", $group_id);
$trend_stmt->execute();
$trend_data = $trend_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── 5. MONTHLY SUMMARY (last 6 months) ───────────────────────────────────────
$monthly_stmt = $conn->prepare("
    SELECT
        DATE_FORMAT(ciu.update_date, '%Y-%m')               AS month_key,
        DATE_FORMAT(ciu.update_date, '%b %Y')               AS month_label,
        SUM(CASE WHEN ciu.update_type = 'added'             THEN ciu.quantity_change      ELSE 0 END) AS items_added,
        SUM(CASE WHEN ciu.update_type = 'consumed'          THEN ABS(ciu.quantity_change) ELSE 0 END) AS items_consumed,
        SUM(CASE WHEN ciu.update_type IN ('spoiled','expired') THEN ABS(ciu.quantity_change) ELSE 0 END) AS items_wasted,
        COUNT(DISTINCT ciu.item_id)                         AS unique_items
    FROM customer_inventory_updates ciu
    JOIN customer_items ci ON ciu.item_id = ci.item_id
    WHERE ci.group_id = ?
      AND ciu.update_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(ciu.update_date, '%Y-%m')
    ORDER BY month_key DESC
");
$monthly_stmt->bind_param("i", $group_id);
$monthly_stmt->execute();
$monthly_data = $monthly_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── 6. CATEGORY BREAKDOWN ────────────────────────────────────────────────────
$cat_stmt = $conn->prepare("
    SELECT
        c.category_name,
        COUNT(ci.item_id)                                                          AS item_count,
        SUM(CASE WHEN ci.expiry_status = 'expired'     THEN 1 ELSE 0 END)         AS expired_count,
        SUM(CASE WHEN ci.expiry_status = 'near_expiry' THEN 1 ELSE 0 END)         AS near_expiry_count
    FROM categories c
    JOIN customer_items ci ON c.category_id = ci.category_id
    WHERE ci.group_id = ?
    GROUP BY c.category_id
    ORDER BY item_count DESC
    LIMIT 10
");
$cat_stmt->bind_param("i", $group_id);
$cat_stmt->execute();
$cat_data = $cat_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── 7. TOP WASTED ITEMS ──────────────────────────────────────────────────────
$top_waste_stmt = $conn->prepare("
    SELECT
        ci.item_name,
        c.category_name,
        ci.unit,
        SUM(ABS(ciu.quantity_change)) AS total_wasted,
        COUNT(*)                      AS waste_events
    FROM customer_inventory_updates ciu
    JOIN customer_items ci ON ciu.item_id = ci.item_id
    LEFT JOIN categories c ON ci.category_id = c.category_id
    WHERE ci.group_id = ?
      AND ciu.update_type IN ('spoiled','expired')
    GROUP BY ci.item_id
    ORDER BY total_wasted DESC
    LIMIT 8
");
$top_waste_stmt->bind_param("i", $group_id);
$top_waste_stmt->execute();
$top_waste = $top_waste_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── 8. CONSUMPTION EFFICIENCY ────────────────────────────────────────────────
$efficiency_stmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN update_type = 'consumed'             THEN ABS(quantity_change) ELSE 0 END) AS total_consumed,
        SUM(CASE WHEN update_type IN ('spoiled','expired') THEN ABS(quantity_change) ELSE 0 END) AS total_wasted
    FROM customer_inventory_updates ciu
    JOIN customer_items ci ON ciu.item_id = ci.item_id
    WHERE ci.group_id = ?
");
$efficiency_stmt->bind_param("i", $group_id);
$efficiency_stmt->execute();
$efficiency = $efficiency_stmt->get_result()->fetch_assoc();
$total_flow    = floatval($efficiency['total_consumed']) + floatval($efficiency['total_wasted']);
$efficiency_pct = $total_flow > 0 ? round((floatval($efficiency['total_consumed']) / $total_flow) * 100, 1) : 0;
$waste_pct      = $total_flow > 0 ? round((floatval($efficiency['total_wasted']) / $total_flow) * 100, 1) : 0;

// ── 9. 14-DAY EXPIRY FORECAST ────────────────────────────────────────────────
$expiry_stmt = $conn->prepare("
    SELECT
        ci.item_name,
        c.category_name,
        ci.expiry_date,
        ci.quantity,
        ci.unit,
        DATEDIFF(ci.expiry_date, CURDATE()) AS days_left
    FROM customer_items ci
    LEFT JOIN categories c ON ci.category_id = c.category_id
    WHERE ci.group_id = ?
      AND ci.quantity > 0
      AND ci.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
      AND ci.expiry_status != 'expired'
    ORDER BY ci.expiry_date ASC
    LIMIT 15
");
$expiry_stmt->bind_param("i", $group_id);
$expiry_stmt->execute();
$expiry_items = $expiry_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── 10. POINTS & ACTIVITY ────────────────────────────────────────────────────
$points_stmt = $conn->prepare("
    SELECT up.total_points,
           COUNT(pl.log_id)                                                 AS total_actions,
           SUM(CASE WHEN pl.action_type = 'CONSUME_ITEM' THEN 1 ELSE 0 END) AS consume_actions,
           SUM(CASE WHEN pl.action_type = 'ADD_ITEM'     THEN 1 ELSE 0 END) AS add_actions
    FROM user_points up
    LEFT JOIN points_log pl ON up.user_id = pl.user_id
    WHERE up.user_id = ?
    GROUP BY up.user_id
");
$points_stmt->bind_param("i", $user_id);
$points_stmt->execute();
$points_data = $points_stmt->get_result()->fetch_assoc();

// ── JSON for charts ──────────────────────────────────────────────────────────
$trend_labels    = json_encode(array_column($trend_data, 'week_start'));
$trend_consumed  = json_encode(array_map('floatval', array_column($trend_data, 'consumed')));
$trend_wasted    = json_encode(array_map('floatval', array_column($trend_data, 'wasted')));
$trend_added     = json_encode(array_map('floatval', array_column($trend_data, 'added')));
$monthly_labels  = json_encode(array_reverse(array_column($monthly_data, 'month_label')));
$monthly_consumed= json_encode(array_map('floatval', array_reverse(array_column($monthly_data, 'items_consumed'))));
$monthly_wasted  = json_encode(array_map('floatval', array_reverse(array_column($monthly_data, 'items_wasted'))));
$cat_labels      = json_encode(array_column($cat_data, 'category_name'));
$cat_counts      = json_encode(array_map('intval', array_column($cat_data, 'item_count')));

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/reports.css">';
require_once __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
/* ── Extends existing reports.css — user-specific additions only ── */
.reports-page { background: #fafafa; min-height: 100vh; padding: 40px 20px; }
.page-container { max-width: 1400px; margin: 0 auto; }

.page-header { background:#fff; border:1px solid rgba(0,0,0,.08); padding:35px 40px; margin-bottom:25px; }
.header-content { display:flex; justify-content:space-between; align-items:center; gap:20px; flex-wrap:wrap; }
.page-title { font-family:'Playfair Display',serif; font-size:30px; font-weight:400; color:#0a0a0a; margin-bottom:4px; }
.page-subtitle { font-size:13px; color:rgba(0,0,0,.5); letter-spacing:.3px; }
.btn-secondary { display:inline-flex; align-items:center; gap:8px; padding:11px 22px; font-size:10px; letter-spacing:1px; text-transform:uppercase; font-weight:500; background:transparent; color:#0a0a0a; border:1px solid rgba(0,0,0,.15); text-decoration:none; transition:all .3s ease; }
.btn-secondary:hover { background:#0a0a0a; color:#fff; }

.summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:20px; margin-bottom:25px; }
.summary-card { background:#fff; border:1px solid rgba(0,0,0,.08); padding:25px; transition:all .3s ease; }
.summary-card:hover { border-color:rgba(0,0,0,.15); box-shadow:0 8px 25px rgba(0,0,0,.06); transform:translateY(-2px); }
.summary-icon { width:48px; height:48px; border-radius:8px; display:flex; align-items:center; justify-content:center; margin-bottom:15px; }
.summary-icon svg { width:22px; height:22px; }
.summary-icon.primary { background:#eff6ff; color:#1d4ed8; }
.summary-icon.success { background:#f0fdf4; color:#166534; }
.summary-icon.warning { background:#fff7ed; color:#c2410c; }
.summary-icon.danger  { background:#fef2f2; color:#b91c1c; }
.summary-content h3 { font-size:26px; font-weight:600; color:#0a0a0a; margin-bottom:5px; }
.summary-content p { font-size:11px; color:rgba(0,0,0,.55); text-transform:uppercase; letter-spacing:1px; }
.summary-content small { font-size:11px; }

.report-section { background:#fff; border:1px solid rgba(0,0,0,.08); padding:30px; margin-bottom:20px; transition:all .3s ease; }
.report-section:hover { border-color:rgba(0,0,0,.12); box-shadow:0 8px 25px rgba(0,0,0,.06); }
.section-title { font-family:'Playfair Display',serif; font-size:22px; font-weight:400; color:#0a0a0a; margin-bottom:6px; }
.section-subtitle { font-size:12px; color:rgba(0,0,0,.5); letter-spacing:.3px; margin-bottom:22px; }

.data-table { width:100%; border-collapse:collapse; }
.data-table th { text-align:left; padding:11px 12px; background:#fafafa; border-bottom:2px solid rgba(0,0,0,.1); font-size:10px; text-transform:uppercase; letter-spacing:1px; color:rgba(0,0,0,.55); font-weight:600; }
.data-table td { padding:12px; border-bottom:1px solid rgba(0,0,0,.05); font-size:13px; }
.data-table tr:hover { background:#fafafa; }

.chart-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(340px,1fr)); gap:20px; margin-top:20px; }
.chart-card { background:#fafafa; border:1px solid rgba(0,0,0,.08); padding:25px; }
.chart-title { font-size:13px; font-weight:600; color:#0a0a0a; margin-bottom:18px; letter-spacing:.3px; }

.week-compare-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:16px; margin-top:20px; }
.week-card { border:1px solid rgba(0,0,0,.08); padding:22px; background:#fafafa; }
.week-card .wc-label { font-size:10px; text-transform:uppercase; letter-spacing:1px; color:rgba(0,0,0,.5); margin-bottom:8px; }
.week-card .wc-value { font-size:26px; font-weight:600; color:#0a0a0a; }
.wc-change { font-size:12px; margin-top:6px; font-weight:500; }
.wc-change.positive { color:#059669; }
.wc-change.negative { color:#b91c1c; }
.wc-change.neutral  { color:rgba(0,0,0,.4); }

.metrics-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:15px; margin-top:20px; }
.metric-card { background:#fafafa; border:1px solid rgba(0,0,0,.08); padding:20px; text-align:center; }
.metric-value { font-size:24px; font-weight:600; color:#0a0a0a; margin-bottom:6px; }
.metric-label { font-size:10px; letter-spacing:1.2px; text-transform:uppercase; color:rgba(0,0,0,.5); }

.efficiency-wrap { background:rgba(0,0,0,.05); height:16px; border-radius:3px; overflow:hidden; margin:10px 0; }
.efficiency-fill { height:100%; transition:width .6s ease; }
.efficiency-fill.good   { background:linear-gradient(90deg,#10b981,#059669); }
.efficiency-fill.medium { background:linear-gradient(90deg,#f59e0b,#d97706); }
.efficiency-fill.bad    { background:linear-gradient(90deg,#ef4444,#b91c1c); }

.prog-bar { width:100%; height:7px; background:rgba(0,0,0,.06); border-radius:3px; overflow:hidden; }
.prog-fill { height:100%; background:#0a0a0a; border-radius:3px; transition:width .4s ease; }

.badge-mini { display:inline-block; padding:4px 9px; border-radius:3px; font-size:10px; font-weight:600; letter-spacing:.4px; }
.badge-mini.success { background:#d1fae5; color:#065f46; }
.badge-mini.warning { background:#fed7aa; color:#92400e; }
.badge-mini.danger  { background:#fecaca; color:#991b1b; }
.badge-mini.info    { background:#dbeafe; color:#1e40af; }

.export-bar { display:flex; gap:10px; justify-content:flex-end; margin-bottom:22px; flex-wrap:wrap; }
.export-btn { display:inline-flex; align-items:center; gap:6px; padding:10px 18px; font-size:9px; letter-spacing:1px; text-transform:uppercase; font-weight:600; background:transparent; color:#0a0a0a; border:1px solid rgba(0,0,0,.15); cursor:pointer; transition:all .3s ease; text-decoration:none; }
.export-btn:hover { background:#0a0a0a; color:#fff; }

.empty-state { text-align:center; padding:50px 20px; color:rgba(0,0,0,.4); }
.empty-state p { font-size:13px; margin-top:12px; }

.tips-box { background:#fafafa; border:1px solid rgba(0,0,0,.06); padding:20px; margin-top:20px; font-size:13px; color:rgba(0,0,0,.65); line-height:1.9; }

@media print {
    .export-bar,.btn-secondary,header,footer,.page-header .btn-secondary { display:none !important; }
    .report-section,.summary-card,.chart-card { break-inside:avoid; }
}
@media (max-width:768px) {
    .reports-page { padding:70px 15px 40px; }
    .page-header { padding:25px 20px; }
    .page-title { font-size:22px; }
    .chart-grid { grid-template-columns:1fr; }
    .week-compare-grid { grid-template-columns:repeat(2,1fr); }
}
@media (max-width:480px) {
    .week-compare-grid { grid-template-columns:1fr; }
    .summary-grid { grid-template-columns:repeat(2,1fr); }
}
</style>

<main class="reports-page">
<div class="page-container">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="header-content">
            <div>
                <h1 class="page-title">My Reports & Analytics</h1>
                <p class="page-subtitle">
                    <?php echo htmlspecialchars($group_info['group_name'] ?? 'Personal'); ?>
                    &mdash; Inventory insights, waste reduction &amp; consumption trends
                </p>
            </div>
            <a href="../dashboard.php" class="btn-secondary">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Dashboard
            </a>
        </div>
    </div>

    <!-- EXPORT BAR -->
    <div class="export-bar">
        <button class="export-btn" onclick="window.print()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            Print / Save PDF
        </button>
        <a href="export_report.php?type=weekly" class="export-btn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Weekly Export (CSV)
        </a>
        <a href="export_report.php?type=monthly" class="export-btn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            Monthly Summary (CSV)
        </a>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                </svg>
            </div>
            <div class="summary-content">
                <h3><?php echo number_format($summary['total_items'] ?? 0); ?></h3>
                <p>Total Items</p>
                <small style="color:rgba(0,0,0,.5);"><?php echo number_format($summary['fresh_items'] ?? 0); ?> fresh &bull; <?php echo number_format($summary['low_stock_items'] ?? 0); ?> low stock</small>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </div>
            <div class="summary-content">
                <h3><?php echo $efficiency_pct; ?>%</h3>
                <p>Consumption Efficiency</p>
                <small style="color:#059669;">Items consumed vs. wasted</small>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon warning">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <div class="summary-content">
                <h3><?php echo number_format(floatval($waste['total_wasted_qty'] ?? 0), 1); ?></h3>
                <p>Total Units Wasted</p>
                <small style="color:#c2410c;"><?php echo number_format($waste['total_waste_events'] ?? 0); ?> waste events logged</small>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            </div>
            <div class="summary-content">
                <h3><?php echo number_format($summary['near_expiry_items'] ?? 0); ?></h3>
                <p>Near Expiry</p>
                <small style="color:#b91c1c;"><?php echo number_format($summary['expired_items'] ?? 0); ?> already expired</small>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div class="summary-content">
                <h3><?php echo number_format($points_data['total_points'] ?? 0); ?></h3>
                <p>Points Earned</p>
                <small style="color:rgba(0,0,0,.5);"><?php echo number_format($points_data['total_actions'] ?? 0); ?> tracked actions</small>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/>
                    <line x1="6" y1="20" x2="6" y2="14"/><line x1="3" y1="20" x2="21" y2="20"/>
                </svg>
            </div>
            <div class="summary-content">
                <h3><?php echo number_format($summary['categories_used'] ?? 0); ?></h3>
                <p>Categories Tracked</p>
            </div>
        </div>
    </div>

    <!-- WEEKLY PERFORMANCE SNAPSHOT -->
    <div class="report-section">
        <h2 class="section-title">Weekly Performance Snapshot</h2>
        <p class="section-subtitle">This week vs. last week — track your progress in real time</p>

        <?php
        function delta_badge(float $current, float $prev, bool $lower_is_better = false): string {
            if ($prev == 0) return '<span class="wc-change neutral">No prior data</span>';
            $pct = round((($current - $prev) / $prev) * 100, 1);
            $up  = $pct >= 0;
            if ($lower_is_better) { $class = $up ? 'negative' : 'positive'; $icon = $up ? '↑' : '↓'; }
            else                   { $class = $up ? 'positive' : 'negative'; $icon = $up ? '↑' : '↓'; }
            return '<span class="wc-change ' . $class . '">' . $icon . ' ' . abs($pct) . '% vs last week</span>';
        }
        $tw_c = floatval($weekly['this_week_consumed'] ?? 0);
        $lw_c = floatval($weekly['last_week_consumed'] ?? 0);
        $tw_a = floatval($weekly['this_week_added']    ?? 0);
        $lw_a = floatval($weekly['last_week_added']    ?? 0);
        $tw_w = floatval($waste['waste_this_week']     ?? 0);
        $lw_w = floatval($waste['waste_last_week']     ?? 0);
        $saved = max(0, $lw_w - $tw_w);
        ?>
        <div class="week-compare-grid">
            <div class="week-card">
                <div class="wc-label">Items Consumed</div>
                <div class="wc-value"><?php echo number_format($tw_c, 1); ?></div>
                <?php echo delta_badge($tw_c, $lw_c); ?>
            </div>
            <div class="week-card">
                <div class="wc-label">Items Added</div>
                <div class="wc-value"><?php echo number_format($tw_a, 1); ?></div>
                <?php echo delta_badge($tw_a, $lw_a); ?>
            </div>
            <div class="week-card">
                <div class="wc-label">Units Wasted</div>
                <div class="wc-value"><?php echo number_format($tw_w, 1); ?></div>
                <?php echo delta_badge($tw_w, $lw_w, true); ?>
            </div>
            <div class="week-card">
                <div class="wc-label">Waste Reduction Saving</div>
                <div class="wc-value"><?php echo number_format($saved, 1); ?> units</div>
                <?php
                if ($saved > 0) echo '<span class="wc-change positive">↓ ' . number_format($saved, 1) . ' fewer wasted</span>';
                elseif ($lw_w > 0 && $tw_w == $lw_w) echo '<span class="wc-change neutral">Same as last week</span>';
                elseif ($lw_w == 0 && $tw_w == 0) echo '<span class="wc-change neutral">No waste recorded</span>';
                else echo '<span class="wc-change negative">↑ More waste than last week</span>';
                ?>
            </div>
        </div>
    </div>

    <!-- WASTE REDUCTION METRICS -->
    <div class="report-section">
        <h2 class="section-title">Waste Reduction Metrics</h2>
        <p class="section-subtitle">How well your household consumes what it buys</p>

        <div class="metrics-row">
            <div class="metric-card">
                <div class="metric-value" style="color:#059669;"><?php echo $efficiency_pct; ?>%</div>
                <div class="metric-label">Consumed</div>
            </div>
            <div class="metric-card">
                <div class="metric-value" style="color:#b91c1c;"><?php echo $waste_pct; ?>%</div>
                <div class="metric-label">Wasted / Expired</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo number_format(floatval($efficiency['total_consumed'] ?? 0), 1); ?></div>
                <div class="metric-label">Units Consumed (All Time)</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo number_format(floatval($waste['spoiled_qty'] ?? 0), 1); ?></div>
                <div class="metric-label">Spoiled</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo number_format(floatval($waste['expired_qty'] ?? 0), 1); ?></div>
                <div class="metric-label">Expired &amp; Discarded</div>
            </div>
        </div>

        <div style="margin-top:24px;">
            <p style="font-size:12px; color:rgba(0,0,0,.6); margin-bottom:8px;">Overall consumption efficiency</p>
            <div class="efficiency-wrap">
                <?php $eff_class = $efficiency_pct >= 80 ? 'good' : ($efficiency_pct >= 60 ? 'medium' : 'bad'); ?>
                <div class="efficiency-fill <?php echo $eff_class; ?>" style="width:<?php echo $efficiency_pct; ?>%"></div>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:11px; color:rgba(0,0,0,.45); margin-top:4px;">
                <span>0% (all wasted)</span>
                <span><?php echo $efficiency_pct; ?>% efficient</span>
                <span>100% (zero waste)</span>
            </div>
        </div>
    </div>

    <!-- CONSUMPTION TREND CHARTS -->
    <div class="report-section">
        <h2 class="section-title">Consumption Trend Analysis</h2>
        <p class="section-subtitle">4-week rolling view of your inventory activity with 6-month historical context</p>
        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-title">Weekly Flow — Consumed vs Wasted vs Added</div>
                <canvas id="trendChart"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-title">Monthly Summary — Last 6 Months</div>
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    </div>

    <!-- CATEGORY BREAKDOWN -->
    <div class="report-section">
        <h2 class="section-title">Category Breakdown</h2>
        <p class="section-subtitle">Item distribution and expiry status across all categories</p>
        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-title">Items by Category</div>
                <canvas id="catChart"></canvas>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Items</th>
                            <th>Near Expiry</th>
                            <th>Expired</th>
                            <th>Distribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_cat = array_sum(array_column($cat_data, 'item_count'));
                        foreach ($cat_data as $cat):
                            $pct = $total_cat > 0 ? ($cat['item_count'] / $total_cat * 100) : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($cat['category_name']); ?></strong></td>
                            <td><?php echo $cat['item_count']; ?></td>
                            <td><?php echo $cat['near_expiry_count'] > 0 ? '<span class="badge-mini warning">'.$cat['near_expiry_count'].'</span>' : '<span style="color:rgba(0,0,0,.3);">—</span>'; ?></td>
                            <td><?php echo $cat['expired_count'] > 0 ? '<span class="badge-mini danger">'.$cat['expired_count'].'</span>' : '<span style="color:rgba(0,0,0,.3);">—</span>'; ?></td>
                            <td style="min-width:130px;">
                                <div class="prog-bar"><div class="prog-fill" style="width:<?php echo $pct; ?>%"></div></div>
                                <small style="color:rgba(0,0,0,.4); font-size:10px;"><?php echo number_format($pct,1); ?>%</small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cat_data)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:30px; color:rgba(0,0,0,.4);">No category data available.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MONTHLY SUMMARY STATISTICS -->
    <div class="report-section">
        <h2 class="section-title">Monthly Summary Statistics</h2>
        <p class="section-subtitle">Month-by-month inventory activity for the last 6 months</p>

        <?php if (!empty($monthly_data)): ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Added</th>
                        <th>Consumed</th>
                        <th>Wasted</th>
                        <th>Unique Items</th>
                        <th>Waste Rate</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly_data as $m):
                        $m_total = floatval($m['items_consumed']) + floatval($m['items_wasted']);
                        $m_waste_rate = $m_total > 0 ? round((floatval($m['items_wasted']) / $m_total) * 100, 1) : 0;
                        $m_status = $m_waste_rate <= 10 ? 'success' : ($m_waste_rate <= 25 ? 'warning' : 'danger');
                        $m_label  = $m_waste_rate <= 10 ? 'Great' : ($m_waste_rate <= 25 ? 'Fair' : 'High Waste');
                        $bar_color = $m_status === 'danger' ? '#ef4444' : ($m_status === 'warning' ? '#f59e0b' : '#10b981');
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($m['month_label']); ?></strong></td>
                        <td><?php echo number_format($m['items_added'], 1); ?></td>
                        <td style="color:#059669;"><?php echo number_format($m['items_consumed'], 1); ?></td>
                        <td style="color:#b91c1c;"><?php echo number_format($m['items_wasted'], 1); ?></td>
                        <td><?php echo number_format($m['unique_items']); ?></td>
                        <td>
                            <div class="prog-bar" style="width:90px; display:inline-block; vertical-align:middle;">
                                <div class="prog-fill" style="width:<?php echo min(100,$m_waste_rate); ?>%; background:<?php echo $bar_color; ?>;"></div>
                            </div>
                            <span style="font-size:11px; color:rgba(0,0,0,.5); margin-left:6px;"><?php echo $m_waste_rate; ?>%</span>
                        </td>
                        <td><span class="badge-mini <?php echo $m_status; ?>"><?php echo $m_label; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state"><p>No monthly data yet. Start logging your inventory activity!</p></div>
        <?php endif; ?>
    </div>

    <!-- TOP WASTED ITEMS -->
    <div class="report-section">
        <h2 class="section-title">Top Wasted Items</h2>
        <p class="section-subtitle">Items most frequently spoiled or expired — reduce waste by focusing here first</p>

        <?php if (!empty($top_waste)): ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Total Wasted</th>
                        <th>Waste Events</th>
                        <th>Assessment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $max_w = max(array_column($top_waste, 'total_wasted'));
                    foreach ($top_waste as $i => $item):
                        $bar_pct = $max_w > 0 ? ($item['total_wasted'] / $max_w * 100) : 0;
                    ?>
                    <tr>
                        <td style="color:rgba(0,0,0,.35); font-weight:600; font-size:12px;">#<?php echo $i+1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                            <div class="prog-bar" style="width:120px; margin-top:5px;">
                                <div class="prog-fill" style="width:<?php echo $bar_pct; ?>%; background:#ef4444;"></div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($item['category_name'] ?? '—'); ?></td>
                        <td style="color:#b91c1c;"><?php echo number_format(floatval($item['total_wasted']), 1); ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                        <td><?php echo number_format($item['waste_events']); ?> ×</td>
                        <td>
                            <?php if ($item['waste_events'] > 3): ?>
                                <span class="badge-mini danger">Recurring Issue</span>
                            <?php elseif ($item['waste_events'] > 1): ?>
                                <span class="badge-mini warning">Monitor</span>
                            <?php else: ?>
                                <span class="badge-mini info">One-off</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg>
            <p>No waste logged yet — keep it up!</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- 14-DAY EXPIRY FORECAST -->
    <div class="report-section">
        <h2 class="section-title">14-Day Expiry Forecast</h2>
        <p class="section-subtitle">Items expiring soon — act now to minimise waste and save money</p>

        <?php if (!empty($expiry_items)): ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Qty</th>
                        <th>Expiry Date</th>
                        <th>Days Left</th>
                        <th>Suggested Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expiry_items as $exp):
                        $d = intval($exp['days_left']);
                        $badge = $d <= 2 ? 'danger' : ($d <= 7 ? 'warning' : 'info');
                        $action = $d <= 2 ? 'Use immediately' : ($d <= 5 ? 'Plan a meal soon' : 'Monitor closely');
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($exp['item_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($exp['category_name'] ?? '—'); ?></td>
                        <td><?php echo number_format($exp['quantity'], 1); ?> <?php echo htmlspecialchars($exp['unit']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($exp['expiry_date'])); ?></td>
                        <td><span class="badge-mini <?php echo $badge; ?>"><?php echo $d; ?> day<?php echo $d == 1 ? '' : 's'; ?></span></td>
                        <td style="font-size:12px; color:rgba(0,0,0,.55);"><?php echo $action; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg>
            <p>Nothing expiring in the next 14 days — your pantry is in great shape!</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- COST SAVINGS CALCULATIONS -->
    <div class="report-section">
        <h2 class="section-title">Cost Savings Calculations</h2>
        <p class="section-subtitle">Estimated impact of your waste reduction this week vs. last week</p>

        <div class="metrics-row">
            <div class="metric-card">
                <div class="metric-value"><?php echo number_format($saved, 1); ?></div>
                <div class="metric-label">Units Saved This Week</div>
            </div>
            <div class="metric-card">
                <div class="metric-value" style="color:#b91c1c;"><?php echo number_format($tw_w, 1); ?></div>
                <div class="metric-label">Wasted This Week</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo number_format($lw_w, 1); ?></div>
                <div class="metric-label">Wasted Last Week</div>
            </div>
            <div class="metric-card">
                <div class="metric-value" style="color:<?php echo ($lw_w >= $tw_w) ? '#059669' : '#b91c1c'; ?>">
                    <?php echo ($lw_w >= $tw_w) ? '↓' : '↑'; ?> <?php echo number_format(abs($lw_w - $tw_w), 1); ?>
                </div>
                <div class="metric-label">Week-over-Week Change</div>
            </div>
        </div>

        <div class="tips-box">
            <strong style="color:#0a0a0a; display:block; margin-bottom:6px;">Personalised Reduction Tips</strong>
            <?php if (!empty($top_waste)): ?>
            &bull; Your most wasted item is <strong><?php echo htmlspecialchars($top_waste[0]['item_name']); ?></strong> — consider buying smaller quantities or planning meals around it sooner.<br>
            <?php endif; ?>
            <?php if ($tw_w > $lw_w): ?>
            &bull; Waste increased this week. Review items expiring in the next 3 days and use them first.<br>
            <?php else: ?>
            &bull; Great progress! Waste decreased this week. Keep consuming near-expiry items first (FIFO).<br>
            <?php endif; ?>
            &bull; Using the <strong>14-day expiry forecast</strong> above daily can significantly reduce expired-item waste.<br>
            &bull; Your consumption efficiency is <strong><?php echo $efficiency_pct; ?>%</strong>
            <?php if ($efficiency_pct >= 80) echo '— excellent! Aim to keep it above 80%.';
            elseif ($efficiency_pct >= 60) echo '— fair. Target 80%+ by buying smaller batches.';
            else echo '— needs attention. Focus on consuming older items before adding new stock.'; ?>
        </div>
    </div>

</div><!-- .page-container -->
</main>

<script>
const pal = {
    consumed : { bg:'rgba(16,185,129,.15)', border:'#10b981' },
    wasted   : { bg:'rgba(239,68,68,.15)',  border:'#ef4444' },
    added    : { bg:'rgba(59,130,246,.15)', border:'#3b82f6' },
};
const baseOpts = {
    responsive:true, maintainAspectRatio:true,
    plugins:{ legend:{ labels:{ font:{family:'Montserrat',size:11}, color:'#444' } } },
    scales:{
        x:{ grid:{display:false}, ticks:{font:{family:'Montserrat',size:10},color:'#888'} },
        y:{ grid:{color:'rgba(0,0,0,.05)'}, ticks:{font:{family:'Montserrat',size:10},color:'#888'} }
    }
};

// Weekly trend
const tL = <?php echo $trend_labels; ?>;
if (tL.length) {
    new Chart(document.getElementById('trendChart'), {
        type:'bar',
        data:{
            labels: tL,
            datasets:[
                { label:'Consumed', data:<?php echo $trend_consumed; ?>, backgroundColor:pal.consumed.bg, borderColor:pal.consumed.border, borderWidth:2, borderRadius:3 },
                { label:'Wasted',   data:<?php echo $trend_wasted; ?>,   backgroundColor:pal.wasted.bg,   borderColor:pal.wasted.border,   borderWidth:2, borderRadius:3 },
                { label:'Added',    data:<?php echo $trend_added; ?>,    type:'line', borderColor:pal.added.border, backgroundColor:'transparent', borderWidth:2, tension:.4, pointRadius:4 }
            ]
        },
        options:{ ...baseOpts, plugins:{...baseOpts.plugins, tooltip:{mode:'index',intersect:false}} }
    });
} else {
    document.getElementById('trendChart').parentElement.innerHTML = '<p style="text-align:center;color:rgba(0,0,0,.4);padding:50px 0;font-size:13px;">No weekly activity logged yet.</p>';
}

// Monthly
const mL = <?php echo $monthly_labels; ?>;
if (mL.length) {
    new Chart(document.getElementById('monthlyChart'), {
        type:'line',
        data:{
            labels: mL,
            datasets:[
                { label:'Consumed', data:<?php echo $monthly_consumed; ?>, borderColor:pal.consumed.border, backgroundColor:pal.consumed.bg, fill:true, tension:.4, pointRadius:5, borderWidth:2 },
                { label:'Wasted',   data:<?php echo $monthly_wasted; ?>,   borderColor:pal.wasted.border,   backgroundColor:pal.wasted.bg,   fill:true, tension:.4, pointRadius:5, borderWidth:2 }
            ]
        },
        options: baseOpts
    });
} else {
    document.getElementById('monthlyChart').parentElement.innerHTML = '<p style="text-align:center;color:rgba(0,0,0,.4);padding:50px 0;font-size:13px;">No monthly data yet.</p>';
}

// Category doughnut
const cL = <?php echo $cat_labels; ?>;
if (cL.length) {
    const colors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#14b8a6','#6366f1','#84cc16'];
    new Chart(document.getElementById('catChart'), {
        type:'doughnut',
        data:{ labels:cL, datasets:[{ data:<?php echo $cat_counts; ?>, backgroundColor:colors.slice(0,cL.length), borderWidth:2, borderColor:'#fff' }] },
        options:{ responsive:true, maintainAspectRatio:true,
            plugins:{ legend:{ position:'bottom', labels:{ font:{family:'Montserrat',size:10}, color:'#555', padding:10, boxWidth:12 } } } }
    });
} else {
    document.getElementById('catChart').parentElement.innerHTML = '<p style="text-align:center;color:rgba(0,0,0,.4);padding:50px 0;font-size:13px;">No category data yet.</p>';
}
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';

$summary_stmt->close();
$waste_stmt->close();
$weekly_stmt->close();
$trend_stmt->close();
$monthly_stmt->close();
$cat_stmt->close();
$top_waste_stmt->close();
$efficiency_stmt->close();
$expiry_stmt->close();
$points_stmt->close();
$group_stmt->close();
$conn->close();
?>