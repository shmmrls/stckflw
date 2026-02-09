<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Total consumption stats
$total_stats = $conn->prepare("
    SELECT 
        COUNT(DISTINCT ci.item_id) as unique_items,
        COUNT(DISTINCT c.category_id) as unique_categories,
        SUM(CASE WHEN ciu.update_type = 'consumed' THEN ciu.quantity_change ELSE 0 END) as total_consumed,
        COUNT(DISTINCT CASE WHEN ciu.update_type = 'added' THEN ciu.update_id END) as total_added
    FROM customer_items ci
    LEFT JOIN customer_inventory_updates ciu ON ci.item_id = ciu.item_id AND ciu.updated_by = ?
    LEFT JOIN categories c ON ci.category_id = c.category_id
    WHERE ci.created_by = ?
");
$total_stats->bind_param("ii", $user_id, $user_id);
$total_stats->execute();
$stats = $total_stats->get_result()->fetch_assoc();

// Consumption by category (for pie chart)
$category_consumption = $conn->prepare("
    SELECT 
        c.category_name,
        COUNT(DISTINCT ciu.update_id) as count,
        SUM(ciu.quantity_change) as total_qty
    FROM customer_items ci
    INNER JOIN categories c ON ci.category_id = c.category_id
    LEFT JOIN customer_inventory_updates ciu ON ci.item_id = ciu.item_id AND ciu.updated_by = ? AND ciu.update_type = 'consumed'
    WHERE ci.created_by = ?
    GROUP BY c.category_id, c.category_name
    ORDER BY count DESC
");
$category_consumption->bind_param("ii", $user_id, $user_id);
$category_consumption->execute();
$category_result = $category_consumption->get_result();

// Monthly trends (last 12 months)
$monthly_trends = $conn->prepare("
    SELECT 
        DATE_FORMAT(ciu.update_date, '%Y-%m') as month,
        DATE_FORMAT(ciu.update_date, '%b %y') as month_label,
        COUNT(CASE WHEN ciu.update_type = 'added' THEN 1 END) as added,
        COUNT(CASE WHEN ciu.update_type = 'consumed' THEN 1 END) as consumed,
        COUNT(CASE WHEN ciu.update_type = 'spoiled' THEN 1 END) as spoiled,
        COUNT(CASE WHEN ciu.update_type = 'expired' THEN 1 END) as expired
    FROM customer_items ci
    LEFT JOIN customer_inventory_updates ciu ON ci.item_id = ciu.item_id AND ciu.updated_by = ?
    WHERE ci.created_by = ?
    GROUP BY DATE_FORMAT(ciu.update_date, '%Y-%m'), DATE_FORMAT(ciu.update_date, '%b %y')
    ORDER BY month DESC
    LIMIT 12
");
$monthly_trends->bind_param("ii", $user_id, $user_id);
$monthly_trends->execute();
$trends_result = $monthly_trends->get_result();

// Top items consumed
$top_items = $conn->prepare("
    SELECT 
        ci.item_name,
        c.category_name,
        COUNT(DISTINCT ciu.update_id) as consumption_count,
        SUM(CASE WHEN ciu.update_type = 'consumed' THEN ciu.quantity_change ELSE 0 END) as total_consumed
    FROM customer_items ci
    INNER JOIN categories c ON ci.category_id = c.category_id
    LEFT JOIN customer_inventory_updates ciu ON ci.item_id = ciu.item_id AND ciu.updated_by = ? AND ciu.update_type = 'consumed'
    WHERE ci.created_by = ?
    GROUP BY ci.item_id, ci.item_name, c.category_name
    HAVING consumption_count > 0
    ORDER BY consumption_count DESC
    LIMIT 10
");
$top_items->bind_param("ii", $user_id, $user_id);
$top_items->execute();
$top_items_result = $top_items->get_result();

// Activity type breakdown (last 30 days)
$activity_breakdown = $conn->prepare("
    SELECT 
        ciu.update_type,
        COUNT(DISTINCT ciu.update_id) as count
    FROM customer_items ci
    LEFT JOIN customer_inventory_updates ciu ON ci.item_id = ciu.item_id AND ciu.updated_by = ? 
        AND DATE_SUB(NOW(), INTERVAL 30 DAY) <= ciu.update_date
    WHERE ci.created_by = ?
    GROUP BY ciu.update_type
");
$activity_breakdown->bind_param("ii", $user_id, $user_id);
$activity_breakdown->execute();
$breakdown_result = $activity_breakdown->get_result();

// Prepare data for JavaScript charts
$categories = [];
$consumption_counts = [];
$monthly_labels = [];
$monthly_consumption = [];
$monthly_added = [];
$monthly_waste = [];

// Populate category data
$category_result->data_seek(0);
while ($row = $category_result->fetch_assoc()) {
    $categories[] = $row['category_name'];
    $consumption_counts[] = (int)$row['count'];
}

// Populate monthly data (reverse for chronological order)
$monthly_data_temp = [];
$trends_result->data_seek(0);
while ($row = $trends_result->fetch_assoc()) {
    $monthly_data_temp[] = $row;
}
$monthly_data_temp = array_reverse($monthly_data_temp);
foreach ($monthly_data_temp as $row) {
    $monthly_labels[] = $row['month_label'];
    $monthly_added[] = (int)$row['added'];
    $monthly_consumption[] = (int)$row['consumed'];
    $monthly_waste[] = ((int)$row['spoiled'] + (int)$row['expired']);
}

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/analytics.css">';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="analytics-page">
    <div class="page-container">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <h1 class="page-title">Analytics</h1>
                <p class="page-subtitle">Analyze your inventory usage and consumption patterns</p>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="analytics-stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <p class="stat-value"><?php echo $stats['unique_items']; ?></p>
                    <p class="stat-label">Items Added</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="9" cy="21" r="1"/>
                        <circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <p class="stat-value"><?php echo number_format($stats['total_consumed'] ?? 0, 1); ?></p>
                    <p class="stat-label">Total Consumed</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <p class="stat-value"><?php echo $stats['unique_categories']; ?></p>
                    <p class="stat-label">Categories</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <p class="stat-value">
                        <?php 
                            $total_actions = ($stats['total_added'] ?? 0);
                            echo $total_actions > 0 ? $total_actions : '0'; 
                        ?>
                    </p>
                    <p class="stat-label">Total Actions</p>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="charts-grid">
            
            <!-- Consumption by Category -->
            <section class="chart-section">
                <div class="section-header">
                    <h2>Consumption by Category</h2>
                </div>
                <div class="chart-container">
                    <canvas id="categoryChart"></canvas>
                </div>
            </section>

            <!-- Monthly Trends -->
            <section class="chart-section full-width">
                <div class="section-header">
                    <h2>Activity Trends (Last 12 Months)</h2>
                </div>
                <div class="chart-container large">
                    <canvas id="trendsChart"></canvas>
                </div>
            </section>

        </div>

        <!-- Data Tables -->
        <div class="data-tables">
            
            <!-- Top Items -->
            <section class="data-section">
                <div class="section-header">
                    <h2>Top Consumed Items</h2>
                </div>
                <?php if ($top_items_result->num_rows === 0): ?>
                    <div class="empty-state">
                        <p>No consumption data available</p>
                    </div>
                <?php else: ?>
                    <div class="data-table">
                        <div class="table-header">
                            <div class="col-rank">#</div>
                            <div class="col-item">Item Name</div>
                            <div class="col-category">Category</div>
                            <div class="col-count">Consumed</div>
                            <div class="col-qty">Qty</div>
                        </div>
                        <?php 
                            $rank = 1;
                            while ($item = $top_items_result->fetch_assoc()): 
                        ?>
                            <div class="table-row">
                                <div class="col-rank">
                                    <span class="rank-badge">
                                        <?php if ($rank === 1): ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                                <circle cx="12" cy="12" r="10" fill="#FFD700"/>
                                                <text x="12" y="16" text-anchor="middle" font-size="10" font-weight="bold" fill="#000">1</text>
                                            </svg>
                                        <?php elseif ($rank === 2): ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                                <circle cx="12" cy="12" r="10" fill="#C0C0C0"/>
                                                <text x="12" y="16" text-anchor="middle" font-size="10" font-weight="bold" fill="#000">2</text>
                                            </svg>
                                        <?php elseif ($rank === 3): ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                                <circle cx="12" cy="12" r="10" fill="#CD7F32"/>
                                                <text x="12" y="16" text-anchor="middle" font-size="10" font-weight="bold" fill="#000">3</text>
                                            </svg>
                                        <?php else: ?>
                                            <?php echo $rank; ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="col-item"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                <div class="col-category">
                                    <span class="category-badge"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                </div>
                                <div class="col-count">
                                    <strong><?php echo $item['consumption_count']; ?></strong>
                                </div>
                                <div class="col-qty"><?php echo number_format($item['total_consumed'] ?? 0, 1); ?></div>
                            </div>
                            <?php $rank++; ?>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </section>

        </div>

    </div>
</main>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
// Category Consumption Pie Chart
const ctxCategory = document.getElementById('categoryChart')?.getContext('2d');
if (ctxCategory) {
    const categoryChart = new Chart(ctxCategory, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($categories); ?>,
            datasets: [{
                data: <?php echo json_encode($consumption_counts); ?>,
                backgroundColor: [
                    'rgba(34, 197, 94, 0.8)',
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(249, 115, 22, 0.8)',
                    'rgba(168, 85, 247, 0.8)',
                    'rgba(236, 72, 153, 0.8)',
                    'rgba(14, 165, 233, 0.8)',
                    'rgba(15, 23, 42, 0.8)',
                    'rgba(107, 114, 128, 0.8)'
                ],
                borderColor: 'rgba(255, 255, 255, 1)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { family: "'Montserrat', sans-serif", size: 11 },
                        padding: 15,
                        color: 'rgba(0, 0, 0, 0.7)'
                    }
                }
            }
        }
    });
}

// Monthly Trends Line Chart
const ctxTrends = document.getElementById('trendsChart')?.getContext('2d');
if (ctxTrends) {
    const trendsChart = new Chart(ctxTrends, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthly_labels); ?>,
            datasets: [
                {
                    label: 'Added',
                    data: <?php echo json_encode($monthly_added); ?>,
                    borderColor: 'rgba(34, 197, 94, 1)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: 'rgba(34, 197, 94, 1)',
                    pointBorderColor: 'rgba(255, 255, 255, 1)',
                    pointBorderWidth: 2
                },
                {
                    label: 'Consumed',
                    data: <?php echo json_encode($monthly_consumption); ?>,
                    borderColor: 'rgba(249, 115, 22, 1)',
                    backgroundColor: 'rgba(249, 115, 22, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: 'rgba(249, 115, 22, 1)',
                    pointBorderColor: 'rgba(255, 255, 255, 1)',
                    pointBorderWidth: 2
                },
                {
                    label: 'Waste (Spoiled + Expired)',
                    data: <?php echo json_encode($monthly_waste); ?>,
                    borderColor: 'rgba(239, 68, 68, 1)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: 'rgba(239, 68, 68, 1)',
                    pointBorderColor: 'rgba(255, 255, 255, 1)',
                    pointBorderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: { family: "'Montserrat', sans-serif", size: 12 },
                        padding: 15,
                        color: 'rgba(0, 0, 0, 0.7)',
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        font: { family: "'Montserrat', sans-serif", size: 10 },
                        color: 'rgba(0, 0, 0, 0.5)'
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    ticks: {
                        font: { family: "'Montserrat', sans-serif", size: 10 },
                        color: 'rgba(0, 0, 0, 0.5)'
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                }
            }
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php 
$total_stats->close();
$category_consumption->close();
$monthly_trends->close();
$top_items->close();
$activity_breakdown->close();
$conn->close();
?>