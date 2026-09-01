/**
 * Customer Support Management System (support-mgt)
 * Master JavaScript - Sidebar, Tooltips, Password Toggles
 */

document.addEventListener('DOMContentLoaded', () => {
    // ----------------------------------------------------
    // 1. Sidebar Desktop Collapse & LocalStorage State
    // ----------------------------------------------------
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const mobileSidebarToggleBtn = document.getElementById('mobileSidebarToggleBtn');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const SIDEBAR_STORAGE_KEY = 'support_mgt_sidebar_collapsed';

    // Restore desktop collapsed state on page load (desktop screens only)
    if (window.innerWidth >= 992) {
        const isCollapsed = localStorage.getItem(SIDEBAR_STORAGE_KEY) === 'true';
        if (isCollapsed) {
            document.body.classList.add('sidebar-collapsed');
        }
    }

    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (window.innerWidth >= 992) {
                // Desktop: Toggle collapsed state
                document.body.classList.toggle('sidebar-collapsed');
                const isNowCollapsed = document.body.classList.contains('sidebar-collapsed');
                localStorage.setItem(SIDEBAR_STORAGE_KEY, isNowCollapsed ? 'true' : 'false');
                updateTooltips();
            } else {
                // Mobile: Toggle drawer
                toggleMobileSidebar();
            }
        });
    }

    if (mobileSidebarToggleBtn) {
        mobileSidebarToggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleMobileSidebar();
        });
    }

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', () => {
            closeMobileSidebar();
        });
    }

    // Close mobile drawer on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && document.body.classList.contains('mobile-sidebar-open')) {
            closeMobileSidebar();
        }
    });

    function toggleMobileSidebar() {
        document.body.classList.toggle('mobile-sidebar-open');
    }

    function closeMobileSidebar() {
        document.body.classList.remove('mobile-sidebar-open');
    }

    // ----------------------------------------------------
    // 2. Initialize Bootstrap Tooltips
    // ----------------------------------------------------
    let tooltipList = [];
    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipList = tooltipTriggerList.map((el) => {
            return new bootstrap.Tooltip(el, {
                trigger: 'hover',
                boundary: 'window'
            });
        });
    }

    function updateTooltips() {
        // Dispose existing tooltips and re-initialize
        tooltipList.forEach((t) => t.dispose());
        initTooltips();
    }

    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        initTooltips();
    }

    // ----------------------------------------------------
    // 3. Password Visibility Toggler
    // ----------------------------------------------------
    const passwordToggles = document.querySelectorAll('.password-toggle-btn');
    passwordToggles.forEach((btn) => {
        btn.addEventListener('click', function () {
            const targetInputId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetInputId);
            const icon = this.querySelector('i');

            if (targetInput) {
                if (targetInput.type === 'password') {
                    targetInput.type = 'text';
                    if (icon) {
                        icon.classList.remove('bi-eye');
                        icon.classList.add('bi-eye-slash');
                    }
                } else {
                    targetInput.type = 'password';
                    if (icon) {
                        icon.classList.remove('bi-eye-slash');
                        icon.classList.add('bi-eye-line', 'bi-eye');
                    }
                }
            }
        });
    });

    // ----------------------------------------------------
    // 4. Auto-dismiss Flash Alerts (After 6 Seconds)
    // ----------------------------------------------------
    const flashAlerts = document.querySelectorAll('.flash-messages-container .alert');
    flashAlerts.forEach((alertEl) => {
        setTimeout(() => {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
                bsAlert.close();
            }
        }, 6000);
    });
});
