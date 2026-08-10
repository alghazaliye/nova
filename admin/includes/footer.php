  </div><!-- .content -->
</main><!-- .main -->
</div><!-- .layout -->

<script>
// Theme Toggling
function toggleTheme() {
  const body = document.body;
  const currentTheme = body.getAttribute('data-theme');
  if (currentTheme === 'dark') {
    body.removeAttribute('data-theme');
    localStorage.setItem('admin-theme', 'light');
  } else {
    body.setAttribute('data-theme', 'dark');
    localStorage.setItem('admin-theme', 'dark');
  }
}

// Load saved theme
(function() {
  const savedTheme = localStorage.getItem('admin-theme');
  if (savedTheme === 'dark') {
    document.body.setAttribute('data-theme', 'dark');
  }
})();

// Auto-hide alerts
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => {
    el.style.opacity = '0';
    el.style.transition = '0.5s';
    setTimeout(() => el.style.display = 'none', 500);
  }, 4000);
});

// Confirm actions
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm || 'هل أنت متأكد؟')) {
      e.preventDefault();
    }
  });
});
</script>
</body>
</html>
