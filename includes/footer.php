</main> <!-- end page-content -->

<footer class="footer text-center py-3 border-top mt-auto bg-white">
  <small class="text-muted">© <?= date('Y') ?> VolunteerHub. All rights reserved.</small>
</footer>

<!-- Scripts -->
<script src="../includes/scripts/bootstrap.bundle.min.js"></script>
<script>
  const sidebar = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('toggleSidebar');
  const closeBtn = document.getElementById('closeSidebar');

  toggleBtn.addEventListener('click', () => sidebar.classList.add('active'));
  closeBtn.addEventListener('click', () => sidebar.classList.remove('active'));
</script>

</body>
</html>
