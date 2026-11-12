<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function resultPath(string $root, string $label): ?string
{
    $dirs = array_filter([getenv('RESULTS_DIR') ?: null, '/results', $root.'/results']);
    foreach ($dirs as $dir) {
        $path = rtrim($dir, '/')."/{$label}.json";
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

try {
    $labels = array_slice($argv, 1);
    if (count($labels) !== 2) {
        throw new RuntimeException('before after の2ラベルを指定してください');
    }
    $parsed = [];
    foreach ($labels as $label) {
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $label)) {
            throw new RuntimeException('ラベルが不正です');
        }
        $path = resultPath($root, $label);
        if ($path === null) {
            throw new RuntimeException("結果がありません: {$label}");
        }
        $parsed[] = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }
    [$a, $b] = $parsed;
    foreach (['fixture', 'users', 'points', 'duration'] as $key) {
        if (json_encode($a[$key]) !== json_encode($b[$key])) {
            throw new RuntimeException('異なる計測条件の結果は比較できません');
        }
    }
    if (! ($a['passed'] ?? false) || ! ($b['passed'] ?? false)) {
        throw new RuntimeException('失格の計測は比較できません');
    }

    $ratio = ($a['score'] ?? 0) > 0 ? sprintf('（%.2f倍）', $b['score'] / $a['score']) : '';
    echo "スコア: {$a['score']} → {$b['score']}{$ratio}\n";
    foreach ($a['users'] as $u => $info) {
        $label = mb_str_pad($info['label'], 16, '　');
        echo '  '.$label.str_pad((string) $a['score_by_user'][$u], 7, ' ', STR_PAD_LEFT).' → '.str_pad((string) $b['score_by_user'][$u], 7, ' ', STR_PAD_LEFT)."\n";
    }
    echo "\n";
    echo str_pad('endpoint', 10).str_pad('p95ms', 22, ' ', STR_PAD_LEFT).str_pad('SQL平均', 16, ' ', STR_PAD_LEFT)."\n";
    $endpoints = array_unique([...array_keys($a['endpoints'] ?? []), ...array_keys($b['endpoints'] ?? [])]);
    foreach ($endpoints as $e) {
        $x = $a['endpoints'][$e] ?? null;
        $y = $b['endpoints'][$e] ?? null;
        $p95 = ($x ? number_format($x['p95_ms'], 1, '.', '') : '-').' → '.($y ? number_format($y['p95_ms'], 1, '.', '') : '-');
        $qLeft = $x === null || $x['sql_avg'] === null ? '-' : number_format($x['sql_avg'], 1, '.', '');
        $qRight = $y === null || $y['sql_avg'] === null ? '-' : number_format($y['sql_avg'], 1, '.', '');
        $q = "{$qLeft} → {$qRight}";
        echo str_pad($e, 10).str_pad($p95, 22, ' ', STR_PAD_LEFT).str_pad($q, 18, ' ', STR_PAD_LEFT)."\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
