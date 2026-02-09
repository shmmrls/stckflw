// Search Overlay Functionality (shared)
document.addEventListener('DOMContentLoaded', function() {
  const searchTriggerBtns = document.querySelectorAll('.search-trigger-btn');
  const searchOverlay = document.getElementById('searchOverlay');
  const searchCloseBtn = document.querySelector('.search-close-btn');
  const searchInput = document.querySelector('.search-input');

  function openSearchOverlay() {
    if (!searchOverlay) return;
    searchOverlay.classList.add('active');
    if (searchInput) searchInput.focus();
    document.body.style.overflow = 'hidden';
  }

  function closeSearchOverlay() {
    if (!searchOverlay) return;
    searchOverlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  // Event listeners
  if (searchTriggerBtns && searchTriggerBtns.length) {
    searchTriggerBtns.forEach(btn => btn.addEventListener('click', openSearchOverlay));
  }

  if (searchCloseBtn) {
    searchCloseBtn.addEventListener('click', closeSearchOverlay);
  }

  // Close on escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && searchOverlay && searchOverlay.classList.contains('active')) {
      closeSearchOverlay();
    }
  });

  // Close on overlay background click
  if (searchOverlay) {
    searchOverlay.addEventListener('click', function(e) {
      if (e.target === searchOverlay) {
        closeSearchOverlay();
      }
    });
  }
});
