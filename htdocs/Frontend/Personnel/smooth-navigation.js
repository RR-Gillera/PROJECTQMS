// Smooth Navigation Enhancement for SeQueueR

class SmoothNavigation {
    constructor() {
        this.cache = new Map();
        this.preloadQueue = new Set();
        this.init();
    }

    init() {
        this.setupPageTransitions();
        this.setupPreloading();
        this.setupScrollOptimizations();
        this.setupPerformanceMonitoring();
    }

    setupPageTransitions() {
        // Add page transition class to body
        document.body.classList.add('page-transition');
        
        // Trigger loaded state after DOM is ready
        requestAnimationFrame(() => {
            document.body.classList.add('loaded');
        });

        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this.optimizeAnimations();
            }
        });
    }

    setupPreloading() {
        // Preload navigation links on hover
        document.addEventListener('mouseover', (e) => {
            const link = e.target.closest('a[href]');
            if (link && this.isInternalLink(link.href)) {
                this.preloadPage(link.href);
            }
        });

        // Preload critical pages immediately
        this.preloadCriticalPages();
    }

    setupScrollOptimizations() {
        let ticking = false;
        
        const optimizeScroll = () => {
            // Throttle scroll events for better performance
            if (!ticking) {
                requestAnimationFrame(() => {
                    this.handleScroll();
                    ticking = false;
                });
                ticking = true;
            }
        };

        window.addEventListener('scroll', optimizeScroll, { passive: true });
    }

    setupPerformanceMonitoring() {
        // Monitor performance and adjust animations accordingly
        if ('performance' in window) {
            const observer = new PerformanceObserver((list) => {
                for (const entry of list.getEntries()) {
                    if (entry.entryType === 'navigation') {
                        this.optimizeForPerformance(entry);
                    }
                }
            });
            
            observer.observe({ entryTypes: ['navigation'] });
        }
    }

    preloadPage(url) {
        if (this.cache.has(url) || this.preloadQueue.has(url)) {
            return;
        }

        this.preloadQueue.add(url);

        // Create prefetch link
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        link.onload = () => {
            this.cache.set(url, true);
            this.preloadQueue.delete(url);
        };
        
        document.head.appendChild(link);
    }

    preloadCriticalPages() {
        // Preload critical navigation pages
        const criticalPages = [
            'Dashboard.php',
            'Queue.php',
            'History.php',
            'User.php'
        ];

        criticalPages.forEach(page => {
            this.preloadPage(page);
        });
    }

    isInternalLink(url) {
        try {
            const linkUrl = new URL(url, window.location.origin);
            return linkUrl.origin === window.location.origin;
        } catch {
            return false;
        }
    }

    handleScroll() {
        // Add scroll-based optimizations
        const scrollY = window.scrollY;
        
        // Add/remove classes based on scroll position
        document.body.classList.toggle('scrolled', scrollY > 10);
    }

    optimizeAnimations() {
        // Reduce animations on slower devices
        if (this.isSlowDevice()) {
            document.body.classList.add('reduce-motion');
        }
    }

    isSlowDevice() {
        // Simple heuristic to detect slower devices
        return navigator.hardwareConcurrency <= 2 || 
               navigator.deviceMemory <= 4 ||
               /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }

    optimizeForPerformance(navigationEntry) {
        // Adjust animations based on page load performance
        const loadTime = navigationEntry.loadEventEnd - navigationEntry.loadEventStart;
        
        if (loadTime > 1000) {
            document.body.classList.add('slow-load');
        }
    }

    // Public method to add loading state to elements
    addLoadingState(element) {
        element.classList.add('loading');
        
        const originalContent = element.innerHTML;
        element.innerHTML = '<div class="loading-spinner"></div>';
        
        return () => {
            element.innerHTML = originalContent;
            element.classList.remove('loading');
        };
    }

    // Public method to create smooth transitions
    createTransition(element, options = {}) {
        const defaults = {
            duration: 300,
            easing: 'ease-in-out',
            properties: ['opacity', 'transform']
        };
        
        const config = { ...defaults, ...options };
        
        element.style.transition = config.properties
            .map(prop => `${prop} ${config.duration}ms ${config.easing}`)
            .join(', ');
    }
}

// Initialize smooth navigation when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.smoothNav = new SmoothNavigation();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SmoothNavigation;
}
