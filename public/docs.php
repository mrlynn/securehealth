<?php
/**
 * Standalone documentation viewer that doesn't depend on Symfony or MongoDB
 * This file serves as a simple documentation browser using direct file access
 */

// Configuration
$docsDir = __DIR__ . '/docs';
$baseUrl = '/docs.php';

// Helper functions
function renderMarkdown($content) {
    // Simple markdown parser for headers, links, and code blocks
    // This is a very basic implementation - for production use a proper Markdown library
    
    // Process headers
    $content = preg_replace('/^# (.*)$/m', '<h1>$1</h1>', $content);
    $content = preg_replace('/^## (.*)$/m', '<h2>$1</h2>', $content);
    $content = preg_replace('/^### (.*)$/m', '<h3>$1</h3>', $content);
    $content = preg_replace('/^#### (.*)$/m', '<h4>$1</h4>', $content);
    
    // Process links
    $content = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $content);
    
    // Process code blocks
    $content = preg_replace_callback('/```(.+?)```/s', function($matches) {
        return '<pre><code>' . htmlspecialchars($matches[1]) . '</code></pre>';
    }, $content);
    
    // Process inline code
    $content = preg_replace('/`([^`]+)`/', '<code>$1</code>', $content);
    
    // Process paragraphs
    $content = preg_replace('/^(?!<h|<pre|<ul|<ol|<p|$)(.+)$/m', '<p>$1</p>', $content);
    
    return $content;
}

function getCategories($docsDir) {
    $categories = [];
    
    if (is_dir($docsDir)) {
        $dir = new DirectoryIterator($docsDir);
        foreach ($dir as $fileinfo) {
            if ($fileinfo->isDir() && !$fileinfo->isDot()) {
                $categories[] = [
                    'name' => ucfirst($fileinfo->getFilename()),
                    'path' => $fileinfo->getFilename()
                ];
            }
        }
    }
    
    return $categories;
}

function getNavigation($docsDir, $category) {
    $navigation = [];
    $categoryDir = $docsDir . '/' . $category;
    
    if (is_dir($categoryDir)) {
        $files = glob($categoryDir . '/*.md');
        
        foreach ($files as $file) {
            $filename = basename($file, '.md');
            
            // Get first line for title
            $firstLine = '';
            $handle = fopen($file, 'r');
            if ($handle) {
                $firstLine = fgets($handle);
                fclose($handle);
            }
            
            $title = '';
            if (strpos($firstLine, '# ') === 0) {
                $title = trim(substr($firstLine, 2));
            } else {
                $title = ucfirst($filename);
            }
            
            $navigation[] = [
                'title' => $title,
                'path' => $filename,
                'is_index' => ($filename === 'index')
            ];
        }
        
        // Sort with index first
        usort($navigation, function($a, $b) {
            if ($a['is_index'] && !$b['is_index']) return -1;
            if (!$a['is_index'] && $b['is_index']) return 1;
            return strcmp($a['title'], $b['title']);
        });
    }
    
    return $navigation;
}

// Process request
$path = $_SERVER['PATH_INFO'] ?? '';
$pathParts = explode('/', trim($path, '/'));

// CSS for basic styling
$css = <<<CSS
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif;
    line-height: 1.6;
    color: #333;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0;
    background-color: #f5f5f7;
}

header {
    background-color: #2d3748;
    color: white;
    padding: 1rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

header h1 {
    margin: 0;
    font-size: 1.5rem;
}

header nav a {
    color: white;
    margin-left: 1rem;
    text-decoration: none;
}

.container {
    display: flex;
    min-height: calc(100vh - 60px);
    background-color: white;
}

.sidebar {
    width: 250px;
    padding: 2rem 1rem;
    background-color: #f8f9fa;
    border-right: 1px solid #e9ecef;
}

.sidebar h3 {
    margin-top: 0;
    margin-bottom: 1rem;
}

.sidebar ul {
    list-style-type: none;
    padding: 0;
    margin: 0;
}

.sidebar li {
    margin-bottom: 0.5rem;
}

.sidebar li.active a {
    font-weight: bold;
    color: #4299e1;
}

.sidebar a {
    text-decoration: none;
    color: #4a5568;
}

.sidebar a:hover {
    text-decoration: underline;
}

.main-content {
    flex: 1;
    padding: 2rem;
}

.back-link {
    display: block;
    margin-bottom: 1rem;
    color: #4a5568;
    text-decoration: none;
}

.back-link:hover {
    text-decoration: underline;
}

.category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.category-card {
    border: 1px solid #e9ecef;
    border-radius: 0.5rem;
    padding: 1.5rem;
    background-color: #f8f9fa;
}

.category-card h2 {
    margin-top: 0;
    color: #2d3748;
}

.btn {
    display: inline-block;
    padding: 0.5rem 1rem;
    background-color: #4299e1;
    color: white;
    text-decoration: none;
    border-radius: 0.25rem;
    margin-top: 1rem;
}

.btn:hover {
    background-color: #3182ce;
}

code {
    background-color: #f0f0f0;
    padding: 0.2rem 0.4rem;
    border-radius: 3px;
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 0.9em;
}

pre {
    background-color: #f6f8fa;
    border-radius: 6px;
    padding: 1rem;
    overflow: auto;
}

pre code {
    background-color: transparent;
    padding: 0;
}

.content img {
    max-width: 100%;
    height: auto;
}

.content table {
    border-collapse: collapse;
    width: 100%;
    margin: 1rem 0;
}

.content th, .content td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}

.content th {
    background-color: #f2f2f2;
}

.content tr:nth-child(even) {
    background-color: #f9f9f9;
}

@media (max-width: 768px) {
    .container {
        flex-direction: column;
    }
    
    .sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #e9ecef;
        padding: 1rem;
    }
}
CSS;

// HTML template start
echo '<!DOCTYPE html>';
echo '<html lang="en">';
echo '<head>';
echo '    <meta charset="UTF-8">';
echo '    <meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '    <title>SecureHealth Documentation</title>';
echo '    <style>' . $css . '</style>';
echo '</head>';
echo '<body>';

// Header
echo '<header>';
echo '    <h1>SecureHealth Documentation</h1>';
echo '    <nav>';
echo '        <a href="/docs.php">Home</a>';
echo '        <a href="/">Main App</a>';
echo '    </nav>';
echo '</header>';

echo '<div class="container">';

if (empty($pathParts[0])) {
    // Home page - show categories
    $categories = getCategories($docsDir);
    
    // Sidebar with categories
    echo '<div class="sidebar">';
    echo '    <h3>Categories</h3>';
    echo '    <ul>';
    foreach ($categories as $category) {
        echo '    <li><a href="' . $baseUrl . '/' . $category['path'] . '">' . $category['name'] . '</a></li>';
    }
    echo '    </ul>';
    echo '</div>';
    
    echo '<div class="main-content">';
    
    // Main content - render index.md
    $indexFile = $docsDir . '/index.md';
    if (file_exists($indexFile)) {
        $content = file_get_contents($indexFile);
        echo renderMarkdown($content);
    } else {
        echo '<h1>Welcome to SecureHealth Documentation</h1>';
        echo '<p>Select a category from the sidebar to begin.</p>';
    }
    
    // Display category cards
    echo '<div class="category-grid">';
    foreach ($categories as $category) {
        echo '<div class="category-card">';
        echo '    <h2>' . $category['name'] . '</h2>';
        echo '    <p>Browse the ' . $category['name'] . ' documentation</p>';
        echo '    <a href="' . $baseUrl . '/' . $category['path'] . '" class="btn">View Documentation</a>';
        echo '</div>';
    }
    echo '</div>';
    
    echo '</div>';
    
} elseif (count($pathParts) === 1) {
    // Category index page
    $category = $pathParts[0];
    $navigation = getNavigation($docsDir, $category);
    
    // Sidebar with navigation
    echo '<div class="sidebar">';
    echo '    <h3>' . ucfirst($category) . '</h3>';
    echo '    <a href="' . $baseUrl . '" class="back-link">&larr; Back to categories</a>';
    echo '    <ul>';
    foreach ($navigation as $item) {
        $active = $item['is_index'] ? ' class="active"' : '';
        echo '    <li' . $active . '><a href="' . $baseUrl . '/' . $category . '/' . $item['path'] . '">' . $item['title'] . '</a></li>';
    }
    echo '    </ul>';
    echo '</div>';
    
    echo '<div class="main-content">';
    
    // Main content - render category index
    $indexFile = $docsDir . '/' . $category . '/index.md';
    if (file_exists($indexFile)) {
        $content = file_get_contents($indexFile);
        echo renderMarkdown($content);
    } else {
        echo '<h1>' . ucfirst($category) . '</h1>';
        echo '<p>Select a topic from the sidebar to begin.</p>';
    }
    
    echo '</div>';
    
} elseif (count($pathParts) === 2) {
    // Specific page within a category
    $category = $pathParts[0];
    $page = $pathParts[1];
    $navigation = getNavigation($docsDir, $category);
    
    // Find current, previous and next pages
    $currentPageIndex = -1;
    $prevPage = null;
    $nextPage = null;
    
    foreach ($navigation as $index => $item) {
        if ($item['path'] === $page) {
            $currentPageIndex = $index;
            break;
        }
    }
    
    if ($currentPageIndex > 0) {
        $prevPage = $navigation[$currentPageIndex - 1];
    }
    
    if ($currentPageIndex < count($navigation) - 1) {
        $nextPage = $navigation[$currentPageIndex + 1];
    }
    
    // Sidebar with navigation
    echo '<div class="sidebar">';
    echo '    <h3>' . ucfirst($category) . '</h3>';
    echo '    <a href="' . $baseUrl . '/' . $category . '" class="back-link">&larr; Back to ' . ucfirst($category) . '</a>';
    echo '    <ul>';
    foreach ($navigation as $item) {
        $active = ($item['path'] === $page) ? ' class="active"' : '';
        echo '    <li' . $active . '><a href="' . $baseUrl . '/' . $category . '/' . $item['path'] . '">' . $item['title'] . '</a></li>';
    }
    echo '    </ul>';
    echo '</div>';
    
    echo '<div class="main-content">';
    
    // Main content - render page
    $pageFile = $docsDir . '/' . $category . '/' . $page . '.md';
    if (file_exists($pageFile)) {
        $content = file_get_contents($pageFile);
        echo renderMarkdown($content);
        
        // Pagination
        echo '<div style="display: flex; justify-content: space-between; margin-top: 2rem;">';
        if ($prevPage) {
            echo '<div><a href="' . $baseUrl . '/' . $category . '/' . $prevPage['path'] . '">&larr; ' . $prevPage['title'] . '</a></div>';
        } else {
            echo '<div></div>';
        }
        if ($nextPage) {
            echo '<div><a href="' . $baseUrl . '/' . $category . '/' . $nextPage['path'] . '">' . $nextPage['title'] . ' &rarr;</a></div>';
        } else {
            echo '<div></div>';
        }
        echo '</div>';
        
    } else {
        echo '<h1>Page Not Found</h1>';
        echo '<p>The requested page does not exist.</p>';
        echo '<p><a href="' . $baseUrl . '/' . $category . '">Return to ' . ucfirst($category) . ' index</a></p>';
    }
    
    echo '</div>';
    
} else {
    // Invalid URL - show 404
    echo '<div class="main-content" style="padding: 2rem;">';
    echo '    <h1>Page Not Found</h1>';
    echo '    <p>The requested page does not exist.</p>';
    echo '    <p><a href="' . $baseUrl . '">Return to Documentation Home</a></p>';
    echo '</div>';
}

echo '</div>'; // end container

echo '</body>';
echo '</html>';