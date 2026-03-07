<?php
include '../conn.php';
include './check_session.php';
$pageCSS = "../styles/notifications.css";
include '../includes/header_volunteer.php';

$user_id = $_SESSION['user_id'];

// Fetch application notifications
$appQuery = "
      SELECT va.status, e.title, e.date
      FROM volunteer_applications va
      JOIN events e ON va.event_id = e.id
      WHERE va.user_id = ? AND va.status IN ('approved', 'rejected')
      ORDER BY e.date DESC
";
$appStmt = $conn->prepare($appQuery);
$appStmt->bind_param("i", $user_id);
$appStmt->execute();
$appResult = $appStmt->get_result();

// Fetch upcoming events within 3 days
$upcomingQuery = "
      SELECT e.title, e.date
      FROM events e
      JOIN volunteer_applications va ON e.id = va.event_id
      WHERE va.user_id = ? AND va.status = 'approved' AND DATEDIFF(e.date, CURDATE()) BETWEEN 0 AND 3
      ORDER BY e.date ASC
";
$upcomingStmt = $conn->prepare($upcomingQuery);
$upcomingStmt->bind_param("i", $user_id);
$upcomingStmt->execute();
$upcomingResult = $upcomingStmt->get_result();

// Fetch newly assigned tasks
$taskQuery = "
      SELECT t.description, e.title AS event_title
      FROM task_assignments ta
      JOIN tasks t ON ta.task_id = t.id
      JOIN events e ON t.event_id = e.id
      WHERE ta.volunteer_id = ?
      ORDER BY ta.assigned_at DESC
      LIMIT 10
";
$taskStmt = $conn->prepare($taskQuery);
$taskStmt->bind_param("i", $user_id);
$taskStmt->execute();
$taskResult = $taskStmt->get_result();
?>

<div class="dashboard-container">
      <h2 class="section-title"><i class="fas fa-bell"></i> Volunteer Notifications</h2>

      <!-- Application Updates Card -->
      <div class="notification-card application-updates">
            <h3>📌 Application Status</h3>
            <ul>
                  <?php if ($appResult->num_rows > 0) { ?>
                        <?php while ($row = $appResult->fetch_assoc()) { ?>
                              <li class="notification-item">
                                    <div class="notification-content">
                                          <span class="message-text">Your application for "<strong><?= htmlspecialchars($row['title']) ?></strong>" was</span>
                                          <span class="status <?= strtolower($row['status']) ?>">
                                                <?= strtoupper($row['status']) ?>
                                          </span>
                                    </div>
                                    <span class="notification-date">
                                          Event Date: <?= htmlspecialchars($row['date']) ?>
                                    </span>
                              </li>
                        <?php } ?>
                  <?php } else { ?>
                <li class="empty-state">No application status updates found.</li>
            <?php } ?>
            </ul>
      </div>

      <!-- Upcoming Events Card -->
      <div class="notification-card upcoming-events">
            <h3>📅 Upcoming Approved Events (0-3 days)</h3>
            <ul>
                  <?php if ($upcomingResult->num_rows > 0) { ?>
                        <?php while ($row = $upcomingResult->fetch_assoc()) { ?>
                              <li class="notification-item">
                                    <span class="message-text">
                                          Reminder: You are volunteering for "<strong><?= htmlspecialchars($row['title']) ?></strong>".
                                    </span>
                                    <span class="notification-date alert-date">
                                          Happening on: <?= htmlspecialchars($row['date']) ?>!
                                    </span>
                              </li>
                        <?php } ?>
                  <?php } else { ?>
                <li class="empty-state">No upcoming events are scheduled within the next 3 days.</li>
            <?php } ?>
            </ul>
      </div>

      <!-- Task Assignments Card -->
      <div class="notification-card task-assignments">
            <h3>🛠️ Recent Task Assignments</h3>
            <ul>
                  <?php if ($taskResult->num_rows > 0) { ?>
                        <?php while ($row = $taskResult->fetch_assoc()) { ?>
                              <li class="notification-item task-item">
                                    <span class="task-icon"><i class="fas fa-list-check"></i></span>
                                    <div class="task-details">
                                          <p class="task-description"><strong>New Task:</strong> <?= htmlspecialchars($row['description']) ?></p>
                                          <p class="task-event">Under Event: <em><?= htmlspecialchars($row['event_title']) ?></em></p>
                                    </div>
                              </li>
                        <?php } ?>
                  <?php } else { ?>
                <li class="empty-state">No new tasks have been assigned recently.</li>
            <?php } ?>
            </ul>
      </div>
</div>

<?php include '../includes/footer.php'; ?>
