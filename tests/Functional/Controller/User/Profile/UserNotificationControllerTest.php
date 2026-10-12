<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\User\Profile;

use App\DTO\MessageDto;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Tests\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UserNotificationControllerTest extends WebTestCase
{
    private const ROW_SELECTOR = '#main [data-notification-id]';

    public function testUserReceivesNotifications(): void
    {
        $owner = $this->getUserByUsername('owner');
        $actor = $this->getUserByUsername('actor');
        $this->magazineManager->subscribe($this->getMagazineByName('acme'), $owner);
        $this->magazineManager->subscribe($this->getMagazineByName('acme'), $actor);
        $this->loadNotificationsFixture();

        foreach (['owner' => 2, 'actor' => 3, 'JohnDoe' => 2] as $username => $count) {
            $this->client->restart();
            $user = $this->getUserByUsername($username);
            $expected = $this->notificationStates($user->getId());
            self::assertCount($count, $expected);
            $crawler = $this->openNotifications($user);
            self::assertCount($count, $crawler->filter(self::ROW_SELECTOR));
            self::assertSame($expected, $this->renderedStates($crawler));
        }
    }

    public function testCanReadAllNotifications(): void
    {
        $owner = $this->getUserByUsername('owner');
        $other = $this->getUserByUsername('actor');
        $this->seedMessageNotifications($owner, 2);
        $this->seedMessageNotifications($other, 1);
        $notifications = $this->notificationRepository->findBy(['user' => $owner], ['id' => 'ASC']);
        $notifications[0]->status = Notification::STATUS_READ;
        $this->entityManager->flush();
        $expected = $this->notificationStates($owner->getId());
        $otherBefore = $this->notificationStates($other->getId());
        self::assertCount(1, $otherBefore);
        self::assertSame([Notification::STATUS_NEW], array_values($otherBefore));
        self::assertCount(2, $expected);
        self::assertSame([Notification::STATUS_READ, Notification::STATUS_NEW], array_values($expected));

        $crawler = $this->openNotifications($owner);
        self::assertSame($expected, $this->renderedStates($crawler));
        $crawler = $this->submitNotificationAction($crawler, 'notifications_read');

        $expected = array_fill_keys(array_keys($expected), Notification::STATUS_READ);
        self::assertSame($expected, $this->renderedStates($crawler));
        self::assertSame($expected, $this->notificationStates($owner->getId()));
        self::assertSame($otherBefore, $this->notificationStates($other->getId()));
    }

    public function testUserCanDeleteAllNotifications(): void
    {
        $owner = $this->getUserByUsername('owner');
        $other = $this->getUserByUsername('actor');
        $this->seedMessageNotifications($owner, 2);
        $this->seedMessageNotifications($other, 1);
        $expected = $this->notificationStates($owner->getId());
        $otherBefore = $this->notificationStates($other->getId());
        self::assertCount(1, $otherBefore);
        self::assertSame([Notification::STATUS_NEW], array_values($otherBefore));
        self::assertCount(2, $expected);

        $crawler = $this->openNotifications($owner);
        self::assertSame($expected, $this->renderedStates($crawler));
        $crawler = $this->submitNotificationAction($crawler, 'notifications_clear');
        self::assertCount(0, $crawler->filter(self::ROW_SELECTOR));
        self::assertSame([], $this->notificationStates($owner->getId()));
        self::assertSame($otherBefore, $this->notificationStates($other->getId()));

        $crawler = $this->client->request('GET', '/settings/notifications');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter(self::ROW_SELECTOR));
    }

    public function testEmptyInbox(): void
    {
        $owner = $this->getUserByUsername('owner');
        self::assertSame([], $this->notificationStates($owner->getId()));
        $crawler = $this->openNotifications($owner);
        self::assertCount(0, $crawler->filter(self::ROW_SELECTOR));
    }

    private function seedMessageNotifications(User $receiver, int $count): void
    {
        $sender = $this->getUserByUsername('JaneDoe');
        for ($i = 0; $i < $count; ++$i) {
            $dto = new MessageDto();
            $dto->body = 'Notification fixture message '.$i;
            $this->messageManager->toThread($dto, $sender, $receiver);
        }
    }

    private function openNotifications(User $user): Crawler
    {
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/settings/notifications');
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    private function submitNotificationAction(Crawler $crawler, string $route): Crawler
    {
        $router = self::getContainer()->get(UrlGeneratorInterface::class);
        $forms = $crawler->filter('form')->reduce(fn (Crawler $form): bool => $form->attr('action') === $router->generate($route));
        self::assertCount(1, $forms);
        $this->client->submit($forms->form());
        self::assertResponseRedirects($router->generate('notifications_front'));
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /** @return array<int, string> */
    private function renderedStates(Crawler $crawler): array
    {
        $states = [];
        foreach ($crawler->filter(self::ROW_SELECTOR) as $row) {
            $id = (int) $row->getAttribute('data-notification-id');
            self::assertGreaterThan(0, $id);
            self::assertArrayNotHasKey($id, $states);
            $status = $row->getAttribute('data-notification-status');
            self::assertContains($status, [Notification::STATUS_NEW, Notification::STATUS_READ]);
            $states[$id] = $status;
        }
        ksort($states);

        return $states;
    }

    /** @return array<int, string> */
    private function notificationStates(int $userId): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $repository = self::getContainer()->get(NotificationRepository::class);
        $states = [];
        foreach ($repository->findBy(['user' => $userId], ['id' => 'ASC']) as $notification) {
            $states[$notification->getId()] = $notification->status;
        }

        return $states;
    }
}
