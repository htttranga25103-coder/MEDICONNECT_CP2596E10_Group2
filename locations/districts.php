
<?php

// =============================================
// 1. START SESSION FOR LOCAL TESTING
// =============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/admin_guard.php';

// =============================================
// 2. DATABASE CONNECTION
// =============================================
require_once __DIR__ . '/../config/database.php';

$message = '';
$error = '';

// =============================================
// 3. HELPER FUNCTIONS
// =============================================
function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Check whether a city exists
function cityExists($pdo, $city_id)
{
    $stmt = $pdo->prepare("
        SELECT city_id
        FROM cities
        WHERE city_id = :city_id
    ");

    $stmt->execute([
        ':city_id' => $city_id
    ]);

    return $stmt->fetchColumn() !== false;
}

// Check whether a district exists
function districtExists($pdo, $district_id)
{
    $stmt = $pdo->prepare("
        SELECT district_id
        FROM districts
        WHERE district_id = :district_id
    ");

    $stmt->execute([
        ':district_id' => $district_id
    ]);

    return $stmt->fetchColumn() !== false;
}

// =============================================
// 4. GENERATE CSRF TOKEN
// =============================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =============================================
// 5. VALIDATE CSRF TOKEN
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !is_string($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $error = 'Invalid or expired request. Please try again.';
    }
}

// =============================================
// 6. PROCESS POST REQUESTS
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {

    $action = $_POST['action'] ?? '';

    if (!is_string($action)) {

        $error = 'Invalid action.';

    }

    // -----------------------------------------
    // ADD DISTRICT
    // -----------------------------------------
    elseif ($action === 'add') {

        $district_name = $_POST['district_name'] ?? '';

        $city_id = filter_var(
            $_POST['city_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            !is_string($district_name) ||
            $district_name === '' ||
            $city_id === false ||
            $city_id === null ||
            $city_id <= 0
        ) {

            $error = 'Please enter a valid district name and select a city.';

        } else {

            $district_name = trim($district_name);

            if (
                $district_name === '' ||
                mb_strlen($district_name, 'UTF-8') > 100
            ) {

                $error = 'District name is required and must not exceed 100 characters.';

            } elseif (!cityExists($pdo, $city_id)) {

                $error = 'Selected city does not exist.';

            } else {

                try {

                    $stmt = $pdo->prepare("
                        INSERT INTO districts (
                            city_id,
                            district_name
                        )
                        VALUES (
                            :city_id,
                            :district_name
                        )
                    ");

                    $stmt->execute([
                        ':city_id' => $city_id,
                        ':district_name' => $district_name
                    ]);

                    header("Location: districts.php?success=added");
                    exit();

                } catch (PDOException $exception) {

                    error_log("Add district error: " . $exception->getMessage());

                    $error = 'Unable to add district. Please try again.';
                }
            }
        }
    }

    // -----------------------------------------
    // UPDATE DISTRICT
    // -----------------------------------------
    elseif ($action === 'update') {

        $district_id = filter_var(
            $_POST['district_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $city_id = filter_var(
            $_POST['city_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $district_name = $_POST['district_name'] ?? '';

        if (!is_string($district_name)) {

            $error = 'Invalid district name.';

        } else {

            $district_name = trim($district_name);

            if (
                $district_id === false ||
                $district_id === null ||
                $district_id <= 0 ||
                $city_id === false ||
                $city_id === null ||
                $city_id <= 0 ||
                $district_name === '' ||
                mb_strlen($district_name, 'UTF-8') > 100
            ) {

                $error = 'Please enter valid district information.';

            } elseif (!districtExists($pdo, $district_id)) {

                $error = 'District not found.';

            } elseif (!cityExists($pdo, $city_id)) {

                $error = 'Selected city does not exist.';

            } else {

                try {

                    $stmt = $pdo->prepare("
                        UPDATE districts
                        SET
                            city_id = :city_id,
                            district_name = :district_name
                        WHERE district_id = :district_id
                    ");

                    $stmt->execute([
                        ':city_id' => $city_id,
                        ':district_name' => $district_name,
                        ':district_id' => $district_id
                    ]);

                    header("Location: districts.php?success=updated");
                    exit();

                } catch (PDOException $exception) {

                    error_log("Update district error: " . $exception->getMessage());

                    $error = 'Unable to update district. Please try again.';
                }
            }
        }
    }

    // -----------------------------------------
    // DELETE DISTRICT
    // -----------------------------------------
    elseif ($action === 'delete') {

        $district_id = filter_var(
            $_POST['district_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $district_id === false ||
            $district_id === null ||
            $district_id <= 0
        ) {

            $error = 'Invalid district ID.';

        } elseif (!districtExists($pdo, $district_id)) {

            $error = 'District not found.';

        } else {

            try {

                $stmt = $pdo->prepare("
                    DELETE FROM districts
                    WHERE district_id = :district_id
                ");

                $stmt->execute([
                    ':district_id' => $district_id
                ]);

                if ($stmt->rowCount() > 0) {

                    header("Location: districts.php?success=deleted");
                    exit();

                } else {

                    $error = 'District not found.';
                }

            } catch (PDOException $exception) {

                error_log("Delete district error: " . $exception->getMessage());

                $error = 'Cannot delete this district because it may contain streets or be used by other records.';
            }
        }
    }

    // -----------------------------------------
    // INVALID ACTION
    // -----------------------------------------
    else {

        $error = 'Unknown action.';
    }
}

// =============================================
// 7. SUCCESS MESSAGES
// =============================================
if (
    isset($_GET['success']) &&
    is_string($_GET['success'])
) {

    $messages = [
        'added' => 'District added successfully!',
        'updated' => 'District updated successfully!',
        'deleted' => 'District deleted successfully!'
    ];

    $message = $messages[$_GET['success']] ?? '';
}

// =============================================
// 8. GET CITIES FOR DROPDOWN
// =============================================
$cityList = [];

try {

    $cityStmt = $pdo->query("
        SELECT city_id, city_name
        FROM cities
        ORDER BY city_name ASC
    ");

    $cityList = $cityStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {

    error_log("Load cities error: " . $exception->getMessage());

    $error = 'Unable to load cities. Please try again later.';
}

// =============================================
// 9. SEARCH AND FILTER DISTRICTS
// =============================================
$keyword = $_GET['search'] ?? '';

if (!is_string($keyword)) {
    $keyword = '';
}

$keyword = trim($keyword);

$selectedCity = filter_var(
    $_GET['city_id'] ?? 0,
    FILTER_VALIDATE_INT
);

if (
    $selectedCity === false ||
    $selectedCity === null ||
    $selectedCity < 0
) {
    $selectedCity = 0;
}

$districts = [];

try {

    $sql = "
        SELECT
            d.district_id,
            d.district_name,
            d.city_id,
            c.city_name,
            d.created_at
        FROM districts d
        INNER JOIN cities c
            ON d.city_id = c.city_id
        WHERE 1 = 1
    ";

    $params = [];

    if ($keyword !== '') {

        $sql .= " AND d.district_name LIKE :keyword";

        $params[':keyword'] = '%' . $keyword . '%';
    }

    if ($selectedCity > 0) {

        $sql .= " AND d.city_id = :city_id";

        $params[':city_id'] = $selectedCity;
    }

    $sql .= " ORDER BY d.district_id DESC";

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {

    error_log("Load districts error: " . $exception->getMessage());

    $error = 'Unable to load districts. Please try again later.';
}

// =============================================
// 10. GET DISTRICT FOR EDITING
// =============================================
$editDistrict = null;

if (isset($_GET['edit'])) {

    $edit_id = filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT
    );

    if (
        $edit_id !== false &&
        $edit_id !== null &&
        $edit_id > 0
    ) {

        try {

            $editStmt = $pdo->prepare("
                SELECT
                    district_id,
                    district_name,
                    city_id
                FROM districts
                WHERE district_id = :district_id
            ");

            $editStmt->execute([
                ':district_id' => $edit_id
            ]);

            $editDistrict = $editStmt->fetch(PDO::FETCH_ASSOC);

            if (!$editDistrict) {
                $error = 'District not found.';
            }

        } catch (PDOException $exception) {

            error_log("Load district for editing error: " . $exception->getMessage());

            $error = 'Unable to load district information.';
        }

    } else {

        $error = 'Invalid district ID.';
    }
}

?>


<?php require_once __DIR__ . '/layout_header.php'; ?>

<main>

    <h2 class="page-title">District Management</h2>

    <p>Manage districts within each city.</p>

    <!-- SUCCESS MESSAGE -->
    <?php if ($message !== ''): ?>
        <div class="alert-box alert-success">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <!-- ERROR MESSAGE -->
    <?php if ($error !== ''): ?>
        <div class="alert-box alert-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- ADD / EDIT DISTRICT -->
    <div class="panel-card">

        <div class="panel-header">
            <h3 class="panel-title">
                <?= $editDistrict ? 'Edit District' : 'Add New District' ?>
            </h3>
        </div>

        <?php if (count($cityList) > 0): ?>

            <form method="POST" action="districts.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="<?= $editDistrict ? 'update' : 'add' ?>"
                >

                <?php if ($editDistrict): ?>
                    <input
                        type="hidden"
                        name="district_id"
                        value="<?= (int)$editDistrict['district_id'] ?>"
                    >
                <?php endif; ?>

                <div class="form-grid">

                    <div class="form-group">

                        <label for="district_name" class="form-label">
                            District Name
                        </label>

                        <input
                            type="text"
                            id="district_name"
                            name="district_name"
                            class="form-control"
                            maxlength="100"
                            required
                            value="<?= e($editDistrict['district_name'] ?? '') ?>"
                            placeholder="Enter district name"
                        >

                    </div>

                    <div class="form-group">

                        <label for="city_id" class="form-label">
                            Select City
                        </label>

                        <select
                            name="city_id"
                            id="city_id"
                            class="form-select"
                            required
                        >

                            <option value="">
                                -- Select City --
                            </option>

                            <?php foreach ($cityList as $city): ?>

                                <option
                                    value="<?= (int)$city['city_id'] ?>"
                                    <?= (int)($editDistrict['city_id'] ?? 0) === (int)$city['city_id'] ? 'selected' : '' ?>
                                >
                                    <?= e($city['city_name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>

                <div class="form-actions">

                    <button type="submit" class="btn-mc btn-mc-teal">
                        <?= $editDistrict ? 'Update District' : 'Add District' ?>
                    </button>

                    <?php if ($editDistrict): ?>
                        <a href="districts.php" class="btn-mc btn-mc-outline-gray">
                            Cancel
                        </a>
                    <?php endif; ?>

                </div>

            </form>

        <?php else: ?>

            <p>Please add a city before creating a district.</p>

            <div class="form-actions">
                <a href="cities.php" class="btn-mc btn-mc-teal">
                    Manage Cities
                </a>
            </div>

        <?php endif; ?>

    </div>

    <!-- SEARCH AND FILTER -->
    <div class="panel-card">

        <div class="panel-header">
            <h3 class="panel-title">Search and Filter Districts</h3>
        </div>

        <form method="GET" action="districts.php" class="search-bar">

            <input
                type="text"
                name="search"
                class="form-control"
                value="<?= e($keyword) ?>"
                placeholder="Search district name"
            >

            <select name="city_id" class="form-select">

                <option value="0">
                    All Cities
                </option>

                <?php foreach ($cityList as $city): ?>

                    <option
                        value="<?= (int)$city['city_id'] ?>"
                        <?= $selectedCity === (int)$city['city_id'] ? 'selected' : '' ?>
                    >
                        <?= e($city['city_name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <button type="submit" class="btn-mc btn-mc-teal">
                Search
            </button>

            <a href="districts.php" class="btn-mc btn-mc-outline-gray">
                Show All
            </a>

        </form>

    </div>

    <!-- DISTRICT LIST -->
    <div class="panel-card">

        <div class="panel-header">
            <h3 class="panel-title">District List</h3>
        </div>

        <?php if (count($districts) > 0): ?>

            <div class="table-responsive">

                <table class="custom-table">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>District Name</th>
                            <th>City</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($districts as $district): ?>

                            <tr>

                                <td>
                                    <?= (int)$district['district_id'] ?>
                                </td>

                                <td>
                                    <?= e($district['district_name']) ?>
                                </td>

                                <td>
                                    <?= e($district['city_name']) ?>
                                </td>

                                <td>
                                    <?= e($district['created_at'] ?? '') ?>
                                </td>

                                <td>

                                    <a
                                        href="districts.php?edit=<?= (int)$district['district_id'] ?>"
                                        class="btn-mc btn-mc-outline-gray"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="districts.php"
                                        style="display:inline;"
                                        onsubmit="return confirm('Are you sure you want to delete this district?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($_SESSION['csrf_token']) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="district_id"
                                            value="<?= (int)$district['district_id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn-mc btn-mc-danger"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p>No districts found.</p>

        <?php endif; ?>

    </div>

    <p>
        <a href="index.php" class="btn-mc btn-mc-outline-gray">
            Back to Location Management
        </a>
    </p>

</main>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
