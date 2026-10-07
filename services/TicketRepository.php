<?php
declare(strict_types=1);

/**
 * Tikettien tietokantakyselyt yhdessä paikassa
 * (käytetään sekä HTML-sivuilla, REST API:ssa että AI-chatissa).
 */
final class TicketRepository
{
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT t.*, c.name AS category_name, cr.name AS creator_name,
                    asg.name AS assignee_name
             FROM tickets t
             LEFT JOIN categories c ON c.id = t.category_id
             LEFT JOIN users cr ON cr.id = t.created_by
             LEFT JOIN users asg ON asg.id = t.assigned_to
             WHERE t.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $ticket = $stmt->fetch();
        return $ticket ?: null;
    }

    /**
     * Hakee tiketit suodattimilla. $onlyUserId rajaa yhden käyttäjän tiketteihin.
     * Suodattimet: q, status, priority, category_id, assigned ('me'|'unassigned'), me (user id)
     */
    public static function search(array $filters, ?int $onlyUserId = null): array
    {
        $sql = 'SELECT t.id, t.title, t.description, t.status, t.priority, t.created_at, t.updated_at,
                       t.category_id, t.created_by, t.assigned_to,
                       c.name AS category_name, cr.name AS creator_name, asg.name AS assignee_name
                FROM tickets t
                LEFT JOIN categories c ON c.id = t.category_id
                LEFT JOIN users cr ON cr.id = t.created_by
                LEFT JOIN users asg ON asg.id = t.assigned_to
                WHERE 1 = 1';
        $params = [];

        if ($onlyUserId !== null) {
            $sql .= ' AND t.created_by = :user_id';
            $params['user_id'] = $onlyUserId;
        }

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= ' AND t.title LIKE :search';
            $params['search'] = '%' . $q . '%';
        }

        $status = (string)($filters['status'] ?? '');
        if ($status !== '' && in_array($status, allowedStatuses(), true)) {
            $sql .= ' AND t.status = :status';
            $params['status'] = $status;
        }

        $priority = (string)($filters['priority'] ?? '');
        if ($priority !== '' && in_array($priority, allowedPriorities(), true)) {
            $sql .= ' AND t.priority = :priority';
            $params['priority'] = $priority;
        }

        $categoryId = (string)($filters['category_id'] ?? '');
        if ($categoryId !== '' && ctype_digit($categoryId)) {
            $sql .= ' AND t.category_id = :category_id';
            $params['category_id'] = (int)$categoryId;
        }

        $assigned = (string)($filters['assigned'] ?? '');
        if ($assigned === 'me' && isset($filters['me'])) {
            $sql .= ' AND t.assigned_to = :me';
            $params['me'] = (int)$filters['me'];
        } elseif ($assigned === 'unassigned') {
            $sql .= ' AND t.assigned_to IS NULL';
        }

        $sql .= $onlyUserId !== null
            ? ' ORDER BY t.created_at DESC'
            : " ORDER BY FIELD(t.priority, 'Kiireellinen','Korkea','Normaali','Matala'), t.created_at DESC";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO tickets (title, description, category_id, status, priority, created_by)
             VALUES (:title, :description, :category_id, :status, :priority, :created_by)'
        );
        $stmt->execute([
            'title' => mb_substr($data['title'], 0, 200),
            'description' => $data['description'],
            'category_id' => $data['category_id'] ?? null,
            'status' => 'Uusi',
            'priority' => in_array($data['priority'] ?? '', allowedPriorities(), true) ? $data['priority'] : 'Normaali',
            'created_by' => $data['created_by'],
        ]);
        return (int)db()->lastInsertId();
    }

    public static function categories(): array
    {
        return db()->query('SELECT id, name, description FROM categories ORDER BY name')->fetchAll();
    }

    public static function categoryIdByName(?string $name): ?int
    {
        $stmt = db()->prepare('SELECT id FROM categories WHERE name = :name');
        $stmt->execute(['name' => (string)$name]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            $stmt->execute(['name' => 'Muu']);
            $id = $stmt->fetchColumn();
        }
        return $id === false ? null : (int)$id;
    }

    /** Opiskelija näkee vain omat tikettinsä, tukihenkilö/admin kaikki. */
    public static function canView(array $ticket, array $user): bool
    {
        return (int)$ticket['created_by'] === (int)$user['id'] || isStaff($user);
    }

    public static function comments(int $ticketId): array
    {
        $stmt = db()->prepare(
            'SELECT c.id, c.message, c.created_at, u.name AS author_name, u.role AS author_role
             FROM comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.ticket_id = :ticket_id
             ORDER BY c.created_at ASC, c.id ASC'
        );
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetchAll();
    }
}
