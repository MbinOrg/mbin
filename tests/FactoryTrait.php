<?php

declare(strict_types=1);

namespace App\Tests;

use App\DTO\EntryCommentDto;
use App\DTO\EntryDto;
use App\DTO\ImageDto;
use App\DTO\MagazineBanDto;
use App\DTO\MagazineDto;
use App\DTO\MessageDto;
use App\DTO\OAuth2ClientDto;
use App\DTO\PostCommentDto;
use App\DTO\PostDto;
use App\DTO\UserDto;
use App\Entity\Client;
use App\Entity\Contracts\VotableInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Image;
use App\Entity\Magazine;
use App\Entity\Message;
use App\Entity\MessageThread;
use App\Entity\Notification;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\Site;
use App\Entity\User;
use App\Service\UserManager;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function PHPUnit\Framework\assertNotNull;

trait FactoryTrait
{
    public function createVote(int $choice, VotableInterface $subject, User $user): void
    {
        if (VotableInterface::VOTE_UP === $choice) {
            $favManager = $this->favouriteManager;
            $favManager->toggle($user, $subject);
        } else {
            $voteManager = $this->voteManager;
            $voteManager->vote($choice, $subject, $user);
        }
    }

    protected function loadExampleMagazines(): void
    {
        $this->loadExampleUsers();

        foreach ($this->provideMagazines() as $data) {
            $this->createMagazine($data['name'], $data['title'], $data['user'], $data['isAdult'], $data['description']);
        }
    }

    protected function loadExampleUsers(): void
    {
        foreach ($this->provideUsers() as $data) {
            $this->createUser($data['username'], $data['email'], $data['password']);
        }
    }

    private function provideUsers(): iterable
    {
        yield [
            'username' => 'adminUser',
            'password' => 'adminUser123',
            'email' => 'adminUser@example.com',
            'type' => 'Person',
        ];

        yield [
            'username' => 'JohnDoe',
            'password' => 'JohnDoe123',
            'email' => 'JohnDoe@example.com',
            'type' => 'Person',
        ];
    }

    private function createUser(string $username, ?string $email = null, ?string $password = null, string $type = 'Person', $active = true, $hideAdult = true, $about = null, $addImage = true): User
    {
        $user = new User($email ?: $username.'@example.com', $username, $password ?: 'secret', $type);

        $user->isVerified = $active;
        $user->notifyOnNewEntry = true;
        $user->notifyOnNewEntryReply = true;
        $user->notifyOnNewEntryCommentReply = true;
        $user->notifyOnNewPost = true;
        $user->notifyOnNewPostReply = true;
        $user->notifyOnNewPostCommentReply = true;
        $user->showProfileFollowings = true;
        $user->showProfileSubscriptions = true;
        $user->hideAdult = $hideAdult;
        $user->apDiscoverable = true;
        $user->about = $about;
        if ($addImage) {
            $user->avatar = $this->createImage(bin2hex(random_bytes(20)).'.png');
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->users->add($user);

        return $user;
    }

    public function createMessage(User $to, User $from, string $content): Message
    {
        $thread = $this->createMessageThread($to, $from, $content);
        /** @var Message $message */
        $message = $thread->messages->get(0);

        return $message;
    }

    public function createMessageThread(User $to, User $from, string $content): MessageThread
    {
        $messageManager = $this->messageManager;
        $dto = new MessageDto();
        $dto->body = $content;

        return $messageManager->toThread($dto, $from, $to);
    }

    public static function createOAuth2AuthCodeClient(): void
    {
        /** @var ClientManagerInterface $manager */
        $manager = self::getContainer()->get(ClientManagerInterface::class);

        $client = new Client('/kbin Test Client', 'testclient', 'testsecret');
        $client->setDescription('An OAuth2 client for testing purposes');
        $client->setContactEmail('test@kbin.test');
        $client->setScopes(...array_map(fn (string $scope) => new Scope($scope), OAuth2ClientDto::AVAILABLE_SCOPES));
        $client->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $client->setRedirectUris(new RedirectUri('https://localhost:3001'));

        $manager->save($client);
    }

    public static function createOAuth2PublicAuthCodeClient(): void
    {
        /** @var ClientManagerInterface $manager */
        $manager = self::getContainer()->get(ClientManagerInterface::class);

        $client = new Client('/kbin Test Client', 'testpublicclient', null);
        $client->setDescription('An OAuth2 public client for testing purposes');
        $client->setContactEmail('test@kbin.test');
        $client->setScopes(...array_map(fn (string $scope) => new Scope($scope), OAuth2ClientDto::AVAILABLE_SCOPES));
        $client->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $client->setRedirectUris(new RedirectUri('https://localhost:3001'));

        $manager->save($client);
    }

    public static function createOAuth2ClientCredsClient(): void
    {
        /** @var ClientManagerInterface $clientManager */
        $clientManager = self::getContainer()->get(ClientManagerInterface::class);

        /** @var UserManager $userManager */
        $userManager = self::getContainer()->get(UserManager::class);

        $client = new Client('/kbin Test Client', 'testclient', 'testsecret');

        $userDto = new UserDto();
        $userDto->username = 'test_bot';
        $userDto->email = 'test@kbin.test';
        $userDto->plainPassword = hash('sha512', random_bytes(32));
        $userDto->isBot = true;
        $user = $userManager->create($userDto, false, false, true);
        $client->setUser($user);

        $client->setDescription('An OAuth2 client for testing purposes');
        $client->setContactEmail('test@kbin.test');
        $client->setScopes(...array_map(fn (string $scope) => new Scope($scope), OAuth2ClientDto::AVAILABLE_SCOPES));
        $client->setGrants(new Grant('client_credentials'));
        $client->setRedirectUris(new RedirectUri('https://localhost:3001'));

        $clientManager->save($client);
    }

    private function provideMagazines(): iterable
    {
        yield [
            'name' => 'acme',
            'title' => 'Magazyn polityczny',
            'user' => $this->getUserByUsername('JohnDoe'),
            'isAdult' => false,
            'description' => 'Foobar',
        ];

        yield [
            'name' => 'kbin',
            'title' => 'kbin devlog',
            'user' => $this->getUserByUsername('adminUser'),
            'isAdult' => false,
            'description' => 'development process in detail',
        ];

        yield [
            'name' => 'adult',
            'title' => 'Adult only',
            'user' => $this->getUserByUsername('JohnDoe'),
            'isAdult' => true,
            'description' => 'Not for kids',
        ];

        yield [
            'name' => 'starwarsmemes@republic.new',
            'title' => 'starwarsmemes@republic.new',
            'user' => $this->getUserByUsername('adminUser'),
            'isAdult' => false,
            'description' => "It's a trap",
        ];
    }

    protected function getUserByUsername(string $username, bool $isAdmin = false, bool $hideAdult = true, ?string $about = null, bool $active = true, bool $isModerator = false, bool $addImage = true, ?string $email = null): User
    {
        $user = $this->users->filter(fn (User $user) => $user->getUsername() === $username)->first();

        if ($user) {
            return $user;
        }

        $user = $this->createUser($username, email: $email, active: $active, hideAdult: $hideAdult, about: $about, addImage: $addImage);

        if ($isAdmin) {
            $user->roles = ['ROLE_ADMIN'];
        } elseif ($isModerator) {
            $user->roles = ['ROLE_MODERATOR'];
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function setAdmin(User $user): void
    {
        $user->roles = ['ROLE_ADMIN'];
        $manager = $this->entityManager;

        $manager->persist($user);
        $manager->flush();

        $manager->refresh($user);
    }

    private function createMagazine(
        string $name,
        ?string $title = null,
        ?User $user = null,
        bool $isAdult = false,
        ?string $description = null,
    ): Magazine {
        $dto = new MagazineDto();
        $dto->name = $name;
        $dto->title = $title ?? 'Magazine title';
        $dto->isAdult = $isAdult;
        $dto->description = $description;

        if (str_contains($name, '@')) {
            [$name, $host] = explode('@', $name);
            $dto->apId = $name;
            $dto->apProfileId = "https://{$host}/m/{$name}";
        }
        $newMod = $user ?? $this->getUserByUsername('JohnDoe');
        $this->entityManager->persist($newMod);

        $magazine = $this->magazineManager->create($dto, $newMod);
        $this->entityManager->persist($magazine);

        $this->magazines->add($magazine);

        return $magazine;
    }

    protected function loadNotificationsFixture()
    {
        $owner = $this->getUserByUsername('owner');
        $magazine = $this->getMagazineByName('acme', $owner);

        $actor = $this->getUserByUsername('actor');
        $regular = $this->getUserByUsername('JohnDoe');

        $entry = $this->getEntryByTitle('test', null, 'test', $magazine, $actor);
        $comment = $this->createEntryComment('test', $entry, $regular);
        $this->entryCommentManager->delete($owner, $comment);
        $this->entryManager->delete($owner, $entry);

        $post = $this->createPost('test', $magazine, $actor);
        $comment = $this->createPostComment('test', $post, $regular);
        $this->postCommentManager->delete($owner, $comment);
        $this->postManager->delete($owner, $post);

        $this->magazineManager->ban(
            $magazine,
            $actor,
            $owner,
            MagazineBanDto::create('test', new \DateTime('+1 day'))
        );
    }

    protected function getMagazineByName(string $name, ?User $user = null, bool $isAdult = false): Magazine
    {
        $magazine = $this->magazines->filter(fn (Magazine $magazine) => $magazine->name === $name)->first();

        return $magazine ?: $this->createMagazine($name, $name, $user, $isAdult);
    }

    protected function getMagazineByNameNoRSAKey(string $name, ?User $user = null, bool $isAdult = false): Magazine
    {
        $magazine = $this->magazines->filter(fn (Magazine $magazine) => $magazine->name === $name)->first();

        if ($magazine) {
            return $magazine;
        }

        $dto = new MagazineDto();
        $dto->name = $name;
        $dto->title = $title ?? 'Magazine title';
        $dto->isAdult = $isAdult;

        if (str_contains($name, '@')) {
            [$name, $host] = explode('@', $name);
            $dto->apId = $name;
            $dto->apProfileId = "https://{$host}/m/{$name}";
        }

        $factory = $this->magazineFactory;
        $magazine = $factory->createFromDto($dto, $user ?? $this->getUserByUsername('JohnDoe'));
        $magazine->apId = $dto->apId;
        $magazine->apProfileId = $dto->apProfileId;
        $magazine->apDiscoverable = true;

        if (!$dto->apId) {
            $urlGenerator = $this->urlGenerator;
            $magazine->publicKey = 'fakepublic';
            $magazine->privateKey = 'fakeprivate';
            $magazine->apProfileId = $urlGenerator->generate(
                'ap_magazine',
                ['name' => $magazine->name],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        $entityManager = $this->entityManager;
        $entityManager->persist($magazine);
        $entityManager->flush();

        $manager = $this->magazineManager;
        $manager->subscribe($magazine, $user ?? $this->getUserByUsername('JohnDoe'));

        $this->magazines->add($magazine);

        return $magazine;
    }

    protected function getEntryByTitle(
        string $title,
        ?string $url = null,
        ?string $body = null,
        ?Magazine $magazine = null,
        ?User $user = null,
        ?ImageDto $image = null,
        string $lang = 'en',
    ): Entry {
        $entry = $this->entries->filter(fn (Entry $entry) => $entry->title === $title)->first();

        if (!$entry) {
            $magazine = $magazine ?? $this->getMagazineByName('acme');
            $user = $user ?? $this->getUserByUsername('JohnDoe');
            $entry = $this->createEntry($title, $magazine, $user, $url, $body, $image, $lang);
        }

        return $entry;
    }

    protected function createEntry(
        string $title,
        Magazine $magazine,
        User $user,
        ?string $url = null,
        ?string $body = 'Test entry content',
        ?ImageDto $imageDto = null,
        string $lang = 'en',
    ): Entry {
        $manager = $this->entryManager;

        $dto = new EntryDto();
        $dto->magazine = $magazine;
        $dto->title = $title;
        $dto->user = $user;
        $dto->url = $url;
        $dto->body = $body;
        $dto->lang = $lang;
        $dto->image = $imageDto;

        $entry = $manager->create($dto, $user);

        $this->entries->add($entry);

        return $entry;
    }

    public function createEntryComment(
        string $body,
        ?Entry $entry = null,
        ?User $user = null,
        ?EntryComment $parent = null,
        ?ImageDto $imageDto = null,
        string $lang = 'en',
    ): EntryComment {
        $manager = $this->entryCommentManager;
        $repository = $this->imageRepository;

        if ($parent) {
            $dto = (new EntryCommentDto())->createWithParent(
                $entry ?? $this->getEntryByTitle('test entry content', 'https://kbin.pub'),
                $parent,
                $imageDto ? $repository->find($imageDto->id) : null,
                $body
            );
        } else {
            $dto = new EntryCommentDto();
            $dto->entry = $entry ?? $this->getEntryByTitle('test entry content', 'https://kbin.pub');
            $dto->body = $body;
            $dto->image = $imageDto;
        }
        $dto->lang = $lang;

        return $manager->create($dto, $user ?? $this->getUserByUsername('JohnDoe'));
    }

    public function createPost(string $body, ?Magazine $magazine = null, ?User $user = null, ?ImageDto $imageDto = null, string $lang = 'en'): Post
    {
        $manager = $this->postManager;
        $dto = new PostDto();
        $dto->magazine = $magazine ?: $this->getMagazineByName('acme');
        $dto->body = $body;
        $dto->lang = $lang;
        $dto->image = $imageDto;

        return $manager->create($dto, $user ?? $this->getUserByUsername('JohnDoe'));
    }

    public function createPostComment(string $body, ?Post $post = null, ?User $user = null, ?ImageDto $imageDto = null, ?PostComment $parent = null, string $lang = 'en'): PostComment
    {
        $manager = $this->postCommentManager;

        $dto = new PostCommentDto();
        $dto->post = $post ?? $this->createPost('test post content');
        $dto->body = $body;
        $dto->lang = $lang;
        $dto->image = $imageDto;
        $dto->parent = $parent;

        return $manager->create($dto, $user ?? $this->getUserByUsername('JohnDoe'));
    }

    public function createPostCommentReply(string $body, ?Post $post = null, ?User $user = null, ?PostComment $parent = null): PostComment
    {
        $manager = $this->postCommentManager;

        $dto = new PostCommentDto();
        $dto->post = $post ?? $this->createPost('test post content');
        $dto->body = $body;
        $dto->lang = 'en';
        $dto->parent = $parent ?? $this->createPostComment('test parent comment', $dto->post);

        return $manager->create($dto, $user ?? $this->getUserByUsername('JohnDoe'));
    }

    public function createImage(string $fileName): Image
    {
        $imageRepo = $this->imageRepository;
        $image = $imageRepo->findOneBy(['fileName' => $fileName]);
        if ($image) {
            return $image;
        }
        $image = new Image(
            $fileName,
            '/dev/random',
            hash('sha256', $fileName),
            100,
            100,
            null,
        );
        $this->entityManager->persist($image);
        $this->entityManager->flush();

        return $image;
    }

    public function createMessageNotification(?User $to = null, ?User $from = null): Notification
    {
        $messageManager = $this->messageManager;
        $repository = $this->notificationRepository;

        $dto = new MessageDto();
        $dto->body = 'test message';
        $messageManager->toThread($dto, $from ?? $this->getUserByUsername('JaneDoe'), $to ?? $this->getUserByUsername('JohnDoe'));

        return $repository->findOneBy(['user' => $to ?? $this->getUserByUsername('JohnDoe')]);
    }

    protected function createInstancePages(): Site
    {
        $siteRepository = $this->siteRepository;
        $entityManager = $this->entityManager;
        $results = $siteRepository->findAll();
        $site = null;
        if (!\count($results)) {
            $site = new Site();
        } else {
            $site = $results[0];
        }
        $site->about = 'about';
        $site->contact = 'contact';
        $site->faq = 'faq';
        $site->privacyPolicy = 'privacyPolicy';
        $site->terms = 'terms';

        $entityManager->persist($site);
        $entityManager->flush();

        return $site;
    }

    /**
     * Creates 5 modlog messages, one each of:
     *   * log_entry_deleted
     *   * log_entry_comment_deleted
     *   * log_post_deleted
     *   * log_post_comment_deleted
     *   * log_ban.
     */
    public function createModlogMessages(): void
    {
        $magazineManager = $this->magazineManager;
        $entryManager = $this->entryManager;
        $entryCommentManager = $this->entryCommentManager;
        $postManager = $this->postManager;
        $postCommentManager = $this->postCommentManager;
        $moderator = $this->getUserByUsername('moderator');
        $magazine = $this->getMagazineByName('acme', $moderator);
        $user = $this->getUserByUsername('user');
        $post = $this->createPost('test post', $magazine, $user);
        $entry = $this->getEntryByTitle('A title', body: 'test entry', magazine: $magazine, user: $user);
        $postComment = $this->createPostComment('test comment', $post, $user);
        $entryComment = $this->createEntryComment('test comment 2', $entry, $user);

        $entryCommentManager->delete($moderator, $entryComment);
        $entryManager->delete($moderator, $entry);
        $postCommentManager->delete($moderator, $postComment);
        $postManager->delete($moderator, $post);
        $magazineManager->ban($magazine, $user, $moderator, MagazineBanDto::create('test ban', new \DateTimeImmutable('+12 hours')));
    }

    public function register($active = false): KernelBrowser
    {
        $crawler = $this->client->request('GET', '/register');

        $this->client->submit(
            $crawler->filter('form[name=user_register]')->selectButton('Register')->form(
                [
                    'user_register[username]' => 'JohnDoe',
                    'user_register[email]' => 'johndoe@kbin.pub',
                    'user_register[plainPassword][first]' => 'secret',
                    'user_register[plainPassword][second]' => 'secret',
                    'user_register[agreeTerms]' => true,
                ]
            )
        );
        if (302 === $this->client->getResponse()->getStatusCode()) {
            $this->client->followRedirect();
        }
        self::assertResponseIsSuccessful();

        if ($active) {
            $user = self::getContainer()->get('doctrine')->getRepository(User::class)
                ->findOneBy(['username' => 'JohnDoe']);
            $user->isVerified = true;

            self::getContainer()->get('doctrine')->getManager()->flush();
            self::getContainer()->get('doctrine')->getManager()->refresh($user);
        }

        return $this->client;
    }

    public function getKibbyImageDto(): ImageDto
    {
        return $this->getKibbyImageVariantDto('');
    }

    public function getKibbyFlippedImageDto(): ImageDto
    {
        return $this->getKibbyImageVariantDto('_flipped');
    }

    private function getKibbyImageVariantDto(string $suffix): ImageDto
    {
        $imageRepository = $this->imageRepository;
        $imageFactory = $this->imageFactory;

        if (!file_exists(\dirname($this->kibbyPath).'/copy')) {
            if (!mkdir(\dirname($this->kibbyPath).'/copy')) {
                throw new \Exception('The copy dir could not be created');
            }
        }

        // Uploading a file appears to delete the file at the given path, so make a copy before upload
        $tmpPath = \dirname($this->kibbyPath).'/copy/'.bin2hex(random_bytes(32)).'.png';
        $srcPath = \dirname($this->kibbyPath).'/'.basename($this->kibbyPath, '.png').$suffix.'.png';
        if (!file_exists($srcPath)) {
            throw new \Exception('For some reason the kibby image got deleted');
        }
        copy($srcPath, $tmpPath);
        /** @var Image $image */
        $image = $imageRepository->findOrCreateFromUpload(new UploadedFile($tmpPath, 'kibby_emoji.png', 'image/png'));
        self::assertNotNull($image);
        $image->altText = 'kibby';
        $this->entityManager->persist($image);
        $this->entityManager->flush();

        $dto = $imageFactory->createDto($image);
        assertNotNull($dto->id);

        return $dto;
    }
}
