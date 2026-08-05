// Performance Optimizer Module
class PerformanceOptimizer {
    constructor() {
        this.initialized = false;
        this.observers = [];
        this.prefetchedUrls = new Set();
    }

    init() {
        if (this.initialized) return;

        // Monitor performance metrics
        this.monitorPerformance();

        // Optimize image loading
        this.optimizeImages();

        // Debounce scroll events
        this.debounceEvents();

        // Optimize animations
        this.optimizeAnimations();

        // NEW: Add link hover prefetch
        this.setupLinkPrefetch();

        // NEW: Cleanup listeners for page navigation
        this.setupPageCleanup();

        this.initialized = true;
    }

    // NEW: Prefetch links on hover for instant navigation
    setupLinkPrefetch() {
        document.addEventListener('mouseover', (e) => {
            const link = e.target.closest('a[href]');
            if (!link) return;

            const href = link.href;

            // Skip external links, already prefetched, or non-page links
            if (!href ||
                !href.includes(window.location.hostname) ||
                this.prefetchedUrls.has(href) ||
                href.includes('#') ||
                link.target === '_blank' ||
                href.toLowerCase().includes('logout') ||
                link.hasAttribute('data-no-instant') ||
                link.classList.contains('btn-logout')) {
                return;
            }

            // Create prefetch link
            const prefetchLink = document.createElement('link');
            prefetchLink.rel = 'prefetch';
            prefetchLink.href = href;
            prefetchLink.as = 'document';
            document.head.appendChild(prefetchLink);

            this.prefetchedUrls.add(href);
            console.log('🚀 Prefetched:', href);
        }, { passive: true });
    }

    // NEW: Cleanup serial connections and intervals before page unload
    setupPageCleanup() {
        window.addEventListener('beforeunload', () => {
            // Cleanup any active serial connections
            if (window.autoSerialConnector) {
                try {
                    window.autoSerialConnector.forceCleanup();
                } catch (e) {
                    console.warn('Cleanup error:', e);
                }
            }

            // Clear all intervals
            const highestId = window.setTimeout(() => { }, 0);
            for (let i = 0; i < highestId; i++) {
                window.clearTimeout(i);
                window.clearInterval(i);
            }
        });
    }

    monitorPerformance() {
        // Monitor LCP
        if ('PerformanceObserver' in window) {
            const lcpObserver = new PerformanceObserver((entryList) => {
                const entries = entryList.getEntries();
                const lastEntry = entries[entries.length - 1];
                // LCP monitoring - console log disabled in production

                // Log to analytics if needed
                if (window.gtag) {
                    gtag('event', 'LCP', {
                        event_category: 'Web Vitals',
                        value: Math.round(lastEntry.startTime)
                    });
                }
            });

            lcpObserver.observe({ entryTypes: ['largest-contentful-paint'] });
            this.observers.push(lcpObserver);
        }

        // Monitor FID
        if ('PerformanceObserver' in window) {
            const fidObserver = new PerformanceObserver((entryList) => {
                for (const entry of entryList.getEntries()) {
                    // FID monitoring - console log disabled in production
                }
            });

            fidObserver.observe({ entryTypes: ['first-input'] });
            this.observers.push(fidObserver);
        }
    }

    optimizeImages() {
        // Lazy loading for images
        const images = document.querySelectorAll('img[data-src]');

        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        observer.unobserve(img);
                    }
                });
            });

            images.forEach(img => imageObserver.observe(img));
        } else {
            // Fallback for older browsers
            images.forEach(img => {
                img.src = img.dataset.src;
            });
        }
    }

    debounceEvents() {
        let scrollTimeout;
        let resizeTimeout;

        // Debounce scroll
        window.addEventListener('scroll', () => {
            if (scrollTimeout) {
                clearTimeout(scrollTimeout);
            }
            scrollTimeout = setTimeout(() => {
                // Handle scroll events
                window.dispatchEvent(new CustomEvent('scrollEnd'));
            }, 100);
        });

        // Debounce resize
        window.addEventListener('resize', () => {
            if (resizeTimeout) {
                clearTimeout(resizeTimeout);
            }
            resizeTimeout = setTimeout(() => {
                // Handle resize events
                window.dispatchEvent(new CustomEvent('resizeEnd'));
            }, 250);
        });
    }

    optimizeAnimations() {
        // Reduce animations on low-end devices
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.style.setProperty('--animation-duration', '0.01ms');
        }

        // Use requestAnimationFrame for smooth animations
        window.requestAnimFrame = (function () {
            return window.requestAnimationFrame ||
                window.webkitRequestAnimationFrame ||
                window.mozRequestAnimationFrame ||
                function (callback) {
                    window.setTimeout(callback, 1000 / 60);
                };
        })();
    }

    // Preload critical resources
    preloadCriticalResources() {
        const criticalResources = [
            '/assets/css/critical.css',
            '/assets/fonts/main-font.woff2'
        ];

        criticalResources.forEach(resource => {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.href = resource;

            if (resource.endsWith('.css')) {
                link.as = 'style';
            } else if (resource.endsWith('.woff2')) {
                link.as = 'font';
                link.type = 'font/woff2';
                link.crossOrigin = 'anonymous';
            }

            document.head.appendChild(link);
        });
    }

    // Optimize DataTables loading
    optimizeDataTables() {
        // Load DataTables only when needed
        const dataTablesElements = document.querySelectorAll('.datatable');

        if (dataTablesElements.length > 0) {
            // Load DataTables dynamically
            this.loadScript('https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js', () => {
                this.loadScript('https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js', () => {
                    // Initialize DataTables
                    dataTablesElements.forEach(table => {
                        if ($.fn.DataTable) {
                            $(table).DataTable({
                                pageLength: 10,
                                responsive: true,
                                language: {
                                    url: window.location.origin + '/jembatantimbangan/assets/id.json'
                                }
                            });
                        }
                    });
                });
            });
        }
    }

    // Dynamic script loader
    loadScript(src, callback) {
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = callback;
        document.head.appendChild(script);
    }

    // Cleanup observers
    destroy() {
        this.observers.forEach(observer => observer.disconnect());
        this.observers = [];
        this.initialized = false;
    }
}

// Initialize performance optimizer
const performanceOptimizer = new PerformanceOptimizer();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => performanceOptimizer.init());
} else {
    performanceOptimizer.init();
}

// Export for global access
window.PerformanceOptimizer = PerformanceOptimizer;
window.performanceOptimizer = performanceOptimizer;