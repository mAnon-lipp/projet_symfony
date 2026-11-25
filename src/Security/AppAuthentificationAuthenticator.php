<?php

namespace App\Security;

class AppAuthentificationAuthenticator extends AbstractAppLoginFormAuthenticator
{
    public const LOGIN_ROUTE = 'app_login';

    protected function getLoginRoute(): string
    {
        return self::LOGIN_ROUTE;
    }

    protected function getSuccessRoute(): string
    {
        return 'app_home';
    }

    protected function shouldRedirectToTargetPath(string $targetPath): bool
    {
        return !$this->isAdminTargetPath($targetPath);
    }
}
