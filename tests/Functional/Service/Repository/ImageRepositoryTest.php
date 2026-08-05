<?php
declare(strict_types=1);

namespace App\Tests\Functional\Service\Repository;

use App\Entity\Client;
use App\Tests\WebTestCase;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;

class ImageRepositoryTest extends WebTestCase
{
    public function testIsImageReferencedEntryNone()
    {
        $img = $this->createImage('test.png');

        self::assertFalse($this->imageRepository->isImageReferenced($img));
    }

    public function testIsImageReferencedEntry()
    {
        $img = $this->createImage('test.png');

        $entry = $this->getEntryByTitle('entry');
        $entry->image = $img;
        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        self::assertTrue($this->imageRepository->isImageReferenced($img));
    }

    public function testIsImageReferencedEntryComment()
    {
        $img = $this->createImage('test.png');

        $entry = $this->getEntryByTitle('entry');
        $comment = $this->createEntryComment('image', $entry);
        $comment->image = $img;
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        self::assertTrue($this->imageRepository->isImageReferenced($img));
    }

    public function testIsImageReferencedPost()
    {
        $img = $this->createImage('test.png');

        $post = $this->createPost('post');
        $post->image = $img;
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        self::assertTrue($this->imageRepository->isImageReferenced($img));
    }

    public function testIsImageReferencedPostComment()
    {
        $img = $this->createImage('test.png');

        $post = $this->createPost('post');
        $comment = $this->createPostComment('image', $post);
        $comment->image = $img;
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        self::assertTrue($this->imageRepository->isImageReferenced($img));
    }

    public function testIsImageReferencedUserAvatar()
    {
        $img = $this->createImage('test.png');

        $user = $this->getUserByUsername('someone');
        $user->avatar = $img;
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        self::assertTrue($this->imageRepository->isImageReferenced($img));
    }

    public function testIsImageReferencedUserCover()
    {
        $img = $this->createImage('test.png');

        $user = $this->getUserByUsername('someone');
        $user->cover = $img;
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        self::assertTrue($this->imageRepository->isImageReferenced($img));
    }

    public function testIsImageReferencedMagazineIcon()
    {
        $img = $this->createImage('test.png');

        $magazine = $this->getMagazineByName('somemagazine');
        $magazine->icon = $img;
        $this->entityManager->persist($magazine);
        $this->entityManager->flush();

        self::assertTrue($this->imageRepository->isImageReferenced($img));
    }

    public function testIsImageReferencedMagazineBanner()
    {
        $img = $this->createImage('test.png');

        $magazine = $this->getMagazineByName('somemagazine');
        $magazine->banner = $img;
        $this->entityManager->persist($magazine);
        $this->entityManager->flush();

        self::assertTrue($this->imageRepository->isImageReferenced($img));
    }

    public function testIsImageReferencedOAuthClient()
    {
        $img = $this->createImage('test.png');

        /** @var ClientManagerInterface $manager */
        $manager = self::getContainer()->get(ClientManagerInterface::class);
        $client = new Client('dummy client', 'dummyclient', 'testsecret');
        $client->setDescription('An OAuth2 client for testIsImageReferencedOAuthClient');
        $client->setContactEmail('testIsImageReferencedOAuthClient@kbin.test');
        $client->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $client->setRedirectUris(new RedirectUri('https://localhost:3001'));
        $client->setImage($img);
        $manager->save($client);

        self::assertTrue($this->imageRepository->isImageReferenced($img));
    }
}
