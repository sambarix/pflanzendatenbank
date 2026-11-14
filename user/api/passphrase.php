<?php
/**
 * PDB Zugangscode pruefen (Grav 2).
 * POST passphrase=... -> {"ok":true|false}. Bei Erfolg wird die Freigabe in der Sitzung gemerkt.
 * Reine Bibliotheksdatei - vom pdb-api-Plugin eingebunden, nie direkt per HTTP.
 */

function pdb_api_passphrase(\Grav\Common\Grav $grav): array
{
    require_once __DIR__ . '/_invite.php';

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return pdb_json(['ok' => false, 'error' => 'POST erforderlich.'], 405);
    }
    // Mit system.session.lazy startet Grav die Sitzung erst beim ersten Schreiben ueber das Grav-Session-Objekt.
    // Das Freigabe-Flag liegt direkt in $_SESSION, darum die Sitzung hier ausdruecklich starten.
    if (isset($grav['session']) && method_exists($grav['session'], 'startPending')) {
        $grav['session']->startPending();
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return pdb_json(['ok' => false, 'error' => 'Sitzung nicht verfügbar.'], 500);
    }

    // Kurze Sperre nach zu vielen Fehlversuchen
    if ((int)($_SESSION['pdb_invite_lock'] ?? 0) > time()) {
        return pdb_json(['ok' => false, 'error' => 'Zu viele Versuche. Bitte später nochmals versuchen.']);
    }

    $hash  = (string)$grav['config']->get('plugins.pdb-api.invite_hash', '');
    $given = (string)($_POST['passphrase'] ?? '');

    if ($hash !== '' && $given !== '' && strlen($given) <= 200 && password_verify($given, $hash)) {
        $_SESSION['pdb_invite_ok'] = time();
        unset($_SESSION['pdb_invite_fail'], $_SESSION['pdb_invite_lock']);
        return pdb_json(['ok' => true]);
    }

    usleep(600000);
    $fail = (int)($_SESSION['pdb_invite_fail'] ?? 0) + 1;
    if ($fail >= 8) {
        $_SESSION['pdb_invite_lock'] = time() + 900;
        $fail = 0;
    }
    $_SESSION['pdb_invite_fail'] = $fail;

    return pdb_json(['ok' => false]);
}
