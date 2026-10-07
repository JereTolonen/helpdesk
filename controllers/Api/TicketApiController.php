<?php
declare(strict_types=1);

namespace Api;

use TicketRepository;

/**
 * Tikettien REST API.
 *
 *   GET    /api/tickets        omat tiketit (tukihenkilö/admin: kaikki), suodattimet ?status=&priority=&q=
 *   POST   /api/ticket         luo tiketti { title, description, category_id?, priority? }
 *   GET    /api/ticket/{id}    yksittäinen tiketti kommentteineen
 *   PUT    /api/ticket/{id}    päivitä (omistaja: title/description/status=Suljettu, staff: status/priority/assigned_to)
 *   DELETE /api/ticket/{id}    poista (admin tai omistaja, jos tila on "Uusi")
 */
final class TicketApiController
{
    public function index(): void
    {
        $user = requireApiUser();
        $filters = [
            'q' => get('q'),
            'status' => get('status'),
            'priority' => get('priority'),
            'category_id' => get('category_id'),
            'assigned' => get('assigned'),
            'me' => $user['id'],
        ];
        $tickets = TicketRepository::search($filters, isStaff($user) ? null : $user['id']);
        jsonResponse(['tickets' => array_map([$this, 'present'], $tickets)]);
    }

    public function show(int $id): void
    {
        $user = requireApiUser();
        $ticket = $this->load($id, $user);
        jsonResponse([
            'ticket' => $this->present($ticket),
            'comments' => TicketRepository::comments($id),
        ]);
    }

    public function store(): void
    {
        $user = requireApiUser();
        requireApiCsrf();
        $in = jsonInput();

        $title = trim((string)($in['title'] ?? ''));
        $description = trim((string)($in['description'] ?? ''));
        $priority = (string)($in['priority'] ?? 'Normaali');
        $categoryId = isset($in['category_id']) && ctype_digit((string)$in['category_id']) ? (int)$in['category_id'] : null;

        $errors = [];
        if (mb_strlen($title) < 3) {
            $errors['title'] = 'Otsikko on liian lyhyt (vähintään 3 merkkiä).';
        }
        if (mb_strlen($description) < 10) {
            $errors['description'] = 'Kuvaus on liian lyhyt (vähintään 10 merkkiä).';
        }
        if (!in_array($priority, allowedPriorities(), true)) {
            $errors['priority'] = 'Virheellinen prioriteetti.';
        }
        if ($errors) {
            jsonError('Virheelliset tiedot.', 422, ['errors' => $errors]);
        }

        $id = TicketRepository::create([
            'title' => $title,
            'description' => $description,
            'category_id' => $categoryId,
            'priority' => $priority,
            'created_by' => $user['id'],
        ]);

        jsonResponse(['ticket' => $this->present(TicketRepository::find($id))], 201);
    }

    public function update(int $id): void
    {
        $user = requireApiUser();
        requireApiCsrf();
        $ticket = $this->load($id, $user);
        $in = jsonInput();

        $isOwner = (int)$ticket['created_by'] === $user['id'];
        $fields = [];

        if ($isOwner) {
            if (isset($in['title']) && mb_strlen(trim((string)$in['title'])) >= 3) {
                $fields['title'] = mb_substr(trim((string)$in['title']), 0, 200);
            }
            if (isset($in['description']) && mb_strlen(trim((string)$in['description'])) >= 10) {
                $fields['description'] = trim((string)$in['description']);
            }
            // Opiskelija voi sulkea oman tikettinsä.
            if (($in['status'] ?? null) === 'Suljettu') {
                $fields['status'] = 'Suljettu';
            }
        }

        if (isStaff($user)) {
            if (isset($in['status']) && in_array($in['status'], allowedStatuses(), true)) {
                $fields['status'] = $in['status'];
            }
            if (isset($in['priority']) && in_array($in['priority'], allowedPriorities(), true)) {
                $fields['priority'] = $in['priority'];
            }
            if (array_key_exists('assigned_to', $in)) {
                $fields['assigned_to'] = ctype_digit((string)$in['assigned_to']) ? (int)$in['assigned_to'] : null;
            }
        }

        if (!$fields) {
            jsonError('Ei päivitettäviä kenttiä tai ei oikeutta muuttaa niitä.', 422);
        }

        $set = implode(', ', array_map(static fn(string $f): string => "$f = :$f", array_keys($fields)));
        db()->prepare("UPDATE tickets SET $set WHERE id = :id")->execute($fields + ['id' => $id]);

        jsonResponse(['ticket' => $this->present(TicketRepository::find($id))]);
    }

    public function destroy(int $id): void
    {
        $user = requireApiUser();
        requireApiCsrf();
        $ticket = $this->load($id, $user);

        $isOwner = (int)$ticket['created_by'] === $user['id'];
        if (!($user['role'] === 'admin' || ($isOwner && $ticket['status'] === 'Uusi'))) {
            jsonError('Vain ylläpitäjä tai tiketin luoja (tila "Uusi") voi poistaa tiketin.', 403);
        }

        db()->prepare('DELETE FROM tickets WHERE id = :id')->execute(['id' => $id]);
        jsonResponse(['deleted' => true]);
    }

    /* ------------------------------------------------------------ */

    private function load(int $id, array $user): array
    {
        $ticket = TicketRepository::find($id);
        if (!$ticket || !TicketRepository::canView($ticket, $user)) {
            jsonError('Tikettiä ei löytynyt.', 404);
        }
        return $ticket;
    }

    private function present(array $t): array
    {
        return [
            'id' => (int)$t['id'],
            'title' => $t['title'],
            'description' => $t['description'] ?? null,
            'status' => $t['status'],
            'priority' => $t['priority'],
            'category' => $t['category_name'] ?? null,
            'category_id' => $t['category_id'] !== null ? (int)$t['category_id'] : null,
            'created_by' => (int)$t['created_by'],
            'creator' => $t['creator_name'] ?? null,
            'assigned_to' => $t['assigned_to'] !== null ? (int)$t['assigned_to'] : null,
            'assignee' => $t['assignee_name'] ?? null,
            'created_at' => $t['created_at'],
            'updated_at' => $t['updated_at'],
            'url' => url('/tickets/' . (int)$t['id']),
        ];
    }
}
