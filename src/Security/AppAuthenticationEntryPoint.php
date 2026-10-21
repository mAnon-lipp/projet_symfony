<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class AppAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $loginRoute = $this->isAdminRequest($request) ? 'admin_login' : 'app_login';

        return new RedirectResponse($this->urlGenerator->generate($loginRoute));
    }

    private function isAdminRequest(Request $request): bool
    {
        $path = $request->getPathInfo();

        return $path === '/admin' || str_starts_with($path, '/admin/');
    }
}
