<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Finder\Finder;
use League\CommonMark\CommonMarkConverter;

/**
 * Controller for in-app documentation system
 */
class DocsController extends AbstractController
{
    private $docsDir;

    public function __construct(string $projectDir = null)
    {
        $projectDir = $projectDir ?? dirname(__DIR__, 2);
        $this->docsDir = $projectDir . '/public/docs';
    }

    /**
     * Documentation homepage - redirect to external docs
     */
    #[Route('/docs', name: 'docs_index')]
    public function index(): Response
    {
        return $this->redirect('https://docs.securehealth.dev', 301);
    }

    /**
     * Display documentation page - redirect to external docs
     */
    #[Route('/docs/{category}/{page}', name: 'docs_page')]
    public function page(string $category, string $page): Response
    {
        return $this->redirect('https://docs.securehealth.dev', 301);
    }

    /**
     * Display category index - redirect to external docs
     */
    #[Route('/docs/{category}', name: 'docs_category')]
    public function category(string $category): Response
    {
        return $this->redirect('https://docs.securehealth.dev', 301);
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