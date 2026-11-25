<?php
session_start();

require_once __DIR__ . '/../config/database.php';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

$old = $_SESSION['old'] ?? [];
unset($_SESSION['old']);

$specializations = [];
$cities = [];
$districts = [];
$streets = [];
$loadError = false;

try {
    $stmt = $pdo->query(
        "SELECT specialization_id, name AS specialization_name
         FROM specializations
         ORDER BY name"
    );
    $specializations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query(
        "SELECT city_id, city_name
         FROM cities
         ORDER BY city_name"
    );
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query(
        "SELECT district_id, city_id, district_name
         FROM districts
         ORDER BY district_name"
    );
    $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query(
        "SELECT street_id, district_id, street_name
         FROM streets
         ORDER BY street_name"
    );
    $streets = $stmt->fetchAll(PDO::FETCH_ASSOC);
/*
} catch (PDOException $e) {
    error_log('Doctor registration data loading error: ' . $e->getMessage());
    $loadError = true;
}
*/
    } catch (PDOException $e) {
    // Tạm thời hiển thị chi tiết lỗi để debug
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => 'Lỗi SQL chi tiết: ' . $e->getMessage()
    ];
    header('Location: ../register.php'); // Đổi lại đúng tên file form của bạn
    exit;
}
$districtJson = json_encode($districts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '[]';
$streetJson = json_encode($streets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '[]';

$hasSpecializations = !empty($specializations);
$canSubmit = !$loadError && $hasSpecializations;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Registration | MediConnect</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
        }

        a {
            text-decoration: none;
        }

        .topbar {
            height: 76px;
            background: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 7%;
            border-bottom: 1px solid #e2e8f0;
        }

        .brand {
            font-size: 25px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.7px;
        }

        .brand span {
            color: #0f766e;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .btn-back-home {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            background: #f0fdfa;
            border: 1px solid #ccfbf1;
            color: #0f766e;
            font-weight: 700;
            font-size: 13.5px;
            transition: 0.15s;
        }

        .btn-back-home:hover {
            background: #ccfbf1;
        }

        .support-link {
            color: #475569;
            font-weight: 600;
            font-size: 14px;
        }

        .hero {
            background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
            color: white;
            padding: 55px 7% 85px;
        }

        .hero-content {
            max-width: 1100px;
            margin: auto;
        }

        .hero-label {
            display: inline-block;
            color: #99f6e4;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 2px;
            margin-bottom: 12px;
        }

        .hero h1 {
            font-size: clamp(30px, 4vw, 43px);
            line-height: 1.2;
            margin-bottom: 14px;
        }

        .hero p {
            max-width: 650px;
            color: #ccfbf1;
            font-size: 16px;
        }

        .page-container {
            max-width: 1000px;
            margin: -42px auto 60px;
            padding: 0 20px;
            position: relative;
        }

        .registration-card {
            background: white;
            border-radius: 15px;
            padding: 38px 42px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.08);
        }

        .form-heading {
            margin-bottom: 28px;
        }

        .form-heading h2 {
            color: #0f172a;
            font-size: 27px;
            margin-bottom: 5px;
        }

        .form-heading p {
            color: #64748b;
            font-size: 14px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f766e;
            padding-bottom: 10px;
            margin: 30px 0 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .avatar-upload-box {
            display: flex;
            align-items: center;
            gap: 16px;
            background: #f8fafc;
            border: 1px dashed #94a3b8;
            border-radius: 10px;
            padding: 14px 18px;
        }

        .avatar-upload-box img {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #0f766e;
            background: #ffffff;
            flex-shrink: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 19px 22px;
        }

        .form-group {
            min-width: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        .required {
            color: #dc2626;
        }

        .form-control {
            width: 100%;
            height: 46px;
            padding: 10px 13px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            background: #fff;
            color: #1e293b;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }

        textarea.form-control {
            height: 105px;
            resize: vertical;
        }

        .form-control:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12);
        }

        .form-control:disabled {
            background: #f1f5f9;
            cursor: not-allowed;
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper .form-control {
            padding-right: 75px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #0f766e;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .field-hint {
            color: #64748b;
            font-size: 11.5px;
            margin-top: 5px;
        }

        .terms-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 25px 0;
            font-size: 13px;
            color: #475569;
        }

        .terms-row input {
            margin-top: 5px;
            accent-color: #0f766e;
        }

        .submit-button {
            width: 100%;
            min-height: 49px;
            border: none;
            border-radius: 8px;
            background: #0f766e;
            color: white;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }

        .submit-button:hover {
            background: #115e59;
        }

        .submit-button:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }

        .login-link {
            text-align: center;
            color: #64748b;
            font-size: 13.5px;
            margin-top: 22px;
        }

        .login-link a {
            color: #0f766e;
            font-weight: 700;
        }

        .alert {
            padding: 13px 15px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .alert-success {
            background: #ccfbf1;
            border: 1px solid #99f6e4;
            color: #0f766e;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .footer {
            text-align: center;
            padding: 22px;
            color: #64748b;
            font-size: 12.5px;
        }

        @media (max-width: 650px) {
            .topbar {
                padding: 0 20px;
            }

            .hero {
                padding: 42px 22px 75px;
            }

            .registration-card {
                padding: 27px 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: auto;
            }
        }
    </style>
</head>
<body>

<header class="topbar">
    <a href="../index.php" class="brand">
        Medi<span>Connect</span>
    </a>

    <div class="topbar-actions">
        <a href="../index.php" class="btn-back-home">&larr; Back to Home</a>
        <a href="mailto:support@mediconnect.com" class="support-link">Support</a>
    </div>
</header>

<section class="hero">
    <div class="hero-content">
        <div class="hero-label">SUPPORTING DOCTORS</div>
        <h1>Connect expertise. Extend care.</h1>
        <p>Create a doctor account to connect with patients and manage your medical practice.</p>
    </div>
</section>

<main class="page-container">
    <div class="registration-card">
        <div class="form-heading">
            <h2>Doctor registration</h2>
            <p>Complete your personal and professional details.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert <?= !empty($message['type']) && $message['type'] === 'success' ? 'alert-success' : 'alert-error' ?>">
                <?= e($message['text'] ?? '') ?>
            </div>
        <?php endif; ?>

        <?php if ($loadError): ?>
            <div class="alert alert-error">
                Unable to load registration information. Please try again later.
            </div>
        <?php elseif (!$hasSpecializations): ?>
            <div class="alert alert-error">
                No medical specialties are currently available. Please contact the administrator.
            </div>
        <?php endif; ?>

        <form action="actions/register_process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <h3 class="section-title">Personal information</h3>

            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label" for="doctor_avatar">Profile Photo (Avatar)</label>
                    <div class="avatar-upload-box">
                        <img id="doctorAvatarPreview" src="../assets/uploads/avatars/default_avatar.png" onerror="this.src='../images/icons/mediconnect.png'" alt="Doctor Avatar Preview">
                        <div>
                            <input type="file" id="doctor_avatar" name="avatar" accept="image/jpeg,image/png,image/webp" onchange="previewDoctorAvatar(this)">
                            <div class="field-hint">Upload a professional portrait photo (JPG, PNG, WEBP, max 2MB).</div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="full_name">
                        Full name <span class="required">*</span>
                    </label>
                    <input class="form-control" type="text" id="full_name" name="full_name" maxlength="100" placeholder="Enter your full name" value="<?= e($old['full_name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone_number">Phone number</label>
                    <input class="form-control" type="tel" id="phone_number" name="phone_number" maxlength="20" placeholder="Enter your phone number" value="<?= e($old['phone_number'] ?? '') ?>">
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="email">
                        Email address <span class="required">*</span>
                    </label>
                    <input class="form-control" type="email" id="email" name="email" maxlength="150" placeholder="example@email.com" value="<?= e($old['email'] ?? '') ?>" required>
                </div>
            </div>

            <h3 class="section-title">Professional information</h3>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="specialization_id">
                        Medical specialty <span class="required">*</span>
                    </label>
                    <select class="form-control" id="specialization_id" name="specialization_id" required>
                        <option value="">Select specialty</option>
                        <?php foreach ($specializations as $item): ?>
                            <option value="<?= e($item['specialization_id']) ?>" <?= (string)($old['specialization_id'] ?? '') === (string)$item['specialization_id'] ? 'selected' : '' ?>>
                                <?= e($item['specialization_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="degree">Medical qualifications</label>
                    <input class="form-control" type="text" id="degree" name="degree" maxlength="150" placeholder="Example: Specialist Level I" value="<?= e($old['degree'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="experience_years">Years of experience</label>
                    <input class="form-control" type="number" id="experience_years" name="experience_years" min="0" max="70" placeholder="Enter years of experience" value="<?= e($old['experience_years'] ?? '0') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="work_place">Workplace and position</label>
                    <input class="form-control" type="text" id="work_place" name="work_place" maxlength="255" placeholder="Hospital, clinic and position" value="<?= e($old['work_place'] ?? '') ?>">
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="bio">Professional introduction</label>
                    <textarea class="form-control" id="bio" name="bio" placeholder="Introduce your experience and professional background..."><?= e($old['bio'] ?? '') ?></textarea>
                </div>
            </div>

            <h3 class="section-title">Residential address</h3>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="city_id">Province / City</label>
                    <select class="form-control" id="city_id" name="city_id">
                        <option value="">Select province / city</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= e($city['city_id']) ?>" <?= (string)($old['city_id'] ?? '') === (string)$city['city_id'] ? 'selected' : '' ?>>
                                <?= e($city['city_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="district_id">District</label>
                    <select class="form-control" id="district_id" name="district_id" disabled>
                        <option value="">Select district</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="street_id">Street / Hamlet</label>
                    <select class="form-control" id="street_id" name="street_id" disabled>
                        <option value="">Select street / hamlet</option>
                    </select>
                </div>
            </div>

            <h3 class="section-title">Account security</h3>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="password">
                        Password <span class="required">*</span>
                    </label>
                    <div class="password-wrapper">
                        <input class="form-control" type="password" id="password" name="password" minlength="8" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,}" placeholder="Create a password" required>
                        <button class="toggle-password" type="button" data-target="password">Show</button>
                    </div>
                    <div class="field-hint">At least 8 characters, including letters and numbers.</div>
                </div>

               <div class="form-group">
                    <label class="form-label" for="confirm_password">
                        Confirm password <span class="required">*</span>
                    </label>
                    <div class="password-wrapper">
                        <input class="form-control" type="password" id="confirm_password" name="confirm_password" minlength="8" placeholder="Confirm your password" required>
                        <button class="toggle-password" type="button" data-target="confirm_password">Show</button>
                    </div>
                </div>
            </div>

            <label class="terms-row">
                <input type="checkbox" name="terms_agree" value="1" required <?= ($old['terms_agree'] ?? '') === '1' ? 'checked' : '' ?>>
                <span>I agree to MediConnect’s Terms of Use and Privacy Policy.</span>
            </label>

            <button class="submit-button" type="submit" <?= !$canSubmit ? 'disabled' : '' ?>>
                Create doctor account
            </button>
        </form>

        <div class="login-link">
            Already have an account? <a href="../auth/login.php">Sign in</a>
        </div>
    </div>
</main>

<footer class="footer">
    &copy; <?= date('Y') ?> MediConnect. All rights reserved.
</footer>

<script>
    function previewDoctorAvatar(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('doctorAvatarPreview').src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    const districts = <?= $districtJson ?>;
    const streets = <?= $streetJson ?>;

    const citySelect = document.getElementById('city_id');
    const districtSelect = document.getElementById('district_id');
    const streetSelect = document.getElementById('street_id');

    const oldDistrictId = <?= json_encode((string)($old['district_id'] ?? '')) ?>;
    const oldStreetId = <?= json_encode((string)($old['street_id'] ?? '')) ?>;

    function resetSelect(select, placeholder) {
        select.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        select.appendChild(option);
    }

    function loadDistricts(cityId, selectedDistrictId = '') {
        resetSelect(districtSelect, 'Select district');
        resetSelect(streetSelect, 'Select street / hamlet');

        streetSelect.disabled = true;
        districtSelect.disabled = !cityId;

        if (!cityId) return;

        districts.forEach(function (district) {
            if (String(district.city_id) === String(cityId)) {
                const option = document.createElement('option');
                option.value = district.district_id;
                option.textContent = district.district_name;
                if (String(district.district_id) === String(selectedDistrictId)) {
                    option.selected = true;
                }
                districtSelect.appendChild(option);
            }
        });

        if (selectedDistrictId) {
            loadStreets(selectedDistrictId, oldStreetId);
        }
    }

    function loadStreets(districtId, selectedStreetId = '') {
        resetSelect(streetSelect, 'Select street / hamlet');
        streetSelect.disabled = !districtId;

        if (!districtId) return;

        streets.forEach(function (street) {
            if (String(street.district_id) === String(districtId)) {
                const option = document.createElement('option');
                option.value = street.street_id;
                option.textContent = street.street_name;
                if (String(street.street_id) === String(selectedStreetId)) {
                    option.selected = true;
                }
                streetSelect.appendChild(option);
            }
        });
    }

    citySelect.addEventListener('change', function () {
        loadDistricts(this.value);
    });

    districtSelect.addEventListener('change', function () {
        loadStreets(this.value);
    });

    if (citySelect.value) {
        loadDistricts(citySelect.value, oldDistrictId);
    }

    document.querySelectorAll('.toggle-password').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(this.dataset.target);
            if (input.type === 'password') {
                input.type = 'text';
                this.textContent = 'Hide';
            } else {
                input.type = 'password';
                this.textContent = 'Show';
            }
        });
    });

    const registrationForm = document.querySelector('form');
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');

    registrationForm.addEventListener('submit', function (event) {
        if (passwordInput.value !== confirmInput.value) {
            event.preventDefault();
            confirmInput.setCustomValidity('Passwords do not match.');
            confirmInput.reportValidity();
        } else {
            confirmInput.setCustomValidity('');
        }
    });

    confirmInput.addEventListener('input', function () {
        this.setCustomValidity('');
    });
</script>

</body>
</html>