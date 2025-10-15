<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class StaffMessagesController extends AbstractController
{
    #[Route('/staff/messages', name: 'staff_messages_inbox', methods: ['GET'])]
    #[IsGranted('ROLE_NURSE')]
    public function index(): Response
    {
        return $this->render('messages/inbox.html.twig', [
            'controller_name' => 'StaffMessagesController',
        ]);
    }
}


