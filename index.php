<?php
// index.php — Minimaler PHP-Prototyp für "Markt Stockstadt"
// Anforderungen aus Mockup (Datum von Mittwoch zu Mittwoch, 3 Anbieter, QR-Param-Update):
// - ?teilnehmer=<UID>&status=on|off  → Schaltet Sichtbarkeit des Anbieters
// - Farben an Stockstadt (Blau/Silber), Font Arial
// - Karten können als Farbrechtecke mit weißer Schrift starten (später durch Produktbilder ersetzbar)
// HINWEIS: Für einen schnellen Start wird ein JSON-File als "Mini-Datenbank" genutzt.

// ------------------ Konfiguration ------------------ //
$DATA_FILE = __DIR__ . '/data/participants.json';
$BASE_URL  = 'https://markt-stockstadt.azurewebsites.net/index.php'; // für QR-Links

// CI-Farben (Blau/Silber – hex anpassbar, Quelle: Wappen Silber & Blau)
$PRIMARY   = '#0B5FA5'; // Blau
$SILVER    = '#C0C0C0'; // Silber/Grau
$TEXT      = '#333333';
$BG        = '#f7f7f8';

// Drei Anbieter mit stabilen UIDs (Beispiel-UIDs, bitte bei Bedarf ersetzen)
$DEFAULT_PARTICIPANTS = [
  [
    'uid' => 'c0e603a4',
    'name' => 'Metzger',
    'desc' => 'Metzgerei Muster aus Stockstadt – frische Fleisch- und Wurstwaren aus der Region.',
    'color' => '#9B1C1C', // dunkles Rot
    'status' => 'off'
  ],
  [
    'uid' => 'b7f2d915',
    'name' => 'Bäcker',
    'desc' => 'Bäckerei Rutz aus Stockstadt, Musterstraße 7 – immer frische Teigwaren für Sie.',
    'color' => '#1F4D7A', // dunkles Blau
    'status' => 'off'
  ],
  [
    'uid' => 'a3d84bc2',
    'name' => 'Gemüse',
    'desc' => 'Frisches Obst & Gemüse aus der Region – saisonal, knackig, nachhaltig.',
    'color' => '#2F855A', // grün
    'status' => 'off'
  ],
];

// ------------------ Helper: Storage ------------------ //
function ensureDataFile($file, $defaults) {
  if (!file_exists($file)) {
    if (!is_dir(dirname($file))) {
      mkdir(dirname($file), 0775, true);
    }
    file_put_contents($file, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  }
}

function loadParticipants($file) {
  $json = @file_get_contents($file);
  $data = json_decode($json, true);
  return is_array($data) ? $data : [];
}

function saveParticipants($file, $data) {
  file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ------------------ Helper: Mittwoch-Logik ------------------ //
// Idee: Anzeige wechselt wöchentlich am Mittwoch (Markttag). Wir bestimmen den "aktuellen Mittwoch"
// (der Mittwoch dieser Woche; wenn heute < Mittwoch, dann der kommende aus der laufenden Woche; 
// wenn heute > Mittwoch, bleibt Anzeige bis zum nächsten Mittwoch).
function currentWednesdayDate() {
  $today = new DateTime('today');
  // In PHP: 1=Montag, 3=Mittwoch, 7=Sonntag
  $dow = (int)$today->format('N');
  $wednesday = clone $today;
  if ($dow <= 3) {
    // Montag (1), Dienstag (2), Mittwoch (3) → auf diesen Mittwoch vor-/zurückstellen
    $wednesday->modify('wednesday this week');
  } else {
    // Donnerstag–Sonntag → nächster Mittwoch
    $wednesday->modify('next wednesday');
  }
  return $wednesday;
}

function formatDateDe(DateTime $d) {
  $wdays = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
  $weekday = $wdays[(int)$d->format('w')];
  return $weekday . ', ' . $d->format('d.m.Y');
}

// ------------------ Init ------------------ //
ensureDataFile($DATA_FILE, $DEFAULT_PARTICIPANTS);
$participants = loadParticipants($DATA_FILE);

// ------------------ GET-API (?teilnehmer=UID&status=on|off) ------------------ //
if (isset($_GET['teilnehmer'], $_GET['status'])) {
  $uid = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['teilnehmer']);
  $status = strtolower($_GET['status']) === 'on' ? 'on' : 'off';
  foreach ($participants as &$p) {
    if ($p['uid'] === $uid) {
      $p['status'] = $status;
      break;
    }
  }
  unset($p);
  saveParticipants($DATA_FILE, $participants);
  // Redirect, damit QR-Aufruf eine saubere Ansicht zeigt
  header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); 
  exit;
}

// Sichtbare Anbieter filtern
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
    :root{
      --primary: <?= $PRIMARY ?>;
      --silver: <?= $SILVER ?>;
      --text:   <?= $TEXT ?>;
      --bg:     <?= $BG ?>;
    }
    *{ box-sizing:border-box; }
    body{ margin:0; font-family: Arial, Helvetica, sans-serif; background: var(--bg); color: var(--text); }
    header{ border-bottom:1px solid var(--silver); }
    .container{ max-width: 1080px; margin: 0 auto; padding: 1.5rem; }
    h1{ color: var(--primary); margin: 0; font-size: clamp(1.8rem, 2.5vw, 2.4rem); }
    p.lead{ margin:.5rem 0 0 0; }
    .sub{ margin:.25rem 0 0 0; }
    .grid{ display:grid; gap:1rem; grid-template-columns: 1fr; }
    @media (min-width: 768px){ .grid{ grid-template-columns: repeat(3, 1fr); } }
    .card{ background:#fff; border:1px solid var(--silver); border-radius:16px; overflow:hidden; display:flex; flex-direction:column; }
    .block{
      position:relative; aspect-ratio: 4/3; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:1.6rem;
    }
    .block span{ position:relative; z-index:2; text-shadow: 0 1px 2px rgba(0,0,0,.35); }
    .content{ padding:1rem 1.25rem; }
    h2{ margin:0; font-size:1.25rem; color:#0A5594; }
    .desc{ margin:.5rem 0 0 0; font-size:.95rem; }
    .btn{ display:inline-block; margin-top:.75rem; background:var(--primary); color:#fff; text-decoration:none; padding:.6rem .9rem; border-radius:12px; font-weight:600; }
    footer{ border-top:1px solid var(--silver); margin-top:1.25rem; }
    .crest{ height:56px; width:auto; }
    .row{ display:flex; gap:.75rem; align-items:center; }
  </style>
</head>
<body>
  <header>
    <div class="container">
      <div class="row">
        <!-- Wappen (SVG) → kann lokal eingebunden werden; hier aus Commons -->
        <img class="crest" alt="Wappen Stockstadt am Main" src="https://upload.wikimedia.org/wikipedia/commons/6/63/Wappen_Stockstadt_am_Main.svg" />
        <div>
          <h1>Markt Stockstadt</h1>
          <p class="lead">Regional, frisch und nahbar – drei Anbieter im Überblick.</p>
          <p class="sub">Folgende Anbieter sind heute auf dem Markt. <strong>(<?= htmlspecialchars(formatDateDe($marketDate)) ?>)</strong></p>
        </div>
      </div>
    </div>
  </header>

  <main>
    <div class="container">
      <section class="grid">
        <?php if (empty($visible)): ?>
          <p><em>Aktuell sind keine Anbieter aktiv geschaltet.</em></p>
        <?php else: foreach ($visible as $p): ?>
          <article class="card">
            <!-- Farbrechteck / später Produktbild → Hintergrundfarbe aus $p['color'] -->
            <div class="block" style="background: <?= htmlspecialchars($p['color']) ?>;">
              <span><?= htmlspecialchars($p['name']) ?></span>
            </div>
            <div class="content">
              <h2><?= htmlspecialchars($p['name']) ?></h2>
              <p class="desc"><?= htmlspecialchars($p['desc']) ?></p>
              <!-- Anbieter-URL hier eintragen (z. B. Detailseite oder externe Website) -->
              <a class="btn" href="#" aria-label="Mehr zu <?= htmlspecialchars($p['name']) ?>">Mehr erfahren</a>
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
      <summary>QR-Links für Beschicker (für den Test)</summary>
      <ul>
        <?php foreach ($participants as $p): ?>
          <li>
            <strong><?= htmlspecialchars($p['name']) ?></strong> — UID <code><?= htmlspecialchars($p['uid']) ?></code>
            <br>
            ON: <a href="<?= $BASE_URL ?>?teilnehmer=<?= urlencode($p['uid']) ?>&status=on"><?= $BASE_URL ?>?teilnehmer=<?= urlencode($p['uid']) ?>&status=on</a>
            &nbsp;|&nbsp;
            OFF: <a href="<?= $BASE_URL ?>?teilnehmer=<?= urlencode($p['uid']) ?>&status=off"><?= $BASE_URL ?>?teilnehmer=<?= urlencode($p['uid']) ?>&status=off</a>
          </li>
        <?php endforeach; ?>
      </ul>
      <p style="font-size:.9rem; opacity:.8;">Hinweis: Für die echte Nutzung sollten wir zusätzlich einen geheimen Token oder eine Signatur einführen, damit Links nicht geraten/missbraucht werden.</p>
    </details>
  </div>
</body>
</html>
