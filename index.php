<?php
// index.php – Wochenfunktion + QR/UID-Aktivierung pro Anbieter (KW-basiert)
// Charset & Zeitzone
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Berlin');

// =============================
// Anbieter-Konfiguration (UIDs)
// =============================
// Jede UID steckt in einem **statischen** QR-Code des Anbieters. Beim Scannen wird
// z.B. https://deine-domain.tld/index.php?uid=UID_METZGER aufgerufen.
// -> Die UID wird auf einen Anbieter gemappt und dessen Status für die *aktuelle* KW getoggelt.
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

// Hilfs-Map von UID -> Vendor-Key
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
// Struktur der Datei data/status.json
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
// QR-Scan: UID verarbeiten -> Toggle für aktuelle KW
// =============================
$flash = null;
if (isset($_GET['uid']) && is_string($_GET['uid'])) {
  $uid = trim($_GET['uid']);
  if (isset($uidToVendor[$uid])) {
    $vendorKey = $uidToVendor[$uid];
    $current = isset($status[$currentKey][$vendorKey]) ? (bool)$status[$currentKey][$vendorKey] : false;
    $status[$currentKey][$vendorKey] = !$current; // Toggle
    saveStatus($dataFile, $status);
    $stateText = $status[$currentKey][$vendorKey] ? 'aktiviert' : 'deaktiviert';
    $flash = sprintf('%s wurde für KW %d/%d %s.', $vendors[$vendorKey]['name'], $currentIsoWeek, $currentIsoYear, $stateText);
  } else {
    $flash = 'Unbekannte UID – Anbieter konnte nicht zugeordnet werden.';
  }
}

// Status für die *anzeigte* Woche auslesen
$displayKey = $displayYear . '-' . $displayWeek;
$weekStatus = $status[$displayKey] ?? [];
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Übersicht – KW <?= htmlspecialchars($displayWeek) ?> / <?= htmlspecialchars($displayYear) ?></title>
  <style>
    :root {
      --bg: #0f172a;       /* slate-900 */
      --card: #111827;     /* gray-900 */
      --muted: #94a3b8;    /* slate-400 */
      --text: #e5e7eb;     /* gray-200 */
      --accent: #22d3ee;   /* cyan-400 */
      --accent-2: #60a5fa; /* blue-400 */
      --good: #10b981;     /* green-500 */
      --bad: #ef4444;      /* red-500 */
      --warn:#f59e0b;      /* amber-500 */
    }
    * { box-sizing: border-box; }
    body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji", "Segoe UI Emoji"; background: var(--bg); color: var(--text); }
    .container { max-width: 1100px; margin: 0 auto; padding: 24px; }
    header { display:flex; align-items:center; gap:16px; margin-bottom: 20px; }
    header img { height: 56px; width: auto; border-radius: 8px; background: #fff; padding: 6px; }
    h1 { margin:0; font-size: clamp(1.4rem, 2.5vw, 2rem); line-height: 1.2; }
    .muted { color: var(--muted); }

    .flash { margin: 10px 0 0; padding: 10px 12px; background: #0b1220; border: 1px solid #1f2937; border-radius: 10px; }

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
    .off { background: rgba(239,68,68,.15); color: var(--bad);  border: 1px solid rgba(239,68,68,.35); }

    footer { margin-top: 28px; color: var(--muted); font-size: 0.9rem; }

    @media (max-width: 900px) { .week { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 600px) { .week { grid-template-columns: repeat(2, 1fr); } .grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <img src="Assets/wappen-stockstadt.gif" alt="Wappen Stockstadt" title="Wappen Stockstadt">
      <div>
        <h1>Übersicht – Kalenderwoche <span class="pill">KW <?= htmlspecialchars($displayWeek) ?></span> <span class="muted">/ <?= htmlspecialchars($displayYear) ?></span></h1>
        <div class="muted">Montag bis Sonntag der ausgewählten Woche</div>
        <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
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
          <!-- Platz für tagesbezogene Inhalte -->
        </article>
      <?php endforeach; ?>
    </section>

    <section class="grid" aria-label="Anbieter">
      <?php foreach ($vendors as $key => $v):
        $active = isset($weekStatus[$key]) ? (bool)$weekStatus[$key] : false;
      ?>
        <div class="card">
          <div class="status <?= $active ? 'on' : 'off' ?>"><?= $active ? 'Aktiv (diese KW)' : 'Inaktiv' ?></div>
          <h2><?= htmlspecialchars($v['name']) ?></h2>
          <div class="media">
            <img src="<?= htmlspecialchars($v['image']) ?>" alt="<?= htmlspecialchars($v['name']) ?>" title="<?= htmlspecialchars($v['name']) ?>">
          </div>
          <p class="muted">Status bezieht sich auf KW <?= htmlspecialchars($displayWeek) ?>/<?= htmlspecialchars($displayYear) ?>.</p>
          <details class="muted">
            <summary>QR/UID-Hinweis</summary>
            <p>Diesen Anbieter aktivieren/deaktivieren, indem der Anbieter seinen <strong>statischen QR-Code</strong> scannt, der auf <code>?uid=...</code> verweist. Der Scan toggelt den Status für die <em>aktuelle</em> Kalenderwoche.</p>
            <p>Beispiel-URL: <code>index.php?uid=<?= htmlspecialchars($v['uid']) ?></code></p>
          </details>
        </div>
      <?php endforeach; ?>
    </section>

    <footer>
      <p>Hinweis: Bilder werden relativ aus <code>/Assets</code> geladen. Achte auf exakte Groß-/Kleinschreibung der Dateinamen (Linux-Server sind case-sensitive).</p>
      <p class="muted">Persistenz: <code>data/status.json</code> speichert pro ISO-KW die Aktivierungen.</p>
    </footer>
  </div>
</body>
</html>