<?php

namespace App\Services;

/** Deterministic, bounded class assignment. A null result never claims a valid schedule. */
final class Timetable
{
    public static function choose(array $courseIds, array $options): ?array
    {
        $courseIds = array_values(array_unique($courseIds));
        usort($courseIds, fn ($a, $b) => [count($options[$a] ?? []), $a] <=> [count($options[$b] ?? []), $b]);
        $nodes = 0;
        $walk = function (int $position, array $selected) use (&$walk, &$nodes, $courseIds, $options): ?array {
            if (++$nodes > 20000) return null;
            if ($position === count($courseIds)) return $selected;
            $course = $courseIds[$position];
            foreach ($options[$course] ?? [] as $candidate) {
                foreach ($selected as $other) {
                    if ($candidate['hari'] === $other['hari'] && $candidate['mulai'] < $other['selesai'] && $candidate['selesai'] > $other['mulai']) {
                        continue 2;
                    }
                }
                $result = $walk($position + 1, $selected + [$course => $candidate]);
                if ($result !== null) return $result;
            }
            return null;
        };
        $selected = $walk(0, []);
        if ($selected === null) return null;
        $result = array_map(fn ($slot) => (int) $slot['id'], $selected);
        ksort($result);
        return $result;
    }
}
