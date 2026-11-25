<?php

namespace App\Security;

class AppAdminAuthenticator extends AbstractAppLoginFormAuthenticator
{
    public const LOGIN_ROUTE = 'admin_login';

    protected function getLoginRoute(): string
    {
        return self::LOGIN_ROUTE;
    }

    protected function getSuccessRoute(): string
    {
        return 'admin';
    }

    protected function shouldRedirectToTargetPath(string $targetPath): bool
    {
        return $this->isAdminTargetPath($targetPath);
    }
}
