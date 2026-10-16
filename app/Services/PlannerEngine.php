<?php

namespace App\Services;

/** Greedy academic plan with backtracking for current classes, not a graduation guarantee. */
class PlannerEngine
{
    public function plan(
        array $courses, array $passed, int $earned, int $requiredCredits, int $start, int $target,
        int $currentLimit, int $futureLimit, string $period, array $offered,
        ?array $fixedCurrent = null, ?array $classOptions = null, ?array $priorPassed = null,
    ): array {
        $known = array_fill_keys($passed, true);
        $priorPassed ??= $passed;
        $remaining = []; $details = []; $warnings = []; $lookup = [];
        foreach ($courses as $course) {
            $lookup[$course['id']] = $course;
            if (!isset($known[$course['id']])) $remaining[$course['id']] = $course;
        }
        $needed = []; $visited = [];
        $visit = function ($id) use (&$visit, &$needed, &$visited, $lookup, $known, &$warnings): void {
            if (isset($known[$id]) || ($visited[$id] ?? 0) === 2) return;
            if (($visited[$id] ?? 0) === 1) {
                $warnings[] = 'Siklus prasyarat terdeteksi. Periksa kurikulum.';
                return;
            }
            $course = $lookup[$id] ?? null;
            if (!$course) {
                $warnings[] = 'Prasyarat tidak ada pada kurikulum program studi.';
                return;
            }
            $needed[$id] = true; $visited[$id] = 1;
            foreach ($course['prerequisites'] as $prerequisite) $visit($prerequisite);
            $visited[$id] = 2;
        };
        foreach ($courses as $course) if ($course['required']) $visit($course['id']);
        $startParity = $period === 'ganjil' ? 1 : 0;
        for ($semester = $start; $semester <= $target; $semester++) {
            $capacity = $semester === $start ? $currentLimit : $futureLimit;
            $available = array_values(array_filter($remaining, function ($course) use ($known, $priorPassed, $semester, $start, $startParity, $offered, $fixedCurrent) {
                $prerequisiteCredits = $semester === $start ? $priorPassed : array_keys($known);
                if (array_diff($course['prerequisites'], $prerequisiteCredits)) return false;
                if ($semester === $start) return in_array($course['id'], $offered, true) && ($fixedCurrent === null || in_array($course['id'], $fixedCurrent, true));
                return ($course['semester'] % 2) === (($startParity + $semester - $start) % 2);
            }));
            usort($available, fn ($a, $b) => [isset($needed[$a['id']]) ? 0 : 1, $a['semester'], $a['id']] <=> [isset($needed[$b['id']]) ? 0 : 1, $b['semester'], $b['id']]);
            $selected = []; $detailIndexes = []; $assignment = [];
            foreach ($available as $course) {
                $fixed = $semester === $start && $fixedCurrent !== null && in_array($course['id'], $fixedCurrent, true);
                if (!$fixed && !isset($needed[$course['id']]) && $earned >= $requiredCredits) continue;
                if ($course['sks'] > $capacity) continue;
                if ($semester === $start && $classOptions !== null) {
                    $candidate = Timetable::choose([...$selected, $course['id']], $classOptions);
                    if ($candidate === null) {
                        $warnings[] = 'Sebagian mata kuliah tidak dapat ditempatkan pada semester aktif karena kuota atau bentrok jadwal. Periksa semester rekomendasi berikutnya.';
                        continue;
                    }
                    $assignment = $candidate;
                }
                $detailIndexes[$course['id']] = count($details);
                $details[] = [
                    'mata_kuliah_id' => $course['id'], 'semester_target' => $semester,
                    'prioritas' => isset($needed[$course['id']]) ? 1 : 2,
                    'alasan' => $course['required'] ? 'Mata kuliah wajib; prasyarat terpenuhi' : (isset($needed[$course['id']]) ? 'Prasyarat untuk mata kuliah wajib' : 'Pilihan untuk memenuhi target SKS'),
                ];
                $selected[] = $course['id']; $capacity -= $course['sks']; $earned += $course['sks'];
                unset($remaining[$course['id']]);
            }
            // Backtracking may have replaced an earlier class. Persist the final complete assignment.
            foreach ($assignment as $courseId => $classId) $details[$detailIndexes[$courseId]]['kelas_id'] = $classId;
            // Prerequisites selected this semester only become available for subsequent semesters.
            foreach ($selected as $id) $known[$id] = true;
        }
        $unresolved = array_filter($remaining, fn ($course) => isset($needed[$course['id']]));
        if ($unresolved) $warnings[] = 'Masih ada '.count($unresolved).' mata kuliah wajib/prasyarat yang belum terjadwal: '.implode(', ', array_column($unresolved, 'kode')).'.';
        if ($earned < $requiredCredits) $warnings[] = 'Target belum tercapai: kekurangan '.($requiredCredits - $earned).' SKS. Perpanjang target atau lengkapi kurikulum/penawaran kelas.';
        return ['details' => $details, 'warnings' => array_values(array_unique($warnings)), 'projected_credits' => $earned, 'feasible' => !$unresolved && $earned >= $requiredCredits];
    }
}
