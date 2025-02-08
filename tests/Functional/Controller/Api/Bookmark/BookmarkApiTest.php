<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Bookmark;

use App\Entity\User;
use App\Tests\WebTestCase;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertIsArray;

class BookmarkApiTest extends WebTestCase
{
    private User $user;
    private string $token;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = $this->getUserByUsername('user');
        $this->client->loginUser($this->user);
        self::createOAuth2PublicAuthCodeClient();
        $codes = self::getPublicAuthorizationCodeTokenResponse($this->client, scopes: 'read bookmark bookmark_list');
        $this->token = $codes['token_type'].' '.$codes['access_token'];
        // it seems that the oauth flow detaches the user object from the entity manager, so fetch it again
        $this->user = $this->userRepository->findOneByUsername('user');
    }

    public function testBookmarkEntryToDefault(): void
    {
        $entry = $this->getEntryByTitle('entry');
        $this->client->request('PUT', "/api/bos/{$entry->getId()}/entry", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $this->bookmarkListRepository->findOneByUserDefault($this->user));
        self::assertIsArray($bookmarks);
        self::assertCount(1, $bookmarks);
    }

    public function testBookmarkEntryCommentToDefault(): void
    {
        $entry = $this->getEntryByTitle('entry');
        $comment = $this->createEntryComment('comment', $entry);
        $this->client->request('PUT', "/api/bos/{$comment->getId()}/entry_comment", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $this->bookmarkListRepository->findOneByUserDefault($this->user));
        self::assertIsArray($bookmarks);
        self::assertCount(1, $bookmarks);
    }

    public function testBookmarkPostToDefault(): void
    {
        $post = $this->createPost('post');
        $this->client->request('PUT', "/api/bos/{$post->getId()}/post", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $this->bookmarkListRepository->findOneByUserDefault($this->user));
        self::assertIsArray($bookmarks);
        self::assertCount(1, $bookmarks);
    }

    public function testBookmarkPostCommentToDefault(): void
    {
        $post = $this->createPost('entry');
        $comment = $this->createPostComment('comment', $post);
        $this->client->request('PUT', "/api/bos/{$comment->getId()}/post_comment", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $this->bookmarkListRepository->findOneByUserDefault($this->user));
        self::assertIsArray($bookmarks);
        self::assertCount(1, $bookmarks);
    }

    public function testRemoveBookmarkEntryFromDefault(): void
    {
        $entry = $this->getEntryByTitle('entry');
        $this->client->request('PUT', "/api/bos/{$entry->getId()}/entry", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $list = $this->bookmarkListRepository->findOneByUserDefault($this->user);
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(1, $bookmarks);

        $this->client->request('DELETE', "/api/rbo/{$entry->getId()}/entry", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(0, $bookmarks);
    }

    public function testRemoveBookmarkEntryCommentFromDefault(): void
    {
        $entry = $this->getEntryByTitle('entry');
        $comment = $this->createEntryComment('comment', $entry);
        $this->client->request('PUT', "/api/bos/{$comment->getId()}/entry_comment", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $list = $this->bookmarkListRepository->findOneByUserDefault($this->user);
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(1, $bookmarks);

        $this->client->request('DELETE', "/api/rbo/{$comment->getId()}/entry_comment", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(0, $bookmarks);
    }

    public function testRemoveBookmarkPostFromDefault(): void
    {
        $post = $this->createPost('post');
        $this->client->request('PUT', "/api/bos/{$post->getId()}/post", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $list = $this->bookmarkListRepository->findOneByUserDefault($this->user);
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(1, $bookmarks);

        $this->client->request('DELETE', "/api/rbo/{$post->getId()}/post", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(0, $bookmarks);
    }

    public function testRemoveBookmarkPostCommentFromDefault(): void
    {
        $post = $this->createPost('entry');
        $comment = $this->createPostComment('comment', $post);
        $this->client->request('PUT', "/api/bos/{$comment->getId()}/post_comment", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $list = $this->bookmarkListRepository->findOneByUserDefault($this->user);
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(1, $bookmarks);

        $this->client->request('DELETE', "/api/rbo/{$comment->getId()}/post_comment", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(0, $bookmarks);
    }

    public function testBookmarkEntryToList(): void
    {
        $this->entityManager->refresh($this->user);
        $list = $this->bookmarkManager->createList($this->user, 'list');

        $entry = $this->getEntryByTitle('entry');
        $this->client->request('PUT', "/api/bol/{$entry->getId()}/entry/$list->name", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(1, $bookmarks);
    }

    public function testBookmarkEntryCommentToList(): void
    {
        $list = $this->bookmarkManager->createList($this->user, 'list');
        $entry = $this->getEntryByTitle('entry');
        $comment = $this->createEntryComment('comment', $entry);
        $this->client->request('PUT', "/api/bol/{$comment->getId()}/entry_comment/$list->name", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(1, $bookmarks);
    }

    public function testBookmarkPostToList(): void
    {
        $list = $this->bookmarkManager->createList($this->user, 'list');
        $post = $this->createPost('post');
        $this->client->request('PUT', "/api/bol/{$post->getId()}/post/$list->name", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(1, $bookmarks);
    }

    public function testBookmarkPostCommentToList(): void
    {
        $list = $this->bookmarkManager->createList($this->user, 'list');
        $post = $this->createPost('entry');
        $comment = $this->createPostComment('comment', $post);
        $this->client->request('PUT', "/api/bol/{$comment->getId()}/post_comment/$list->name", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(1, $bookmarks);
    }

    public function testRemoveBookmarkEntryFromList(): void
    {
        $list = $this->bookmarkManager->createList($this->user, 'list');
        $entry = $this->getEntryByTitle('entry');
        $this->client->request('PUT', "/api/bol/{$entry->getId()}/entry/$list->name", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(1, $bookmarks);

        $this->client->request('DELETE', "/api/rbol/{$entry->getId()}/entry/$list->name", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(0, $bookmarks);
    }

    public function testRemoveBookmarkEntryCommentFromList(): void
    {
        $list = $this->bookmarkManager->createList($this->user, 'list');
        $entry = $this->getEntryByTitle('entry');
        $comment = $this->createEntryComment('comment', $entry);
        $this->client->request('PUT', "/api/bol/{$comment->getId()}/entry_comment/$list->name", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(1, $bookmarks);

        $this->client->request('DELETE', "/api/rbol/{$comment->getId()}/entry_comment/$list->name", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(0, $bookmarks);
    }

    public function testRemoveBookmarkPostFromList(): void
    {
        $list = $this->bookmarkManager->createList($this->user, 'list');
        $post = $this->createPost('post');
        $this->client->request('PUT', "/api/bol/{$post->getId()}/post/$list->name", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(1, $bookmarks);

        $this->client->request('DELETE', "/api/rbol/{$post->getId()}/post/$list->name", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(0, $bookmarks);
    }

    public function testRemoveBookmarkPostCommentFromList(): void
    {
        $list = $this->bookmarkManager->createList($this->user, 'list');
        $post = $this->createPost('entry');
        $comment = $this->createPostComment('comment', $post);
        $this->client->request('PUT', "/api/bol/{$comment->getId()}/post_comment/$list->name", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(1, $bookmarks);

        $this->client->request('DELETE', "/api/rbol/{$comment->getId()}/post_comment/$list->name", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $bookmarks = $this->bookmarkRepository->findByList($this->user, $list);
        self::assertIsArray($bookmarks);
        self::assertCount(0, $bookmarks);
    }

    public function testBookmarkedEntryJson(): void
    {
        $entry = $this->getEntryByTitle('entry');
        $list = $this->bookmarkManager->createList($this->user, 'list');
        $this->bookmarkManager->addBookmarkToDefaultList($this->user, $entry);
        $this->bookmarkManager->addBookmark($this->user, $list, $entry);
        $this->client->request('GET', "/api/entry/{$entry->getId()}", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);
        assertIsArray($jsonData['bookmarks']);
        assertCount(2, $jsonData['bookmarks']);
        self::assertContains('list', $jsonData['bookmarks']);
    }

    public function testBookmarkedEntryCommentJson(): void
    {
        $entry = $this->getEntryByTitle('entry');
        $comment = $this->createEntryComment('comment', $entry);
        $list = $this->bookmarkManager->createList($this->user, 'list');
        $this->bookmarkManager->addBookmarkToDefaultList($this->user, $comment);
        $this->bookmarkManager->addBookmark($this->user, $list, $comment);
        $this->client->request('GET', "/api/comments/{$comment->getId()}", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);
        assertIsArray($jsonData['bookmarks']);
        assertCount(2, $jsonData['bookmarks']);
        self::assertContains('list', $jsonData['bookmarks']);
    }

    public function testBookmarkedPostJson(): void
    {
        $post = $this->createPost('post');
        $list = $this->bookmarkManager->createList($this->user, 'list');
        $this->bookmarkManager->addBookmarkToDefaultList($this->user, $post);
        $this->bookmarkManager->addBookmark($this->user, $list, $post);
        $this->client->request('GET', "/api/post/{$post->getId()}", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);
        assertIsArray($jsonData['bookmarks']);
        assertCount(2, $jsonData['bookmarks']);
        self::assertContains('list', $jsonData['bookmarks']);
    }

    public function testBookmarkedPostCommentJson(): void
    {
        $post = $this->createPost('post');
        $comment = $this->createPostComment('comment', $post);
        $list = $this->bookmarkManager->createList($this->user, 'list');
        $this->bookmarkManager->addBookmarkToDefaultList($this->user, $comment);
        $this->bookmarkManager->addBookmark($this->user, $list, $comment);
        $this->client->request('GET', "/api/post-comments/{$comment->getId()}", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);
        assertIsArray($jsonData['bookmarks']);
        assertCount(2, $jsonData['bookmarks']);
        self::assertContains('list', $jsonData['bookmarks']);
    }

    public function testBookmarkListFront(): void
    {
        $list = $this->bookmarkManager->createList($this->user, 'list');
        $entry = $this->getEntryByTitle('entry');
        $comment = $this->createEntryComment('comment', $entry);
        $comment2 = $this->createEntryComment('coment2', $entry, parent: $comment);

        $post = $this->createPost('post');
        $postComment = $this->createPostComment('comment', $post);
        $postComment2 = $this->createPostComment('comment2', $post, parent: $postComment);

        $this->bookmarkManager->addBookmark($this->user, $list, $entry);
        $this->bookmarkManager->addBookmark($this->user, $list, $comment);
        $this->bookmarkManager->addBookmark($this->user, $list, $comment2);
        $this->bookmarkManager->addBookmark($this->user, $list, $post);
        $this->bookmarkManager->addBookmark($this->user, $list, $postComment);
        $this->bookmarkManager->addBookmark($this->user, $list, $postComment2);

        $this->client->request('GET', "/api/bookmark-lists/show?list_id={$list->getId()}", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        assertIsArray($jsonData['items']);
        assertCount(6, $jsonData['items']);
    }
}
