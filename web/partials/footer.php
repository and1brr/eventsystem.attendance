            </div>
        </main>
        
        <!-- Footer -->
        <footer class="py-3 px-4 border-top text-muted text-sm">
            <div class="container-fluid text-center">
                &copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?>
            </div>
        </footer>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Toast Helper Scripts -->
<script src="/comprog/web/includes/toasts.js"></script>
<script src="/comprog/web/includes/ajax_toasts.js"></script>

<!-- Mobile Sidebar Management -->
<script>
document.querySelectorAll('#site-sidebar a').forEach(a => {
  a.addEventListener('click', () => {
    const sidebar = document.getElementById('site-sidebar');
    if (sidebar) {
      sidebar.classList.add('d-none', 'd-md-flex');
    }
  });
});

// Mobile menu toggle
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
if (mobileMenuBtn) {
  mobileMenuBtn.addEventListener('click', () => {
    const sidebar = document.getElementById('site-sidebar');
    if (sidebar) {
      sidebar.classList.toggle('d-none');
    }
  });
}
</script>
</body>
</html>
