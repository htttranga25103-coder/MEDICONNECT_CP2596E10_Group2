
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
// 3. ESCAPE OUTPUT
// =============================================
function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
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
    // ADD CITY
    // -----------------------------------------
    elseif ($action === 'add') {

        $city_name = $_POST['city_name'] ?? '';

        if (!is_string($city_name)) {

            $error = 'Invalid city name.';

        } else {

            $city_name = trim($city_name);

            if (
                $city_name === '' ||
                mb_strlen($city_name, 'UTF-8') > 100
            ) {

                $error = 'City name is required and must not exceed 100 characters.';

            } else {

                try {

                    $stmt = $pdo->prepare("
                        INSERT INTO cities (city_name)
                        VALUES (:city_name)
                    ");

                    $stmt->execute([
                        ':city_name' => $city_name
                    ]);

                    header("Location: cities.php?success=added");
                    exit();

                } catch (PDOException $exception) {

                    error_log($exception->getMessage());

                    $error = 'Unable to add city. Please check the information and try again.';
                }
            }
        }
    }

    // -----------------------------------------
    // UPDATE CITY
    // -----------------------------------------
    elseif ($action === 'update') {

        $city_id = filter_var(
            $_POST['city_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $city_name = $_POST['city_name'] ?? '';

        if (!is_string($city_name)) {

            $error = 'Invalid city name.';

        } else {

            $city_name = trim($city_name);

            if (
                $city_id === false ||
                $city_id === null ||
                $city_id <= 0 ||
                $city_name === '' ||
                mb_strlen($city_name, 'UTF-8') > 100
            ) {

                $error = 'Please enter a valid city ID and city name (maximum 100 characters).';

            } else {

                try {

                    $checkStmt = $pdo->prepare("
                        SELECT city_id
                        FROM cities
                        WHERE city_id = :city_id
                    ");

                    $checkStmt->execute([
                        ':city_id' => $city_id
                    ]);

                    if ($checkStmt->fetchColumn() === false) {

                        $error = 'City not found.';

                    } else {

                        $stmt = $pdo->prepare("
                            UPDATE cities
                            SET city_name = :city_name
                            WHERE city_id = :city_id
                        ");

                        $stmt->execute([
                            ':city_name' => $city_name,
                            ':city_id' => $city_id
                        ]);

                        header("Location: cities.php?success=updated");
                        exit();
                    }

                } catch (PDOException $exception) {

                    error_log($exception->getMessage());

                    $error = 'Unable to update city. Please try again.';
                }
            }
        }
    }

    // -----------------------------------------
    // DELETE CITY
    // -----------------------------------------
    elseif ($action === 'delete') {

        $city_id = filter_var(
            $_POST['city_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $city_id === false ||
            $city_id === null ||
            $city_id <= 0
        ) {

            $error = 'Invalid city ID.';

        } else {

            try {

                // Check whether city exists
                $checkStmt = $pdo->prepare("
                    SELECT city_id
                    FROM cities
                    WHERE city_id = :city_id
                ");

                $checkStmt->execute([
                    ':city_id' => $city_id
                ]);

                if ($checkStmt->fetchColumn() === false) {

                    $error = 'City not found.';

                } else {

                    // Check related records before deleting
                    $relatedStmt = $pdo->prepare("
                        SELECT
                            (SELECT COUNT(*)
                             FROM districts
                             WHERE city_id = :district_city_id)
                            +
                            (SELECT COUNT(*)
                             FROM doctors
                             WHERE city_id = :doctor_city_id)
                            +
                            (SELECT COUNT(*)
                             FROM patients
                             WHERE city_id = :patient_city_id)
                        AS total_related
                    ");

                    $relatedStmt->execute([
                        ':district_city_id' => $city_id,
                        ':doctor_city_id' => $city_id,
                        ':patient_city_id' => $city_id
                    ]);

                    $totalRelated = (int)$relatedStmt->fetchColumn();

                    if ($totalRelated > 0) {

                        $error = 'Cannot delete this city because it is being used by districts, doctors, or patients.';

                    } else {

                        $stmt = $pdo->prepare("
                            DELETE FROM cities
                            WHERE city_id = :city_id
                        ");

                        $stmt->execute([
                            ':city_id' => $city_id
                        ]);

                        header("Location: cities.php?success=deleted");
                        exit();
                    }
                }

            } catch (PDOException $exception) {

                error_log($exception->getMessage());

                $error = 'Unable to delete this city. It may be used by other records.';
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
        'added' => 'City added successfully!',
        'updated' => 'City updated successfully!',
        'deleted' => 'City deleted successfully!'
    ];

    $message = $messages[$_GET['success']] ?? '';
}

// =============================================
// 8. SEARCH CITIES
// =============================================
$keyword = $_GET['search'] ?? '';

if (!is_string($keyword)) {
    $keyword = '';
}

$keyword = trim($keyword);

try {

    $sql = "
        SELECT city_id, city_name, created_at
        FROM cities
    ";

    if ($keyword !== '') {
        $sql .= " WHERE city_name LIKE :keyword";
    }

    $sql .= " ORDER BY city_id DESC";

    $stmt = $pdo->prepare($sql);

    if ($keyword !== '') {

        $stmt->execute([
            ':keyword' => '%' . $keyword . '%'
        ]);

    } else {

        $stmt->execute();
    }

    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {

    error_log($exception->getMessage());

    $cities = [];

    $error = 'Unable to load cities. Please try again later.';
}

// =============================================
// 9. GET CITY FOR EDITING
// =============================================
$editCity = null;

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
                SELECT city_id, city_name
                FROM cities
                WHERE city_id = :city_id
            ");

            $editStmt->execute([
                ':city_id' => $edit_id
            ]);

            $editCity = $editStmt->fetch(PDO::FETCH_ASSOC);

            if (!$editCity) {
                $error = 'City not found.';
            }

        } catch (PDOException $exception) {

            error_log($exception->getMessage());

            $error = 'Unable to load city information.';
        }

    } else {

        $error = 'Invalid city ID.';
    }
}

?>


<?php require_once __DIR__ . '/layout_header.php'; ?>

<main>

    <h2 class="page-title">City Management</h2>

    <p>Manage cities in the MediConnect system.</p>

    <?php if ($message !== ''): ?>
        <div class="alert-box alert-success">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert-box alert-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- ADD / EDIT CITY -->
    <div class="panel-card">

        <div class="panel-header">
            <h3 class="panel-title">
                <?= $editCity ? 'Edit City' : 'Add New City' ?>
            </h3>
        </div>

        <form method="POST" action="cities.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="<?= $editCity ? 'update' : 'add' ?>"
            >

            <?php if ($editCity): ?>
                <input
                    type="hidden"
                    name="city_id"
                    value="<?= (int)$editCity['city_id'] ?>"
                >
            <?php endif; ?>

            <div class="form-grid">

                <div class="form-group col-span-2">

                    <label for="city_name" class="form-label">
                        City Name
                    </label>

                    <input
                        type="text"
                        id="city_name"
                        name="city_name"
                        class="form-control"
                        maxlength="100"
                        required
                        value="<?= e($editCity['city_name'] ?? '') ?>"
                        placeholder="Enter city name"
                    >

                </div>

            </div>

            <div class="form-actions">

                <button type="submit" class="btn-mc btn-mc-teal">
                    <?= $editCity ? 'Update City' : 'Add City' ?>
                </button>

                <?php if ($editCity): ?>
                    <a href="cities.php" class="btn-mc btn-mc-outline-gray">
                        Cancel
                    </a>
                <?php endif; ?>

            </div>

        </form>

    </div>

    <!-- SEARCH CITIES -->
    <div class="panel-card">

        <div class="panel-header">
            <h3 class="panel-title">Search Cities</h3>
        </div>

        <form method="GET" action="cities.php" class="search-bar">

            <input
                type="text"
                name="search"
                class="form-control"
                value="<?= e($keyword) ?>"
                placeholder="Enter city name"
            >

            <button type="submit" class="btn-mc btn-mc-teal">
                Search
            </button>

            <a href="cities.php" class="btn-mc btn-mc-outline-gray">
                Show All
            </a>

        </form>

    </div>

    <!-- CITY LIST -->
    <div class="panel-card">

        <div class="panel-header">
            <h3 class="panel-title">City List</h3>
        </div>

        <?php if (count($cities) > 0): ?>

            <div class="table-responsive">

                <table class="custom-table">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>City Name</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($cities as $city): ?>

                            <tr>

                                <td>
                                    <?= (int)$city['city_id'] ?>
                                </td>

                                <td>
                                    <?= e($city['city_name']) ?>
                                </td>

                                <td>
                                    <?= e($city['created_at'] ?? '') ?>
                                </td>

                                <td>

                                    <a
                                        href="cities.php?edit=<?= (int)$city['city_id'] ?>"
                                        class="btn-mc btn-mc-outline-gray"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="cities.php"
                                        style="display:inline;"
                                        onsubmit="return confirm('Are you sure you want to delete this city?');"
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
                                            name="city_id"
                                            value="<?= (int)$city['city_id'] ?>"
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

            <p>No cities found.</p>

        <?php endif; ?>

    </div>

    <p>
        <a href="index.php" class="btn-mc btn-mc-outline-gray">
            Back to Location Management
        </a>
    </p>

</main>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
