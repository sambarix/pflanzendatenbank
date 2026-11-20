<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Events\PermissionsRegisterEvent;
use Grav\Framework\Acl\PermissionsReader;
use Grav\Framework\Psr7\Response;

/**
 * PDB API Plugin
 *
 * Routet die eigenen Daten-Endpunkte der Pflanzendatenbank sauber ueber
 * Gravs Request-Zyklus (onPluginsInitialized -> onPagesInitialized),
 * genau das Muster, das auch das Login-Plugin fuer eigene Routen nutzt.
 *
 * Die eigentliche Logik liegt in user/api/*.php - das sind aber reine
 * PHP-Bibliotheksdateien (Funktionsdefinitionen), NIE direkt per HTTP
 * aufrufbar. Gravs eigene user/.htaccess blockt user/*.php ohnehin -
 * dieses Plugin arbeitet MIT diesem Schutz, nicht dagegen.
 *
 * Vertrag einer API-Funktion: fn(Grav $grav): array
 *   [ body, contentType, status = 200, extraHeaders = [] ]
 * Endpunkte mit Gaertnerei-Daten pruefen den Login selbst
 * (pdb_current_nursery() in _common.php) - der DB-Pfad kommt nie vom Browser.
 */
class PdbApiPlugin extends Plugin
{
    /** @var array<string,array{file:string,fn:string}> */
    const ROUTES = [
        '/pdb-api/liste'      => ['file' => 'liste.php',      'fn' => 'pdb_api_liste'],
        '/pdb-api/categories' => ['file' => 'categories.php', 'fn' => 'pdb_api_categories'],
        '/pdb-api/bestand'    => ['file' => 'bestand.php',    'fn' => 'pdb_api_bestand'],
        '/pdb-api/standorte'  => ['file' => 'standorte.php',  'fn' => 'pdb_api_standorte'],
        '/pdb-api/export'     => ['file' => 'export.php',     'fn' => 'pdb_api_export'],
        '/pdb-api/portrait'   => ['file' => 'portrait.php',   'fn' => 'pdb_api_portrait'],
        '/pdb-api/eingabe'    => ['file' => 'eingabe.php',    'fn' => 'pdb_api_eingabe'],
        '/pdb-api/anfrage'    => ['file' => 'anfrage.php',    'fn' => 'pdb_api_anfrage'],
        '/pdb-api/vorschlaege' => ['file' => 'vorschlaege.php', 'fn' => 'pdb_api_vorschlaege'],
        // Registrierung: Zugangscode -> Sitzungspruefung -> Konto anlegen (Sitzung, kein Login noetig)
        '/pdb-api/passphrase'    => ['file' => 'passphrase.php',    'fn' => 'pdb_api_passphrase'],
        '/pdb-api/session_check' => ['file' => 'session_check.php', 'fn' => 'pdb_api_session_check'],
        '/pdb-api/register'      => ['file' => 'register.php',      'fn' => 'pdb_api_register'],
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            // Recht site.pdb_editor im Rechtebaum (Backend: Gruppen und Konten)
            PermissionsRegisterEvent::class => ['onRegisterPermissions', 1000],
        ];
    }

    public function onRegisterPermissions(PermissionsRegisterEvent $event): void
    {
        $actions = PermissionsReader::fromYaml("plugin://{$this->name}/permissions.yaml");
        $event->permissions->addActions($actions);
    }

    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $path = rtrim($this->grav['uri']->path(), '/');

        if (isset(self::ROUTES[$path])) {
            $this->enable([
                'onPagesInitialized' => ['serveApiRoute', 0],
            ]);
        }
    }

    public function serveApiRoute(): void
    {
        $path = rtrim($this->grav['uri']->path(), '/');
        $route = self::ROUTES[$path] ?? null;
        if ($route === null) {
            return;
        }

        // Schreibende Anfragen nur von der eigenen Seite (Schutz gegen CSRF, zusaetzlich zu SameSite=Lax der Cookies)
        if ($this->isForeignWrite()) {
            $this->grav->close(new Response(403, [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'private, no-store',
            ], json_encode(['error' => 'Anfrage von fremder Herkunft abgelehnt.'], JSON_UNESCAPED_UNICODE)));
            return;
        }

        $apiDir = __DIR__ . '/../../api/';
        require_once $apiDir . '_common.php';
        require_once $apiDir . $route['file'];

        $fn = $route['fn'];
        $result = $fn($this->grav);

        [$body, $contentType] = $result;
        $status  = $result[2] ?? 200;
        $headers = ['Content-Type' => $contentType, 'Cache-Control' => 'private, no-store'] + ($result[3] ?? []);

        $response = new Response($status, $headers, $body);
        $this->grav->close($response);
    }

    /**
     * CSRF-Schutz: Schreibende Anfragen (alles ausser GET, HEAD, OPTIONS) muessen von der eigenen Seite kommen.
     * 1. Browser-Header Sec-Fetch-Site (alle aktuellen Browser): "same-origin" (eigene Seite) und "none" (direkte
     *    Eingabe) sind in Ordnung; "same-site" (andere Subdomain) und "cross-site" werden abgelehnt.
     * 2. Fehlt er: Origin, sonst Referer; der Host muss dem Host dieser Anfrage entsprechen ("null" gilt als fremd).
     * 3. Fehlen alle drei, ist es kein Browser mit fremder Seite im Spiel (Skripte, Tests): durchlassen.
     */
    private function isForeignWrite(): bool
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return false;
        }

        $site = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
        if ($site !== '') {
            return !in_array($site, ['same-origin', 'none'], true);
        }

        $norm = static function (string $authority): string {
            // Standardports weglassen, damit "host" und "host:443" gleich gelten
            return preg_replace('/:(80|443)$/', '', strtolower(trim($authority)));
        };
        $host = $norm((string)($_SERVER['HTTP_HOST'] ?? ''));

        foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $key) {
            $value = trim((string)($_SERVER[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $parts = parse_url($value);
            if ($parts === false || empty($parts['host'])) {
                return true; // "null" oder unlesbar
            }
            $authority = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
            return $host === '' || $norm($authority) !== $host;
        }

        return false;
    }
}
