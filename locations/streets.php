
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/admin_guard.php';

require_once __DIR__ . '/../config/database.php';

// Helper functions
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function districtExists($pdo, $district_id) {
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

function streetExists($pdo, $street_id) {
    $stmt = $pdo->prepare("
        SELECT street_id
        FROM streets
        WHERE street_id = :street_id
    ");

    $stmt->execute([
        ':street_id' => $street_id
    ]);

    return $stmt->fetchColumn() !== false;
}

// Create CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate CSRF token
    if (
        !isset($_POST['csrf_token']) ||
        !is_string($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit('Invalid security token. Please reload the page and try again.');
    }

    // Validate POST field types
    $fields = [
        'action',
        'street_name',
        'district_id',
        'street_id'
    ];

    foreach ($fields as $field) {
        if (
            isset($_POST[$field]) &&
            !is_string($_POST[$field])
        ) {
            http_response_code(400);
            exit('Invalid form data.');
        }
    }

    $action = $_POST['action'] ?? '';

    // ADD STREET
    if ($action === 'add') {

        $street_name = trim($_POST['street_name'] ?? '');

        $district_id = filter_var(
            $_POST['district_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $street_name === '' ||
            mb_strlen($street_name, 'UTF-8') > 150 ||
            $district_id === false ||
            $district_id === null ||
            $district_id <= 0
        ) {
            $error = 'Please enter a valid street name (maximum 150 characters) and select a district.';

        } elseif (!districtExists($pdo, $district_id)) {

            $error = 'Selected district does not exist.';

        } else {

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO streets (district_id, street_name)
                    VALUES (:district_id, :street_name)
                ");

                $stmt->execute([
                    ':district_id' => $district_id,
                    ':street_name' => $street_name
                ]);

                header("Location: streets.php?success=added");
                exit();

            } catch (PDOException $e) {

                error_log("Add street error: " . $e->getMessage());

                $error = 'Unable to add street. Please try again.';
            }
        }
    }

    // UPDATE STREET
    elseif ($action === 'update') {

        $street_id = filter_var(
            $_POST['street_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $street_name = trim($_POST['street_name'] ?? '');

        $district_id = filter_var(
            $_POST['district_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $street_id === false ||
            $street_id === null ||
            $street_id <= 0 ||
            $street_name === '' ||
            mb_strlen($street_name, 'UTF-8') > 150 ||
            $district_id === false ||
            $district_id === null ||
            $district_id <= 0
        ) {
            $error = 'Please enter valid street information.';

        } else {

            try {
                if (!streetExists($pdo, $street_id)) {

                    $error = 'Street not found.';

                } elseif (!districtExists($pdo, $district_id)) {

                    $error = 'Selected district does not exist.';

                } else {

                    $stmt = $pdo->prepare("
                        UPDATE streets
                        SET district_id = :district_id,
                            street_name = :street_name
                        WHERE street_id = :street_id
                    ");

                    $stmt->execute([
                        ':district_id' => $district_id,
                        ':street_name' => $street_name,
                        ':street_id' => $street_id
                    ]);

                    header("Location: streets.php?success=updated");
                    exit();
                }

            } catch (PDOException $e) {

                error_log("Update street error: " . $e->getMessage());

                $error = 'Unable to update street. Please try again.';
            }
        }
    }

    // DELETE STREET
    elseif ($action === 'delete') {

        $street_id = filter_var(
            $_POST['street_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $street_id === false ||
            $street_id === null ||
            $street_id <= 0
        ) {
            $error = 'Invalid street ID.';

        } else {

            try {
                if (!streetExists($pdo, $street_id)) {

                    $error = 'Street not found.';

                } else {

                    $stmt = $pdo->prepare("
                        DELETE FROM streets
                        WHERE street_id = :street_id
                    ");

                    $stmt->execute([
                        ':street_id' => $street_id
                    ]);

                    header("Location: streets.php?success=deleted");
                    exit();
                }

            } catch (PDOException $e) {

                error_log("Delete street error: " . $e->getMessage());

                $error = 'Cannot delete this street because it is being used by other records.';
            }
        }
    }

    else {
        http_response_code(400);
        exit('Invalid action.');
    }
}

// SUCCESS MESSAGES
if (
    isset($_GET['success']) &&
    is_string($_GET['success'])
) {
    $messages = [
        'added' => 'Street added successfully!',
        'updated' => 'Street updated successfully!',
        'deleted' => 'Street deleted successfully!'
    ];

    $message = $messages[$_GET['success']] ?? '';
}

// GET DISTRICTS WITH CITY NAMES
$districtStmt = $pdo->query("
    SELECT
        d.district_id,
        d.district_name,
        c.city_id,
        c.city_name
    FROM districts d
    JOIN cities c ON d.city_id = c.city_id
    ORDER BY c.city_name ASC, d.district_name ASC
");

$districtList = $districtStmt->fetchAll(PDO::FETCH_ASSOC);

// GET CITIES FOR FILTER
$cityStmt = $pdo->query("
    SELECT city_id, city_name
    FROM cities
    ORDER BY city_name ASC
");

$cityList = $cityStmt->fetchAll(PDO::FETCH_ASSOC);

// SEARCH AND FILTER
$keyword = '';

if (
    isset($_GET['search']) &&
    is_string($_GET['search'])
) {
    $keyword = trim($_GET['search']);
}

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

$selectedDistrict = filter_var(
    $_GET['district_id'] ?? 0,
    FILTER_VALIDATE_INT
);

if (
    $selectedDistrict === false ||
    $selectedDistrict === null ||
    $selectedDistrict < 0
) {
    $selectedDistrict = 0;
}

// BUILD STREET QUERY
$sql = "
    SELECT
        s.street_id,
        s.street_name,
        s.district_id,
        d.district_name,
        c.city_id,
        c.city_name,
        s.created_at
    FROM streets s
    JOIN districts d ON s.district_id = d.district_id
    JOIN cities c ON d.city_id = c.city_id
    WHERE 1 = 1
";

$params = [];

if ($keyword !== '') {
    $sql .= " AND s.street_name LIKE :keyword";
    $params[':keyword'] = '%' . $keyword . '%';
}

if ($selectedCity > 0) {
    $sql .= " AND c.city_id = :city_id";
    $params[':city_id'] = $selectedCity;
}

if ($selectedDistrict > 0) {
    $sql .= " AND s.district_id = :district_id";
    $params[':district_id'] = $selectedDistrict;
}

$sql .= " ORDER BY s.street_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$streets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// GET STREET BEING EDITED
$editStreet = null;

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
        $editStmt = $pdo->prepare("
            SELECT street_id, street_name, district_id
            FROM streets
            WHERE street_id = :street_id
        ");

        $editStmt->execute([
            ':street_id' => $edit_id
        ]);

        $editStreet = $editStmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>


<?php require_once __DIR__ . '/layout_header.php'; ?>

<main>

    <h2 class="page-title">Street Management</h2>

    <p>Manage street information within each district.</p>

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

    <!-- ADD / EDIT STREET -->
    <div class="panel-card">

        <div class="panel-header">
            <h3 class="panel-title">
                <?= $editStreet ? 'Edit Street' : 'Add New Street' ?>
            </h3>
        </div>

        <?php if (count($districtList) > 0): ?>

            <form method="POST" action="streets.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="<?= $editStreet ? 'update' : 'add' ?>"
                >

                <?php if ($editStreet): ?>
                    <input
                        type="hidden"
                        name="street_id"
                        value="<?= (int)$editStreet['street_id'] ?>"
                    >
                <?php endif; ?>

                <div class="form-grid">

                    <div class="form-group">

                        <label for="street_name" class="form-label">
                            Street Name
                        </label>

                        <input
                            type="text"
                            id="street_name"
                            name="street_name"
                            class="form-control"
                            maxlength="150"
                            required
                            value="<?= e($editStreet['street_name'] ?? '') ?>"
                            placeholder="Enter street name"
                        >

                    </div>

                    <div class="form-group">

                        <label for="district_id" class="form-label">
                            Select District
                        </label>

                        <select
                            name="district_id"
                            id="district_id"
                            class="form-select"
                            required
                        >

                            <option value="">
                                -- Select District --
                            </option>

                            <?php foreach ($districtList as $district): ?>

                                <option
                                    value="<?= (int)$district['district_id'] ?>"
                                    <?= (int)($editStreet['district_id'] ?? 0) === (int)$district['district_id'] ? 'selected' : '' ?>
                                >
                                    <?= e($district['district_name']) ?>
                                    - <?= e($district['city_name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>

                <div class="form-actions">

                    <button type="submit" class="btn-mc btn-mc-teal">
                        <?= $editStreet ? 'Update Street' : 'Add Street' ?>
                    </button>

                    <?php if ($editStreet): ?>
                        <a href="streets.php" class="btn-mc btn-mc-outline-gray">
                            Cancel
                        </a>
                    <?php endif; ?>

                </div>

            </form>

        <?php else: ?>

            <p>Please add a city and district before creating a street.</p>

            <div class="form-actions">
                <a href="districts.php" class="btn-mc btn-mc-teal">
                    Manage Districts
                </a>
            </div>

        <?php endif; ?>

    </div>

    <!-- SEARCH AND FILTER -->
    <div class="panel-card">

        <div class="panel-header">
            <h3 class="panel-title">Search and Filter Streets</h3>
        </div>

        <form method="GET" action="streets.php" class="search-bar">

            <input
                type="text"
                name="search"
                class="form-control"
                value="<?= e($keyword) ?>"
                placeholder="Search street name"
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

            <select name="district_id" class="form-select">

                <option value="0">
                    All Districts
                </option>

                <?php foreach ($districtList as $district): ?>

                    <option
                        value="<?= (int)$district['district_id'] ?>"
                        <?= $selectedDistrict === (int)$district['district_id'] ? 'selected' : '' ?>
                    >
                        <?= e($district['district_name']) ?>
                        - <?= e($district['city_name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <button type="submit" class="btn-mc btn-mc-teal">
                Search
            </button>

            <a href="streets.php" class="btn-mc btn-mc-outline-gray">
                Show All
            </a>

        </form>

    </div>

    <!-- STREET LIST -->
    <div class="panel-card">

        <div class="panel-header">
            <h3 class="panel-title">Street List</h3>
        </div>

        <?php if (count($streets) > 0): ?>

            <div class="table-responsive">

                <table class="custom-table">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Street Name</th>
                            <th>District</th>
                            <th>City</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($streets as $street): ?>

                            <tr>

                                <td>
                                    <?= (int)$street['street_id'] ?>
                                </td>

                                <td>
                                    <?= e($street['street_name']) ?>
                                </td>

                                <td>
                                    <?= e($street['district_name']) ?>
                                </td>

                                <td>
                                    <?= e($street['city_name']) ?>
                                </td>

                                <td>
                                    <?= e($street['created_at'] ?? '') ?>
                                </td>

                                <td>

                                    <a
                                        href="streets.php?edit=<?= (int)$street['street_id'] ?>"
                                        class="btn-mc btn-mc-outline-gray"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="streets.php"
                                        style="display:inline;"
                                        onsubmit="return confirm('Are you sure you want to delete this street?');"
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
                                            name="street_id"
                                            value="<?= (int)$street['street_id'] ?>"
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

            <p>No streets found.</p>

        <?php endif; ?>

    </div>

    <p>
        <a href="index.php" class="btn-mc btn-mc-outline-gray">
            Back to Location Management
        </a>
    </p>

</main>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
