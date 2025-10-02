/**
 * Documentation System JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Simple client-side search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                const query = searchInput.value.trim().toLowerCase();
                if (query) {
                    searchDocumentation(query);
                }
            }
        });
    }
    
    // Handle code syntax highlighting
    highlightCodeBlocks();
    
    // Add anchor links to headings
    addHeadingAnchors();
});

/**
 * Search documentation (simple implementation)
 * In a real application, this would connect to a backend search API
 */
function searchDocumentation(query) {
    // For now, just redirect to documentation index with query parameter
    window.location.href = '/docs?q=' + encodeURIComponent(query);
}

/**
 * Add syntax highlighting to code blocks
 * This is a placeholder - in a real application you would use a library like highlight.js or Prism
 */
function highlightCodeBlocks() {
    const codeBlocks = document.querySelectorAll('pre code');
    
    if (codeBlocks.length > 0) {
        // If highlight.js is loaded
        if (typeof hljs !== 'undefined') {
            codeBlocks.forEach(block => {
                hljs.highlightBlock(block);
            });
        } else {
            // Basic styling for code blocks if no syntax highlighter is available
            codeBlocks.forEach(block => {
                block.classList.add('code-block');
            });
        }
    }
}

/**
 * Add anchor links to headings for easier reference
 */
function addHeadingAnchors() {
    const content = document.querySelector('.content');
    if (!content) return;
    
    const headings = content.querySelectorAll('h2, h3, h4, h5, h6');
    headings.forEach(heading => {
        // Create an ID from the heading text
        if (!heading.id) {
            const id = heading.textContent
                .toLowerCase()
                .replace(/[^\w]+/g, '-');
            heading.id = id;
        }
        
        // Add anchor link
        const anchor = document.createElement('a');
        anchor.className = 'heading-anchor';
        anchor.href = `#${heading.id}`;
        anchor.innerHTML = '#';
        anchor.title = 'Link to this section';
        
        heading.appendChild(anchor);
    });
}

/**
 * Handle responsive navigation
 */
function toggleMobileNavigation() {
    const sidebar = document.querySelector('.docs-sidebar');
    if (sidebar) {
        sidebar.classList.toggle('mobile-visible');
    }
}