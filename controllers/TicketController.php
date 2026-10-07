<?php
declare(strict_types=1);

final class TicketController
{
    /** Omat tiketit (opiskelija). */
    public function index(): void
    {
        $user = requireRole(['student']);

        $filters = [
            'q' => get('q'),
            'status' => get('status'),
            'priority' => get('priority'),
            'category_id' => get('category_id'),
        ];

        render('tickets/index', [
            'pageTitle' => 'Omat tiketit',
            'filters' => $filters,
            'categories' => TicketRepository::categories(),
            'tickets' => TicketRepository::search($filters, $user['id']),
        ]);
    }

    public function create(): void
    {
        requireRole(['student']);
        $this->renderForm([], ['title' => '', 'description' => '', 'category_id' => '', 'priority' => 'Normaali']);
    }

    public function store(): void
    {
        $user = requireRole(['student']);
        requireCsrf();

        $old = [
            'title' => post('title'),
            'description' => post('description'),
            'category_id' => post('category_id'),
            'priority' => post('priority', 'Normaali'),
        ];

        $errors = [];
        if (mb_strlen($old['title']) < 3) {
            $errors['title'] = 'Anna otsikko (vähintään 3 merkkiä).';
        }
        if (mb_strlen($old['description']) < 10) {
            $errors['description'] = 'Kuvaile ongelmaa tarkemmin (vähintään 10 merkkiä).';
        }
        if ($old['category_id'] === '' || !ctype_digit($old['category_id'])) {
            $errors['category_id'] = 'Valitse kategoria.';
        }
        if (!in_array($old['priority'], allowedPriorities(), true)) {
            $errors['priority'] = 'Virheellinen prioriteetti.';
        }

        if ($errors) {
            $this->renderForm($errors, $old);
            return;
        }

        $ticketId = TicketRepository::create([
            'title' => $old['title'],
            'description' => $old['description'],
            'category_id' => (int)$old['category_id'],
            'priority' => $old['priority'],
            'created_by' => $user['id'],
        ]);

        flash('success', 'Tiketti #' . $ticketId . ' luotu onnistuneesti.');
        redirect('/tickets/' . $ticketId);
    }

    public function show(int $id, array $errors = []): void
    {
        $user = requireLogin();
        $ticket = $this->loadTicket($id, $user);

        $isStaff = isStaff($user);
        $staffList = $isStaff
            ? db()->query("SELECT id, name FROM users WHERE role IN ('support','admin') ORDER BY name")->fetchAll()
            : [];

        // Onko tiketti luotu AI-chatista? Näytetään linkki keskusteluun.
        $stmt = db()->prepare('SELECT id FROM chat_conversations WHERE ticket_id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $conversationId = $stmt->fetchColumn() ?: null;

        render('tickets/show', [
            'pageTitle' => 'Tiketti #' . $id,
            'ticket' => $ticket,
            'comments' => TicketRepository::comments($id),
            'isStaff' => $isStaff,
            'isOwner' => (int)$ticket['created_by'] === $user['id'],
            'staffList' => $staffList,
            'conversationId' => $conversationId,
            'errors' => $errors,
        ]);
    }

    /** Tiketin lomaketoiminnot: kommentti, päivitys, käsittelyyn otto, sulkeminen. */
    public function action(int $id): void
    {
        $user = requireLogin();
        requireCsrf();
        $ticket = $this->loadTicket($id, $user);

        $isOwner = (int)$ticket['created_by'] === $user['id'];
        $isStaff = isStaff($user);
        $action = post('action');
        $errors = [];

        // --- Kommentin lisäys: sekä opiskelija (omaan tikettiinsä) että tukihenkilö ---
        if ($action === 'add_comment') {
            $message = post('message');
            if (mb_strlen($message) < 1) {
                $errors['message'] = 'Kommentti ei voi olla tyhjä.';
            } else {
                db()->prepare('INSERT INTO comments (ticket_id, user_id, message) VALUES (:ticket_id, :user_id, :message)')
                    ->execute(['ticket_id' => $id, 'user_id' => $user['id'], 'message' => $message]);

                // Jos opiskelija vastaa "Odottaa opiskelijaa" -tilassa olevaan tikettiin,
                // siirretään se takaisin käsittelyyn.
                if ($isOwner && $ticket['status'] === 'Odottaa opiskelijaa') {
                    db()->prepare('UPDATE tickets SET status = :status WHERE id = :id')
                        ->execute(['status' => 'Käsittelyssä', 'id' => $id]);
                }

                flash('success', 'Kommentti lisätty.');
                redirect('/tickets/' . $id);
            }
        }

        // --- Tilan / prioriteetin / vastuuhenkilön päivitys: vain tukihenkilö/admin ---
        if ($action === 'update_ticket' && $isStaff) {
            $newStatus = post('status');
            $newPriority = post('priority');
            $assignedTo = post('assigned_to');

            if (!in_array($newStatus, allowedStatuses(), true)) {
                $errors['status'] = 'Virheellinen tila.';
            }
            if (!in_array($newPriority, allowedPriorities(), true)) {
                $errors['priority'] = 'Virheellinen prioriteetti.';
            }

            if (!$errors) {
                db()->prepare('UPDATE tickets SET status = :status, priority = :priority, assigned_to = :assigned_to WHERE id = :id')
                    ->execute([
                        'status' => $newStatus,
                        'priority' => $newPriority,
                        'assigned_to' => ($assignedTo !== '' && ctype_digit($assignedTo)) ? (int)$assignedTo : null,
                        'id' => $id,
                    ]);
                flash('success', 'Tiketti päivitetty.');
                redirect('/tickets/' . $id);
            }
        }

        // --- "Ota käsittelyyn" -pikatoiminto tukihenkilölle ---
        if ($action === 'take_ticket' && $isStaff) {
            db()->prepare('UPDATE tickets SET status = :status, assigned_to = :assigned_to WHERE id = :id')
                ->execute(['status' => 'Käsittelyssä', 'assigned_to' => $user['id'], 'id' => $id]);
            flash('success', 'Otit tiketin käsittelyyn.');
            redirect('/tickets/' . $id);
        }

        // --- Tiketin sulkeminen: omistaja tai tukihenkilö ---
        if ($action === 'close_ticket' && ($isOwner || $isStaff)) {
            db()->prepare('UPDATE tickets SET status = :status WHERE id = :id')
                ->execute(['status' => 'Suljettu', 'id' => $id]);
            flash('success', 'Tukipyyntö suljettu.');
            redirect('/tickets/' . $id);
        }

        $this->show($id, $errors);
    }

    private function loadTicket(int $id, array $user): array
    {
        $ticket = TicketRepository::find($id);
        if (!$ticket) {
            abort(404, 'Tikettiä ei löytynyt.');
        }
        // Käyttöoikeuksien tarkistus palvelinpuolella.
        if (!TicketRepository::canView($ticket, $user)) {
            abort(403, 'Ei käyttöoikeutta tähän tikettiin.');
        }
        return $ticket;
    }

    private function renderForm(array $errors, array $old): void
    {
        render('tickets/new', [
            'pageTitle' => 'Uusi tiketti',
            'errors' => $errors,
            'old' => $old,
            'categories' => TicketRepository::categories(),
        ]);
    }
}
