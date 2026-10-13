<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\AjaxController;
use App\DTO\internal\EmbedDto;
use App\Repository\UserPushSubscriptionRepository;
use App\Service\EmbedService;
use App\Service\Notification\UserPushSubscriptionManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class AjaxControllerTest extends TestCase
{
    #[DataProvider('previewProvider')]
    public function testPreviewDoesNotReloadRejectedContent(?string $html, ?string $embedUrl, bool $expectFrame): void
    {
        $dto = new EmbedDto();
        $dto->url = 'http://mbin.localhost:8080/_dev/m3u8-embed/master.m3u8';
        $dto->html = $html;
        $dto->embedUrl = $embedUrl;
        $service = $this->createStub(EmbedService::class);
        $service->method('fetch')->willReturn($dto);
        $controller = new AjaxController(
            $this->createStub(UserPushSubscriptionRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(UserPushSubscriptionManager::class),
            $service,
            $this->createStub(TranslatorInterface::class),
            $this->createStub(SettingsManager::class),
            $this->createStub(Security::class),
        );
        $container = new Container();
        $container->set('twig', new Environment(new FilesystemLoader(\dirname(__DIR__, 3).'/templates'), ['autoescape' => 'html']));
        $controller->setContainer($container);

        $response = $controller->fetchEmbed(Request::create('/ajax/fetch_embed', 'GET', ['url' => $dto->url]));
        $preview = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)['html'];

        self::assertSame(200, $response->getStatusCode());
        if (!$expectFrame) {
            self::assertSame('<div class="preview"></div>', $preview);
        } else {
            self::assertStringContainsString('<iframe', $preview);
            self::assertStringNotContainsString('src="'.$dto->url.'"', $preview);
        }
    }

    /**
     * @return iterable<string, array{?string, ?string, bool}>
     */
    public static function previewProvider(): iterable
    {
        yield 'rejected HTML' => [null, null, false];
        yield 'empty HTML' => ['', null, false];
        yield 'HTML embed' => ['<p>Preview</p>', null, true];
        yield 'URL embed' => [null, 'https://example.com/player', true];
    }
}
