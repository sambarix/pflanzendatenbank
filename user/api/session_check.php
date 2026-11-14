<?php
/**
 * PDB Freigabe pruefen (Grav 2): hat die Sitzung den Zugangscode bestanden?
 * GET -> {"ok":true|false}
 * Reine Bibliotheksdatei - vom pdb-api-Plugin eingebunden, nie direkt per HTTP.
 */

function pdb_api_session_check(\Grav\Common\Grav $grav): array
{
    require_once __DIR__ . '/_invite.php';
    return pdb_json(['ok' => pdb_invite_session_ok()]);
}
