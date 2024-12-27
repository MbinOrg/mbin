<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\Contracts\MessageInterface;
use App\Message\Contracts\SendConfirmationEmailInterface;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
class SentUserConfirmationEmailHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
        private readonly SettingsManager $settingsManager,
        private readonly EmailVerifier $emailVerifier,
        private readonly UserRepository $repository,
        private readonly ParameterBagInterface $params,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(SendConfirmationEmailInterface $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof SendConfirmationEmailInterface)) {
            throw new \LogicException();
        }
        $user = $this->repository->find($message->userId);
        if (!$user) {
            throw new UnrecoverableMessageHandlingException('User not found');
        }

        $this->sendConfirmationEmail($user);
    }

    /**
     * @param User $user user that will be sent the confirmation email
     *
     * @throws \Exception
     */
    public function sendConfirmationEmail(User $user): void
    {
        try {
            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->from(
                        new Address($this->settingsManager->get('KBIN_SENDER_EMAIL'), $this->params->get('kbin_domain'))
                    )
                    ->to($user->email)
                    ->subject($this->translator->trans('email_confirm_title'))
                    ->htmlTemplate('_email/confirmation_email.html.twig')
            );
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
