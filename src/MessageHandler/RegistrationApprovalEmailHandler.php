<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enums\EApplicationStatus;
use App\Message\Contracts\MessageInterface;
use App\Message\RegistrationApprovalEmailMessage;
use App\Repository\UserRepository;
use App\Service\SettingsManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
class RegistrationApprovalEmailHandler extends MbinMessageHandler
{
    public function __construct(
        EntityManagerInterface $entityManager,
        KernelInterface $kernel,
        private readonly UserRepository $repository,
        private readonly SettingsManager $settings,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly Connection $connection,
    ) {
        parent::__construct($entityManager, $kernel);
    }

    public function __invoke(RegistrationApprovalEmailMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!$message instanceof RegistrationApprovalEmailMessage) {
            throw new \LogicException();
        }
        // Workers may have an older SettingsManager instance; read the enable switch from storage.
        if ('true' !== $this->connection->fetchOne('SELECT value FROM settings WHERE name = :name', ['name' => 'MBIN_STOPFORUMSPAM_ENABLED'])) {
            return;
        }
        $approval = $this->connection->fetchOne('SELECT value FROM settings WHERE name = :name', ['name' => 'MBIN_NEW_USERS_NEED_APPROVAL']);
        if (false !== $approval && 'true' !== $approval) {
            return;
        }
        $user = $this->repository->find($message->userId);
        $admin = $this->repository->find($message->adminId);
        if (!$user || !$admin || !$admin->isAdmin() || !$admin->notifyOnUserSignup
            || $admin->isAccountDeleted() || $admin->isSoftDeleted() || null !== $admin->markedForDeletionAt
            || $user->isAccountDeleted() || $user->isSoftDeleted() || null !== $user->markedForDeletionAt
            || EApplicationStatus::Pending !== $user->getApplicationStatus()
            || null === $user->getRegistrationScreening()
            || (false === $approval && !$this->settings->getNewUsersNeedApproval())) {
            return;
        }
        $this->mailer->send((new TemplatedEmail())
            ->from(new Address($this->settings->get('KBIN_SENDER_EMAIL'), $this->settings->get('KBIN_DOMAIN')))
            ->to($admin->email)
            ->subject($this->translator->trans('stopforumspam_approval_email_title'))
            ->htmlTemplate('_email/registration_approval.html.twig')
            ->context(['user' => $user, 'screening' => $user->getRegistrationScreening()]));
    }
}
