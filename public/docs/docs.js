/**
 * Simple JavaScript-based markdown documentation browser
 * This script allows browsing the markdown files without requiring PHP or MongoDB
 */

// Configuration
const docsBasePath = '/docs';

// Main function when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Get container elements
    const sidebarContainer = document.getElementById('sidebar');
    const contentContainer = document.getElementById('content');
    const categoryContainer = document.getElementById('category-title');
    const breadcrumbContainer = document.getElementById('breadcrumb');
    
    // Parse URL to determine what to show
    const urlPath = window.location.pathname;
    const pathParts = urlPath.replace(docsBasePath, '').split('/').filter(part => part.length > 0);
    
    // Load page based on URL structure
    if (pathParts.length === 0) {
        // Main index page
        loadIndexPage();
    } else if (pathParts.length === 1) {
        // Category index page
        loadCategoryPage(pathParts[0]);
    } else if (pathParts.length === 2) {
        // Specific page within category
        loadDocPage(pathParts[0], pathParts[1]);
    }

    // Helper function to fetch and render markdown
    function fetchMarkdown(url) {
        return fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Failed to load markdown file: ${response.status}`);
                }
                return response.text();
            })
            .then(markdown => {
                return marked.parse(markdown);
            });
    }
    
    // Load main index page
    function loadIndexPage() {
        updateBreadcrumb([]);
        
        // Fetch and render main index.md
        fetchMarkdown(`${docsBasePath}/index.md`)
            .then(html => {
                contentContainer.innerHTML = html;
                categoryContainer.textContent = 'Documentation';
                
                // Load categories for sidebar
                return fetch(`${docsBasePath}/categories.json`)
                    .catch(error => {
                        console.error('Failed to load categories.json, using fallback', error);
                        // Fallback categories if JSON not available
                        return Promise.resolve({
                            json: () => Promise.resolve([
                                { name: 'Getting Started', path: 'getting-started' },
                                { name: 'API Reference', path: 'api' },
                                { name: 'Encryption', path: 'encryption' },
                                { name: 'Security', path: 'security' },
                                { name: 'Help', path: 'help' }
                            ])
                        });
                    })
                    .then(response => response.json())
                    .then(categories => {
                        renderCategorySidebar(categories);
                        renderCategoryGrid(categories);
                    });
            })
            .catch(error => {
                console.error('Error loading index page:', error);
                contentContainer.innerHTML = '<h1>Documentation</h1><p>Welcome to SecureHealth documentation.</p><p>Please select a category from the sidebar.</p>';
            });
    }
    
    // Load category index page
    function loadCategoryPage(category) {
        updateBreadcrumb([{ name: formatCategoryName(category), path: category }]);
        
        // Fetch and render category index.md
        fetchMarkdown(`${docsBasePath}/${category}/index.md`)
            .then(html => {
                contentContainer.innerHTML = html;
                categoryContainer.textContent = formatCategoryName(category);
                
                // Load navigation for this category
                loadCategoryNavigation(category);
            })
            .catch(error => {
                console.error(`Error loading category page for ${category}:`, error);
                contentContainer.innerHTML = `<h1>${formatCategoryName(category)}</h1><p>No content available for this category.</p>`;
            });
    }
    
    // Load specific doc page
    function loadDocPage(category, page) {
        updateBreadcrumb([
            { name: formatCategoryName(category), path: category },
            { name: formatPageName(page), path: `${category}/${page}` }
        ]);
        
        // Fetch and render specific markdown page
        fetchMarkdown(`${docsBasePath}/${category}/${page}.md`)
            .then(html => {
                contentContainer.innerHTML = html;
                categoryContainer.textContent = `${formatCategoryName(category)} - ${formatPageName(page)}`;
                
                // Load navigation for this category and highlight current page
                loadCategoryNavigation(category, page);
            })
            .catch(error => {
                console.error(`Error loading doc page for ${category}/${page}:`, error);
                contentContainer.innerHTML = `<h1>${formatPageName(page)}</h1><p>The requested page could not be found.</p>`;
            });
    }
    
    // Load navigation for a category
    function loadCategoryNavigation(category, currentPage = null) {
        // Try to load pages.json for the category
        fetch(`${docsBasePath}/${category}/pages.json`)
            .catch(error => {
                console.error(`No pages.json for ${category}, scanning directory`);
                return scanCategoryPages(category);
            })
            .then(response => response.json())
            .then(pages => {
                renderPageSidebar(category, pages, currentPage);
                
                // If we're on a specific page, add prev/next navigation
                if (currentPage) {
                    renderPagination(category, pages, currentPage);
                }
            })
            .catch(error => {
                console.error(`Error loading navigation for ${category}:`, error);
                sidebarContainer.innerHTML = `<ul><li><a href="${docsBasePath}/${category}">Back to ${formatCategoryName(category)}</a></li></ul>`;
            });
    }
    
    // Helper to scan available pages (when pages.json is not available)
    function scanCategoryPages(category) {
        // This is a fallback that doesn't actually scan directories (not possible in client-side JS)
        // Instead it returns a reasonable default based on common page names
        const fallbackPages = [
            { title: "Overview", path: "index" },
            { title: "Introduction", path: "introduction" },
            { title: "Installation", path: "installation" },
            { title: "Quick Start", path: "quick-start" },
            { title: "Examples", path: "examples" },
            { title: "FAQ", path: "faq" }
        ];
        
        return Promise.resolve({ json: () => Promise.resolve(fallbackPages) });
    }
    
    // Render sidebar with categories
    function renderCategorySidebar(categories) {
        let html = '<ul class="category-list">';
        categories.forEach(category => {
            html += `<li><a href="${docsBasePath}/${category.path}">${category.name}</a></li>`;
        });
        html += '</ul>';
        sidebarContainer.innerHTML = html;
    }
    
    // Render sidebar with pages for a category
    function renderPageSidebar(category, pages, currentPage = null) {
        let html = `<div class="sidebar-header">
            <h3>${formatCategoryName(category)}</h3>
            <a href="${docsBasePath}" class="back-link">← Back to categories</a>
        </div>
        <ul class="page-list">`;
        
        pages.forEach(page => {
            const isActive = page.path === currentPage;
            html += `<li${isActive ? ' class="active"' : ''}>
                <a href="${docsBasePath}/${category}/${page.path}">${page.title}</a>
            </li>`;
        });
        
        html += '</ul>';
        sidebarContainer.innerHTML = html;
    }
    
    // Render grid of category cards on index page
    function renderCategoryGrid(categories) {
        let html = '<div class="category-grid">';
        
        categories.forEach(category => {
            html += `<div class="category-card">
                <h3>${category.name}</h3>
                <p>Browse the ${category.name} documentation</p>
                <a href="${docsBasePath}/${category.path}" class="btn">View Documentation</a>
            </div>`;
        });
        
        html += '</div>';
        
        // Append the grid after the main content
        const gridContainer = document.createElement('div');
        gridContainer.className = 'grid-container';
        gridContainer.innerHTML = html;
        contentContainer.parentNode.insertBefore(gridContainer, contentContainer.nextSibling);
    }
    
    // Render prev/next pagination for doc pages
    function renderPagination(category, pages, currentPage) {
        // Find current page index
        const currentIndex = pages.findIndex(p => p.path === currentPage);
        if (currentIndex === -1) return;
        
        const prevPage = currentIndex > 0 ? pages[currentIndex - 1] : null;
        const nextPage = currentIndex < pages.length - 1 ? pages[currentIndex + 1] : null;
        
        let html = '<div class="pagination">';
        
        if (prevPage) {
            html += `<a href="${docsBasePath}/${category}/${prevPage.path}" class="prev">← ${prevPage.title}</a>`;
        } else {
            html += '<span class="prev"></span>';
        }
        
        if (nextPage) {
            html += `<a href="${docsBasePath}/${category}/${nextPage.path}" class="next">${nextPage.title} →</a>`;
        } else {
            html += '<span class="next"></span>';
        }
        
        html += '</div>';
        
        // Add pagination after content
        const paginationContainer = document.createElement('div');
        paginationContainer.className = 'pagination-container';
        paginationContainer.innerHTML = html;
        contentContainer.appendChild(paginationContainer);
    }
    
    // Update breadcrumb navigation
    function updateBreadcrumb(items) {
        let html = `<a href="${docsBasePath}">Home</a>`;
        
        items.forEach(item => {
            html += ` / <a href="${docsBasePath}/${item.path}">${item.name}</a>`;
        });
        
        breadcrumbContainer.innerHTML = html;
    }
    
    // Format category name for display
    function formatCategoryName(category) {
        return category
            .split('-')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    }
    
    // Format page name for display
    function formatPageName(page) {
        if (page === 'index') return 'Overview';
        
        return page
            .split('-')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    }
});