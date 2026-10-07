<?php
declare(strict_types=1);

final class HomeController
{
    public function index(): void
    {
        render('home', ['pageTitle' => 'Etusivu']);
    }
}
