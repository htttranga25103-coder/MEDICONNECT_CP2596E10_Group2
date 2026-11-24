
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/admin_guard.php';
require_once __DIR__ . '/../config/database.php';

// Helper function
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

try {

    // Count cities
    $cityCount = (int)$pdo->query("
        SELECT COUNT(*) FROM cities
    ")->fetchColumn();

    // Count districts
    $districtCount = (int)$pdo->query("
        SELECT COUNT(*) FROM districts
    ")->fetchColumn();

    // Count streets
    $streetCount = (int)$pdo->query("
        SELECT COUNT(*) FROM streets
    ")->fetchColumn();

} catch (PDOException $exception) {

    error_log("Location dashboard error: " . $exception->getMessage());

    http_response_code(500);
    exit("Unable to load location management data. Please try again later.");
}

require_once __DIR__ . '/layout_header.php';
?>

<main>

    <h2 class="page-title">Location Management</h2>

    <p>
        Manage cities, districts, and streets in the MediConnect system.
    </p>

    <div class="stat-summary-row">

        <div class="stat-summary-card">

            <div class="stat-summary-label">Total Cities</div>

            <div class="stat-summary-value-row">
                <span class="stat-summary-number">
                    <?= number_format($cityCount) ?>
                </span>
                <span class="stat-summary-sub">Cities</span>
            </div>

            <a href="cities.php" class="btn-mc btn-mc-teal">
                Manage Cities
            </a>

        </div>

        <div class="stat-summary-card">

            <div class="stat-summary-label">Total Districts</div>

            <div class="stat-summary-value-row">
                <span class="stat-summary-number">
                    <?= number_format($districtCount) ?>
                </span>
                <span class="stat-summary-sub">Districts</span>
            </div>

            <a href="districts.php" class="btn-mc btn-mc-teal">
                Manage Districts
            </a>

        </div>

        <div class="stat-summary-card">

            <div class="stat-summary-label">Total Streets</div>

            <div class="stat-summary-value-row">
                <span class="stat-summary-number">
                    <?= number_format($streetCount) ?>
                </span>
                <span class="stat-summary-sub">Streets</span>
            </div>

            <a href="streets.php" class="btn-mc btn-mc-teal">
                Manage Streets
            </a>

        </div>

    </div>

    <div class="panel-card">

        <div class="panel-header">
            <h3 class="panel-title">Location Database Overview</h3>
        </div>

        <p>
            The Location Management module allows administrators to
            organize and maintain the geographical data used by MediConnect.
        </p>

        <p>
            Select one of the management sections above to view, add,
            edit, delete, or search location records.
        </p>

    </div>

</main>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
