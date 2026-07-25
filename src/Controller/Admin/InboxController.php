<?php

namespace App\Controller\Admin;

use App\Entity\Submission;
use App\Repository\SubmissionRepository;
use App\Service\LocationWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class InboxController extends AbstractController
{
    #[Route('', name: 'admin_inbox', methods: ['GET'])]
    public function index(SubmissionRepository $submissions): Response
    {
        return $this->render('admin/inbox.html.twig', [
            'submissions' => $submissions->findOpenOrdered(),
        ]);
    }

    #[Route('/submissions/{id}/approve', name: 'admin_submission_approve', methods: ['POST'])]
    public function approve(Submission $submission, Request $request, LocationWorkflow $workflow): Response
    {
        if (!$this->isCsrfTokenValid('approve'.$submission->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $workflow->approve($submission);
            $this->addFlash('success', sprintf('Submission #%d freigegeben.', $submission->getId()));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_inbox');
    }

    #[Route('/submissions/{id}/reject', name: 'admin_submission_reject', methods: ['POST'])]
    public function reject(Submission $submission, Request $request, LocationWorkflow $workflow): Response
    {
        if (!$this->isCsrfTokenValid('reject'.$submission->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $workflow->reject($submission);
            $this->addFlash('success', sprintf('Submission #%d abgelehnt.', $submission->getId()));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_inbox');
    }
}
