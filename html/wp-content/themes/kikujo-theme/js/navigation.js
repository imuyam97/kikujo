/**
 * Navigation - Mobile menu toggle
 *
 * @package Kikujo_Theme
 */

(function() {
    'use strict';

    const menuToggle = document.querySelector('.menu-toggle');
    const navigation = document.querySelector('.main-navigation');

    if (!menuToggle || !navigation) {
        return;
    }

    // Toggle navigation
    menuToggle.addEventListener('click', function() {
        const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';

        menuToggle.setAttribute('aria-expanded', !isExpanded);
        navigation.classList.toggle('toggled');

        if (!isExpanded) {
            navigation.style.display = 'block';
        } else {
            navigation.style.display = '';
        }
    });

    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
        if (!navigation.contains(event.target) && !menuToggle.contains(event.target)) {
            if (navigation.classList.contains('toggled')) {
                menuToggle.setAttribute('aria-expanded', 'false');
                navigation.classList.remove('toggled');
                navigation.style.display = '';
            }
        }
    });

    // Close menu on escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && navigation.classList.contains('toggled')) {
            menuToggle.setAttribute('aria-expanded', 'false');
            navigation.classList.remove('toggled');
            navigation.style.display = '';
            menuToggle.focus();
        }
    });

    // Handle resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth >= 768) {
                navigation.classList.remove('toggled');
                navigation.style.display = '';
                menuToggle.setAttribute('aria-expanded', 'false');
            }
        }, 250);
    });
})();
