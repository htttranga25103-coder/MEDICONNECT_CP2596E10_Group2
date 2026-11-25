<?php
session_start();
require_once __DIR__ . '/config/MyConnection.php';
require_once __DIR__ . '/models/ChatManager.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_name'] ?? '', ['PATIENT', 'DOCTOR'])) {
    header("Location: auth/login.php");
    exit();
}

$conn = MyConnection::connectDatabase();
$chatManager = new ChatManager($conn);

$current_user_id = (int)$_SESSION['user_id'];
$role_name       = $_SESSION['role_name'];
$contacts        = $chatManager->getChatContacts($current_user_id, $role_name);
$partner_id      = isset($_GET['partner_id']) ? (int)$_GET['partner_id'] : 0;

if ($partner_id === 0 && count($contacts) > 0) {
    $partner_id = (int)$contacts[0]['user_id'];
}
$partner  = $partner_id > 0 ? $chatManager->getPartnerInfo($partner_id) : null;
$homeLink = ($role_name === 'PATIENT') ? 'patient/book_appointment.php' : 'doctor/dashboard.php';
function resolveUserAvatar(?string $avatarUrl): string {
    $defaultAvatar = 'assets/uploads/avatars/.png';

    if (empty($avatarUrl)) {
        return $defaultAvatar;
    }

    $possiblePaths = [
        $avatarUrl,
        'assets/uploads/avatars/' . $avatarUrl,
        'uploads/avatars/' . $avatarUrl,
        'assets/images/avatars/' . $avatarUrl,
        'uploads/' . $avatarUrl,
    ];

    foreach ($possiblePaths as $relPath) {
        $relPath = ltrim($relPath, '/');
        if (file_exists(__DIR__ . '/' . $relPath)) {
            return $relPath;
        }
    }

    return $defaultAvatar;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediConnect - Consultation Chat</title>
    <link rel="stylesheet" href="assets/css/patient_booking.css?v=5">
    <link rel="stylesheet" href="assets/css/chat_style.css?v=3">
    <style>
        .navbar-container { gap: 16px; }
        .nav-links { display: flex; align-items: center; flex-wrap: nowrap; gap: 12px; list-style: none; margin: 0; padding: 0; }
        .nav-links > li { position: relative; white-space: nowrap; }
        .nav-links a { white-space: nowrap; }
    </style>
</head>
<body>
<nav class="patient-navbar">
    <div class="navbar-container">
        <a href="index.php" class="brand-logo">MediConnect</a>

        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <?php if ($role_name === 'DOCTOR'): ?>
                <li><a href="doctor/dashboard.php">Doctor Portal</a></li>
                <li><a href="chat.php" class="active">Consultation Chat</a></li>
                <li><a href="articles/news.php">Articles</a></li>
                <li><a href="reviews/index.php">Reviews</a></li>
            <?php elseif ($role_name === 'PATIENT'): ?>
                <li><a href="patient/book_appointment.php">Book Appointment</a></li>
                <li><a href="patient/my_appointments.php">My Appointments</a></li>
                <li><a href="chat.php" class="active">Consultation Chat</a></li>
                <li><a href="articles/news.php">Articles</a></li>
                <li><a href="reviews/index.php">Reviews</a></li>
            <?php endif; ?>
        </ul>

        <div class="nav-user">
            <span>Hello, <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></strong></span>
            <a href="auth/logout.php" class="btn-logout-patient" onclick="return confirm('Are you sure you want to log out?');">
                Logout
            </a>
        </div>
    </div>
</nav>
<main class="main-container" style="margin-top: 26px;">
    <div class="chat-wrapper">
        <aside class="chat-sidebar">
            <div class="chat-sidebar-header">
                <?= ($role_name === 'PATIENT') ? 'My Doctors' : 'My Patients' ?>
            </div>
            <div class="contact-list">
                <?php if (count($contacts) > 0): ?>
                    <?php foreach ($contacts as $c): ?>
                        <a href="chat.php?partner_id=<?= $c['user_id'] ?>"
                           class="contact-item <?= ($partner_id === (int)$c['user_id']) ? 'active' : '' ?>">
                            <div class="contact-left">
                                <img src="<?= htmlspecialchars(resolveUserAvatar($c['avatar_url'] ?? null)) ?>"
                                     alt="Avatar"
                                     class="chat-contact-avatar">
                                <div>
                                    <div class="contact-name">
                                        <?= ($role_name === 'PATIENT') ? 'Dr. ' : '' ?><?= htmlspecialchars($c['full_name']) ?>
                                    </div>
                                    <div class="contact-sub">
                                        <?php if ($role_name === 'PATIENT'): ?>
                                            <?= htmlspecialchars($c['specialty'] ?? 'Specialist') ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars($c['gender'] ?? 'Patient') ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ((int)$c['unread_count'] > 0): ?>
                                <span class="unread-badge"><?= (int)$c['unread_count'] ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 20px; font-size: 13.5px; color: #64748b; text-align: center;">
                        <?= ($role_name === 'PATIENT')
                            ? 'Book an appointment with a doctor first to start chatting.'
                            : 'No patients have booked an appointment with you yet.' ?>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <section class="chat-main">
            <?php if ($partner): ?>
                <div class="chat-header">
                    <div class="chat-header-info">
                        <img src="<?= htmlspecialchars(resolveUserAvatar($partner['avatar_url'] ?? null)) ?>"
                             alt="Avatar"
                             class="chat-header-avatar">
                        <div>
                            <div class="chat-partner-title">
                                <?= ($partner['role_name'] === 'DOCTOR') ? 'Dr. ' : '' ?><?= htmlspecialchars($partner['full_name']) ?>
                            </div>
                            <span class="online-status"> Online</span>
                        </div>
                    </div>
                </div>

                <div class="chat-messages-box" id="chatMessagesBox">
                    <div style="text-align:center; color:#94a3b8; font-size:13px;">Loading messages...</div>
                </div>

                <form class="chat-input-form" id="chatForm">
                    <input type="hidden" id="partnerId" value="<?= $partner['user_id'] ?>">
                    <input type="text" id="messageInput" placeholder="Type your consultation message here..." autocomplete="off" required>
                    <button type="submit" class="btn-send-chat">Send ➤</button>
                </form>
            <?php else: ?>
                <div class="chat-empty-state">
                    <h3>No Conversation Selected</h3>
                    <p style="margin-top: 6px; font-size: 14px;">
                        Select a contact on the left panel to start exchanging consultation messages.
                    </p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<footer class="patient-footer">
    © 2026 MediConnect Healthcare Portal. All rights reserved.
</footer>

<?php if ($partner): ?>
<script>
    const partnerId = document.getElementById('partnerId').value;
    const messagesBox = document.getElementById('chatMessagesBox');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    let lastRenderedCount = -1;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function fetchMessages(forceScroll = false) {
        fetch(`api/chat_api.php?action=fetch&partner_id=${partnerId}`)
            .then(res => res.json())
            .then(data => {
                if (!data.status) return;
                const msgs = data.messages;
                const currentUserId = parseInt(data.current_user_id);

                if (msgs.length === lastRenderedCount && !forceScroll) return;
                lastRenderedCount = msgs.length;

                if (msgs.length === 0) {
                    messagesBox.innerHTML = '<div style="text-align:center; color:#94a3b8; margin-top:40px;">No messages yet. Say hello to start the consultation!</div>';
                    return;
                }

                let html = '';
                msgs.forEach(m => {
                    const isSent = parseInt(m.sender_id) === currentUserId;
                    const rowClass = isSent ? 'sent' : 'received';
                    const readTick = isSent ? (parseInt(m.is_read) === 1 ? ' • Read' : ' • Sent') : '';
                    html += `
                        <div class="msg-row ${rowClass}">
                            <div class="msg-bubble">${escapeHtml(m.message)}</div>
                            <div class="msg-time">${m.created_at}${readTick}</div>
                        </div>
                    `;
                });

                messagesBox.innerHTML = html;
                messagesBox.scrollTop = messagesBox.scrollHeight;
            })
            .catch(err => console.error('Chat fetch error:', err));
    }

    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const text = messageInput.value.trim();
        if (!text) return;

        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('receiver_id', partnerId);
        formData.append('message', text);

        messageInput.value = '';

        fetch('api/chat_api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status) {
                fetchMessages(true);
            } else {
                alert(data.message || 'Failed to send message.');
            }
        })
        .catch(err => console.error('Chat send error:', err));
    });

    fetchMessages(true);
    setInterval(() => fetchMessages(false), 3000);
</script>
<?php endif; ?>
</body>
</html>