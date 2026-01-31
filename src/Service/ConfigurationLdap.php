<?php

namespace App\Service;

use Exception;
use LDAP\Connection;

class ConfigurationLdap
{
    // Paramètres de connexion LDAP IUT Montpellier
    private static string $ldapServer = "10.10.1.30";
    private static int $ldapPort = 389;
    private static ?Connection $ldapConnection = null;
    private static string $ldapBaseDN = "dc=info,dc=iutmontp,dc=univ-montp2,dc=fr";

    /**
     * Établit la connexion au serveur LDAP
     */
    public static function connecterServeur()
    {
        // Connexion au serveur LDAP
        self::$ldapConnection = ldap_connect("ldap://" . self::$ldapServer . ":" . self::$ldapPort);

        if (!self::$ldapConnection) {
            throw new Exception("Impossible de se connecter au serveur LDAP.");
        }

        // Configuration des options LDAP
        ldap_set_option(self::$ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option(self::$ldapConnection, LDAP_OPT_SIZELIMIT, 0); // Pas de limite de résultats
        ldap_set_option(self::$ldapConnection, LDAP_OPT_REFERRALS, 0);

        return self::$ldapConnection;
    }

    /**
     * Ferme la connexion LDAP
     */
    public static function deconnecterServeur(): void
    {
        if (self::$ldapConnection) {
            ldap_close(self::$ldapConnection);
            self::$ldapConnection = null;
        }
    }

    /**
     * Récupère tous les utilisateurs LDAP
     * @return array Liste des utilisateurs avec leurs attributs
     */
    public static function getAll(): array
    {
        if (!self::$ldapConnection) {
            throw new Exception("Connexion LDAP non établie. Appelez connecterServeur() d'abord.");
        }

        // Recherche de toutes les personnes dans l'annuaire
        $ldapSearch = @ldap_search(
            self::$ldapConnection,
            self::getLdapBaseDN(),
            "(objectClass=person)",
            ['uid', 'givenName', 'sn', 'mail', 'dn'] // Attributs à récupérer
        );

        if ($ldapSearch === false) {
            throw new Exception("Erreur lors de la recherche LDAP : " . ldap_error(self::$ldapConnection));
        }

        $ldapResults = ldap_get_entries(self::$ldapConnection, $ldapSearch);

        // Formatage des résultats
        $utilisateurs = [];
        for ($i = 0; $i < $ldapResults['count']; $i++) {
            $entry = $ldapResults[$i];

            // Extraction de la promotion depuis le DN
            // Exemple DN : uid=desertg,ou=BUT3,dc=info,dc=iutmontp,dc=univ-montp2,dc=fr
            $promotion = null;
            if (isset($entry['dn'])) {
                $dnParts = explode(',', $entry['dn']);
                if (isset($dnParts[1])) {
                    $ouPart = explode('=', $dnParts[1]);
                    if (count($ouPart) === 2 && $ouPart[0] === 'ou') {
                        $promotion = $ouPart[1];
                    }
                }
            }

            $utilisateurs[] = [
                'login' => $entry['uid'][0] ?? null,
                'prenom' => $entry['sn'][0] ?? null,  // sn = surname (nom de famille en LDAP)
                'nom' => $entry['givenname'][0] ?? null,  // givenName = prénom
                'email' => $entry['mail'][0] ?? null,
                'promotion' => $promotion,
                'dn' => $entry['dn'] ?? null
            ];
        }

        return $utilisateurs;
    }

    /**
     * Récupère un utilisateur LDAP par son login
     * @param string $login Login de l'utilisateur
     * @return array|null Données de l'utilisateur ou null si non trouvé
     */
    public static function getByLogin(string $login): ?array
    {
        if (!self::$ldapConnection) {
            throw new Exception("Connexion LDAP non établie.");
        }

        $ldapSearch = @ldap_search(
            self::$ldapConnection,
            self::getLdapBaseDN(),
            sprintf("(&(uid=%s)(objectClass=person))", ldap_escape($login, '', LDAP_ESCAPE_FILTER)),
            ['uid', 'givenName', 'sn', 'mail', 'dn']
        );

        if ($ldapSearch === false) {
            return null;
        }

        $ldapResults = ldap_get_entries(self::$ldapConnection, $ldapSearch);

        if ($ldapResults['count'] === 0) {
            return null;
        }

        $entry = $ldapResults[0];

        // Extraction promotion
        $promotion = null;
        if (isset($entry['dn'])) {
            $dnParts = explode(',', $entry['dn']);
            if (isset($dnParts[1])) {
                $ouPart = explode('=', $dnParts[1]);
                if (count($ouPart) === 2 && $ouPart[0] === 'ou') {
                    $promotion = $ouPart[1];
                }
            }
        }

        return [
            'login' => $entry['uid'][0] ?? null,
            'prenom' => $entry['sn'][0] ?? null,
            'nom' => $entry['givenname'][0] ?? null,
            'email' => $entry['mail'][0] ?? null,
            'promotion' => $promotion,
            'dn' => $entry['dn'] ?? null
        ];
    }

    /**
     * Vérifie les credentials LDAP d'un utilisateur
     * @param string $login Login de l'utilisateur
     * @param string $password Mot de passe
     * @return bool True si authentification réussie
     */
    public static function verifierCredentials(string $login, string $password): bool
    {
        if (empty($password)) {
            return false;
        }

        try {
            self::connecterServeur();
            $user = self::getByLogin($login);

            if (!$user || !isset($user['dn'])) {
                self::deconnecterServeur();
                return false;
            }

            // Crée une nouvelle connexion pour le bind
            $ldapConn = ldap_connect("ldap://" . self::$ldapServer . ":" . self::$ldapPort);

            if (!$ldapConn) {
                self::deconnecterServeur();
                return false;
            }

            ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);

            // Tentative d'authentification avec le DN et le password
            $bind = @ldap_bind($ldapConn, $user['dn'], $password);

            ldap_close($ldapConn);
            self::deconnecterServeur();

            return $bind !== false;

        } catch (Exception $e) {
            self::deconnecterServeur();
            return false;
        }
    }

    /**
     * Retourne le serveur LDAP
     */
    public static function getLdapServer(): string
    {
        return self::$ldapServer;
    }

    /**
     * Retourne le port LDAP
     */
    public static function getLdapPort(): int
    {
        return self::$ldapPort;
    }

    /**
     * Retourne la connexion LDAP active
     */
    public static function getLdapConnection()
    {
        return self::$ldapConnection;
    }

    /**
     * Retourne le Base DN
     */
    public static function getLdapBaseDN(): string
    {
        return self::$ldapBaseDN;
    }

    /**
     * Définit les paramètres LDAP (utile pour les tests)
     */
    public static function setLdapParams(string $server, int $port, string $baseDN): void
    {
        self::$ldapServer = $server;
        self::$ldapPort = $port;
        self::$ldapBaseDN = $baseDN;
    }
}
