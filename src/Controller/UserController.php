<?php

namespace App\Controller;

class UserController extends AbstractController
{
    public function login(): string
    {
        return $this->twig->render('User/login.html.twig');
    }

    public function logout()
    {
    }

    public function register(): string
    {
        return $this->twig->render('User/register.html.twig');
    }
}
