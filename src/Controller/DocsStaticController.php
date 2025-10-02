<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Finder\Finder;
use League\CommonMark\CommonMarkConverter;

/**
 * Static documentation controller that doesn't rely on MongoDB
 */
#[Route('/docs')]
class DocsStaticController extends AbstractController
{
    private $docsDir;

    public function __construct(string $projectDir = null)
    {
        $projectDir = $projectDir ?? dirname(__DIR__, 2);
        $this->docsDir = $projectDir . '/public/docs';
    }

    #[Route('', name: 'docs_static_index')]
    public function index(): Response
    {
        // Get documentation categories from directory structure
        $categories = [];
        $finder = new Finder();
        
        // Ensure directory exists
        if (!is_dir($this->docsDir)) {
            mkdir($this->docsDir, 0755, true);
        }
        
        if (is_dir($this->docsDir)) {
            $finder->directories()->in($this->docsDir)->depth(0);
            
            foreach ($finder as $dir) {
                $categories[] = [
                    'name' => ucfirst($dir->getRelativePathname()),
                    'path' => $dir->getRelativePathname()
                ];
            }
        }

        // Get index content
        $indexContent = $this->renderMarkdownFile('index.md');
        if (!$indexContent) {
            $indexContent = '<h1>Documentation</h1><p>Welcome to the SecureHealth Documentation. Please select a category from the sidebar.</p>';
        }

        return $this->render('docs/index.html.twig', [
            'title' => 'Documentation',
            'content' => $indexContent,
            'categories' => $categories
        ]);
    }

    #[Route('/{category}/{page}', name: 'docs_static_page')]
    public function page(string $category, string $page): Response
    {
        // Build sidebar navigation for the category
        $navigation = $this->buildNavigation($category);
        
        // Get the content for the requested page
        $content = $this->renderMarkdownFile("$category/$page.md");
        
        // If file doesn't exist, show 404
        if (!$content) {
            throw $this->createNotFoundException('Documentation page not found');
        }

        return $this->render('docs/page.html.twig', [
            'title' => ucfirst($page),
            'category' => ucfirst($category),
            'content' => $content,
            'navigation' => $navigation,
            'current_page' => $page
        ]);
    }

    #[Route('/{category}', name: 'docs_static_category')]
    public function category(string $category): Response
    {
        // Build sidebar navigation for the category
        $navigation = $this->buildNavigation($category);
        
        // Get the content for the category index
        $content = $this->renderMarkdownFile("$category/index.md");
        
        // If file doesn't exist, show 404
        if (!$content) {
            throw $this->createNotFoundException('Category not found');
        }

        return $this->render('docs/page.html.twig', [
            'title' => ucfirst($category),
            'category' => ucfirst($category),
            'content' => $content,
            'navigation' => $navigation,
            'current_page' => 'index'
        ]);
    }

    /**
     * Build navigation for a category
     */
    private function buildNavigation(string $category): array
    {
        $navigation = [];
        $finder = new Finder();
        
        // Check if category directory exists
        if (!is_dir($this->docsDir . '/' . $category)) {
            return $navigation;
        }
        
        // Find all .md files in the category
        $finder->files()->in($this->docsDir . '/' . $category)->name('*.md')->sortByName();
        
        foreach ($finder as $file) {
            $filename = $file->getFilenameWithoutExtension();
            
            // Read first line of file to get title
            $firstLine = fgets(fopen($file->getRealPath(), 'r'));
            $title = $firstLine && strpos($firstLine, '#') === 0 
                ? trim(substr($firstLine, strpos($firstLine, ' ')))
                : ucfirst($filename);
            
            $navigation[] = [
                'title' => $title,
                'path' => $filename,
                'is_index' => $filename === 'index'
            ];
        }
        
        return $navigation;
    }

    /**
     * Render markdown file to HTML
     */
    private function renderMarkdownFile(string $relativePath): ?string
    {
        $filePath = $this->docsDir . '/' . $relativePath;
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        $markdown = file_get_contents($filePath);
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        
        return $converter->convertToHtml($markdown);
    }
}