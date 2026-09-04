// Simplified UI Animations for Thread and Press Hub Fashion Store
// Focus: Clean, fast animations for better browsing

class SmoothUIAnimations {
    constructor() {
        this.navbar = document.querySelector('.cafe-navbar');
        this.init();
    }

    init() {
        this.setupIntersectionObserver();
        this.setupNavbarShrink();
        this.setupSmoothScrolling();
    }

    // Simple fade-in on scroll
    setupIntersectionObserver() {
        const options = {
            threshold: 0.05,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    observer.unobserve(entry.target);
                }
            });
        }, options);

        // Only observe major sections (lighter than before)
        document.querySelectorAll('section, .product-card, .card').forEach(el => {
            el.style.opacity = '0.95';
            observer.observe(el);
        });
    }

    // Navbar shrink effect on scroll
    setupNavbarShrink() {
        window.addEventListener('scroll', () => {
            if (this.navbar && window.scrollY > 50) {
                this.navbar.classList.add('navbar-scrolled');
            } else if (this.navbar) {
                this.navbar.classList.remove('navbar-scrolled');
            }
        }, { passive: true });
    }

    // Smooth scrolling for anchor links
    setupSmoothScrolling() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', (e) => {
                const href = anchor.getAttribute('href');
                
                // Skip if it's just '#'
                if (href === '#') return;

                const target = document.querySelector(href);
                
                if (target) {
                    e.preventDefault();
                    const offsetTop = target.offsetTop - 80; // Account for navbar height

                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Smooth scroll for "Shop Now" and similar buttons
        document.querySelectorAll('a[href="shop.php"], .btn-hero, [class*="shop-now"]').forEach(link => {
            link.addEventListener('click', (e) => {
                if (link.getAttribute('href') === 'shop.php') {
                    // Standard navigation with smooth scroll
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        });
    }

    // Scroll-triggered animations for elements
    setupScrollAnimations() {
        const observerOptions = {
            threshold: 0.15,
            rootMargin: '0px 0px -50px 0px'
        };

        const scrollObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Add animation classes
                    entry.target.classList.add('animate-in');

                    // Stagger child elements
                    const children = entry.target.querySelectorAll('.product-card, .card, .stat-card, [class*="col"]');
                    if (children.length > 0) {
                        children.forEach((child, index) => {
                            child.style.animationDelay = `${index * 0.1}s`;
                            child.classList.add('stagger-animate');
                        });
                    }
                }
            });
        }, observerOptions);

        // Observe all major sections
        document.querySelectorAll('section, .row, .admin-container').forEach(el => {
            if (el.children.length > 0) {
                scrollObserver.observe(el);
            }
        });
    }

    // Animate buttons on hover
    animateButtons() {
        const buttons = document.querySelectorAll('.btn, button');
        buttons.forEach(btn => {
            btn.addEventListener('mouseenter', () => {
                btn.style.transform = 'translateY(-3px)';
            });
            btn.addEventListener('mouseleave', () => {
                btn.style.transform = 'translateY(0)';
            });
        });
    }
}

// Initialize animations when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new SmoothUIAnimations();
    });
} else {
    new SmoothUIAnimations();
}
