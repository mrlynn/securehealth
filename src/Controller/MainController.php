<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends AbstractController
{
    /**
     * Home page
     */
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('main/index.html.twig', [
            'title' => 'SecureHealth - HIPAA-Compliant Healthcare Records'
        ]);
    }

    /**
     * Documentation landing page
     */
    #[Route('/help', name: 'app_help')]
    public function help(): Response
    {
        return $this->render('main/help.html.twig', [
            'title' => 'SecureHealth - Help & Documentation'
        ]);
    }
}