<?php
/**
 * Zugangscode (Passphrase) vor der Registrierung - gemeinsame Hilfen.
 * Reine Bibliotheksdatei - wird nur von den API-Dateien eingebunden, nie direkt per HTTP.
 *
 * Der Zugangscode wird als Hash (password_hash) in der Plugin-Konfiguration gehalten:
 *   user/config/plugins/pdb-api.yaml  ->  invite_hash
 * Nach korrekter Eingabe merkt sich die PHP-Sitzung die Freigabe fuer 30 Minuten.
 */

const PDB_INVITE_TTL = 1800;

function pdb_invite_session_ok(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    $ts = $_SESSION['pdb_invite_ok'] ?? 0;
    return is_int($ts) && $ts > 0 && (time() - $ts) < PDB_INVITE_TTL;
}

function pdb_json(array $data, int $status = 200): array
{
    return [json_encode($data, JSON_UNESCAPED_UNICODE), 'application/json; charset=utf-8', $status];
}
