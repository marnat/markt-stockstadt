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
    :root {
      --bg: #0f172a; --card: #111827; --muted: #94a3b8; --text: #e5e7eb; --accent: #22d3ee; --accent-2: #60a5fa; --good: #10b981; --bad:#ef4444;
    }
    * { box-sizing: border-box; }
    body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans"; background: var(--bg); color: var(--text); }
    .container { max-width: 1100px; margin: 0 auto; padding: 24px; }
    header { display:flex; align-items:center; gap:16px; margin-bottom: 20px; }
    header img { height: 56px; width: auto; border-radius: 8px; background: #fff; padding: 6px; }
    h1 { margin:0; font-size: clamp(1.4rem, 2.5vw, 2rem); line-height: 1.2; }
    .muted { color: var(--muted); }
    .nav { display:flex; align-items:center; justify-content: space-between; gap:12px; margin: 18px 0 24px; }
    .btn { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius: 10px; border: 1px solid #1f2937; background: linear-gradient(180deg,#1f2937,#111827); color: var(--text); text-decoration:none; }
    .btn:hover { border-color:#374151; filter: brightness(1.05); }
    .pill { display:inline-flex; align-items:center; gap:8px; padding:6px 10px; border-radius:999px; background:#1f2937; color: var(--accent); font-weight:600; }
    .week { display:grid; grid-template-columns: repeat(7, 1fr); gap:10px; }
    .day { background: var(--card); border: 1px solid #1f2937; border-radius: 12px; padding: 10px; min-height: 90px; }
    .day.today { outline: 2px solid var(--accent-2); }
    .day h3 { margin:0 0 8px; font-size: 0.95rem; display:flex; align-items:center; justify-content: space-between; }
    .day .date { color: var(--muted); font-size: 0.85rem; }
    .grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; margin-top: 28px; }
    .card { background: var(--card); border: 1px solid #1f2937; border-radius: 14px; padding: 16px; position: relative; }
    .card h2 { margin:0 0 12px; font-size: 1.1rem; }
    .media { display:flex; align-items:center; justify-content:center; background:#0b1220; border-radius: 12px; padding: 12px; height: 180px; }
    .media img { max-width: 100%; max-height: 160px; object-fit: contain; }
    .status { position: absolute; top: 14px; right: 14px; padding: 6px 10px; border-radius: 999px; font-weight: 600; font-size: 0.85rem; }
    .on  { background: rgba(16,185,129,.15); color: var(--good); border: 1px solid rgba(16,185,129,.35); }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <img src="Assets/wappen-stockstadt.gif" alt="Wappen Stockstadt" title="Wappen Stockstadt">
      <div>
        <h1>Übersicht – Kalenderwoche <span class="pill">KW <?= htmlspecialchars($displayWeek) ?></span> <span class="muted">/ <?= htmlspecialchars($displayYear) ?></span></h1>
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
      <p class="muted">Persistenz in <code>data/status.json</code>. Bilder unter <code>/Assets</code>. Deaktivierte Anbieter werden nicht angezeigt.</p>
    </footer>
  </div>
</body>
</html>
