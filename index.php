<?php
// index.php — PHP-Prototyp „Markt Stockstadt“ (Session-Storage, Azure/Linux geeignet)
// - Keine Dateischreibrechte (nur $_SESSION)
// - Kein Redirect (Seite rendert direkt mit neuem Zustand)
// - GET-API: ?teilnehmer=<UID>&status=on|off
// - Responsives 3-Spalten-Layout, Arial, Blau/Silber

// Optional: Session-Pfad stabil setzen (Ordner wird bei Bedarf erstellt)
@mkdir('/home/site/wwwroot/sessions', 0775, true);
ini_set('session.save_path', '/home/site/wwwroot/sessions');

session_start();

// ------------------ Konfiguration ------------------ //
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');
$BASE_URL = $scheme . '://' . $host . ($dir ? $dir : '') . '/index.php';

$PRIMARY = '#0B5FA5';
$SILVER  = '#C0C0C0';
$TEXT    = '#333333';
$BG      = '#f7f7f8';

$DEFAULT_PARTICIPANTS = [
  ['uid'=>'c0e603a4','name'=>'Metzger','desc'=>'Metzgerei Muster aus Stockstadt – frische Fleisch- und Wurstwaren aus der Region.','color'=>'#9B1C1C','status'=>'off'],
  ['uid'=>'b7f2d915','name'=>'Bäcker','desc'=>'Bäckerei Rutz aus Stockstadt, Musterstraße 7 – immer frische Teigwaren für Sie.','color'=>'#1F4D7A','status'=>'off'],
  ['uid'=>'a3d84bc2','name'=>'Gemüse','desc'=>'Frisches Obst & Gemüse aus der Region – saisonal, knackig, nachhaltig.','color'=>'#2F855A','status'=>'off'],
];

// ------------------ Session-Storage ------------------ //
const SESSION_KEY = 'markt_stockstadt_participants';
// Demo-Start: alle "on", damit man sofort etwas sieht
$defaultsOn = array_map(function($p){ $p['status'] = 'on'; return $p; }, $DEFAULT_PARTICIPANTS);
if (!isset($_SESSION[SESSION_KEY]) || !is_array($_SESSION[SESSION_KEY])) {
  $_SESSION[SESSION_KEY] = $defaultsOn;
}
$participants = $_SESSION[SESSION_KEY];

// ------------------ Mittwoch-Logik ------------------ //
function currentWednesdayDate() {
  $today = new DateTime('today');
  $dow = (int)$today->format('N'); // 1=Mo … 7=So
  $w = clone $today;
  $dow <= 3 ? $w->modify('wednesday this week') : $w->modify('next wednesday');
  return $w;
}
function formatDateDe(DateTime $d) {
  $wdays = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
  return $wdays[(int)$d->format('w')] . ', ' . $d->format('d.m.Y');
}

// ------------------ API (ohne Redirect) ------------------ //
// 1) Einzelumschaltung via GET (?teilnehmer=UID&status=on|off)
if (isset($_GET['teilnehmer'], $_GET['status'])) {
  $uid = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['teilnehmer']);
  $status = strtolower($_GET['status']) === 'on' ? 'on' : 'off';
  foreach ($participants as &$p) { if ($p['uid'] === $uid) { $p['status'] = $status; break; } }
  unset($p);
  $_SESSION[SESSION_KEY] = $participants;
}

// 2) Bulk-Aktionen via POST (Buttons)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'all_on' || $action === 'all_off') {
    $newStatus = $action === 'all_on' ? 'on' : 'off';
    foreach ($participants as &$p) { $p['status'] = $newStatus; }
    unset($p);
  } elseif ($action === 'toggle' && !empty($_POST['uid'])) {
    $uid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['uid']);
    foreach ($participants as &$p) { if ($p['uid'] === $uid) { $p['status'] = ($p['status'] === 'on' ? 'off' : 'on'); break; } }
    unset($p);
  }
  $_SESSION[SESSION_KEY] = $participants;
}

$visible = array_values(array_filter($participants, fn($p) => $p['status'] === 'on'));
$marketDate = currentWednesdayDate();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Markt Stockstadt</title>
  <style>
    :root{ --primary: <?= $PRIMARY ?>; --silver: <?= $SILVER ?>; --text: <?= $TEXT ?>; --bg: <?= $BG ?>; }
    *{ box-sizing:border-box; }
    body{ margin:0; font-family: Arial, Helvetica, sans-serif; background: var(--bg); color: var(--text); }
    header{ border-bottom:1px solid var(--silver); }
    .container{ max-width:1080px; margin:0 auto; padding:1.5rem; }
    h1{ color:var(--primary); margin:0; font-size:clamp(1.8rem, 2.5vw, 2.4rem); }
    p.lead{ margin:.5rem 0 0 0; }
    .sub{ margin:.25rem 0 0 0; }
    .grid{ display:grid; gap:1rem; grid-template-columns:1fr; }
    @media (min-width:768px){ .grid{ grid-template-columns:repeat(3,1fr); } }
    .card{ background:#fff; border:1px solid var(--silver); border-radius:16px; overflow:hidden; display:flex; flex-direction:column; }
    .block{ position:relative; aspect-ratio:4/3; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:1.6rem; }
    .block span{ position:relative; z-index:2; text-shadow:0 1px 2px rgba(0,0,0,.35); }
    .content{ padding:1rem 1.25rem; }
    h2{ margin:0; font-size:1.25rem; color:#0A5594; }
    .desc{ margin:.5rem 0 0 0; font-size:.95rem; }
    .btn{ display:inline-block; margin-top:.75rem; background:var(--primary); color:#fff; text-decoration:none; padding:.6rem .9rem; border-radius:12px; font-weight:600; }
    footer{ border-top:1px solid var(--silver); margin-top:1.25rem; }
    .crest{ height:56px; width:auto; filter: drop-shadow(0 1px 2px rgba(0,0,0,.15)); }
    .row{ display:flex; gap:.75rem; align-items:center; }
    .meta{ font-size:.85rem; opacity:.75; }
    code{ background:#f0f0f0; padding:.1rem .3rem; border-radius:4px; }
  </style>
</head>
<body>
  <header>
    <div class="container">
      <div class="row">
        <img class="crest" alt="Wappen Stockstadt am Main"
             src="https://upload.wikimedia.org/wikipedia/commons/6/63/Wappen_Stockstadt_am_Main.svg" />
        <div>
          <h1>Markt Stockstadt</h1>
          <p class="lead">Regional, frisch und nahbar – drei Anbieter im Überblick.</p>
          <p class="sub">Folgende Anbieter sind heute auf dem Markt. <strong>(<?= htmlspecialchars(formatDateDe($marketDate)) ?>)</strong></p>
          <p class="meta">Speicher: <code>Session</code> · BASE_URL: <code><?= htmlspecialchars($BASE_URL) ?></code></p>
        </div>
      </div>
    </div>
  </header>

  <main>
    <div class="container">
      <!-- Demo-Steuerleiste (ohne QR) -->
      <form method="post" style="display:flex; gap:.5rem; margin-bottom:1rem;">
        <button class="btn" name="action" value="all_on" type="submit">Alle aktivieren</button>
        <button class="btn" name="action" value="all_off" type="submit"
                style="background:transparent;color:var(--primary);border:1px solid var(--primary);">
          Alle deaktivieren
        </button>
      </form>

      <section class="grid">
        <?php if (empty($visible)): ?>
          <p><em>Aktuell sind keine Anbieter aktiv geschaltet.</em></p>
        <?php else: foreach ($visible as $p): ?>
          <article class="card">
            <div class="block" style="background: <?= htmlspecialchars($p['color']) ?>;">
              <span><?= htmlspecialchars($p['name']) ?></span>
            </div>
            <div class="content">
              <h2><?= htmlspecialchars($p['name']) ?></h2>
              <p class="desc"><?= htmlspecialchars($p['desc']) ?></p>
              <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
                <a class="btn" href="#" aria-label="Mehr zu <?= htmlspecialchars($p['name']) ?>">Mehr erfahren</a>
                <form method="post">
                  <input type="hidden" name="action" value="toggle" />
                  <input type="hidden" name="uid" value="<?= htmlspecialchars($p['uid']) ?>" />
                  <button class="btn" type="submit"
                          style="background:<?= $p['status']==='on' ? '#888' : 'var(--primary)'; ?>">
                    <?= $p['status']==='on' ? 'Deaktivieren' : 'Aktivieren' ?>
                  </button>
                </form>
              </div>
            </div>
          </article>
        <?php endforeach; endif; ?>
      </section>
    </div>
  </main>

  <footer>
    <div class="container" style="display:flex; justify-content:space-between; align-items:center;">
      <small>© <?= date('Y') ?> Markt Stockstadt</small>
      <a href="#" class="btn" style="background:transparent; color:var(--primary); border:1px solid var(--primary);">Impressum</a>
    </div>
  </footer>

  <div class="container" style="margin-top:1rem;">
    <details>
      <summary>QR-Links für Beschicker (optional)</summary>
      <ul>
        <?php foreach ($participants as $p): ?>
          <li>
            <strong><?= htmlspecialchars($p['name']) ?></strong> — UID <code><?= htmlspecialchars($p['uid']) ?></code>
            <br>
            ON: <a href="<?= htmlspecialchars($BASE_URL) ?>?teilnehmer=<?= urlencode($p['uid']) ?>&status=on"><?= htmlspecialchars($BASE_URL) ?>?teilnehmer=<?= urlencode($p['uid']) ?>&status=on</a>
            &nbsp;|&nbsp;
            OFF: <a href="<?= htmlspecialchars($BASE_URL) ?>?teilnehmer=<?= urlencode($p['uid']) ?>&status=off"><?= htmlspecialchars($BASE_URL) ?>?teilnehmer=<?= urlencode($p['uid']) ?>&status=off</a>
          </li>
        <?php endforeach; ?>
      </ul>
    </details>
  </div>
</body>
</html>
