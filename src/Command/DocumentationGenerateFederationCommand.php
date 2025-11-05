<?php

declare(strict_types=1);

namespace App\Command;

use App\DTO\EntryCommentDto;
use App\DTO\EntryDto;
use App\DTO\MagazineDto;
use App\DTO\MessageDto;
use App\DTO\PostCommentDto;
use App\DTO\PostDto;
use App\DTO\ReportDto;
use App\DTO\UserDto;
use App\Entity\Image;
use App\Entity\MagazineBan;
use App\Factory\ActivityPub\AddRemoveFactory;
use App\Factory\ActivityPub\BlockFactory;
use App\Factory\ActivityPub\CollectionFactory;
use App\Factory\ActivityPub\EntryCommentNoteFactory;
use App\Factory\ActivityPub\EntryPageFactory;
use App\Factory\ActivityPub\FlagFactory;
use App\Factory\ActivityPub\GroupFactory;
use App\Factory\ActivityPub\InstanceFactory;
use App\Factory\ActivityPub\MessageFactory;
use App\Factory\ActivityPub\PersonFactory;
use App\Factory\ActivityPub\PostCommentNoteFactory;
use App\Factory\ActivityPub\PostNoteFactory;
use App\Factory\ImageFactory;
use App\Repository\UserRepository;
use App\Service\ActivityPub\ActivityJsonBuilder;
use App\Service\ActivityPub\ContextsProvider;
use App\Service\ActivityPub\Wrapper\AnnounceWrapper;
use App\Service\ActivityPub\Wrapper\CollectionInfoWrapper;
use App\Service\ActivityPub\Wrapper\CreateWrapper;
use App\Service\ActivityPub\Wrapper\DeleteWrapper;
use App\Service\ActivityPub\Wrapper\FollowResponseWrapper;
use App\Service\ActivityPub\Wrapper\FollowWrapper;
use App\Service\ActivityPub\Wrapper\LikeWrapper;
use App\Service\ActivityPub\Wrapper\UndoWrapper;
use App\Service\ActivityPub\Wrapper\UpdateWrapper;
use App\Service\EntryCommentManager;
use App\Service\EntryManager;
use App\Service\MagazineManager;
use App\Service\MessageManager;
use App\Service\PostCommentManager;
use App\Service\PostManager;
use App\Service\ReportManager;
use App\Service\SettingsManager;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouterInterface;

#[AsCommand(
    name: 'mbin:docs:gen:federation',
    description: 'This command allows you to generate the federation JSON for the documentation.'
)]
class DocumentationGenerateFederationCommand extends Command
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly SettingsManager $settingsManager,
        private readonly UserManager $userManager,
        private readonly MagazineManager $magazineManager,
        private readonly MessageManager $messageManager,
        private readonly EntryManager $entryManager,
        private readonly EntryCommentManager $entryCommentManager,
        private readonly PostManager $postManager,
        private readonly PostCommentManager $postCommentManager,
        private readonly ImageFactory $imageFactory,
        private readonly PersonFactory $personFactory,
        private readonly GroupFactory $groupFactory,
        private readonly InstanceFactory $instanceFactory,
        private readonly EntryPageFactory $entryPageFactory,
        private readonly EntryCommentNoteFactory $entryCommentNoteFactory,
        private readonly PostNoteFactory $postNoteFactory,
        private readonly PostCommentNoteFactory $postCommentNoteFactory,
        private readonly MessageFactory $messageFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly ContextsProvider $contextsProvider,
        private readonly EntityManagerInterface $entityManager,
        private readonly ReportManager $reportManager,
        private readonly ActivityJsonBuilder $activityJsonBuilder,
        private readonly CreateWrapper $createWrapper,
        private readonly FollowWrapper $followWrapper,
        private readonly FollowResponseWrapper $followResponseWrapper,
        private readonly UndoWrapper $undoWrapper,
        private readonly FlagFactory $flagFactory,
        private readonly AddRemoveFactory $addRemoveFactory,
        private readonly AnnounceWrapper $announceWrapper,
        private readonly LikeWrapper $likeWrapper,
        private readonly DeleteWrapper $deleteWrapper,
        private readonly UpdateWrapper $updateWrapper,
        private readonly UserRepository $userRepository,
        private readonly CollectionInfoWrapper $collectionInfoWrapper,
        private readonly BlockFactory $blockFactory,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('target', InputArgument::REQUIRED, 'the target file the generated markdown should be saved to');
        $this->addOption('overwrite', 'o', InputOption::VALUE_NONE, 'should the target file be overwritten in case it exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // do everything in a transaction so we can roll that back afterward
        $this->entityManager->beginTransaction();

        $this->settingsManager->set('KBIN_FEDERATION_ENABLED', false);
        $this->settingsManager->set('KBIN_DOMAIN', 'mbin.example');
        $context = $this->router->getContext();
        $context->setHost('mbin.example');

        $io = new SymfonyStyle($input, $output);
        $file = './docs/05-fediverse_developers/README.md';
        if (!file_exists($file)) {
            $io->error('File "'.$file.'" not found');

            return Command::FAILURE;
        }

        $content = file_get_contents($file);
        if (false === $content) {
            $io->error('File "'.$file.'" could not be read');

            return Command::FAILURE;
        }

        $target = $input->getArgument('target');
        $overwrite = $input->getOption('overwrite');

        if (file_exists($target) && !$overwrite) {
            $io->error('File "'.$target.'" already exists');

            return Command::FAILURE;
        }

        $content = $this->generateMarkdown($content);
        $this->entityManager->rollback();

        if (false === file_put_contents($target, $content)) {
            $io->error('File "'.$target.'" could not be written');

            return Command::FAILURE;
        }
        $io->success('Markdown has been generated and saved to "'.$target.'"');

        return Command::SUCCESS;
    }

    private function generateMarkdown(string $content): string
    {
        $image = $this->createImage();
        $imageDto = $this->imageFactory->createDto($image);
        $dto = UserDto::create('BentiGorlich', 'a@b.test', avatar: $imageDto, cover: $imageDto);
        $dto->plainPassword = 'secret';
        $user = $this->userManager->create($dto, verifyUserEmail: false, preApprove: true);
        $user = $this->userManager->edit($user, $dto);

        $dto = UserDto::create('Melroy', 'a2@b.test', avatar: $imageDto, cover: $imageDto);
        $dto->plainPassword = 'secret';
        $user2 = $this->userManager->create($dto, verifyUserEmail: false, preApprove: true);
        $user2 = $this->userManager->edit($user2, $dto);

        $this->userManager->follow($user, $user2);
        $this->userManager->follow($user2, $user);

        $dto = new MagazineDto();
        $dto->name = 'melroyMag';
        $dto->title = 'Melroys Magazine';
        $dto->description = 'Melroys wonderful magazine';
        $dto->icon = $image;
        $magazine = $this->magazineManager->create($dto, $user);

        $dto = new EntryDto();
        $dto->user = $user;
        $dto->magazine = $magazine;
        $dto->title = 'Bentis thread';
        $dto->body = 'Bentis thread in melroys magazine';
        $dto->lang = 'en';
        $entry = $this->entryManager->create($dto, $user, rateLimit: false, stickyIt: true);
        $entryCreate = $this->createWrapper->build($entry);

        $dto = new EntryCommentDto();
        $dto->user = $user;
        $dto->magazine = $magazine;
        $dto->entry = $entry;
        $dto->body = 'melroys comment';
        $dto->lang = 'en';
        $entryComment = $this->entryCommentManager->create($dto, $user2, rateLimit: false);
        $entryCommentCreate = $this->createWrapper->build($entryComment);

        $dto = new PostDto();
        $dto->user = $user;
        $dto->magazine = $magazine;
        $dto->lang = 'en';
        $dto->body = 'Melroys post';
        $post = $this->postManager->create($dto, $user, rateLimit: false);
        $postCreate = $this->createWrapper->build($post);

        $dto = new PostCommentDto();
        $dto->user = $user;
        $dto->magazine = $magazine;
        $dto->lang = 'en';
        $dto->body = 'Bentis post comment';
        $dto->post = $post;
        $postComment = $this->postCommentManager->create($dto, $user, rateLimit: false);
        $postCommentCreate = $this->createWrapper->build($postComment);

        $dto = new MessageDto();
        $dto->body = 'Bentis message';
        $thread = $this->messageManager->toThread($dto, $user, $user2);
        $message = $thread->getLastMessage();

        $userOutboxCollectionInfo = $this->collectionFactory->getUserOutboxCollection($user, false);
        $userOutboxCollectionItems = $this->collectionFactory->getUserOutboxCollectionItems($user, 1, false);
        $userFollowerCollection = $this->collectionInfoWrapper->build('ap_user_followers', ['username' => $user->username], $this->userRepository->findFollowers(1, $user)->getNbResults());
        unset($userFollowerCollection['@context']);
        $userFollowingCollection = $this->collectionInfoWrapper->build('ap_user_following', ['username' => $user->username], $this->userRepository->findFollowing(1, $user)->getNbResults());
        unset($userFollowingCollection['@context']);
        $moderatorCollection = $this->collectionFactory->getMagazineModeratorCollection($magazine, false);
        $pinnedCollection = $this->collectionFactory->getMagazinePinnedCollection($magazine, false);
        $magazineFollowersCollections = $this->collectionInfoWrapper->build('ap_magazine_followers', ['name' => $magazine->name], $magazine->subscriptionsCount);
        $magazineFollowersCollectionItems = [];

        $dto = ReportDto::create($entry, 'Spam');
        $report = $this->reportManager->report($dto, $user2);

        $activityUserFollow = $this->followWrapper->build($user, $user2);
        $activityUserUndoFollow = $this->undoWrapper->build($activityUserFollow);
        $activityUserAccept = $this->followResponseWrapper->build($user2, $activityUserFollow);
        $activityUserCreate = $entryCreate;
        $activityUserFlag = $this->flagFactory->build($report);
        $activityUserLike = $this->likeWrapper->build($user2, $entry);
        $activityUserUndoLike = $this->undoWrapper->build($activityUserLike);
        $activityUserAnnounce = $this->announceWrapper->build($user2, $entry, true);
        $activityUserUpdate = $this->updateWrapper->buildForActor($user2);
        $activityUserEdit = $this->updateWrapper->buildForActivity($entry);
        $activityUserDelete = $this->deleteWrapper->build($entry, includeContext: false);

        $magazineBan = new MagazineBan($magazine, $user, $user2, 'A very specific reason', \DateTimeImmutable::createFromFormat('Y-m-d', '2025-01-01'));
        $this->entityManager->persist($magazineBan);

        $activityModAddMod = $this->addRemoveFactory->buildAddModerator($user, $user2, $magazine);
        $activityModRemoveMod = $this->addRemoveFactory->buildRemoveModerator($user, $user2, $magazine);
        $activityModAddPin = $this->addRemoveFactory->buildAddPinnedPost($user, $entry);
        $activityModRemovePin = $this->addRemoveFactory->buildRemovePinnedPost($user, $entry);
        $activityModDelete = $this->deleteWrapper->adjustDeletePayload($user, $entryComment, false);
        $activityModBan = $this->blockFactory->createActivityFromMagazineBan($magazineBan);

        $activityMagAnnounce = $this->announceWrapper->build($magazine, $entryCreate);
        $activityAdminBan = $this->blockFactory->createActivityFromInstanceBan($user2, $user);

        $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        $replaceVariables = [
            '%@context%' => json_encode($this->contextsProvider->referencedContexts(), $jsonFlags),
            '%@context_additional%' => json_encode(ContextsProvider::embeddedContexts(), $jsonFlags),
            '%actor_instance%' => json_encode($this->instanceFactory->create(false), $jsonFlags),
            '%actor_user%' => json_encode($this->personFactory->create($user, false), $jsonFlags),
            '%actor_magazine%' => json_encode($this->groupFactory->create($magazine, false), $jsonFlags),
            '%object_entry%' => json_encode($this->entryPageFactory->create($entry, []), $jsonFlags),
            '%object_entry_comment%' => json_encode($this->entryCommentNoteFactory->create($entryComment, []), $jsonFlags),
            '%object_post%' => json_encode($this->postNoteFactory->create($post, []), $jsonFlags),
            '%object_post_comment%' => json_encode($this->postCommentNoteFactory->create($postComment, []), $jsonFlags),
            '%object_message%' => json_encode($this->messageFactory->build($message, false), $jsonFlags),
            '%collection_user_outbox%' => json_encode($userOutboxCollectionInfo, $jsonFlags),
            '%collection_items_user_outbox%' => json_encode($userOutboxCollectionItems, $jsonFlags),
            '%collection_user_followers%' => json_encode($userFollowerCollection, $jsonFlags),
            '%collection_user_followings%' => json_encode($userFollowingCollection, $jsonFlags),
            '%collection_magazine_outbox%' => json_encode(new \stdClass(), $jsonFlags),
            '%collection_magazine_followers%' => json_encode($magazineFollowersCollections, $jsonFlags),
            '%collection_items_magazine_followers%' => json_encode($magazineFollowersCollectionItems, $jsonFlags),
            '%collection_magazine_moderators%' => json_encode($moderatorCollection, $jsonFlags),
            '%collection_magazine_featured%' => json_encode($pinnedCollection, $jsonFlags),
            '%activity_user_follow%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityUserFollow, false), $jsonFlags),
            '%activity_user_undo_follow%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityUserUndoFollow, false), $jsonFlags),
            '%activity_user_accept%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityUserAccept, false), $jsonFlags),
            '%activity_user_create%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityUserCreate, false), $jsonFlags),
            '%activity_user_flag%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityUserFlag, false), $jsonFlags),
            '%activity_user_like%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityUserLike, false), $jsonFlags),
            '%activity_user_undo_like%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityUserUndoLike, false), $jsonFlags),
            '%activity_user_announce%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityUserAnnounce, false), $jsonFlags),
            '%activity_user_update_user%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityUserUpdate, false), $jsonFlags),
            '%activity_user_update_content%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityUserEdit, false), $jsonFlags),
            '%activity_user_delete%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityUserDelete, false), $jsonFlags),
            '%activity_mod_add_mod%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityModAddMod, false), $jsonFlags),
            '%activity_mod_remove_mod%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityModRemoveMod, false), $jsonFlags),
            '%activity_mod_add_pin%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityModAddPin, false), $jsonFlags),
            '%activity_mod_remove_pin%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityModRemovePin, false), $jsonFlags),
            '%activity_mod_delete%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityModDelete, false), $jsonFlags),
            '%activity_mod_ban%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityModBan, false), $jsonFlags),
            '%activity_mag_announce%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityMagAnnounce, false), $jsonFlags),
            '%activity_admin_ban%' => json_encode($this->activityJsonBuilder->buildActivityJson($activityAdminBan, false), $jsonFlags),
        ];

        foreach ($replaceVariables as $key => $value) {
            $content = str_replace($key, $value, $content);
        }

        return $content;
    }

    protected function createImage(): Image
    {
        $fileName = hash('sha256', 'random');
        $image = new Image($fileName, $fileName, $fileName, 100, 100, null);
        $this->entityManager->persist($image);

        return $image;
    }
}
