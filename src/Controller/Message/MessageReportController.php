<?php

namespace App\Controller\Message;

use App\Controller\AbstractController;
use App\DTO\ReportDto;
use App\Entity\Message;
use App\Entity\MessageThread;
use App\Entity\Report;
use App\Exception\SubjectHasBeenReportedException;
use App\Form\ReportType;
use App\Repository\MessageRepository;
use App\Repository\NotificationRepository;
use App\Service\ReportManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class MessageReportController extends AbstractController
{
    public function __construct(
        private readonly MessageRepository $repository,
        private readonly NotificationRepository $notificationRepository,
        private readonly ReportManager $reportManager,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
    ) {
    }

    public function reports(
        Request $request,
        string $status,
    ): Response {
        if (!$this->security->isGranted('ROLE_ADMIN') && !$this->security->isGranted('ROLE_MODERATOR')) {
            throw new AccessDeniedException();
        }

        $reports = $this->repository->findReports($this->getPageNb($request), status: $status);

        $reportIds = array_map(function (Report $report) { return $report->getId(); }, [...$reports->getCurrentPageResults()]);
        $this->notificationRepository->markReportNotificationsOfMessagesAsRead($this->getUserOrThrow(), $reportIds);

        return $this->render(
            'messages/reports.html.twig',
            [
                'reports' => $reports,
            ]
        );
    }

    public function reportApprove(
        #[MapEntity(id: 'report_id')]
        Report $report,
        Request $request,
    ): Response {
        if (!$this->security->isGranted('ROLE_ADMIN') && !$this->security->isGranted('ROLE_MODERATOR')) {
            throw new AccessDeniedException();
        }

        $this->validateCsrf('report_approve', $request->getPayload()->get('token'));

        $this->reportManager->accept($report, $this->getUserOrThrow());

        return $this->redirectToRefererOrHome($request);
    }

    public function reportReject(
        #[MapEntity(id: 'report_id')]
        Report $report,
        Request $request,
    ): Response {
        if (!$this->security->isGranted('ROLE_ADMIN') && !$this->security->isGranted('ROLE_MODERATOR')) {
            throw new AccessDeniedException();
        }

        $this->validateCsrf('report_decline', $request->getPayload()->get('token'));

        $this->reportManager->reject($report, $this->getUserOrThrow());

        return $this->redirectToRefererOrHome($request);
    }

    #[IsGranted('ROLE_USER')]
    public function reportMessage(
        #[MapEntity]
        Message $subject,
        Request $request,
    ): Response {
        $user = $this->getUserOrThrow();
        $thread = $subject->thread;
        if(!$thread->userIsParticipant($user)) {
            throw new AccessDeniedException();
        }

        $dto = ReportDto::create($subject);

        $form = $this->createForm(
            ReportType::class,
            $dto,
            ['action' => $this->generateUrl($dto->getRouteName(), ['id' => $subject->getId()])]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->handleReportRequest($dto, $request);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->getJsonFormResponse($form, 'report/_form_report.html.twig');
        }

        return $this->render(
            'report/create.html.twig',
            [
                'form' => $form->createView(),
                'magazine' => null,
                'subject' => $subject,
            ]
        );
    }

    private function handleReportRequest(ReportDto $dto, Request $request): Response
    {
        $reportError = false;
        try {
            $this->reportManager->report($dto, $this->getUserOrThrow());
            $responseMessage = $this->translator->trans('subject_reported');

            //TODO should the message be deleted directly or at report-accept?
        } catch (SubjectHasBeenReportedException $exception) {
            $reportError = true;
            $responseMessage = $this->translator->trans('subject_reported_exists');
        } finally {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(
                    [
                        'success' => true,
                        'html' => \sprintf("<div class='alert %s'>%s</div>", ($reportError) ? 'alert__danger' : 'alert__info', $responseMessage),
                    ]
                );
            }

            $this->addFlash($reportError ? 'error' : 'info', $responseMessage);

            return $this->redirectToRefererOrHome($request);
        }
    }
}
