<?php
include '../conn.php';
include './check_session.php';

// Set the CSS file before including header
$pageCSS = "/VolunteerHub/styles/dashboard.css"; 
include '../includes/header_admin.php';

// Leaderboard Query
$leaderboardQuery = "
    SELECT users.id, users.name, COUNT(tasks.id) AS completed_tasks
    FROM users
    JOIN task_assignments ON users.id = task_assignments.volunteer_id
    JOIN tasks ON task_assignments.task_id = tasks.id
    WHERE tasks.status = 'Completed'
    GROUP BY users.id
    ORDER BY completed_tasks DESC
    LIMIT 10";
$leaderboardResult = $conn->query($leaderboardQuery);

// Daily Reports
$dailyReportQuery = "
    SELECT 
        events.id, 
        events.title, 
        (
            SELECT COUNT(*) 
            FROM volunteer_applications 
            WHERE event_id = events.id AND status = 'approved'
        ) AS approved_volunteers,
        (
            SELECT COUNT(*) 
            FROM tasks 
            WHERE event_id = events.id AND status = 'Completed'
        ) AS completed_tasks
    FROM events
    ORDER BY events.date DESC
    LIMIT 5";
$dailyReportResult = $conn->query($dailyReportQuery);

// Weekly Events
$weeklyEventsQuery = "
    SELECT id, title, date, location 
    FROM events 
    WHERE WEEK(date) = WEEK(CURDATE()) AND status = 'Open'
    ORDER BY date ASC";
$weeklyEventsResult = $conn->query($weeklyEventsQuery);

// Gender Participation Data (from volunteer dashboard)
$genderQuery = "
    SELECT vd.gender, COUNT(DISTINCT va.event_id) AS event_count
    FROM volunteer_details vd
    JOIN volunteer_applications va ON vd.user_id = va.user_id
    WHERE va.status = 'approved'
    GROUP BY vd.gender
    ORDER BY FIELD(vd.gender, 'Male', 'Female')
";
$genderData = $conn->query($genderQuery);

$genderLabels = [];
$genderCounts = [];
while ($row = $genderData->fetch_assoc()) {
    $genderLabels[] = $row['gender'];
    $genderCounts[] = $row['event_count'];
}
?>

<div class="container-fluid dashboard-page">

  <!-- 🏆 Leaderboard -->
  <div class="row g-4 mb-4">
    <div class="col-12 col-lg-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white border-0 pb-0">
          <h5 class="fw-bold text-primary mb-0">
            <i class="fas fa-trophy me-2 text-warning"></i>Leaderboard
          </h5>
        </div>
        <div class="card-body">
          <table class="table table-striped align-middle">
            <thead class="table-light">
              <tr>
                <th>Rank</th>
                <th>Name</th>
                <th>Completed Tasks</th>
              </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                while ($row = $leaderboardResult->fetch_assoc()) {
                    echo "<tr>
                            <td>{$rank}</td>
                            <td>" . htmlspecialchars($row['name']) . "</td>
                            <td>{$row['completed_tasks']}</td>
                          </tr>";
                    $rank++;
                } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>


     <!-- 📊 Gender Chart -->
    <div class="col-12 col-lg-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white border-0 pb-0">
          <h5 class="fw-bold text-primary mb-0">
            <i class="fas fa-venus-mars me-2 text-info"></i>Gender Participation
          </h5>
        </div>
        <div class="card-body text-center">
                <canvas id="genderChart"></canvas> 
<script src="../vendor/chart.min.js"></script>
<script>
    const genderLabels = <?= json_encode($genderLabels) ?>;
    const genderCounts = <?= json_encode($genderCounts) ?>;

    const ctx = document.getElementById('genderChart').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: genderLabels,
            datasets: [{
                label: 'Event Participation',
                data: genderCounts,
                backgroundColor: ['#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0'],
                borderColor: '#fff',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                title: { display: true, text: 'Event Participation by Gender' }
            }
        }
    });
    
</script>
</div>
      </div>
    </div>
  </div>
      <!-- 📅 Daily Reports -->
  <div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pb-0">
      <h5 class="fw-bold text-primary mb-0">
        <i class="fas fa-calendar-day me-2 text-secondary"></i>Daily Reports (Event Participation)
      </h5>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Event Title</th>
              <th>Approved Volunteers</th>
              <th>Completed Tasks</th>
            </tr>
          </thead>
          <tbody>
                <?php while ($row = $dailyReportResult->fetch_assoc()) { ?>
                    <tr>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= $row['approved_volunteers'] ?></td>
                        <td><?= $row['completed_tasks'] ?></td>
                    </tr>
                <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

 <!-- 📅 Weekly Events -->
  <div class="card shadow-sm border-0">
    <div class="card-header bg-white border-0 pb-0">
      <h5 class="fw-bold text-primary mb-0">
        <i class="fas fa-thumbtack me-2 text-danger"></i>Weekly Available Events
      </h5>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Event Title</th>
              <th>Date</th>
              <th>Location</th>
            </tr>
          </thead>
          <tbody>
                <?php while ($row = $weeklyEventsResult->fetch_assoc()) { ?>
                    <tr>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= htmlspecialchars($row['date']) ?></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
                    </tr>
                <?php } ?>
              </tbody>
        </table>
      </div>
    </div>
  </div>

<?php include '../includes/footer.php'; ?>