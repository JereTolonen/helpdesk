<?php
declare(strict_types=1);

final class StaffController
{
    public function dashboard(): void
    {
        $user = requireRole(['support', 'admin']);

        $filters = [
            'q' => get('q'),
            'status' => get('status'),
            'priority' => get('priority'),
            'category_id' => get('category_id'),
            'assigned' => get('assigned'), // 'me' | 'unassigned' | ''
            'me' => $user['id'],
        ];

        // Pikatilastot
        $stats = db()->query(
            "SELECT
                SUM(status = 'Uusi') AS new_count,
                SUM(status = 'Käsittelyssä') AS in_progress_count,
                SUM(status = 'Odottaa opiskelijaa') AS waiting_count,
                SUM(status IN ('Ratkaistu','Suljettu')) AS closed_count
             FROM tickets"
        )->fetch();

        // AI-chatin tilastot: kuinka moni keskustelu ratkesi ilman tikettiä
        $chatStats = db()->query(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'resolved') AS resolved,
                SUM(status = 'escalated') AS escalated
             FROM chat_conversations"
        )->fetch();

        render('staff/dashboard', [
            'pageTitle' => 'Tukihenkilön työpöytä',
            'filters' => $filters,
            'stats' => $stats,
            'chatStats' => $chatStats,
            'categories' => TicketRepository::categories(),
            'tickets' => TicketRepository::search($filters),
        ]);
    }
}
