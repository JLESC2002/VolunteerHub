<?php
include '../conn.php';
include './check_session.php';

$pageTitle = "Manage Tasks";
// $pageCSS = "../styles/admin_manage.css";
include '../includes/header_admin.php';

$admin_id = $_SESSION['user_id'];

/* ===== Fetch all events created by admin for dropdown ===== */
$events_stmt = $conn->prepare("SELECT id, title FROM events WHERE created_by = ?");
$events_stmt->bind_param("i", $admin_id);
$events_stmt->execute();
$events_result = $events_stmt->get_result();

$event_data = null;
$selected_event_id = $_GET['event_id'] ?? null;

/* ===== Fetch selected event details (ensures ownership) ===== */
if ($selected_event_id) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ii", $selected_event_id, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $event_data = $result->fetch_assoc();
    $stmt->close();
}

/* ===== Handle creating new task ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    $event_id = intval($_POST['event_id']);
    $description = $_POST['task_description'];

    $check = $conn->prepare("SELECT id FROM events WHERE id = ? AND created_by = ?");
    $check->bind_param("ii", $event_id, $admin_id);
    $check->execute();
    $own = $check->get_result()->num_rows > 0;
    $check->close();

if ($own) {
    // record which admin created/assigned the task in tasks.assigned_by
    $stmt = $conn->prepare("INSERT INTO tasks (event_id, description, assigned_by) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $event_id, $description, $admin_id);
    $stmt->execute();
    $stmt->close();
}

}

/* ===== Handle editing existing task ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_task'])) {
    $task_id = intval($_POST['task_id']);
    $description = $_POST['task_description'];

    $guard = $conn->prepare("
        SELECT t.id 
        FROM tasks t 
        JOIN events e ON e.id = t.event_id 
        WHERE t.id = ? AND e.created_by = ?
    ");
    $guard->bind_param("ii", $task_id, $admin_id);
    $guard->execute();
    $can_edit = $guard->get_result()->num_rows > 0;
    $guard->close();

    if ($can_edit) {
        $stmt = $conn->prepare("UPDATE tasks SET description = ? WHERE id = ?");
        $stmt->bind_param("si", $description, $task_id);
        $stmt->execute();
        $stmt->close();
    }
}

/* ===== Fetch tasks ===== */
$tasks = [];
if ($selected_event_id && $event_data) {
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE event_id = ?");
    $stmt->bind_param("i", $selected_event_id);
    $stmt->execute();
    $tasks_result = $stmt->get_result();
    while ($row = $tasks_result->fetch_assoc()) $tasks[] = $row;
    $stmt->close();
}

/* ===== Assign volunteer ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_volunteer'])) {
    $volunteer_id = intval($_POST['volunteer_id']);
    $task_id = intval($_POST['task_id']);
    $assigned_by = $_SESSION['user_id'];

    $check = $conn->prepare("SELECT id FROM task_assignments WHERE task_id = ? AND volunteer_id = ?");
    $check->bind_param("ii", $task_id, $volunteer_id);
    $check->execute();
    $check_result = $check->get_result();

    if ($check_result->num_rows === 0) {
        $insert = $conn->prepare("
            INSERT INTO task_assignments (task_id, volunteer_id, assigned_by, assigned_at, progress) 
            VALUES (?, ?, ?, NOW(), 'Not Started')
        ");
        $insert->bind_param("iii", $task_id, $volunteer_id, $assigned_by);
        
        if ($insert->execute()) {
          // Update the tasks table so the task row itself shows the assigned volunteer and who assigned it
$updateTask = $conn->prepare("
    UPDATE tasks
    SET volunteer_id = ?, assigned_by = ?
    WHERE id = ?
");
$updateTask->bind_param("iii", $volunteer_id, $assigned_by, $task_id);
$updateTask->execute();
$updateTask->close();

            $updateStatus = $conn->prepare("
                UPDATE volunteer_applications va
                JOIN tasks t ON t.event_id = va.event_id
                SET va.status = 'approved'
                WHERE va.user_id = ? AND t.id = ? AND va.status = 'pending'
            ");
            $updateStatus->bind_param("ii", $volunteer_id, $task_id);
            $updateStatus->execute();
            $updateStatus->close();

            echo "<script>alert('Volunteer assigned and approved!'); window.location.href='manage_tasks.php?event_id=$selected_event_id';</script>";
            exit;
        } else {
            echo "<script>alert('Error assigning volunteer.');</script>";
        }
    } else {
        echo "<script>alert('Volunteer is already assigned to this task.');</script>";
    }
}
?>

<div class="container-fluid px-4 py-4 manage-tasks-page">
  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h4 class="fw-bold text-primary mb-0"><i class="fas fa-tasks me-2"></i>Manage Tasks</h4>
      <form method="GET" class="d-flex align-items-center gap-2">
        <select name="event_id" class="form-select" onchange="this.form.submit()" style="width:240px;">
          <option value="">-- Select Event --</option>
          <?php while ($event = $events_result->fetch_assoc()): ?>
            <option value="<?= $event['id'] ?>" <?= $selected_event_id == $event['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($event['title']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </form>
    </div>
  </div>

  <?php if ($event_data): ?>
  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
      <h5 class="fw-bold text-secondary mb-3"><i class="fas fa-info-circle me-2"></i>Event Info</h5>
      <p><strong>Title:</strong> <?= htmlspecialchars($event_data['title']) ?></p>
      <p><strong>Status:</strong> <?= htmlspecialchars($event_data['status']) ?></p>
      <p><strong>Date:</strong> <?= htmlspecialchars($event_data['date']) ?></p>
      <button class="btn btn-outline-primary btn-sm" onclick="openQRModal(<?= $event_data['id'] ?>)">
        <i class="fas fa-qrcode me-1"></i> Generate QR Codes
      </button>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-primary"><i class="fas fa-list me-2"></i>Tasks</h5>
    <button class="btn btn-primary btn-sm" onclick="openCreateTaskModal()">
      <i class="fas fa-plus me-1"></i> Create Task
    </button>
  </div>

  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table align-middle table-hover">
          <thead class="table-light">
            <tr>
              <th>Task Description</th>
              <th style="width: 90px; text-align: center;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tasks as $task): ?>
              <tr>
                <td>
                  <form method="POST" class="task-edit-form">
                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                    <textarea class="editable form-control" name="task_description" data-orig-desc="<?= htmlspecialchars($task['description']) ?>" readonly><?= htmlspecialchars($task['description']) ?></textarea>
                  </form>
                </td>
                <td class="text-center position-relative">
                  <div class="dropdown">
                    <button type="button" class="action-menu-btn" onclick="toggleDropdown(this)">☰</button>
                    <div class="action-menu shadow-sm">
                      <button type="button" onclick="openAssignModal(<?= intval($selected_event_id) ?>, <?= intval($task['id']) ?>)">👥 Assign Volunteer</button>
                      <button type="button" class="editBtn">✏️ Edit</button>
                      <form method="POST" class="save-button-form">
                        <input type="hidden" name="edit_task" value="1">
                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                        <button type="submit" name="edit_task" class="btn-primary saveBtn d-none">💾 Save</button>
                      </form>
                      <button type="button" class="btn-danger" onclick="confirmDelete(<?= $task['id'] ?>)">🗑 Delete</button>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Volunteer Assignments -->
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <h5 class="fw-bold text-primary mb-3"><i class="fas fa-users-cog me-2"></i>Volunteer Assignments</h5>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Volunteer</th>
              <th>Task</th>
              <th>Attendance</th>
              <th>Progress</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $q = $conn->prepare("
              SELECT u.id AS uid, u.name AS full_name, t.id AS tid, t.description,
                    COALESCE(ea.attended, 0) AS attended,
                    CASE
                      WHEN ea.attended = 2 THEN 'Completed'
                      WHEN ea.attended = 1 THEN 'In Progress'
                      ELSE 'Not Started'
                    END AS progress
              FROM task_assignments ta
              JOIN users u ON u.id = ta.volunteer_id
              JOIN tasks t ON t.id = ta.task_id
              LEFT JOIN event_attendance ea 
                ON ea.event_id = t.event_id 
                AND ea.volunteer_id = u.id
              WHERE t.event_id = ?
              ORDER BY u.name ASC, t.id ASC
            ");
            $q->bind_param("i", $selected_event_id);
            $q->execute();
            $res = $q->get_result();

            if ($res->num_rows > 0) {
              while ($r = $res->fetch_assoc()) { ?>
                <tr>
                  <td><?= htmlspecialchars($r['full_name']) ?></td>
                  <td><?= htmlspecialchars($r['description']) ?></td>
                  <td>
                    <select class="form-select form-select-sm attendance-select" data-event="<?= $selected_event_id ?>" data-volunteer="<?= $r['uid'] ?>">
                      <option value="0" <?= $r['attended']==0?'selected':'' ?>>Not Checked In</option>
                      <option value="1" <?= $r['attended']==1?'selected':'' ?>>Checked In</option>
                      <option value="2" <?= $r['attended']==2?'selected':'' ?>>Checked Out</option>
                    </select>

                  </td>
                  <td>
                    <select class="form-select form-select-sm progress-select" data-task="<?= $r['tid'] ?>" data-volunteer="<?= $r['uid'] ?>">
                      <option value="Not Started" <?= $r['progress']=='Not Started'?'selected':'' ?>>Not Started</option>
                      <option value="In Progress" <?= $r['progress']=='In Progress'?'selected':'' ?>>In Progress</option>
                      <option value="Completed" <?= $r['progress']=='Completed'?'selected':'' ?>>Completed</option>
                    </select>
                  </td>
                </tr>
              <?php }
            } else {
              echo "<tr><td colspan='4' class='text-center text-muted'>No assignments yet.</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php endif; ?>
</div>

<!-- Modals -->
<div id="createTaskModal" class="wh-modal">
  <div class="wh-modal-backdrop" onclick="closeCreateTaskModal()"></div>
  <div class="wh-modal-content">
    <button class="wh-modal-close" onclick="closeCreateTaskModal()">&times;</button>
    <h5 class="fw-bold mb-3 text-primary"><i class="fas fa-plus-circle me-2"></i>Create Task</h5>
    <form method="POST">
      <input type="hidden" name="event_id" value="<?= htmlspecialchars($selected_event_id ?? '') ?>">
      <div class="mb-3">
        <label class="form-label">Task Description</label>
        <textarea name="task_description" class="form-control" required></textarea>
      </div>
      <button type="submit" name="create_task" class="btn btn-primary w-100" <?= $selected_event_id?'':'disabled' ?>>Save</button>
    </form>
  </div>
</div>

<div id="assignModal" class="wh-modal">
  <div class="wh-modal-backdrop" onclick="closeAssignModal()"></div>
  <div class="wh-modal-content">
    <button class="wh-modal-close" onclick="closeAssignModal()">&times;</button>
    <h5 class="fw-bold text-primary mb-3"><i class="fas fa-user-plus me-2"></i>Assign Task</h5>
    <form method="POST">
      <input type="hidden" name="event_id" id="modal_event_id" value="<?= htmlspecialchars($selected_event_id ?? '') ?>">
      <input type="hidden" name="task_id" id="modal_task_id">
      <label class="form-label">Select Volunteer</label>
      <select name="volunteer_id" id="volunteer_dropdown" class="form-select mb-3"><option>Loading...</option></select>
      <button type="submit" name="assign_volunteer" class="btn btn-success w-100">Assign</button>
    </form>
  </div>
</div>

<div id="qrModal" class="wh-modal">
  <div class="wh-modal-backdrop" onclick="closeQRModal()"></div>
  <div class="wh-modal-content text-center">
    <button class="wh-modal-close" onclick="closeQRModal()">&times;</button>
    <h5 class="fw-bold text-primary mb-3"><i class="fas fa-qrcode me-2"></i>Event QR Codes</h5>
    <div id="qrContent">Loading...</div>
  </div>
</div>

<style>
.action-menu-btn {
  border: none;
  background: transparent;
  font-size: 18px;
  cursor: pointer;
  color: #374151;
  padding: 4px 8px;
  border-radius: 6px;
}
.action-menu-btn:hover { background: rgba(0,0,0,0.05); }
.action-menu {
  display: none;
  position: absolute;
  right: 0;
  top: 110%;
  background: #fff;
  min-width: 180px;
  border-radius: 8px;
  box-shadow: 0 6px 20px rgba(0,0,0,0.12);
  z-index: 999;
  animation: fadeIn .15s ease forwards;
}
.action-menu button {
  display: block;
  width: 100%;
  border: none;
  background: transparent;
  text-align: left;
  padding: 10px 14px;
  font-size: 14px;
  cursor: pointer;
  color: #333;
  transition: background .2s;
}
.action-menu button:hover { background: #f1f5f9; }
.wh-modal{display:none;position:fixed;inset:0;z-index:1200;}
.wh-modal.show, .wh-modal[style*="flex"]{display:block;}
.wh-modal-backdrop{position:fixed;inset:0;background:rgba(6,10,17,0.36);backdrop-filter:blur(4px);}
.wh-modal-content{position:relative;width:100%;max-width:600px;margin:8vh auto;background:#fff;border-radius:12px;
padding:20px;box-shadow:0 12px 40px rgba(2,8,23,0.12);animation:fadePop .2s ease;}
.wh-modal-close{position:absolute;right:12px;top:10px;border:none;background:none;font-size:22px;color:#6b7280;cursor:pointer;}
@keyframes fadePop{from{opacity:0;transform:scale(.95);}to{opacity:1;transform:scale(1);}}
.table-hover tbody tr:hover{background:#f9fafb;}

.table-responsive {
  overflow: visible;
  position: relative;
}
</style>

<script>
function toggleDropdown(btn){
  const menu=btn.nextElementSibling;
  const visible=menu.style.display==="block";
  document.querySelectorAll('.action-menu').forEach(m=>m.style.display="none");
  if(!visible)menu.style.display="block";
}
window.addEventListener('click',e=>{
  if(!e.target.closest('.dropdown'))document.querySelectorAll('.action-menu').forEach(m=>m.style.display="none");
});

document.querySelectorAll('.editBtn').forEach(btn=>{
  btn.addEventListener('click',function(e){
    e.stopPropagation();
    const row=this.closest('tr');
    const editForm=row.querySelector('.task-edit-form');
    const editable=editForm.querySelector('.editable');
    const saveBtn=row.querySelector('.saveBtn');
    const original=editable.getAttribute('data-orig-desc');

    if(this.classList.contains('editing')){
      editable.readOnly=true;
      editable.value=original;
      this.classList.remove('editing');
      this.textContent='✏️ Edit';
      saveBtn.classList.add('d-none');
    }else{
      editable.readOnly=false;
      editable.focus();
      this.classList.add('editing');
      this.textContent='❌ Cancel';
      saveBtn.classList.remove('d-none');
    }
  });
});

document.querySelectorAll('.save-button-form').forEach(form=>{
  form.addEventListener('submit',function(e){
    e.preventDefault();
    const row=this.closest('tr');
    const editForm=row.querySelector('.task-edit-form');
    const editable=editForm.querySelector('.editable');
    const taskId=editForm.querySelector('input[name="task_id"]').value;
    const desc=editable.value.trim();
    if(!desc){alert('Description required');return;}
    fetch('manage_tasks.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`edit_task=1&task_id=${taskId}&task_description=${encodeURIComponent(desc)}`})
    .then(r=>r.text()).then(()=>location.reload());
  });
});

function confirmDelete(taskId){
  if(confirm("Are you sure you want to delete this task?")){
    const f=document.createElement("form");
    f.method="POST";
    f.action="delete_item.php";
    f.innerHTML=`<input type="hidden" name="type" value="task"><input type="hidden" name="id" value="${taskId}">`;
    document.body.appendChild(f);f.submit();
  }
}

function openCreateTaskModal(){document.getElementById('createTaskModal').style.display='flex';}
function closeCreateTaskModal(){document.getElementById('createTaskModal').style.display='none';}
function openAssignModal(e,t){const m=document.getElementById('assignModal');m.style.display='flex';
document.getElementById('modal_event_id').value=e;document.getElementById('modal_task_id').value=t;
const d=document.getElementById('volunteer_dropdown');d.innerHTML='<option>Loading...</option>';
fetch('fetch_volunteers.php?event_id='+e).then(r=>r.text()).then(h=>d.innerHTML=h);}
function closeAssignModal(){document.getElementById('assignModal').style.display='none';}
function openQRModal(id){const m=document.getElementById('qrModal');const q=document.getElementById('qrContent');
m.style.display='flex';q.innerHTML='Loading...';fetch('generate_qr.php?event_id='+id).then(r=>r.text()).then(h=>q.innerHTML=h);}
function closeQRModal(){document.getElementById('qrModal').style.display='none';}
document.querySelectorAll('.attendance-select').forEach(sel=>{
  sel.addEventListener('change',()=>{
    const eventId = sel.dataset.event;
    const volunteerId = sel.dataset.volunteer;
    const attended = sel.value;

    const body = new URLSearchParams({
      event_id: eventId,
      volunteer_id: volunteerId,
      attended: attended
    });

    fetch('update_attendance.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: body.toString(),
      credentials: 'same-origin'
    })
    .then(r=>r.text())
    .then(t=>{
      console.log('Update:', t);
      location.reload(); // refresh to show updated progress
    })
    .catch(err=>{
      console.error('Error:', err);
      alert('Failed to update attendance');
    });
  });
});

</script>

<?php include '../includes/footer.php'; ?>
