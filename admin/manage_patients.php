<?php
session_start();
require_once '../config/MyConnection.php';
require_once '../models/PatientManager.php';

if (!isset($_SESSION['role_name']) || $_SESSION['role_name'] !== 'ADMIN') {
    header("Location: ../auth/login.php");
    exit();
}

$conn = MyConnection::connectDatabase();
$patientManager = new PatientManager($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $result = null;

    if ($action === 'add') {
        $result = $patientManager->addPatient($_POST);
    } elseif ($action === 'edit') {
        $result = $patientManager->updatePatient($_POST);
    } elseif ($action === 'delete') {
        $result = $patientManager->deletePatient($_POST['patient_id']);
    }

    if ($result) {
        $_SESSION[$result['status'] ? 'success' : 'error'] = $result['message'];
    }

    $redirectParams = array_filter([
        'keyword' => !empty($_POST['current_keyword']) ? trim($_POST['current_keyword']) : null,
        'page'    => !empty($_POST['current_page']) && (int)$_POST['current_page'] > 1 ? (int)$_POST['current_page'] : null,
    ]);
    $redirectUrl = "manage_patients.php" . (!empty($redirectParams) ? "?" . http_build_query($redirectParams) : "");

    header("Location: " . $redirectUrl);
    exit();
}

$addressData = $patientManager->getAddressOptions();
$cities      = $addressData['cities'];
$districts   = $addressData['districts'];
$streets     = $addressData['streets'];

$keyword = trim($_GET['keyword'] ?? '');

$limit         = 7;
$currentPage   = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$totalPatients = $patientManager->countPatients($keyword);
$totalPages    = max(1, (int)ceil($totalPatients / $limit));

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset   = ($currentPage - 1) * $limit;
$patients = $patientManager->getPatients($keyword, $limit, $offset);

$startItem = $totalPatients > 0 ? ($offset + 1) : 0;
$endItem   = min($offset + $limit, $totalPatients);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediConnect - Patient Management</title>
    <link rel="stylesheet" href="../assets/css/admin_style.css?v=3">
</head>
<body>

<div class="admin-wrapper">
    <aside class="sidebar">
        <a href="dashboard.php" class="sidebar-brand">MediConnect</a>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">Overview</a></li>
            <li><a href="manage_patients.php" class="active">Patients</a></li>
            <li><a href="manage_doctors.php"> Doctors</a></li>
            <li><a href="review_applications.php">Review Applications</a></li>
            <li><a href="../articles/manage.php">News Management</a></li>
        </ul>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <div class="topbar-user">
                <span>Welcome, <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></strong></span>
                <a href="../auth/logout.php" class="btn-logout" onclick="return confirm('Are you sure you want to log out?');">
                    Logout
                </a>
            </div>
        </header>

        <main class="content-container">
            <h2 class="page-title">Patient Management</h2>
            <div class="stat-summary-row">
                <div class="stat-summary-card">
                    <div class="stat-summary-label">Total accounts</div>
                    <div class="stat-summary-value-row">
                        <span class="stat-summary-number"><?= number_format($totalPatients) ?></span>
                        <span class="stat-summary-sub">All patients</span>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert-box alert-success">
                    <span><?= htmlspecialchars($_SESSION['success']) ?></span>
                    <button type="button" class="alert-close" onclick="this.parentElement.remove()">✕</button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert-box alert-error">
                    <span><?= htmlspecialchars($_SESSION['error']) ?></span>
                    <button type="button" class="alert-close" onclick="this.parentElement.remove()">✕</button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="panel-card">
                <div class="panel-header">
                    <span class="panel-title" id="form_header">Add New Patient</span>
                </div>

                <form method="POST" action="manage_patients.php" id="patientForm">
                    <input type="hidden" name="action" id="form_action" value="add">
                    <input type="hidden" name="patient_id" id="patient_id" value="">
                    <input type="hidden" name="user_id" id="user_id" value="">
                    <input type="hidden" name="current_keyword" value="<?= htmlspecialchars($keyword) ?>">
                    <input type="hidden" name="current_page" value="<?= $currentPage ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Full Name <span class="req">*</span></label>
                            <input type="text" name="full_name" id="full_name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email <span class="req">*</span></label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" id="password_label">Password <span class="req">*</span></label>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone_number" id="phone_number" class="form-control">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="date_of_birth" id="date_of_birth" class="form-control">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <select name="gender" id="gender" class="form-select">
                                <option value="">-- Select Gender --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Account Status</label>
                            <select name="is_active" id="is_active" class="form-select">
                                <option value="1">Active</option>
                                <option value="0">Locked</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">City / Province</label>
                            <select name="city_id" id="city_id" class="form-select">
                                <option value="">-- Select City --</option>
                                <?php foreach ($cities as $c): ?>
                                    <option value="<?= $c['city_id'] ?>"><?= htmlspecialchars($c['city_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">District</label>
                            <select name="district_id" id="district_id" class="form-select">
                                <option value="">-- Select District --</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= $d['district_id'] ?>"><?= htmlspecialchars($d['district_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Street</label>
                            <select name="street_id" id="street_id" class="form-select">
                                <option value="">-- Select Street --</option>
                                <?php foreach ($streets as $s): ?>
                                    <option value="<?= $s['street_id'] ?>"><?= htmlspecialchars($s['street_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group col-span-2">
                            <label class="form-label">House No. / Detailed Address</label>
                            <input type="text" name="address" id="address" class="form-control" placeholder="House number, alley, ward...">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-mc btn-mc-teal" id="btn_submit">
                            Add Patient
                        </button>
                        <button type="button" class="btn-mc btn-mc-outline-gray d-none" id="btn_cancel" onclick="resetForm()">
                            Cancel / Reset Form
                        </button>
                    </div>
                </form>
            </div>

            <div class="panel-card">
                <div class="panel-header">
                    <span class="panel-title">Patient List (<?= $totalPatients ?>)</span>

                    <form method="GET" action="manage_patients.php" class="search-bar">
                        <input type="text" name="keyword" class="form-control" placeholder="Search name, email, phone..." value="<?= htmlspecialchars($keyword) ?>">
                        <button type="submit" class="btn-mc btn-mc-teal">Search</button>
                        <?php if (!empty($keyword)): ?>
                            <a href="manage_patients.php" class="btn-mc btn-mc-outline-gray">Clear Filter</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Email / Phone</th>
                                <th>DOB / Gender</th>
                                <th>Address</th>
                                <th>Created on</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($patients) > 0): ?>
                                <?php foreach ($patients as $row): ?>
                                    <?php
                                        $addrParts = array_filter([$row['address'], $row['street_name'], $row['district_name'], $row['city_name']]);
                                        $fullAddress = !empty($addrParts) ? implode(', ', $addrParts) : '<span class="text-muted-italic">Not updated</span>';
                                        $createdDate = !empty($row['created_at']) ? date('d/m/Y', strtotime($row['created_at'])) : '---';
                                    ?>
                                    <tr>
                                        <td><strong>#<?= $row['patient_id'] ?></strong></td>
                                        <td><strong><?= htmlspecialchars($row['full_name']) ?></strong></td>
                                        <td>
                                            <div><?= htmlspecialchars($row['email']) ?></div>
                                            <span class="text-sub"><?= htmlspecialchars($row['phone_number'] ?? '') ?></span>
                                        </td>
                                        <td>
                                            <div><?= !empty($row['date_of_birth']) ? date('Y-m-d', strtotime($row['date_of_birth'])) : '---' ?></div>
                                            <span class="text-sub"><?= htmlspecialchars($row['gender'] ?? '') ?></span>
                                        </td>
                                        <td style="max-width: 220px;"><?= $fullAddress ?></td>
                                        <td><?= $createdDate ?></td>
                                        <td>
                                            <?= ($row['is_active'] == 1)
                                                ? '<span class="badge-pill badge-active">Active</span>'
                                                : '<span class="badge-pill badge-locked">Locked</span>' ?>
                                        </td>
                                        <td class="action-cell">
                                            <button type="button" class="btn-mc btn-mc-teal"
                                                    onclick='editPatient(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)'>
                                                Edit
                                            </button>

                                            <button type="button" class="btn-mc btn-mc-danger"
                                                    onclick="deletePatient(<?= $row['patient_id'] ?>, '<?= htmlspecialchars(addslashes($row['full_name'])) ?>')">
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-cell">
                                        No patient records found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php
                $buildPageUrl = function(int $page) use ($keyword): string {
                    $params = array_filter([
                        'keyword' => $keyword !== '' ? $keyword : null,
                        'page'    => $page > 1 ? $page : null,
                    ]);
                    return 'manage_patients.php' . (!empty($params) ? '?' . http_build_query($params) : '');
                };

                $pageNumbers = [];
                if ($totalPages <= 5) {
                    $pageNumbers = range(1, $totalPages);
                } else {
                    $pageNumbers[] = 1;
                    if ($currentPage > 3) {
                        $pageNumbers[] = '...';
                    }
                    for ($i = max(2, $currentPage - 1); $i <= min($totalPages - 1, $currentPage + 1); $i++) {
                        $pageNumbers[] = $i;
                    }
                    if ($currentPage < $totalPages - 2) {
                        $pageNumbers[] = '...';
                    }
                    $pageNumbers[] = $totalPages;
                }
                ?>

                <div class="table-footer-pagination">
                    <div class="pagination-info">
                        Showing <?= number_format($startItem) ?>–<?= number_format($endItem) ?> of <?= number_format($totalPatients) ?> accounts
                    </div>

                    <div class="pagination-controls">
                        <span class="per-page-label"><?= $limit ?> / page</span>

                        <?php if ($currentPage > 1): ?>
                            <a href="<?= $buildPageUrl($currentPage - 1) ?>" class="pg-btn">Previous</a>
                        <?php else: ?>
                            <span class="pg-btn disabled">Previous</span>
                        <?php endif; ?>

                        <?php foreach ($pageNumbers as $p): ?>
                            <?php if ($p === '...'): ?>
                                <span class="pg-btn dots">...</span>
                            <?php else: ?>
                                <a href="<?= $buildPageUrl($p) ?>" class="pg-btn <?= ($p === $currentPage) ? 'active' : '' ?>">
                                    <?= $p ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if ($currentPage < $totalPages): ?>
                            <a href="<?= $buildPageUrl($currentPage + 1) ?>" class="pg-btn">Next</a>
                        <?php else: ?>
                            <span class="pg-btn disabled">Next</span>
                        <?php endif; ?>
                    </div>
                </div>
        </main>

        <footer class="admin-footer">
            <div>© 2026 MediConnect. All rights reserved.</div>
            <div class="footer-links">
                <a href="#">Terms</a>
                <a href="#">Privacy</a>
                <a href="#">Support</a>
            </div>
        </footer>
    </div>
</div>

<script>
    const form = document.getElementById('patientForm');
    const formHeader = document.getElementById('form_header');
    const formAction = document.getElementById('form_action');
    const btnSubmit = document.getElementById('btn_submit');
    const btnCancel = document.getElementById('btn_cancel');
    const btnDeleteInForm = document.getElementById('btn_delete_in_form');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const passwordLabel = document.getElementById('password_label');

    function editPatient(data) {
        formAction.value = 'edit';
        document.getElementById('patient_id').value = data.patient_id;
        document.getElementById('user_id').value = data.user_id;
        document.getElementById('full_name').value = data.full_name || '';
        document.getElementById('phone_number').value = data.phone_number || '';
        document.getElementById('date_of_birth').value = data.date_of_birth || '';
        document.getElementById('gender').value = data.gender || '';
        document.getElementById('is_active').value = data.is_active;
        document.getElementById('city_id').value = data.city_id || '';
        document.getElementById('district_id').value = data.district_id || '';
        document.getElementById('street_id').value = data.street_id || '';
        document.getElementById('address').value = data.address || '';
        emailInput.value = data.email || '';
        emailInput.readOnly = true;

        passwordInput.value = '';
        passwordInput.required = false;
        passwordInput.placeholder = 'Leave blank to keep unchanged';
        passwordLabel.innerHTML = 'New Password';

        formHeader.style.color = '#d97706';
        formHeader.innerHTML = 'Editing Patient #' + data.patient_id;
        btnSubmit.innerHTML = 'Save Changes';
        btnCancel.classList.remove('d-none');
        btnDeleteInForm.classList.remove('d-none');

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function deletePatient(patientId, fullName) {
        if (confirm('Are you sure you want to delete/lock the record for patient "' + fullName + '"?')) {
            formAction.value = 'delete';
            document.getElementById('patient_id').value = patientId;
            form.submit();
        }
    }

    function submitDeleteFromForm() {
        const patientId = document.getElementById('patient_id').value;
        const fullName = document.getElementById('full_name').value;
        deletePatient(patientId, fullName);
    }

    function resetForm() {
        form.reset();
        formAction.value = 'add';
        document.getElementById('patient_id').value = '';
        document.getElementById('user_id').value = '';

        emailInput.readOnly = false;

        passwordInput.required = true;
        passwordInput.placeholder = '';
        passwordLabel.innerHTML = 'Password <span class="req">*</span>';

        formHeader.style.color = '#1e293b';
        formHeader.innerHTML = 'Add New Patient';
        formHeader.innerHTML = 'Add New Patient';
        btnSubmit.innerHTML = 'Add Patient';
        btnCancel.classList.add('d-none');
        btnDeleteInForm.classList.add('d-none');
    }
</script>
</body>
</html>