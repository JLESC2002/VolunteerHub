<?php
session_start();
include 'conn.php';

$action = $_GET['action'] ?? '';

if ($action === 'confirm') {
    // User confirmed logout
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }

    session_unset();
    session_destroy();
    setcookie("session_token", "", time() - 3600, "/");
    
    // Return success response for AJAX or redirect
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['json'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    }
    
    // Redirect to home with logout message
    header('Location: index.php?logged_out=1');
    exit();
}

// Show logout confirmation modal
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout — VolunteerHub</title>
    <link href="includes/css/bootstrap.min.css" rel="stylesheet">
    <link href="includes/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        /* Confirmation modal */
        .logout-modal {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            padding: 40px 36px;
            max-width: 380px;
            width: 100%;
            margin: 16px;
            text-align: center;
            animation: modalSlideIn 0.35s cubic-bezier(.22,1,.36,1) both;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logout-icon {
            width: 64px;
            height: 64px;
            border-radius: 12px;
            background: linear-gradient(135deg, #ff6b6b, #ff5252);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 32px;
            color: white;
        }

        .logout-modal h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1a1f2e;
            margin-bottom: 8px;
        }

        .logout-modal p {
            font-size: 0.95rem;
            color: #64748b;
            margin-bottom: 28px;
            line-height: 1.5;
        }

        .logout-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .logout-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            flex: 1;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #64748b;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-logout {
            background: linear-gradient(135deg, #ff6b6b, #ff5252);
            color: white;
        }

        .btn-logout:hover {
            background: linear-gradient(135deg, #f55555, #ff3939);
            box-shadow: 0 8px 16px rgba(255, 107, 107, 0.3);
        }

        .btn-logout.loading {
            opacity: 0.7;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="logout-modal">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h2>Are you sure?</h2>
        <p>You'll be logged out of your account. You can log back in anytime.</p>
        <div class="logout-actions">
            <button class="logout-btn btn-cancel" onclick="handleCancel()">
                <i class="fas fa-arrow-left" style="margin-right: 6px;"></i> Stay
            </button>
            <button class="logout-btn btn-logout" id="logoutBtn" onclick="handleLogout()">
                <i class="fas fa-sign-out-alt" style="margin-right: 6px;"></i> Logout
            </button>
        </div>
    </div>

    <script>
        function handleCancel() {
            // Return to previous page or dashboard
            const referrer = document.referrer;
            if (referrer && referrer.includes(window.location.hostname)) {
                window.history.back();
            } else {
                // Default fallback routes
                const pathSegments = window.location.pathname.split('/');
                if (pathSegments.includes('admin')) {
                    window.location.href = '/VolunteerHub/admin/admin_dashboard.php';
                } else {
                    window.location.href = '/VolunteerHub/volunteer/volunteer_dashboard.php';
                }
            }
        }

        function handleLogout() {
            const btn = document.getElementById('logoutBtn');
            btn.classList.add('loading');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 6px;"></i> Logging out...';

            // Perform logout
            window.location.href = 'logout.php?action=confirm';
        }
    </script>
</body>
</html>
