<?php
declare(strict_types=1);

namespace Tds\Ext\ContactTickets\Tests;

use DI\Container;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tds\Ext\ContactTickets\ContactTicketsModule;
use Tds\Panel\Contract\UserContext;

/** Configurable UserContext double. */
final class FakeUser implements UserContext
{
    /** @param string[] $perms */
    public function __construct(private bool $auth = true, private bool $admin = false, private array $perms = [])
    {
    }

    public function isAuthenticated(): bool
    {
        return $this->auth;
    }

    public function userId(): ?int
    {
        return 1;
    }

    public function email(): ?string
    {
        return null;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    /** @return string[] */
    public function permissions(): array
    {
        return $this->perms;
    }

    public function has(string $permission): bool
    {
        return $this->admin || in_array($permission, $this->perms, true);
    }

    public function activeCompanyId(): ?int
    {
        return null;
    }
}

/** Route + RBAC + validation coverage without a DB (all tested paths short-circuit before the repo). */
final class ContactTicketsModuleTest extends TestCase
{
    private function appWith(UserContext $user): \Slim\App
    {
        $container = new Container();
        $container->set(UserContext::class, $user);
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        (new ContactTicketsModule())->register($app);
        return $app;
    }

    private function get(\Slim\App $app, string $path): \Psr\Http\Message\ResponseInterface
    {
        return $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path));
    }

    /** @param array<string,mixed> $body */
    private function post(\Slim\App $app, string $path, array $body): \Psr\Http\Message\ResponseInterface
    {
        return $app->handle(
            (new ServerRequestFactory())->createServerRequest('POST', $path)->withParsedBody($body)
        );
    }

    public function testMetadata(): void
    {
        $module = new ContactTicketsModule();
        self::assertSame('contact-tickets', $module->id());
        $ids = array_map(static fn ($p): string => $p->id, $module->permissions());
        self::assertSame(['contact:read', 'contact:write'], $ids);
        self::assertDirectoryExists($module->migrations()[0]);
    }

    public function testPublicSubmitValidatesPayload(): void
    {
        $res = $this->post($this->appWith(new FakeUser(auth: false)), '/contact', [
            'name' => 'A',
            'email' => 'bad',
            'message' => 'short',
        ]);
        self::assertSame(422, $res->getStatusCode());
    }

    public function testHoneypotSilentlyAccepts(): void
    {
        $res = $this->post($this->appWith(new FakeUser(auth: false)), '/contact', [
            'name' => 'Bot',
            'email' => 'bot@x.de',
            'message' => 'a fairly long spam message body here',
            'website' => 'http://spam',
        ]);
        self::assertSame(202, $res->getStatusCode());
    }

    public function testSummaryRequiresReadPermission(): void
    {
        self::assertSame(401, $this->get($this->appWith(new FakeUser(auth: false)), '/contact/summary')->getStatusCode());
        self::assertSame(403, $this->get($this->appWith(new FakeUser(perms: [])), '/contact/summary')->getStatusCode());
    }

    public function testMessagesRequireRead(): void
    {
        self::assertSame(403, $this->get($this->appWith(new FakeUser(perms: [])), '/contact/messages')->getStatusCode());
    }

    public function testReplyRequiresWrite(): void
    {
        // Unauthenticated → 401, authenticated-but-read-only → 403, both before the repo.
        self::assertSame(401, $this->post($this->appWith(new FakeUser(auth: false)), '/contact/messages/1/reply', ['body' => 'hi'])->getStatusCode());
        self::assertSame(403, $this->post($this->appWith(new FakeUser(perms: ['contact:read'])), '/contact/messages/1/reply', ['body' => 'hi'])->getStatusCode());
    }
}
