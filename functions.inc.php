<?php
namespace FreePBX\modules;

function getAllExtensions() {
    $freepbx = \FreePBX::create();
    $db = $freepbx->Database;
    $stmt = $db->prepare("SELECT extension, name FROM users WHERE extension REGEXP '^[0-9]{2,5}$' ORDER BY CAST(extension AS UNSIGNED)");
    $stmt->execute();
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

function getExtensionStatuses($astman) {
    if (!$astman || !$astman->connected()) return [];
    $response = $astman->Command('core show hints');
    $lines = explode("\n", $response['data'] ?? '');
    $statuses = [];

    foreach ($lines as $line) {
        if (preg_match('/^([0-9]+)@.*State:\s*([A-Za-z_]+)/', trim($line), $m)) {
            $ext = $m[1];
            $state = strtoupper(trim($m[2]));
            $statuses[$ext] = [
                'state' => $state,
                'text'  => getStateText($state),
                'color' => getStateColor($state)
            ];
        }
    }
    return $statuses;
}

function getStateText($state) {
    $map = [
        'IDLE' => 'Свободен', 'NOT_INUSE' => 'Свободен',
        'INUSE' => 'В разговоре', 'RINGING' => 'Звонит',
        'ONHOLD' => 'На удержании', 'BUSY' => 'Занят',
        'UNAVAILABLE' => 'Недоступен', 'UNKNOWN' => 'Неизвестно'
    ];
    return $map[$state] ?? $state;
}

function getStateColor($state) {
    $state = strtoupper($state);
    if (in_array($state, ['IDLE','NOT_INUSE'])) return 'green';
    if (in_array($state, ['INUSE','RINGING','ONHOLD'])) return 'orange';
    if ($state === 'BUSY') return 'blue';   // ← голубой для "Занят"
    return 'red';
}

function getQueuesStatus($astman) {
    if (!$astman || !$astman->connected()) return [];

    $queues = [];
    $currentQueue = null;

    $response = $astman->Command('queue show');
    $lines = explode("\n", $response['data'] ?? '');

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        if (preg_match('/^(\d+)\s+has\s+(\d+)\s+calls/', $line, $m)) {
            $currentQueue = $m[1];
            $queues[$currentQueue] = [
                'queue' => $currentQueue,
                'waiting' => (int)$m[2],
                'members' => [],
                'callers' => []
            ];
            continue;
        }

        if (!$currentQueue) continue;

        $lower = strtolower($line);

        if (preg_match('/(Local|PJSIP|SIP)\/(\d+)/', $line, $m) &&
            (strpos($line, 'from-queue') !== false || strpos($lower, 'has taken') !== false)) {

            $ext = $m[2];
            $statusText = 'Недоступен';
            $statusColor = 'red';

            if (strpos($lower, 'not in use') !== false || strpos($lower, 'idle') !== false) {
                $statusText = 'Свободен';
                $statusColor = 'green';
            } elseif (strpos($lower, 'busy') !== false) {
                $statusText = 'Занят';
                $statusColor = 'blue';
            } elseif (strpos($lower, 'in use') !== false || strpos($lower, 'talking') !== false || strpos($lower, 'ringing') !== false || strpos($lower, 'in call') !== false) {
                $statusText = 'В разговоре';
                $statusColor = 'orange';
            }

            $callsTaken = 0;
            if (preg_match('/taken\s+(\d+)\s+calls/', $line, $cm)) {
                $callsTaken = (int)$cm[1];
            }

            $queues[$currentQueue]['members'][$ext] = [
                'ext' => $ext,
                'status' => ['text' => $statusText, 'color' => $statusColor],
                'calls_taken' => $callsTaken
            ];
        }

        if (preg_match('/^(\d+)\.\s+(.+?)\s+\(wait:\s*([\d:]+)/i', $line, $m)) {
            $position = (int)$m[1];
            $raw = trim($m[2]);
            $waitFormatted = $m[3];

            $callerID = 'Неизвестно';
            if (preg_match('/(?:PJSIP|SIP)\/(.+?)(?=-\d{4,})/', $raw, $cm)) {
                $callerID = $cm[1];
            } elseif (preg_match('/(?:PJSIP|SIP)\/([^-\s]+)/', $raw, $cm)) {
                $callerID = $cm[1];
            } elseif (preg_match('/(\d{6,})/', $raw, $cm)) {
                $callerID = $cm[1];
            }

            $queues[$currentQueue]['callers'][] = [
                'position' => $position,
                'callerid' => $callerID,
                'wait'     => $waitFormatted
            ];
        }
    }

    foreach ($queues as &$q) {
        usort($q['callers'], fn($a, $b) => $a['position'] <=> $b['position']);
    }

    return $queues;
}
?>