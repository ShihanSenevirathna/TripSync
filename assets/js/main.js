/**
 * TripSync Main JavaScript
 * Handles UI interactions like navbar effects and mobile menu
 */

document.addEventListener('DOMContentLoaded', () => {
    // Navbar Scroll Effect
    const navbar = document.querySelector('nav');
    if (navbar) {
        const isIndexPage = window.location.pathname.endsWith('index.php')
            || window.location.pathname === '/'
            || window.location.pathname.endsWith('TripSync/');

        function applyScrolledStyle() {
            navbar.classList.remove('bg-transparent');
            navbar.classList.add('bg-white', 'shadow-md');
            // Switch all white-text elements to dark for readability
            navbar.querySelectorAll('a, button, i, span.font-bold').forEach(el => {
                if (el.classList.contains('text-white')) {
                    el.classList.remove('text-white');
                    el.classList.add('text-gray-700');
                    el.dataset.wasWhite = 'true';
                }
                if (el.classList.contains('hover:text-teal-300')) {
                    el.classList.remove('hover:text-teal-300');
                    el.classList.add('hover:text-teal-600');
                    el.dataset.hadTealHover = 'true';
                }
            });
        }

        function applyTransparentStyle() {
            navbar.classList.add('bg-transparent');
            navbar.classList.remove('bg-white', 'shadow-md');
            // Restore white text colors
            navbar.querySelectorAll('a, button, i, span.font-bold').forEach(el => {
                if (el.dataset.wasWhite === 'true') {
                    el.classList.add('text-white');
                    el.classList.remove('text-gray-700');
                    delete el.dataset.wasWhite;
                }
                if (el.dataset.hadTealHover === 'true') {
                    el.classList.add('hover:text-teal-300');
                    el.classList.remove('hover:text-teal-600');
                    delete el.dataset.hadTealHover;
                }
            });
        }

        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                if (navbar.classList.contains('bg-transparent')) {
                    applyScrolledStyle();
                }
            } else {
                // Only go transparent on the homepage hero
                if (isIndexPage && !navbar.classList.contains('bg-transparent')) {
                    applyTransparentStyle();
                }
            }
        });

        // On non-index pages, always show the solid navbar from the start
        if (!isIndexPage) {
            applyScrolledStyle();
        }
    }

    // Mobile Menu Toggle
    const menuBtn = document.querySelector('button.md\\:hidden');
    if (menuBtn) {
        menuBtn.addEventListener('click', () => {
            // Implementation for mobile menu can be added here
            console.log('Mobile menu clicked');
        });
    }

    // FCM Real-time Notifications Initializer
    if (localStorage.getItem('fcm_enabled') || confirmFCMRequest()) {
        import('./fcm_setup.js').then(module => {
            module.setupFCM();
        }).catch(err => {
            console.error('FCM Setup failed (check Firebase config):', err);
        });
    }

    function confirmFCMRequest() {
        // Only ask once or if we have a reason to enable notifications
        if (sessionStorage.getItem('fcm_asked')) return false;
        sessionStorage.setItem('fcm_asked', 'true');
        return true;
    }

    // Dropdown Logic (for profile menu etc)
    // The current header.php uses Tailwind's group-hover, so JS might not be strictly needed for desktop
});