console.log('Debug script loaded!');

// Add a basic debug panel
function addDebugPanel() {
    const debugPanel = document.createElement('div');
    debugPanel.style.position = 'fixed';
    debugPanel.style.bottom = '10px';
    debugPanel.style.right = '10px';
    debugPanel.style.backgroundColor = '#f0f0f0';
    debugPanel.style.border = '1px solid #ccc';
    debugPanel.style.padding = '10px';
    debugPanel.style.maxWidth = '400px';
    debugPanel.style.maxHeight = '300px';
    debugPanel.style.overflow = 'auto';
    debugPanel.style.zIndex = '9999';
    debugPanel.style.fontSize = '12px';
    debugPanel.style.fontFamily = 'monospace';
    debugPanel.id = 'debug-panel';
    
    // Add a title
    const title = document.createElement('h3');
    title.textContent = 'Debug Info';
    title.style.margin = '0 0 10px 0';
    debugPanel.appendChild(title);
    
    // Add URL info
    const urlInfo = document.createElement('div');
    urlInfo.innerHTML = `<strong>URL Path:</strong> ${window.location.pathname}<br>`;
    urlInfo.innerHTML += `<strong>URL:</strong> ${window.location.href}<br>`;
    debugPanel.appendChild(urlInfo);
    
    // Add a section for fetch operations
    const fetchSection = document.createElement('div');
    fetchSection.innerHTML = '<strong>Fetch Operations:</strong><br>';
    fetchSection.id = 'fetch-operations';
    debugPanel.appendChild(fetchSection);
    
    // Add a close button
    const closeBtn = document.createElement('button');
    closeBtn.textContent = 'Close';
    closeBtn.style.marginTop = '10px';
    closeBtn.onclick = function() {
        debugPanel.style.display = 'none';
    };
    debugPanel.appendChild(closeBtn);
    
    document.body.appendChild(debugPanel);
}

// Override fetch to log operations
const originalFetch = window.fetch;
window.fetch = function(url, options) {
    console.log(`Fetch: ${url}`, options);
    
    const fetchSection = document.getElementById('fetch-operations');
    if (fetchSection) {
        const fetchItem = document.createElement('div');
        fetchItem.textContent = `→ ${url}`;
        fetchItem.style.marginBottom = '5px';
        fetchSection.appendChild(fetchItem);
    }
    
    return originalFetch(url, options)
        .then(response => {
            console.log(`Response for ${url}:`, response);
            
            if (fetchSection) {
                const lastItem = fetchSection.lastChild;
                if (lastItem) {
                    lastItem.innerHTML += ` - ${response.status} ${response.statusText}`;
                    lastItem.style.color = response.ok ? 'green' : 'red';
                }
            }
            
            return response;
        })
        .catch(error => {
            console.error(`Error for ${url}:`, error);
            
            if (fetchSection) {
                const lastItem = fetchSection.lastChild;
                if (lastItem) {
                    lastItem.innerHTML += ` - ERROR: ${error.message}`;
                    lastItem.style.color = 'red';
                }
            }
            
            throw error;
        });
};

// Execute when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded event fired');
    addDebugPanel();
    
    // Log all DOM elements with IDs
    console.log('Elements with IDs:');
    const elements = document.querySelectorAll('[id]');
    elements.forEach(el => {
        console.log(`#${el.id}:`, el);
    });
});