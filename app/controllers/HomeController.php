<?php
final class HomeController
{
    public function index(array $p): void
    {
        $count = db()->query('SELECT COUNT(*) FROM properties')->fetchColumn();
        view('home', ['propertyCount' => (int) $count]);
    }
}
