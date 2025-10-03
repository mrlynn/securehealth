<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AppointmentController extends AbstractController
{
    #[Route('/reception/schedule', name: 'reception_schedule')]
    #[IsGranted('ROLE_RECEPTIONIST')]
    public function schedule(): Response
    {
        // Keep legacy route functional but align with the static SPA flow.
        return $this->redirect('/scheduling.html');
    }
}
