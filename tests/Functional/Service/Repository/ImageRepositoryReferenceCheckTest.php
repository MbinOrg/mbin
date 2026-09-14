<?php
declare(strict_types=1);

namespace App\Tests\Functional\Service\Repository;

use App\Entity\Client;
use App\Tests\WebTestCase;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;

class ImageRepositoryReferenceCheckTest extends WebTestCase
{
    public function testIsImageReferencedEntryNone()
    {
        $img1 = $this->createImage('test1.png');
        $img2 = $this->createImage('test2.png');

        $result = $this->imageRepository->areImagesReferenced([$img1, $img2]);
        self::assertCount(2, $result);
        self::assertFalse($result[$img1->getId()]);
        self::assertFalse($result[$img2->getId()]);
    }

    public function testIsImageReferencedEntry()
    {
        $img1 = $this->createImage('test1.png');
        $img2 = $this->createImage('test2.png');

        $entry = $this->getEntryByTitle('entry');
        $entry->image = $img1;
        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        $result = $this->imageRepository->areImagesReferenced([$img1, $img2]);
        self::assertCount(2, $result);
        self::assertTrue($result[$img1->getId()]);
        self::assertFalse($result[$img2->getId()]);
    }

    public function testIsImageReferencedEntryComment()
    {
        $img1 = $this->createImage('test1.png');
        $img2 = $this->createImage('test2.png');

        $entry = $this->getEntryByTitle('entry');
        $comment = $this->createEntryComment('image', $entry);
        $comment->image = $img1;
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $result = $this->imageRepository->areImagesReferenced([$img1, $img2]);
        self::assertCount(2, $result);
        self::assertTrue($result[$img1->getId()]);
        self::assertFalse($result[$img2->getId()]);
    }

    public function testIsImageReferencedPost()
    {
        $img1 = $this->createImage('test1.png');
        $img2 = $this->createImage('test2.png');

        $post = $this->createPost('post');
        $post->image = $img1;
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $result = $this->imageRepository->areImagesReferenced([$img1, $img2]);
        self::assertCount(2, $result);
        self::assertTrue($result[$img1->getId()]);
        self::assertFalse($result[$img2->getId()]);
    }

    public function testIsImageReferencedPostComment()
    {
        $img1 = $this->createImage('test1.png');
        $img2 = $this->createImage('test2.png');

        $post = $this->createPost('post');
        $comment = $this->createPostComment('image', $post);
        $comment->image = $img1;
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $result = $this->imageRepository->areImagesReferenced([$img1, $img2]);
        self::assertCount(2, $result);
        self::assertTrue($result[$img1->getId()]);
        self::assertFalse($result[$img2->getId()]);
    }

    public function testIsImageReferencedUserAvatar()
    {
        $img1 = $this->createImage('test1.png');
        $img2 = $this->createImage('test2.png');

        $user = $this->getUserByUsername('someone');
        $user->avatar = $img1;
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $result = $this->imageRepository->areImagesReferenced([$img1, $img2]);
        self::assertCount(2, $result);
        self::assertTrue($result[$img1->getId()]);
        self::assertFalse($result[$img2->getId()]);
    }

    public function testIsImageReferencedUserCover()
    {
        $img1 = $this->createImage('test1.png');
        $img2 = $this->createImage('test2.png');

        $user = $this->getUserByUsername('someone');
        $user->cover = $img1;
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $result = $this->imageRepository->areImagesReferenced([$img1, $img2]);
        self::assertCount(2, $result);
        self::assertTrue($result[$img1->getId()]);
        self::assertFalse($result[$img2->getId()]);
    }

    public function testIsImageReferencedMagazineIcon()
    {
        $img1 = $this->createImage('test1.png');
        $img2 = $this->createImage('test2.png');

        $magazine = $this->getMagazineByName('somemagazine');
        $magazine->icon = $img1;
        $this->entityManager->persist($magazine);
        $this->entityManager->flush();

        $result = $this->imageRepository->areImagesReferenced([$img1, $img2]);
        self::assertCount(2, $result);
        self::assertTrue($result[$img1->getId()]);
        self::assertFalse($result[$img2->getId()]);
    }

    public function testIsImageReferencedMagazineBanner()
    {
        $img1 = $this->createImage('test1.png');
        $img2 = $this->createImage('test2.png');

        $magazine = $this->getMagazineByName('somemagazine');
        $magazine->banner = $img1;
        $this->entityManager->persist($magazine);
        $this->entityManager->flush();

        $result = $this->imageRepository->areImagesReferenced([$img1, $img2]);
        self::assertCount(2, $result);
        self::assertTrue($result[$img1->getId()]);
        self::assertFalse($result[$img2->getId()]);
    }

    public function testIsImageReferencedOAuthClient()
    {
        $img1 = $this->createImage('test1.png');
        $img2 = $this->createImage('test2.png');

        /** @var ClientManagerInterface $manager */
        $manager = self::getContainer()->get(ClientManagerInterface::class);
        $client = new Client('dummy client', 'dummyclient', 'testsecret');
        $client->setDescription('An OAuth2 client for testIsImageReferencedOAuthClient');
        $client->setContactEmail('testIsImageReferencedOAuthClient@kbin.test');
        $client->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $client->setRedirectUris(new RedirectUri('https://localhost:3001'));
        $client->setImage($img1);
        $manager->save($client);

        $result = $this->imageRepository->areImagesReferenced([$img1, $img2]);
        self::assertCount(2, $result);
        self::assertTrue($result[$img1->getId()]);
        self::assertFalse($result[$img2->getId()]);
    }
}
