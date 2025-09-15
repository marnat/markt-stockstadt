<?php
// index.php – Wochenfunktion + QR/UID Aktivierung pro Anbieter (ON/OFF, KW-basiert)
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');

// =============================
// Anbieter-Konfiguration (UIDs)
// =============================
// Jede UID steckt in einem **statischen** QR-Code des Anbieters.
// Aufruf: index.php?uid=UID_METZGER&action=on|off
$vendors = [
  'metzger' => [
    'name' => 'Metzger',
    'uid'  => 'UID_METZGER', // TODO: echte UID einsetzen
    'image'=> 'Assets/Metzger.png',
  ],
  'baecker' => [
    'name' => 'Bäcker',
    'uid'  => 'UID_BAECKER', // TODO: echte UID einsetzen
    'image'=> 'Assets/Bäcker.png',
  ],
  'gemuese' => [
    'name' => 'Gemüse',
    'uid'  => 'UID_GEMUESE', // TODO: echte UID einsetzen
    'image'=> 'Assets/Gemüse.png',
  ],
];

// Hilfs-Map UID -> Vendor-Key
$uidToVendor = [];
foreach ($vendors as $k => $v) { $uidToVendor[$v['uid']] = $k; }

// =============================
// Wochenlogik (Anzeige)
// =============================
$today = new DateTimeImmutable('today');
$displayWeek = isset($_GET['week']) && ctype_digit($_GET['week']) ? (int)$_GET['week'] : (int)$today->format('W');
$displayYear = isset($_GET['year']) && ctype_digit($_GET['year']) ? (int)$_GET['year'] : (int)$today->format('o');

function isoWeeksInYear(int $year): int {
  $dt = new DateTimeImmutable($year . '-12-28');
  return (int)$dt->format('W');
}
$maxWeeks = isoWeeksInYear($displayYear);
if ($displayWeek < 1) { $displayYear--; $displayWeek = isoWeeksInYear($displayYear); }
if ($displayWeek > $maxWeeks) { $displayWeek = 1; $displayYear++; }

$monday = (new DateTimeImmutable())->setISODate($displayYear, $displayWeek);
$weekdayNames = ['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'];
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
  $d = $monday->modify("+{$i} day");
  $weekDays[] = [
    'name' => $weekdayNames[$i],
    'date' => $d->format('d.m.Y'),
    'iso'  => $d->format('Y-m-d'),
    'isToday' => $d->format('Y-m-d') === $today->format('Y-m-d')
  ];
}

$prevWeek = $displayWeek - 1; $prevYear = $displayYear;
if ($prevWeek < 1) { $prevYear = $displayYear - 1; $prevWeek = isoWeeksInYear($prevYear); }
$nextWeek = $displayWeek + 1; $nextYear = $displayYear;
if ($nextWeek > isoWeeksInYear($displayYear)) { $nextYear = $displayYear + 1; $nextWeek = 1; }

function weekUrl($w, $y): string { return '?week=' . rawurlencode((string)$w) . '&year=' . rawurlencode((string)$y); }

// =============================
// Persistenz der Aktivierung (pro *aktueller* KW)
// =============================
// Struktur: data/status.json
// {
//   "2025-37": { "metzger": true, "baecker": false, "gemuese": true }
// }
$dataDir = __DIR__ . '/data';
$dataFile = $dataDir . '/status.json';
if (!is_dir($dataDir)) { @mkdir($dataDir, 0775, true); }

function loadStatus(string $file): array {
  if (!is_file($file)) return [];
  $json = @file_get_contents($file);
  if ($json === false || $json === '') return [];
  $data = json_decode($json, true);
  return is_array($data) ? $data : [];
}

function saveStatus(string $file, array $data): void {
  $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  @file_put_contents($file, $json, LOCK_EX);
}

$status = loadStatus($dataFile);

$currentIsoWeek = (int)$today->format('W');
$currentIsoYear = (int)$today->format('o');
$currentKey = $currentIsoYear . '-' . $currentIsoWeek; // z.B. 2025-37
if (!isset($status[$currentKey])) { $status[$currentKey] = []; }

// =============================
// QR-Scan: UID + ACTION (on/off) -> Setzen für aktuelle KW
// =============================
$flash = null; // bewusst NICHT anzeigen – nur intern nutzbar; bleibt hier für Logging/Hooks
if (isset($_GET['uid'], $_GET['action']) && is_string($_GET['uid']) && is_string($_GET['action'])) {
  $uid = trim($_GET['uid']);
  $action = strtolower(trim($_GET['action']));
  if (isset($uidToVendor[$uid]) && in_array($action, ['on','off'], true)) {
    $vendorKey = $uidToVendor[$uid];
    $status[$currentKey][$vendorKey] = ($action === 'on');
    saveStatus($dataFile, $status);
    // $flash = sprintf('%s wurde %s.', $vendors[$vendorKey]['name'], $action === 'on' ? 'aktiviert' : 'deaktiviert');
  }
}

// Status für die *anzeigte* Woche
$displayKey = $displayYear . '-' . $displayWeek;
$weekStatus = $status[$displayKey] ?? [];

// =============================
// Rendering-Helfer
// =============================
function isActive(array $weekStatus, string $vendorKey): bool {
  return isset($weekStatus[$vendorKey]) ? (bool)$weekStatus[$vendorKey] : false;
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Übersicht – KW <?= htmlspecialchars($displayWeek) ?> / <?= htmlspecialchars($displayYear) ?></title>
<style>
  :root{ --primary:#0B5FA5; --silver:#C0C0C0; --text:#333; --bg:#f7f7f8; }
  *{ box-sizing:border-box; }
  body{ margin:0; font-family: Arial, Helvetica, sans-serif; background: var(--bg); color: var(--text); }

  /* Header */
  header{ border-bottom:1px solid var(--silver); }
  .container{ max-width:1080px; margin:0 auto; padding:1rem; }
  @media (min-width:768px){ .container{ padding:1.5rem; } }

  h1{ color:var(--primary); margin:0; font-size:clamp(1.6rem, 2.5vw, 2.4rem); }
  p.lead{ margin:.5rem 0 0 0; }
  .sub{ margin:.25rem 0 0 0; }
  .meta{ font-size:.85rem; opacity:.75; }
  code{ background:#f0f0f0; padding:.1rem .3rem; border-radius:4px; }

  /* Header-Zeile: mobil untereinander, ab Tablet nebeneinander */
  .row{ display:flex; gap:.75rem; align-items:center; flex-wrap:wrap; }
  @media (max-width:640px){
    .row{ flex-direction:column; align-items:flex-start; gap:.5rem; }
  }
  .crest{ height:56px; width:auto; filter:drop-shadow(0 1px 2px rgba(0,0,0,.15)); }
  @media (max-width:640px){ .crest{ height:48px; } }

  /* Grid: mobil 1 Spalte, ab 640px 2, ab 1024px 3 */
  .grid{ display:grid; gap:1rem; grid-template-columns:1fr; }
  @media (min-width:640px){ .grid{ grid-template-columns:repeat(2,1fr); } }
  @media (min-width:1024px){ .grid{ grid-template-columns:repeat(3,1fr); } }

  /* Karten */
  .card{ background:#fff; border:1px solid var(--silver); border-radius:16px; overflow:hidden; display:flex; flex-direction:column; }
  .block{ position:relative; width:100%; aspect-ratio:4/3; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:1.4rem; text-align:center; }
  @media (min-width:768px){ .block{ font-size:1.6rem; } }
  .block span{ position:relative; z-index:2; text-shadow:0 1px 2px rgba(0,0,0,.35); }
  .content{ padding:1rem 1.25rem; }
  h2{ margin:0; font-size:1.15rem; color:#0A5594; }
  .desc{ margin:.5rem 0 0 0; font-size:.95rem; }

  /* Buttons: mobil gut klickbar, Wrap bei wenig Platz */
  .btn{ display:inline-block; background:var(--primary); color:#fff; text-decoration:none; padding:.6rem .9rem; border-radius:12px; font-weight:600; border:none; cursor:pointer; }
  .btn.outline{ background:transparent; color:var(--primary); border:1px solid var(--primary); }
  .actions{ display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.5rem; }
  .btn.muted{ background:#888; }

  /* Footer */
  footer{ border-top:1px solid var(--silver); margin-top:1.25rem; }
  .footer-row{ display:flex; justify-content:space-between; align-items:center; gap:.75rem; flex-wrap:wrap; }

  /* Optional hilfreich, falls später echte Bilder kommen */
  img{ max-width:100%; height:auto; }
</style>

</head>
<body>
  <div class="container">
    <header>
      <img src="Assets/wappen-stockstadt.gif" alt="Wappen Stockstadt" title="Wappen Stockstadt">
      <div>
        <h1>Übersicht Wochenmarkt Stockstadt $1</h1>
        <div class="muted">Montag bis Sonntag der ausgewählten Woche</div>
      </div>
    </header>

    <nav class="nav">
      <a class="btn" href="<?= htmlspecialchars(weekUrl($prevWeek, $prevYear)) ?>" aria-label="Vorherige Woche">⟵ Vorherige Woche</a>
      <div>
        <a class="btn" href="<?= htmlspecialchars(weekUrl((int)$today->format('W'), (int)$today->format('o'))) ?>">Heute / Aktuelle KW</a>
      </div>
      <a class="btn" href="<?= htmlspecialchars(weekUrl($nextWeek, $nextYear)) ?>" aria-label="Nächste Woche">Nächste Woche ⟶</a>
    </nav>

    <section class="week" aria-label="Wochentage">
      <?php foreach ($weekDays as $d): ?>
        <article class="day <?= $d['isToday'] ? 'today' : '' ?>">
          <h3>
            <span><?= htmlspecialchars($d['name']) ?></span>
            <span class="date"><?= htmlspecialchars($d['date']) ?></span>
          </h3>
        </article>
      <?php endforeach; ?>
    </section>

    <section class="grid" aria-label="Anbieter">
      <?php foreach ($vendors as $key => $v):
        if (!isActive($weekStatus, $key)) continue; // Deaktivierte Anbieter NICHT anzeigen
      ?>
        <div class="card">
          <div class="status on">Aktiv (diese KW)</div>
          <h2><?= htmlspecialchars($v['name']) ?></h2>
          <div class="media">
            <img src="<?= htmlspecialchars($v['image']) ?>" alt="<?= htmlspecialchars($v['name']) ?>" title="<?= htmlspecialchars($v['name']) ?>">
          </div>
          <p class="muted">Aktiv für KW <?= htmlspecialchars($displayWeek) ?>/<?= htmlspecialchars($displayYear) ?>.</p>
        </div>
      <?php endforeach; ?>
    </section>

    <footer>
      <p class="muted">Es werden nur die Anbieter des dieswöchigen Wochenmarktes angezeigt.</p>
    </footer>
  </div>
</body>
</html>
