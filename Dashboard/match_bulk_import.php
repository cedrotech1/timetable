<?php
/**
 * Match parsed Excel timetable rows against modules, facilities, lecturers, groups.
 * POST JSON: { sections: [ { title, year, program_hint, group_hint, rows: [...] } ] }
 */
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

include('connection.php');

function respond($ok, $message = '', $data = []) {
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $data));
    exit;
}

if (!isset($_SESSION['id'])) {
    respond(false, 'Not logged in.');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['sections']) || !is_array($input['sections'])) {
    respond(false, 'No sections to match. Upload and parse an Excel file first.');
}

$systemSemester = isset($input['semester']) ? trim((string)$input['semester']) : '';
// Fallback: load from system table
if ($systemSemester === '') {
    $sys = mysqli_query($connection, "SELECT semester FROM system LIMIT 1");
    if ($sys && ($sr = mysqli_fetch_assoc($sys))) {
        $systemSemester = (string)($sr['semester'] ?? '');
    }
}

function norm($s) {
    $s = strtolower(trim((string)$s));
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}

function strip_titles($name) {
    $n = preg_replace('/\b(prof\.?|dr\.?|mr\.?|mrs\.?|ms\.?|eng\.?)\b/i', '', (string)$name);
    $n = preg_replace('/\([^)]*\)/', ' ', $n);
    $n = preg_replace('/\s+/', ' ', trim($n));
    return $n;
}

function like_score($a, $b) {
    $a = norm($a);
    $b = norm($b);
    if ($a === '' || $b === '') return 0;
    if ($a === $b) return 100;
    if (strpos($a, $b) !== false || strpos($b, $a) !== false) return 80;
    similar_text($a, $b, $pct);
    return (int)round($pct);
}

// Load reference data
$modules = [];
$mq = mysqli_query($connection, "SELECT m.id, m.name, m.code, m.year, m.semester, m.program_id, p.name AS program_name
    FROM module m LEFT JOIN program p ON p.id = m.program_id");
while ($r = mysqli_fetch_assoc($mq)) {
    $modules[] = $r;
}

$facilities = [];
$fq = mysqli_query($connection, "SELECT f.id, f.name, f.capacity, f.buildname, f.type, s.name AS site_name
    FROM facility f LEFT JOIN site s ON s.id = f.site");
while ($r = mysqli_fetch_assoc($fq)) {
    $facilities[] = $r;
}

$lecturers = [];
$lq = mysqli_query($connection, "SELECT id, names, email, ur_email FROM users WHERE role != 'admin' AND role IS NOT NULL");
while ($r = mysqli_fetch_assoc($lq)) {
    $lecturers[] = $r;
}

$programs = [];
$pq = mysqli_query($connection, "SELECT id, name, code FROM program");
while ($r = mysqli_fetch_assoc($pq)) {
    $programs[] = $r;
}

// Groups with program + year context
$groups = [];
$gq = mysqli_query($connection, "
    SELECT g.id, g.name, g.size, i.id AS intake_id, i.year_of_study, i.program_id,
           p.name AS program_name, p.code AS program_code, c.name AS campus_name
    FROM student_group g
    JOIN intake i ON i.id = g.intake_id
    JOIN program p ON p.id = i.program_id
    LEFT JOIN campus c ON c.id = i.campus_id
");
while ($r = mysqli_fetch_assoc($gq)) {
    $groups[] = $r;
}

function match_program($programs, $hint, $year = null, $moduleProgramVotes = []) {
    $hintN = norm($hint);
    $hintN = str_replace(['hounrs', 'honors'], 'honours', $hintN);
    $stop = ['bachelor','master','business','administration','with','honours','in','of','the','and','group','year','programme','program','each'];
    $hintTokens = array_values(array_filter(preg_split('/\s+/', $hintN), function ($t) use ($stop) {
        return strlen($t) >= 4 && !in_array($t, $stop, true);
    }));

    $hintBachelor = (bool)preg_match('/\bbachelor\b/', $hintN);
    $hintMaster = (bool)preg_match('/\bmaster\b/', $hintN);

    $scored = [];
    foreach ($programs as $p) {
        $nameN = norm($p['name']);
        $nameN = str_replace(['hounrs', 'honors'], 'honours', $nameN);
        $score = 0;

        // Exact / contains full hint fragment
        $score = max($score, like_score($hint, $p['name']));

        // Distinctive token hits (transport, logistics, accounting, finance…)
        $hits = 0;
        foreach ($hintTokens as $t) {
            if (strpos($nameN, $t) !== false) $hits++;
        }
        if (!empty($hintTokens)) {
            $score += (int)round(70 * ($hits / count($hintTokens)));
        }

        // Degree level must agree
        $isMaster = (bool)preg_match('/\bmaster\b/', $nameN);
        $isBachelor = (bool)preg_match('/\bbachelor\b/', $nameN);
        if ($hintBachelor && $isMaster) $score -= 50;
        if ($hintMaster && $isBachelor) $score -= 50;
        if ($hintBachelor && $isBachelor) $score += 15;
        if ($hintMaster && $isMaster) $score += 15;

        // Votes from module codes already in this section
        $pid = (int)$p['id'];
        if (!empty($moduleProgramVotes[$pid])) {
            $score += min(40, 12 * (int)$moduleProgramVotes[$pid]);
        }

        $scored[] = ['program' => $p, 'score' => $score];
    }

    usort($scored, function ($a, $b) { return $b['score'] <=> $a['score']; });
    $best = $scored[0] ?? null;
    if (!$best || $best['score'] < 55) {
        return [null, $best['score'] ?? 0, array_slice($scored, 0, 8)];
    }
    return [$best['program'], $best['score'], array_slice($scored, 0, 8)];
}

function infer_program_votes_from_modules($modules, $rows) {
    $votes = [];
    foreach ($rows as $row) {
        $code = strtoupper(preg_replace('/\s+/', '', (string)($row['module_code'] ?? '')));
        if ($code === '') continue;
        // Exact code only for voting
        foreach ($modules as $m) {
            $mCode = strtoupper(preg_replace('/\s+/', '', (string)$m['code']));
            if ($mCode === $code && !empty($m['program_id'])) {
                $pid = (int)$m['program_id'];
                $votes[$pid] = ($votes[$pid] ?? 0) + 1;
                break;
            }
        }
    }
    return $votes;
}

function parse_group_size_hint($text) {
    // "GROUP 1 &2 = 109 EACH GROUP" or "= 260"
    if (preg_match('/=\s*(\d+)\s*each/i', $text, $m)) {
        return ['mode' => 'each', 'size' => (int)$m[1]];
    }
    if (preg_match('/=\s*(\d+)/', $text, $m)) {
        return ['mode' => 'total', 'size' => (int)$m[1]];
    }
    return ['mode' => 'each', 'size' => 0];
}

function parse_group_numbers($text) {
    $nums = [];
    // "GROUP 1 & 2" / "GROUP 1&2" / "GROUP 7"
    if (preg_match('/group\s*((?:\d+\s*[&,and\s]*)+)/i', $text, $m)) {
        if (preg_match_all('/\d+/', $m[1], $mm)) {
            foreach ($mm[0] as $n) $nums[] = (int)$n;
        }
    }
    if (preg_match_all('/\b(?:gp|g)\s*([0-9]+)\b/i', $text, $m)) {
        foreach ($m[1] as $n) $nums[] = (int)$n;
    }
    return array_values(array_unique(array_filter($nums)));
}

function match_groups($groups, $programId, $year, $groupNums, $timeGroupNums = []) {
    $nums = $timeGroupNums ?: $groupNums;
    if (empty($nums)) $nums = $groupNums;
    $matched = [];
    foreach ($groups as $g) {
        if ($programId && (int)$g['program_id'] !== (int)$programId) continue;
        if ($year && (int)$g['year_of_study'] !== (int)$year) continue;
        $gNums = parse_group_numbers($g['name']);
        // Also accept names like "Group 1", "G1", "1"
        if (empty($gNums) && preg_match('/(?:^|\D)(\d+)(?:\D|$)/', $g['name'], $m)) {
            $gNums = [(int)$m[1]];
        }
        if (empty($nums)) {
            // no numbers — skip auto-pick all
            continue;
        }
        foreach ($gNums as $gn) {
            if (in_array($gn, $nums, true)) {
                $matched[$g['id']] = $g;
                break;
            }
        }
    }
    // Fallback: if time groups empty, use section group nums again already done
    return array_values($matched);
}

function match_module($modules, $code, $name, $programId = null, $year = null, $semester = null) {
    $codeN = strtoupper(preg_replace('/\s+/', '', (string)$code));
    $nameN = norm($name);

    // Prefer exact CODE match only (same as picking a real module in timetable_set)
    $exactCode = [];
    if ($codeN !== '') {
        foreach ($modules as $m) {
            $mCode = strtoupper(preg_replace('/\s+/', '', (string)$m['code']));
            if ($mCode === $codeN) $exactCode[] = $m;
        }
    }

    if (!empty($exactCode)) {
        usort($exactCode, function ($a, $b) use ($programId, $year, $semester) {
            $sa = 0; $sb = 0;
            if ($programId && (int)$a['program_id'] === (int)$programId) $sa += 20;
            if ($programId && (int)$b['program_id'] === (int)$programId) $sb += 20;
            if ($year && (string)$a['year'] === (string)$year) $sa += 10;
            if ($year && (string)$b['year'] === (string)$year) $sb += 10;
            if ($semester !== null && $semester !== '' && (string)$a['semester'] === (string)$semester) $sa += 10;
            if ($semester !== null && $semester !== '' && (string)$b['semester'] === (string)$semester) $sb += 10;
            return $sb <=> $sa;
        });
        return [$exactCode[0], 100, $exactCode];
    }

    // No exact code in system → do NOT invent another module by fuzzy name
    // (prevents ESP2112 → CL80311). Offer same-program candidates for manual pick.
    $candidates = [];
    foreach ($modules as $m) {
        if ($programId && (int)$m['program_id'] !== (int)$programId) continue;
        if ($year && (string)$m['year'] !== (string)$year) continue;
        if ($semester !== null && $semester !== '' && (string)$m['semester'] !== (string)$semester) continue;
        $candidates[] = $m;
    }
    // If too few, loosen to program only
    if (count($candidates) < 3 && $programId) {
        $candidates = array_values(array_filter($modules, function ($m) use ($programId) {
            return (int)$m['program_id'] === (int)$programId;
        }));
    }

    // Optional: exact name within program (only if excel had no usable code)
    if ($codeN === '' && $nameN !== '') {
        foreach ($candidates as $m) {
            if (norm($m['name']) === $nameN) {
                return [$m, 95, $candidates];
            }
        }
    }

    return [null, 0, array_slice($candidates, 0, 40)];
}

function match_facility($facilities, $room, $capacity = null) {
    $room = trim((string)$room);
    if ($room === '') return [null, 0];
    $best = null;
    $bestScore = 0;
    foreach ($facilities as $f) {
        $score = max(
            like_score($room, $f['name']),
            like_score($room, $f['buildname'] ?? ''),
            like_score($room, trim(($f['name'] ?? '') . ' ' . ($f['buildname'] ?? '')))
        );
        // Token overlap for "126 KOICA CLASS ROOM 4"
        $tokens = preg_split('/\s+/', norm($room));
        $hay = norm(($f['name'] ?? '') . ' ' . ($f['buildname'] ?? '') . ' ' . ($f['site_name'] ?? ''));
        $hits = 0;
        foreach ($tokens as $t) {
            if (strlen($t) >= 2 && strpos($hay, $t) !== false) $hits++;
        }
        if (count($tokens) > 0) {
            $score = max($score, (int)round(100 * $hits / count($tokens)));
        }
        if ($capacity && isset($f['capacity']) && (int)$f['capacity'] === (int)$capacity) {
            $score += 5;
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $f;
        }
    }
    if ($bestScore < 40) return [null, $bestScore];
    return [$best, $bestScore];
}

function match_lecturers($lecturers, $raw) {
    $raw = trim((string)$raw);
    $leader = null;
    $others = [];
    $warnings = [];
    if ($raw === '' || preg_match('/language\s*center/i', $raw)) {
        return [$leader, $others, $warnings];
    }

    // Split on commas / semicolons not inside simple patterns
    $parts = preg_split('/\s*,\s*(?![^()]*\))/', $raw);
    if (count($parts) === 1) {
        $parts = preg_split('/\s*;\s*/', $raw);
    }

    $isFirst = true;
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $isMl = (bool)preg_match('/\(ML\)|\bmodule\s*leader\b/i', $part);
        $clean = strip_titles($part);
        if ($clean === '') continue;

        $best = null;
        $bestScore = 0;
        foreach ($lecturers as $l) {
            $score = like_score($clean, $l['names'] ?? '');
            // Compare last-name tokens
            $ct = preg_split('/\s+/', norm($clean));
            $lt = preg_split('/\s+/', norm($l['names'] ?? ''));
            $overlap = count(array_intersect($ct, $lt));
            if ($overlap >= 2) $score = max($score, 90);
            elseif ($overlap === 1 && count($ct) <= 3) $score = max($score, 70);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $l;
            }
        }

        if (!$best || $bestScore < 55) {
            $warnings[] = "Lecturer not matched: {$part}";
            continue;
        }

        $obj = [
            'id' => (int)$best['id'],
            'names' => $best['names'],
            'email' => $best['email'] ?? '',
            'ur_email' => $best['ur_email'] ?? ''
        ];
        if ($isMl || ($isFirst && !$leader)) {
            $leader = $obj;
        } else {
            if ($leader && (int)$leader['id'] === (int)$obj['id']) continue;
            $others[] = $obj;
        }
        $isFirst = false;
    }

    // Dedupe others
    $seen = [];
    $others = array_values(array_filter($others, function ($o) use (&$seen, $leader) {
        if ($leader && (int)$o['id'] === (int)$leader['id']) return false;
        if (isset($seen[$o['id']])) return false;
        $seen[$o['id']] = true;
        return true;
    }));

    return [$leader, $others, $warnings];
}

$outSections = [];
foreach ($input['sections'] as $secIndex => $sec) {
    $title = $sec['title'] ?? '';
    $year = isset($sec['year']) ? (int)$sec['year'] : 0;
    $programHint = $sec['program_hint'] ?? $title;
    $groupHint = $sec['group_hint'] ?? $title;
    $groupNums = parse_group_numbers($groupHint . ' ' . $title);
    $sizeHint = parse_group_size_hint($title . ' ' . $groupHint);

    // Prefer program inferred from module codes in this section (TL* / AF* …)
    $moduleVotes = infer_program_votes_from_modules($modules, $sec['rows'] ?? []);
    // Allow forced program from UI after user picks / creates
    if (!empty($sec['forced_program_id'])) {
        $forcedId = (int)$sec['forced_program_id'];
        $program = null;
        foreach ($programs as $p) {
            if ((int)$p['id'] === $forcedId) { $program = $p; break; }
        }
        $pScore = $program ? 100 : 0;
        $programCandidates = [];
    } else {
        [$program, $pScore, $programCandidates] = match_program($programs, $programHint, $year, $moduleVotes);
    }
    $programId = $program['id'] ?? null;

    $sectionGroups = match_groups($groups, $programId, $year ?: null, $groupNums, []);
    $matchedRows = [];
    $okCount = 0;
    $warnCount = 0;
    $errCount = 0;

    foreach (($sec['rows'] ?? []) as $rowIndex => $row) {
        $errors = [];
        $warnings = [];

        $day = trim($row['day'] ?? '');
        $start = trim($row['start'] ?? '');
        $end = trim($row['end'] ?? '');
        $moduleCode = trim($row['module_code'] ?? '');
        $moduleName = trim($row['module_name'] ?? '');
        $lecturersRaw = trim($row['lecturers'] ?? '');
        $classroom = trim($row['classroom'] ?? '');
        $capacity = $row['room_capacity'] ?? null;
        $timeGroupNums = array_map('intval', $row['time_group_nums'] ?? []);

        if ($day === '') $errors[] = 'Missing day';
        if ($start === '' || $end === '') $errors[] = 'Missing time';
        if ($moduleCode === '' && $moduleName === '' && empty($row['forced_module_id'])) {
            $warnings[] = 'No module in Excel — pick a system module';
        }

        $mod = null;
        $mScore = 0;
        $moduleCandidates = [];

        // Manual override from import UI (must be a real system module id)
        if (!empty($row['forced_module_id'])) {
            $fid = (int)$row['forced_module_id'];
            foreach ($modules as $m) {
                if ((int)$m['id'] === $fid) {
                    $mod = $m;
                    $mScore = 100;
                    break;
                }
            }
            if (!$mod) $warnings[] = 'Selected module id not found in system';
        } else {
            [$mod, $mScore, $moduleCandidates] = match_module(
                $modules,
                $moduleCode,
                $moduleName,
                $programId,
                $year ?: null,
                $systemSemester
            );
            if (!$mod) {
                $warnings[] = 'Module code not in system: ' . ($moduleCode ?: $moduleName) . ' — pick one below';
            }
        }

        // Always offer program modules for manual pick (like timetable_set module selector)
        if (empty($moduleCandidates) && $programId) {
            foreach ($modules as $m) {
                if ((int)$m['program_id'] !== (int)$programId) continue;
                if ($year && (string)$m['year'] !== (string)$year) continue;
                if ($systemSemester !== '' && (string)$m['semester'] !== (string)$systemSemester) continue;
                $moduleCandidates[] = $m;
            }
            if (count($moduleCandidates) < 3) {
                $moduleCandidates = array_values(array_filter($modules, function ($m) use ($programId) {
                    return (int)$m['program_id'] === (int)$programId;
                }));
            }
        }

        $fac = null;
        $fScore = 0;
        if (!empty($row['forced_facility_id'])) {
            $ffid = (int)$row['forced_facility_id'];
            foreach ($facilities as $f) {
                if ((int)$f['id'] === $ffid) {
                    $fac = $f;
                    $fScore = 100;
                    break;
                }
            }
            if (!$fac) $warnings[] = 'Selected facility not found';
        } else {
            [$fac, $fScore] = match_facility($facilities, $classroom, $capacity);
            if ($classroom !== '' && !$fac) $warnings[] = 'Facility not matched: ' . $classroom . ' — pick one below';
            elseif ($classroom === '') $warnings[] = 'No classroom in Excel — pick a facility';
            elseif ($fScore < 70) $warnings[] = "Facility weak match ({$fScore}%): {$fac['name']}";
        }

        $leader = null;
        $others = [];
        if (!empty($row['forced_leader_id']) || !empty($row['forced_other_lecturer_ids'])) {
            $flid = (int)($row['forced_leader_id'] ?? 0);
            $fothers = $row['forced_other_lecturer_ids'] ?? [];
            if (!is_array($fothers)) $fothers = [];
            foreach ($lecturers as $l) {
                if ($flid && (int)$l['id'] === $flid) {
                    $leader = [
                        'id' => (int)$l['id'],
                        'names' => $l['names'] ?? ($l['name'] ?? ''),
                        'email' => $l['email'] ?? '',
                        'ur_email' => $l['ur_email'] ?? ''
                    ];
                }
            }
            foreach ($fothers as $oid) {
                $oid = (int)$oid;
                if (!$oid || ($leader && (int)$leader['id'] === $oid)) continue;
                foreach ($lecturers as $l) {
                    if ((int)$l['id'] === $oid) {
                        $others[] = [
                            'id' => (int)$l['id'],
                            'names' => $l['names'] ?? ($l['name'] ?? ''),
                            'email' => $l['email'] ?? '',
                            'ur_email' => $l['ur_email'] ?? ''
                        ];
                        break;
                    }
                }
            }
        } else {
            [$leader, $others, $lw] = match_lecturers($lecturers, $lecturersRaw);
            $warnings = array_merge($warnings, $lw);
        }

        $rowGroups = $sectionGroups;
        if (!empty($timeGroupNums) && $programId) {
            $tg = match_groups($groups, $programId, $year ?: null, $groupNums, $timeGroupNums);
            if (!empty($tg)) $rowGroups = $tg;
        }
        if (empty($rowGroups)) {
            $warnings[] = 'No groups matched for this section/row — create intake/groups below';
        }

        $status = empty($errors) ? (empty($warnings) ? 'ok' : 'warning') : 'error';
        if ($status === 'ok') $okCount++;
        elseif ($status === 'warning') { $okCount++; $warnCount++; }
        else $errCount++;

        $candOut = [];
        foreach (array_slice($moduleCandidates ?: [], 0, 40) as $mc) {
            $candOut[] = [
                'id' => (int)$mc['id'],
                'code' => $mc['code'],
                'name' => $mc['name'],
                'year' => $mc['year'],
                'semester' => $mc['semester'],
                'program_id' => (int)$mc['program_id'],
                'credits' => $mc['credits'] ?? null
            ];
        }

        $matchedRows[] = [
            'row_index' => $rowIndex,
            'status' => $status,
            'errors' => $errors,
            'warnings' => $warnings,
            'day' => $day,
            'start' => $start,
            'end' => $end,
            'excel' => [
                'module_code' => $moduleCode,
                'module_name' => $moduleName,
                'lecturers' => $lecturersRaw,
                'classroom' => $classroom,
                'time_raw' => $row['time_raw'] ?? ''
            ],
            'module' => $mod ? [
                'id' => (int)$mod['id'],
                'code' => $mod['code'],
                'name' => $mod['name'],
                'year' => $mod['year'],
                'semester' => $mod['semester'],
                'program_id' => (int)$mod['program_id'],
                'credits' => $mod['credits'] ?? null
            ] : null,
            'module_candidates' => $candOut,
            'facility' => $fac ? [
                'id' => (int)$fac['id'],
                'name' => $fac['name'],
                'capacity' => (int)($fac['capacity'] ?? 0),
                'site_name' => $fac['site_name'] ?? '',
                'buildname' => $fac['buildname'] ?? ''
            ] : null,
            'lecturers' => [
                'leader' => $leader,
                'others' => $others
            ],
            'groups' => array_map(function ($g) {
                return [
                    'id' => (int)$g['id'],
                    'name' => $g['name'],
                    'size' => (int)($g['size'] ?? 0),
                    'program_id' => (int)$g['program_id'],
                    'program_name' => $g['program_name'],
                    'year_of_study' => (int)$g['year_of_study'],
                    'campus_name' => $g['campus_name'] ?? ''
                ];
            }, $rowGroups)
        ];
    }

    $candOut = [];
    foreach ($programCandidates as $c) {
        $candOut[] = [
            'id' => (int)$c['program']['id'],
            'name' => $c['program']['name'],
            'code' => $c['program']['code'] ?? '',
            'score' => (int)$c['score']
        ];
    }

    $outSections[] = [
        'section_index' => $secIndex,
        'title' => $title,
        'year' => $year,
        'group_numbers' => $groupNums,
        'size_hint' => $sizeHint,
        'needs_groups' => empty($sectionGroups),
        'program' => $program ? [
            'id' => (int)$program['id'],
            'name' => $program['name'],
            'code' => $program['code'],
            'match_score' => $pScore
        ] : null,
        'program_candidates' => $candOut,
        'groups' => array_map(function ($g) {
            return [
                'id' => (int)$g['id'],
                'name' => $g['name'],
                'size' => (int)($g['size'] ?? 0),
                'program_id' => (int)$g['program_id'],
                'program_name' => $g['program_name'],
                'year_of_study' => (int)$g['year_of_study'],
                'campus_name' => $g['campus_name'] ?? ''
            ];
        }, $sectionGroups),
        'stats' => [
            'rows' => count($matchedRows),
            'ok' => $okCount,
            'warnings' => $warnCount,
            'errors' => $errCount
        ],
        'rows' => $matchedRows
    ];
}

respond(true, 'Matching complete.', ['sections' => $outSections]);
