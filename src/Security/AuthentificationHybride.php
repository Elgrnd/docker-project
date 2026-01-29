<?php

namespace App\Security;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\ConfigurationLdap;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Psr\Log\LoggerInterface;

class AuthentificationHybride extends AbstractLoginFormAuthenticator
{
    public const LOGIN_ROUTE = 'connexion';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UtilisateurRepository $utilisateurRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private LoggerInterface $logger
    ) {}

    // ✅ CORRIGÉ : Simplifie la méthode supports
    public function supports(Request $request): bool
    {
        $isLoginRoute = $request->attributes->get('_route') === self::LOGIN_ROUTE;
        $isPost = $request->isMethod('POST');

        $this->logger->debug("supports() - Route: {$request->attributes->get('_route')}, Method: {$request->getMethod()}, isLoginRoute: " . ($isLoginRoute ? 'true' : 'false') . ", isPost: " . ($isPost ? 'true' : 'false'));

        return $isLoginRoute && $isPost;
    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->request->get('_username', '');
        $password = $request->request->get('_password', '');
        $csrfToken = $request->request->get('_csrf_token', '');

        $this->logger->info("authenticate() appelé pour: $username");

        $request->getSession()->set('_security.last_username', $username);

        return new Passport(
            new UserBadge($username, function($userIdentifier) {
                $this->logger->info("UserBadge - Recherche user: $userIdentifier");

                $user = $this->utilisateurRepository->findOneBy(['login' => $userIdentifier]);

                if (!$user && filter_var($userIdentifier, FILTER_VALIDATE_EMAIL)) {
                    $this->logger->info("UserBadge - Recherche par email: $userIdentifier");
                    $user = $this->utilisateurRepository->findOneBy(['adresseMail' => $userIdentifier]);
                }

                if (!$user) {
                    $this->logger->warning("UserBadge - User non trouvé: $userIdentifier");
                    throw new CustomUserMessageAuthenticationException('Identifiants invalides.');
                }

                $this->logger->info("UserBadge - User trouvé: {$user->getLogin()}");
                return $user;
            }),
            new CustomCredentials(
                function($credentials, Utilisateur $user) {
                    $this->logger->info("CustomCredentials - Vérification pour: {$user->getLogin()}");

                    if ($user->getPassword() !== null && !empty($user->getPassword())) {
                        $this->logger->info("Auth locale pour: {$user->getLogin()}");
                        $valid = $this->passwordHasher->isPasswordValid($user, $credentials);
                        $this->logger->info("Auth locale résultat: " . ($valid ? 'OK' : 'FAIL'));
                        return $valid;
                    }

                    $this->logger->info("Auth LDAP pour: {$user->getLogin()}");
                    return $this->checkLdapCredentials($user->getLogin(), $credentials);
                },
                $password
            ),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
            ]
        );
    }

    private function checkLdapCredentials(string $login, string $password): bool
    {
        if (empty($password)) {
            $this->logger->warning("LDAP - Password vide pour: $login");
            return false;
        }

        try {
            $this->logger->info("LDAP - Vérification credentials pour: $login");
            $result = ConfigurationLdap::verifierCredentials($login, $password);

            if ($result) {
                $this->logger->info("LDAP - Auth réussie pour: $login");
            } else {
                $this->logger->warning("LDAP - Auth échouée pour: $login");
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error("LDAP - Erreur pour $login: " . $e->getMessage());
            throw new CustomUserMessageAuthenticationException('Erreur lors de l\'authentification.');
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        $this->logger->info("onAuthenticationSuccess - Connexion réussie pour: {$user->getUserIdentifier()}");

        return new RedirectResponse($this->urlGenerator->generate('index'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
