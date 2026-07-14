<?php
declare(strict_types=1);

namespace Tds\Ext\ContactTickets;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Tds\Ext\ContactTickets\Domain\ContactRepository;
use Tds\Panel\Contract\AbstractModule;
use Tds\Panel\Contract\Email;
use Tds\Panel\Contract\Mailer;
use Tds\Panel\Contract\PermissionDef;
use Tds\Panel\Contract\UserContext;

/**
 * Backend Module for the contact-form inbox. `POST /contact` is PUBLIC (the
 * marketing site's form submits here) — validated + honeypot-guarded, stored as
 * a contact_message, and (best-effort) the admin is notified via the core Mailer.
 * The admin inbox (`/contact/*`) is gated by `contact:read`/`contact:write`.
 */
final class ContactTicketsModule extends AbstractModule
{
    private const STATUSES = ['new', 'handled', 'spam'];

    public function id(): string
    {
        return 'contact-tickets';
    }

    /** @return PermissionDef[] */
    public function permissions(): array
    {
        return [
            new PermissionDef('contact:read', 'Kontaktanfragen ansehen', 'contact-tickets'),
            new PermissionDef('contact:write', 'Kontaktanfragen bearbeiten', 'contact-tickets'),
        ];
    }

    /** @return string[] */
    public function migrations(): array
    {
        return [__DIR__ . '/../db/migrations'];
    }

    public function register(App $app): void
    {
        $c = $app->getContainer();
        if ($c !== null && !$c->has(ContactRepository::class)) {
            $c->set(ContactRepository::class, static fn ($c) => new ContactRepository($c->get(PDO::class)));
        }

        // PUBLIC — the marketing contact form submits here (no auth).
        $app->post('/contact', function (Request $req, Response $res) use ($c): Response {
            $body = (array) $req->getParsedBody();
            // Honeypot: a filled hidden "website" field ⇒ bot. Accept silently.
            if (trim((string) ($body['website'] ?? '')) !== '') {
                return self::json($res, ['ok' => true], 202);
            }
            $name = trim((string) ($body['name'] ?? ''));
            $email = strtolower(trim((string) ($body['email'] ?? '')));
            $message = trim((string) ($body['message'] ?? ''));
            if (mb_strlen($name) < 2 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($message) < 20) {
                return self::json($res, ['error' => 'Invalid contact payload'], 422);
            }
            $company = self::optional($body['company'] ?? null, 200);
            $subject = self::optional($body['subject'] ?? null, 200);
            $id = $c->get(ContactRepository::class)->create($name, $email, $company, $subject, mb_substr($message, 0, 10000));
            self::notifyAdmin($c->get(Mailer::class), $id, $name, $email);
            return self::json($res, ['id' => $id], 201);
        });

        $app->get('/contact/summary', function (Request $req, Response $res) use ($c): Response {
            if (($deny = self::require($c->get(UserContext::class), 'contact:read', $res)) !== null) {
                return $deny;
            }
            return self::json($res, ['new' => $c->get(ContactRepository::class)->newCount()]);
        });

        $app->get('/contact/messages', function (Request $req, Response $res) use ($c): Response {
            if (($deny = self::require($c->get(UserContext::class), 'contact:read', $res)) !== null) {
                return $deny;
            }
            $status = $req->getQueryParams()['status'] ?? null;
            $status = in_array($status, self::STATUSES, true) ? (string) $status : null;
            return self::json($res, ['messages' => $c->get(ContactRepository::class)->list($status)]);
        });

        $app->get('/contact/messages/{id:[0-9]+}', function (Request $req, Response $res, array $args) use ($c): Response {
            if (($deny = self::require($c->get(UserContext::class), 'contact:read', $res)) !== null) {
                return $deny;
            }
            $msg = $c->get(ContactRepository::class)->find((int) $args['id']);
            if ($msg === null) {
                return self::json($res, ['error' => 'Not found'], 404);
            }
            return self::json($res, $msg);
        });

        $app->patch('/contact/messages/{id:[0-9]+}', function (Request $req, Response $res, array $args) use ($c): Response {
            if (($deny = self::require($c->get(UserContext::class), 'contact:write', $res)) !== null) {
                return $deny;
            }
            $repo = $c->get(ContactRepository::class);
            $id = (int) $args['id'];
            if ($repo->find($id) === null) {
                return self::json($res, ['error' => 'Not found'], 404);
            }
            $status = (string) (((array) $req->getParsedBody())['status'] ?? '');
            if (!in_array($status, self::STATUSES, true)) {
                return self::json($res, ['error' => 'status must be new|handled|spam'], 422);
            }
            $repo->setStatus($id, $status);
            return self::json($res, ['ok' => true]);
        });
    }

    // --- helpers ---------------------------------------------------------------

    private static function notifyAdmin(Mailer $mailer, int $id, string $name, string $email): void
    {
        $to = (string) (getenv('CONTACT_ADMIN_EMAIL') ?: getenv('TICKET_ADMIN_EMAIL') ?: '');
        if ($to === '' || !$mailer->isConfigured()) {
            return;
        }
        $mailer->send(new Email(
            $to,
            '',
            "Neue Kontaktanfrage #{$id} von {$name}",
            '<p>Neue Kontaktanfrage über das Formular.</p><p><strong>#' . $id . '</strong> — '
                . htmlspecialchars($name) . ' &lt;' . htmlspecialchars($email) . '&gt;</p>',
            null,
            $email, // Reply-To the submitter
        ));
    }

    private static function optional(mixed $value, int $limit): ?string
    {
        $v = trim((string) ($value ?? ''));
        return $v === '' ? null : mb_substr($v, 0, $limit);
    }

    private static function require(UserContext $user, string $permission, Response $res): ?Response
    {
        if (!$user->isAuthenticated()) {
            return self::json($res, ['error' => 'Unauthorized'], 401);
        }
        if (!$user->has($permission)) {
            return self::json($res, ['error' => 'Forbidden'], 403);
        }
        return null;
    }

    private static function json(Response $res, mixed $data, int $status = 200): Response
    {
        $res->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));
        return $res->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
