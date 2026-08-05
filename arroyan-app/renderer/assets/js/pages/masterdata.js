/**
 * Masterdata Index JS
 * Logic for interactive elements on the masterdata dashboard
 */

document.addEventListener('DOMContentLoaded', function () {
    // Add smooth hover effects for category cards
    const cards = document.querySelectorAll('.masterdata-category');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function () {
            this.style.transform = 'translateY(-5px)';
        });

        card.addEventListener('mouseleave', function () {
            this.style.transform = 'translateY(0)';
        });
    });

    // Add click ripple animation to links
    const links = document.querySelectorAll('.masterdata-link');
    links.forEach(link => {
        link.addEventListener('click', function (e) {
            // Create ripple element
            const ripple = document.createElement('span');
            ripple.style.position = 'absolute';
            ripple.style.width = '20px';
            ripple.style.height = '20px';
            ripple.style.background = 'rgba(220, 38, 38, 0.5)';
            ripple.style.borderRadius = '50%';
            ripple.style.transform = 'translate(-50%, -50%)';
            ripple.style.pointerEvents = 'none';
            ripple.style.animation = 'ripple 0.6s ease-out';

            // Position ripple
            const rect = this.getBoundingClientRect();
            ripple.style.left = (e.clientX - rect.left) + 'px';
            ripple.style.top = (e.clientY - rect.top) + 'px';

            // Add to link
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);

            // Remove after animation
            setTimeout(() => ripple.remove(), 600);
        });
    });
});
