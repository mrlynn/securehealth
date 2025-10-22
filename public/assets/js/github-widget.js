/**
 * SecureHealth GitHub Engagement Widget
 * A lightweight widget to encourage GitHub interactions
 */
class SecureHealthGitHubWidget {
    constructor(options = {}) {
        this.options = {
            repoOwner: options.repoOwner || 'mrlynn',
            repoName: options.repoName || 'securehealth',
            container: options.container || 'body',
            theme: options.theme || 'light', // 'light' or 'dark'
            showStats: options.showStats !== false,
            showButtons: options.showButtons !== false,
            ...options
        };
        
        this.init();
    }
    
    init() {
        this.createWidget();
        if (this.options.showStats) {
            this.loadStats();
        }
        this.setupInteractions();
    }
    
    createWidget() {
        const container = typeof this.options.container === 'string' 
            ? document.querySelector(this.options.container)
            : this.options.container;
            
        if (!container) {
            console.error('SecureHealth GitHub Widget: Container not found');
            return;
        }
        
        const widget = document.createElement('div');
        widget.className = `securehealth-github-widget ${this.options.theme}`;
        widget.innerHTML = this.getWidgetHTML();
        
        container.appendChild(widget);
        this.widget = widget;
    }
    
    getWidgetHTML() {
        const themeClass = this.options.theme === 'dark' ? 'dark-theme' : '';
        const statsHTML = this.options.showStats ? this.getStatsHTML() : '';
        const buttonsHTML = this.options.showButtons ? this.getButtonsHTML() : '';
        
        return `
            <div class="github-widget-container ${themeClass}">
                <div class="github-widget-header">
                    <h4>
                        <i class="fab fa-github"></i>
                        Support SecureHealth
                    </h4>
                    <p>Help us grow the community!</p>
                </div>
                
                ${statsHTML}
                ${buttonsHTML}
                
                <div class="github-widget-footer">
                    <small>
                        <i class="fas fa-heart"></i>
                        Made with love for healthcare security
                    </small>
                </div>
            </div>
            
            <style>
                .securehealth-github-widget {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    margin: 1rem 0;
                }
                
                .github-widget-container {
                    background: #fff;
                    border-radius: 8px;
                    padding: 1.5rem;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    border: 1px solid #e1e4e8;
                }
                
                .github-widget-container.dark-theme {
                    background: #24292e;
                    color: #fff;
                    border-color: #444;
                }
                
                .github-widget-header h4 {
                    margin: 0 0 0.5rem 0;
                    font-size: 1.1rem;
                    font-weight: 600;
                }
                
                .github-widget-header p {
                    margin: 0 0 1rem 0;
                    color: #666;
                    font-size: 0.9rem;
                }
                
                .dark-theme .github-widget-header p {
                    color: #ccc;
                }
                
                .github-stats-row {
                    display: flex;
                    gap: 1rem;
                    margin-bottom: 1rem;
                }
                
                .github-stat {
                    text-align: center;
                    flex: 1;
                }
                
                .github-stat-number {
                    font-size: 1.5rem;
                    font-weight: 700;
                    color: #00ED64;
                    display: block;
                }
                
                .github-stat-label {
                    font-size: 0.8rem;
                    color: #666;
                    margin-top: 0.25rem;
                }
                
                .dark-theme .github-stat-label {
                    color: #ccc;
                }
                
                .github-buttons-row {
                    display: flex;
                    gap: 0.5rem;
                    margin-bottom: 1rem;
                }
                
                .github-btn {
                    flex: 1;
                    padding: 0.5rem 0.75rem;
                    border: none;
                    border-radius: 6px;
                    font-size: 0.85rem;
                    font-weight: 500;
                    text-decoration: none;
                    text-align: center;
                    transition: all 0.2s ease;
                    cursor: pointer;
                }
                
                .github-btn-star {
                    background: #ffc107;
                    color: #000;
                }
                
                .github-btn-star:hover {
                    background: #e0a800;
                    color: #000;
                }
                
                .github-btn-fork {
                    background: #28a745;
                    color: #fff;
                }
                
                .github-btn-fork:hover {
                    background: #218838;
                    color: #fff;
                }
                
                .github-btn-watch {
                    background: #6c757d;
                    color: #fff;
                }
                
                .github-btn-watch:hover {
                    background: #5a6268;
                    color: #fff;
                }
                
                .github-widget-footer {
                    text-align: center;
                    margin-top: 1rem;
                    padding-top: 1rem;
                    border-top: 1px solid #e1e4e8;
                }
                
                .dark-theme .github-widget-footer {
                    border-top-color: #444;
                }
                
                .github-widget-footer small {
                    color: #666;
                    font-size: 0.8rem;
                }
                
                .dark-theme .github-widget-footer small {
                    color: #ccc;
                }
                
                .github-widget-footer i {
                    color: #ff6b6b;
                    margin-right: 0.25rem;
                }
                
                @media (max-width: 480px) {
                    .github-stats-row,
                    .github-buttons-row {
                        flex-direction: column;
                    }
                }
            </style>
        `;
    }
    
    getStatsHTML() {
        return `
            <div class="github-stats-row">
                <div class="github-stat">
                    <span class="github-stat-number" id="widget-star-count">-</span>
                    <div class="github-stat-label">Stars</div>
                </div>
                <div class="github-stat">
                    <span class="github-stat-number" id="widget-fork-count">-</span>
                    <div class="github-stat-label">Forks</div>
                </div>
                <div class="github-stat">
                    <span class="github-stat-number" id="widget-watch-count">-</span>
                    <div class="github-stat-label">Watchers</div>
                </div>
            </div>
        `;
    }
    
    getButtonsHTML() {
        const repoUrl = `https://github.com/${this.options.repoOwner}/${this.options.repoName}`;
        
        return `
            <div class="github-buttons-row">
                <a href="${repoUrl}" target="_blank" class="github-btn github-btn-star" data-action="star">
                    <i class="fas fa-star"></i> Star
                </a>
                <a href="${repoUrl}/fork" target="_blank" class="github-btn github-btn-fork" data-action="fork">
                    <i class="fas fa-code-branch"></i> Fork
                </a>
                <a href="${repoUrl}/subscription" target="_blank" class="github-btn github-btn-watch" data-action="watch">
                    <i class="fas fa-eye"></i> Watch
                </a>
            </div>
        `;
    }
    
    async loadStats() {
        try {
            const response = await fetch(`https://api.github.com/repos/${this.options.repoOwner}/${this.options.repoName}`);
            if (response.ok) {
                const data = await response.json();
                
                const starCount = this.widget.querySelector('#widget-star-count');
                const forkCount = this.widget.querySelector('#widget-fork-count');
                const watchCount = this.widget.querySelector('#widget-watch-count');
                
                if (starCount) starCount.textContent = data.stargazers_count || 0;
                if (forkCount) forkCount.textContent = data.forks_count || 0;
                if (watchCount) watchCount.textContent = data.watchers_count || 0;
                
                this.animateStats();
            } else {
                this.showFallbackStats();
            }
        } catch (error) {
            console.log('Error fetching GitHub stats:', error);
            this.showFallbackStats();
        }
    }
    
    showFallbackStats() {
        const starCount = this.widget.querySelector('#widget-star-count');
        const forkCount = this.widget.querySelector('#widget-fork-count');
        const watchCount = this.widget.querySelector('#widget-watch-count');
        
        if (starCount) starCount.textContent = '⭐';
        if (forkCount) forkCount.textContent = '🍴';
        if (watchCount) watchCount.textContent = '👀';
    }
    
    animateStats() {
        const statNumbers = this.widget.querySelectorAll('.github-stat-number');
        statNumbers.forEach((stat, index) => {
            stat.style.opacity = '0';
            stat.style.transform = 'translateY(10px)';
            
            setTimeout(() => {
                stat.style.transition = 'all 0.3s ease';
                stat.style.opacity = '1';
                stat.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }
    
    setupInteractions() {
        const buttons = this.widget.querySelectorAll('.github-btn');
        buttons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const action = btn.getAttribute('data-action');
                this.trackInteraction(action);
                this.showInteractionMessage(action);
            });
        });
    }
    
    trackInteraction(action) {
        // Analytics tracking
        if (typeof gtag !== 'undefined') {
            gtag('event', 'github_widget_interaction', {
                'event_category': 'engagement',
                'event_label': action,
                'value': 1
            });
        }
        
        // Custom event for parent page
        const event = new CustomEvent('securehealth-github-interaction', {
            detail: { action, timestamp: Date.now() }
        });
        document.dispatchEvent(event);
    }
    
    showInteractionMessage(action) {
        const messages = {
            'star': '🌟 Thank you for starring! Your support means the world!',
            'fork': '🍴 Awesome! Forking helps us grow the community!',
            'watch': '👀 Thanks for watching! Stay updated with our progress!'
        };
        
        const message = messages[action] || 'Thank you for your support!';
        
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 1rem;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 9999;
            max-width: 300px;
            font-size: 0.9rem;
        `;
        notification.innerHTML = `
            <i class="fas fa-heart" style="margin-right: 0.5rem;"></i>${message}
            <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; float: right; cursor: pointer;">×</button>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 4000);
    }
}

// Auto-initialize if data attributes are present
document.addEventListener('DOMContentLoaded', function() {
    const widgets = document.querySelectorAll('[data-securehealth-github-widget]');
    widgets.forEach(element => {
        const options = {
            repoOwner: element.dataset.repoOwner || 'mrlynn',
            repoName: element.dataset.repoName || 'securehealth',
            container: element,
            theme: element.dataset.theme || 'light',
            showStats: element.dataset.showStats !== 'false',
            showButtons: element.dataset.showButtons !== 'false'
        };
        
        new SecureHealthGitHubWidget(options);
    });
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SecureHealthGitHubWidget;
}
