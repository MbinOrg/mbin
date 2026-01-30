<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\Contracts\MessageInterface;
use App\Message\UserApplicationAnswerMessage;
use App\Repository\UserRepository;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
class SendApplicationAnswerMailHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsManager $settingsManager,
        private readonly UserRepository $repository,
        private readonly ParameterBagInterface $params,
        private readonly TranslatorInterface $translator,
        private readonly MailerInterface $mailer,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(UserApplicationAnswerMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof UserApplicationAnswerMessage)) {
            throw new \LogicException();
        }
        $user = $this->repository->find($message->userId);
        if (!$user) {
            throw new UnrecoverableMessageHandlingException('User not found');
        }

        $this->sendAnswerMail($user, $message->approved);
    }

    public function sendAnswerMail(User $user, bool $approved): void
    {
        $mail = (new TemplatedEmail())
            ->from(
                new Address($this->settingsManager->get('KBIN_SENDER_EMAIL'), $this->params->get('kbin_domain'))
            )
            ->to($user->email);

        if ($approved) {
            $mail->subject($this->translator->trans('email_application_approved_title'))
                ->htmlTemplate('_email/application_approved.html.twig')
                ->context(['user' => $user]);
        } else {
            $mail->subject($this->translator->trans('email_application_rejected_title'))
                ->htmlTemplate('_email/application_rejected.html.twig')
                ->context(['user' => $user]);
        }
        $this->mailer->send($mail);
    }
}
