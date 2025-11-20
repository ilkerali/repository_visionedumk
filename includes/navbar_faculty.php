<nav class="navbar">
    <div class="navbar-container">
        <div class="navbar-brand">
            <img src="../assets/images/logo.png" alt="Logo" class="navbar-logo" onerror="this.style.display='none'">
            <span class="navbar-title">Akademik Yayın Repositori</span>
        </div>
        
        <div class="navbar-menu">
            <a href="dashboard.php" class="nav-link">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                </svg>
                Ana Sayfa
            </a>
            <a href="add_publication.php" class="nav-link">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="16"></line>
                    <line x1="8" y1="12" x2="16" y2="12"></line>
                </svg>
                Yeni Yayın
            </a>
            <a href="my_publications.php" class="nav-link">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
                Yayınlarım
            </a>
            <a href="profile.php" class="nav-link">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                Profilim
            </a>
        </div>
        
        <div class="navbar-user">
            <div class="user-info">
                <span class="user-name"><?php echo sanitize($_SESSION['full_name']); ?></span>
                <span class="user-role"><?php echo sanitize($_SESSION['department']); ?></span>
            </div>
            <a href="../logout.php" class="btn btn-sm btn-secondary">
                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                Çıkış
            </a>
        </div>
    </div>
</nav>